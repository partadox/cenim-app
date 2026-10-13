<?php

declare(strict_types=1);

namespace Cenim\Storage;

use Cenim\Core\Logger;
use Throwable;

/**
 * Pemilih driver penyimpanan sesuai STORAGE_DRIVER di .env (local | s3).
 * Bila driver pilihan tidak siap, otomatis jatuh ke LocalDriver dan dicatat
 * ke log — upload author tidak boleh gagal hanya karena konfigurasi remote.
 */
final class StorageManager
{
    private static ?StorageInterface $default = null;

    public static function driver(?string $name = null): StorageInterface
    {
        $name ??= (string) env('STORAGE_DRIVER', 'local');

        if ($name === 's3') {
            try {
                return new S3Driver(S3Client::fromEnv());
            } catch (Throwable $e) {
                Logger::warn('Storage: driver s3 tidak siap, memakai local', ['error' => $e->getMessage()]);
                return new LocalDriver();
            }
        }

        return new LocalDriver();
    }

    /** Driver default (di-cache per request). */
    public static function default(): StorageInterface
    {
        return self::$default ??= self::driver();
    }

    /** Driver yang cocok untuk satu baris tabel files (mengikuti kolom driver-nya). */
    public static function forFile(array $file): StorageInterface
    {
        return self::driver((string) ($file['driver'] ?? 'local'));
    }

    /**
     * Simpan file dengan driver default; bila object storage sedang tidak bisa
     * dihubungi, simpan lokal (baris tercatat driver=local) supaya upload author
     * tetap berhasil. Pindahkan belakangan dengan: php bin/storage.php migrate-files
     */
    public static function put(string $sourcePath, string $relativePath, string $mime): array
    {
        $driver = self::default();

        try {
            return $driver->put($sourcePath, $relativePath, $mime);
        } catch (Throwable $e) {
            if ($driver instanceof LocalDriver) {
                throw $e;
            }

            Logger::warn('Storage: upload ke ' . $driver->name() . ' gagal, disimpan lokal', [
                'path'  => $relativePath,
                'error' => $e->getMessage(),
            ]);

            return (new LocalDriver())->put($sourcePath, $relativePath, $mime);
        }
    }
}
