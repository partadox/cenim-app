<?php

declare(strict_types=1);

namespace Cenim\Support;

use RuntimeException;

/**
 * Klien HTTP kecil untuk object storage dan Turnstile.
 *
 * Memakai ekstensi curl bila ada; bila tidak (hosting EasyApache tanpa
 * ea-phpXY-php-curl), bicara HTTP/1.1 langsung lewat socket TLS bawaan PHP
 * (stream_socket_client + openssl). Jalur socket tidak bergantung pada
 * allow_url_fopen dan tetap streaming untuk unggahan/unduhan besar.
 *
 * HTTP_TRANSPORT=socket di .env memaksa jalur socket (untuk uji).
 */
final class Http
{
    /**
     * @param array{
     *   headers?: array<string,string>,
     *   body?: string|null,
     *   upload?: array{handle:resource,size:int}|null,
     *   sink?: resource|null,
     *   timeout?: int,
     *   connect_timeout?: int,
     * } $options
     *   sink: body respons 200 ditulis ke sini; body status lain tetap
     *   dikembalikan sebagai string supaya pesan error bisa dibaca.
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    public static function request(string $method, string $url, array $options = []): array
    {
        return self::transport() === 'curl'
            ? self::viaCurl($method, $url, $options)
            : self::viaSocket($method, $url, $options);
    }

    /** 'curl' atau 'socket'. */
    public static function transport(): string
    {
        if (strtolower((string) env('HTTP_TRANSPORT', '')) === 'socket') {
            return 'socket';
        }

        return ext_available('curl') ? 'curl' : 'socket';
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function viaCurl(string $method, string $url, array $options): array
    {
        $headers = $options['headers'] ?? [];
        $upload  = $options['upload'] ?? null;
        $body    = $options['body'] ?? null;
        $sink    = $options['sink'] ?? null;

        $respHeaders = [];
        $out         = '';

        $ch   = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => array_map(static fn ($k, $v) => $k . ': ' . $v, array_keys($headers), $headers),
            CURLOPT_CONNECTTIMEOUT => $options['connect_timeout'] ?? 10,
            CURLOPT_TIMEOUT        => $options['timeout'] ?? 60,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$respHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ];

        if ($method === 'HEAD') {
            $opts[CURLOPT_NOBODY] = true;
        }

        if ($upload !== null) {
            $opts[CURLOPT_UPLOAD]     = true;
            $opts[CURLOPT_INFILE]     = $upload['handle'];
            $opts[CURLOPT_INFILESIZE] = $upload['size'];
        } elseif ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        if ($sink !== null) {
            $opts[CURLOPT_WRITEFUNCTION] = static function ($ch, string $chunk) use ($sink, &$out): int {
                if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200) {
                    return (int) fwrite($sink, $chunk);
                }
                $out .= $chunk;
                return strlen($chunk);
            };
        } else {
            $opts[CURLOPT_RETURNTRANSFER] = true;
        }

        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);

        if ($result === false) {
            throw new RuntimeException('HTTP request failed: ' . $error);
        }
        if ($sink === null) {
            $out = (string) $result;
        }

        return ['status' => $status, 'headers' => $respHeaders, 'body' => $out];
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    private static function viaSocket(string $method, string $url, array $options): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw new RuntimeException('Invalid URL: ' . $url);
        }

        $https  = strtolower($parts['scheme'] ?? 'https') === 'https';
        $host   = $parts['host'];
        $port   = (int) ($parts['port'] ?? ($https ? 443 : 80));
        $target = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $context = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'peer_name'        => $host,
            'SNI_enabled'      => true,
        ]]);

        $errno  = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            ($https ? 'ssl://' : 'tcp://') . $host . ':' . $port,
            $errno,
            $errstr,
            (float) ($options['connect_timeout'] ?? 10),
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new RuntimeException('HTTP request failed: cannot connect to ' . $host . ' (' . trim($errstr) . ')');
        }

        stream_set_timeout($socket, (int) ($options['timeout'] ?? 60));

        try {
            $upload = $options['upload'] ?? null;
            $body   = $options['body'] ?? null;
            $length = $upload['size'] ?? ($body !== null ? strlen($body) : null);

            $lines = [$method . ' ' . $target . ' HTTP/1.1', 'Host: ' . $host . (isset($parts['port']) ? ':' . $port : '')];
            foreach ($options['headers'] ?? [] as $name => $value) {
                if (in_array(strtolower((string) $name), ['host', 'content-length', 'connection'], true)) {
                    continue;
                }
                $lines[] = $name . ': ' . $value;
            }
            if ($length !== null || in_array($method, ['POST', 'PUT'], true)) {
                $lines[] = 'Content-Length: ' . (int) $length;
            }
            $lines[] = 'Connection: close';

            self::write($socket, implode("\r\n", $lines) . "\r\n\r\n");

            if ($upload !== null) {
                while (!feof($upload['handle'])) {
                    $chunk = fread($upload['handle'], 65536);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    self::write($socket, $chunk);
                }
            } elseif ($body !== null && $body !== '') {
                self::write($socket, $body);
            }

            // ── Status & header (lewati 100 Continue) ─────────────────────────
            do {
                $statusLine = self::readLine($socket);
                if (!preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $statusLine, $m)) {
                    throw new RuntimeException('HTTP request failed: invalid response from ' . $host);
                }
                $status  = (int) $m[1];
                $headers = [];

                while (($line = self::readLine($socket)) !== '') {
                    $pair = explode(':', $line, 2);
                    if (count($pair) === 2) {
                        $headers[strtolower(trim($pair[0]))] = trim($pair[1]);
                    }
                }
            } while ($status === 100);

            // ── Body ─────────────────────────────────────────────────────────
            $sink = ($options['sink'] ?? null) !== null && $status === 200 ? $options['sink'] : null;
            $out  = '';
            $emit = static function (string $chunk) use ($sink, &$out): void {
                if ($sink !== null) {
                    if (fwrite($sink, $chunk) !== strlen($chunk)) {
                        throw new RuntimeException('HTTP request failed: cannot write the download.');
                    }
                } else {
                    $out .= $chunk;
                }
            };

            if ($method === 'HEAD' || $status === 204 || $status === 304) {
                // tanpa body
            } elseif (str_contains(strtolower($headers['transfer-encoding'] ?? ''), 'chunked')) {
                while (true) {
                    $size = hexdec(trim(explode(';', self::readLine($socket))[0]));
                    if ($size <= 0) {
                        while (self::readLine($socket) !== '') {
                            // trailer
                        }
                        break;
                    }
                    self::readExact($socket, (int) $size, $emit);
                    self::readLine($socket);
                }
            } elseif (isset($headers['content-length'])) {
                self::readExact($socket, (int) $headers['content-length'], $emit);
            } else {
                while (!feof($socket)) {
                    $chunk = fread($socket, 65536);
                    self::guard($socket);
                    if ($chunk === false) {
                        break;
                    }
                    $emit($chunk);
                }
            }

            return ['status' => $status, 'headers' => $headers, 'body' => $out];
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private static function write($socket, string $data): void
    {
        while ($data !== '') {
            $written = fwrite($socket, $data);
            self::guard($socket);
            if ($written === false || $written === 0) {
                throw new RuntimeException('HTTP request failed: connection closed while sending.');
            }
            $data = substr($data, $written);
        }
    }

    /** @param resource $socket */
    private static function readLine($socket): string
    {
        $line = fgets($socket);
        self::guard($socket);

        if ($line === false) {
            throw new RuntimeException('HTTP request failed: connection closed early.');
        }

        return rtrim($line, "\r\n");
    }

    /** @param resource $socket */
    private static function readExact($socket, int $length, callable $emit): void
    {
        while ($length > 0) {
            $chunk = fread($socket, min(65536, $length));
            self::guard($socket);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('HTTP request failed: response ended early.');
            }
            $emit($chunk);
            $length -= strlen($chunk);
        }
    }

    /** @param resource $socket */
    private static function guard($socket): void
    {
        if ((stream_get_meta_data($socket)['timed_out'] ?? false) === true) {
            throw new RuntimeException('HTTP request failed: timed out.');
        }
    }
}
