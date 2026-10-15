<?php

declare(strict_types=1);

namespace Cenim\Storage;

use Cenim\Support\Http;
use RuntimeException;

/**
 * Klien S3 minimal (AWS Signature V4, path-style) tanpa library — cukup untuk
 * object storage S3-compatible seperti RustFS di server Tandikat.
 *
 * Hanya operasi yang dipakai aplikasi: put/get/head/delete objek, list prefix,
 * dan request mentah untuk subresource bucket (?policy, ?cors).
 */
final class S3Client
{
    private const SERVICE = 's3';

    public function __construct(
        private readonly string $endpoint,
        private readonly string $region,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly int $timeout = 60,
    ) {
        if ($endpoint === '' || $bucket === '' || $accessKey === '' || $secretKey === '') {
            throw new RuntimeException('Konfigurasi object storage belum lengkap (TANDIKAT_*).');
        }
    }

    /** Dibangun dari variabel TANDIKAT_* di .env. */
    public static function fromEnv(): self
    {
        return new self(
            rtrim((string) env('TANDIKAT_ENDPOINT', ''), '/'),
            (string) env('TANDIKAT_REGION', 'us-east-1'),
            (string) env('TANDIKAT_BUCKET', ''),
            (string) env('TANDIKAT_USER', ''),
            (string) env('TANDIKAT_PASSWORD', ''),
        );
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    /** URL publik objek (hanya bisa dibuka bila policy bucket mengizinkan baca anonim). */
    public function publicUrl(string $key): string
    {
        return $this->endpoint . $this->path($key);
    }

    /** Unggah berkas lokal. */
    public function putFile(string $key, string $file, string $mime, array $headers = []): void
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Tidak bisa membaca ' . $file);
        }

        try {
            $res = $this->request('PUT', $key, [], $headers + ['content-type' => $mime], null, [
                'hash'   => hash_file('sha256', $file),
                'handle' => $handle,
                'size'   => (int) filesize($file),
            ]);
        } finally {
            fclose($handle);
        }

