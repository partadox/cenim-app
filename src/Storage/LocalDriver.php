<?php

declare(strict_types=1);

namespace Cenim\Storage;

use RuntimeException;

/**
 * Simpan file di folder storage privat (di luar web root). File hanya bisa
 * diakses lewat public/file.php yang memeriksa otorisasi lebih dulu.
 */
final class LocalDriver implements StorageInterface
{
    public function name(): string
    {
        return 'local';
    }

    public function put(string $sourcePath, string $relativePath, string $mime): array
    {
        $target = storage_path($relativePath);

        if (!ensure_dir(dirname($target))) {
            throw new RuntimeException('Folder penyimpanan tidak writable: ' . dirname($target));
        }

        // move_uploaded_file untuk file hasil upload, rename untuk file yang kita buat sendiri.
        $moved = is_uploaded_file($sourcePath)
            ? move_uploaded_file($sourcePath, $target)
            : rename($sourcePath, $target);

        if (!$moved) {
            throw new RuntimeException('File gagal disimpan ke penyimpanan lokal.');
        }

        @chmod($target, 0644);

        return [
            'driver'     => 'local',
            'path'       => $relativePath,
            'remote_id'  => null,
            'remote_url' => null,
        ];
    }

    public function localPath(array $file): ?string
    {
        $path = (string) ($file['path'] ?? '');
        if ($path === '') {
            return null;
        }

        $absolute = storage_path($path);

        // Cegah path traversal: hasil akhir harus tetap di dalam folder storage.
        $real     = realpath($absolute);
        $rootReal = realpath(storage_path());

        if ($real === false || $rootReal === false || !str_starts_with($real, $rootReal)) {
            return null;
        }

        return $real;
    }

    public function delete(array $file): bool
    {
        $path = $this->localPath($file);
        return $path !== null && is_file($path) ? @unlink($path) : true;
    }
}
