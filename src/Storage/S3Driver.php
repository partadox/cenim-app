<?php

declare(strict_types=1);

namespace Cenim\Storage;

use RuntimeException;

/**
 * Simpan file di object storage Tandikat (RustFS, S3-compatible).
 *
 * Tata letak bucket:
 *   public/theme/...   aset tema landing (diunggah dengan bin/storage.php push-theme)
 *   public/media/...   gambar publik dari panel (foto speaker) — path relatif "media/..."
 *   private/uploads/.. bukti student/IEEE, bukti bayar, tanda tangan, background sertifikat
 *
 * Bucket sendiri privat. Semua yang "public/" disajikan lewat public/asset.php
 * (atau langsung dari TANDIKAT_PUBLIC_URL bila policy bucket mengizinkan baca
 * anonim); yang "private/" hanya lewat public/file.php setelah cek otorisasi.
 *
 * localPath() mengunduh objek ke cache lokal (storage/cache/s3) — tFPDF dan GD
 * butuh berkas fisik, dan file.php cukup menyajikan salinan itu.
 */
final class S3Driver implements StorageInterface
{
    public function __construct(private readonly S3Client $client)
    {
    }

    public function name(): string
    {
        return 's3';
    }

    /** Kunci objek untuk path relatif aplikasi (uploads/... atau media/...). */
    public static function keyFor(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');

        return (str_starts_with($relativePath, 'media/') ? 'public/' : 'private/') . $relativePath;
    }

    public function put(string $sourcePath, string $relativePath, string $mime): array
    {
        $key = self::keyFor($relativePath);

        $this->client->putFile($key, $sourcePath, $mime);

        // Samakan semantik dengan LocalDriver ("pindahkan"): berkas buatan kita
        // sendiri dihapus; file upload PHP dibersihkan PHP di akhir request.
        if (!is_uploaded_file($sourcePath)) {
            @unlink($sourcePath);
        }

        return [
            'driver'     => 's3',
            'path'       => $key,
            'remote_id'  => null,
            'remote_url' => null,
        ];
    }

    public function localPath(array $file): ?string
    {
        $key = (string) ($file['path'] ?? '');

        if ($key === '' || str_contains($key, '..') || !preg_match('#^(public|private)/#', $key)) {
            return null;
        }

        $cached = self::cachePath($key);

        if (is_file($cached)) {
            return $cached;
        }

        if (!ensure_dir(dirname($cached))) {
            throw new RuntimeException('Folder cache tidak writable: ' . dirname($cached));
        }

        try {
            $this->client->getToFile($key, $cached);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'HTTP 404')) {
                return null;
            }
            throw $e;
        }

        return $cached;
    }

    public function delete(array $file): bool
    {
        $key = (string) ($file['path'] ?? '');
        if ($key === '') {
            return true;
        }

        $this->client->delete($key);
        @unlink(self::cachePath($key));

        return true;
    }

    /** Salinan lokal sebuah objek (di luar web root, ikut STORAGE_PATH). */
    public static function cachePath(string $key): string
    {
        return storage_path('cache/s3/' . ltrim($key, '/'));
    }
}