        $this->expect($res, [200], 'PUT ' . $key);
    }

    public function putString(string $key, string $body, string $mime, array $headers = []): void
    {
        $this->expect($this->request('PUT', $key, [], $headers + ['content-type' => $mime], $body), [200], 'PUT ' . $key);
    }

    /** Unduh objek ke berkas lokal. */
    public function getToFile(string $key, string $target): void
    {
        $tmp    = $target . '.part-' . bin2hex(random_bytes(4));
        $handle = fopen($tmp, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Tidak bisa menulis ' . $tmp);
        }

        try {
            $res = $this->request('GET', $key, [], [], null, null, $handle);
        } finally {
            fclose($handle);
        }

        if ($res['status'] !== 200) {
            @unlink($tmp);
            $this->expect($res, [200], 'GET ' . $key);
        }

        if (!rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('Gagal menyimpan ' . $target);
        }
    }

    /**
     * Ukuran & ETag objek, null bila tidak ada.
     *
     * Sengaja lewat ListObjectsV2, bukan HEAD atau GET ber-Range: s3.ceits.id
     * berada di belakang Cloudflare, yang kadang mengubah request HEAD dan
     * membuang header Range sebelum sampai ke RustFS — tanda tangan SigV4 jadi
     * tidak cocok (403 SignatureDoesNotMatch). GET polos tanpa header tambahan
     * selalu lolos.
     *
     * @return array{content-length:string,etag:string}|null
     */
    public function head(string $key): ?array
    {
        foreach ($this->list($key, 1) as $item) {
            if ($item['key'] === $key) {
                return ['content-length' => (string) $item['size'], 'etag' => $item['etag']];
            }
        }

        return null;
    }

    public function delete(string $key): void
    {
        $this->expect($this->request('DELETE', $key), [200, 204, 404], 'DELETE ' . $key);
    }

    /**
     * Daftar objek di bawah prefix (mengikuti paginasi).
     *
     * @return list<array{key:string,size:int,etag:string}>
     */
    public function list(string $prefix = '', int $limit = PHP_INT_MAX): array
    {
        $items = [];
        $token = null;

        do {
            $query = ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => (string) min(1000, $limit)];
            if ($token !== null) {
                $query['continuation-token'] = $token;
            }

            $res = $this->request('GET', '', $query);
            $this->expect($res, [200], 'LIST ' . $prefix);

            $xml = simplexml_load_string($res['body']);
            if ($xml === false) {
                throw new RuntimeException('Respons LIST tidak valid.');
            }

            foreach ($xml->Contents as $c) {
                $items[] = ['key' => (string) $c->Key, 'size' => (int) $c->Size, 'etag' => trim((string) $c->ETag, '"')];
            }

            $token = (string) $xml->IsTruncated === 'true' ? (string) $xml->NextContinuationToken : null;
        } while ($token !== null && count($items) < $limit);

        return array_slice($items, 0, $limit === PHP_INT_MAX ? null : $limit);
    }

    /**
     * Request mentah ke bucket/objek. $key '' = bucket itu sendiri.
     *
     * @param  array<string,string> $query
     * @param  array<string,string> $headers
     * @param  array{hash:string,handle:resource,size:int}|null $upload
     * @param  resource|null $sink  tulis body respons ke sini (untuk unduhan besar)
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    public function request(
        string $method,
        string $key = '',
        array $query = [],
        array $headers = [],
        ?string $body = null,
        ?array $upload = null,
        $sink = null,
    ): array {
        $path        = $this->path($key);
        $payloadHash = $upload['hash'] ?? hash('sha256', $body ?? '');
        $amzDate     = gmdate('Ymd\THis\Z');
        $date        = substr($amzDate, 0, 8);
        $host        = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $port        = parse_url($this->endpoint, PHP_URL_PORT);

        $headers = array_change_key_case($headers, CASE_LOWER) + [
            'host'                 => $port ? $host . ':' . $port : $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'           => $amzDate,
        ];
        ksort($headers);

        ksort($query);
        $canonicalQuery = implode('&', array_map(
            static fn ($k, $v) => rawurlencode((string) $k) . '=' . rawurlencode((string) $v),
            array_keys($query),
            $query
        ));

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim((string) $value) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headers));

        $scope   = $date . '/' . $this->region . '/' . self::SERVICE . '/aws4_request';
        $request = implode("\n", [$method, $path, $canonicalQuery, $canonicalHeaders, $signedHeaders, $payloadHash]);
        $toSign  = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $request)]);

        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $headers['authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKey,
            $scope,
            $signedHeaders,
            hash_hmac('sha256', $toSign, $kSigning)
        );
        unset($headers['host']); // dikirim klien HTTP sendiri

        $url = $this->endpoint . $path . ($canonicalQuery !== '' ? '?' . $canonicalQuery : '');

        try {
            return Http::request($method, $url, [
                'headers'         => $headers,
                'body'            => $upload === null ? $body : null,
                'upload'          => $upload !== null ? ['handle' => $upload['handle'], 'size' => $upload['size']] : null,
                'sink'            => $sink,
                'timeout'         => $this->timeout,
                'connect_timeout' => 10,
            ]);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Object storage tidak bisa dihubungi: ' . $e->getMessage(), 0, $e);
        }
    }

    /** /bucket/key dengan setiap segmen di-encode (format kanonik SigV4). */
    private function path(string $key): string
    {
        $segments = $key === '' ? [] : explode('/', ltrim($key, '/'));

        return '/' . rawurlencode($this->bucket) . ($segments === [] ? '' : '/' . implode('/', array_map('rawurlencode', $segments)));
    }

    /** @param array{status:int,body:string} $res */
    private function expect(array $res, array $ok, string $what): void
    {
        if (in_array($res['status'], $ok, true)) {
            return;
        }

        $code = preg_match('#<Code>([^<]+)</Code>#', $res['body'], $m) ? $m[1] : '';
        $msg  = preg_match('#<Message>([^<]+)</Message>#', $res['body'], $n) ? $n[1] : '';

        throw new RuntimeException(trim(sprintf('Object storage: %s gagal (HTTP %d) %s %s', $what, $res['status'], $code, $msg)));
    }
}
