<?php

declare(strict_types=1);

namespace Cenim\Support;

use RuntimeException;

/**
 * Arsip ZIP untuk ekspor massal (sertifikat per jenis). Ditulis dengan
 * ZipWriter (PHP murni), jadi tidak butuh ekstensi zip.
 */
final class Zip
{
    /**
     * Buat arsip ZIP dari daftar file.
     *
     * @param array<string,string> $files nama di dalam zip => path absolut
     */
    public static function create(string $zipPath, array $files): string
    {
        if (!ensure_dir(dirname($zipPath))) {
            throw new RuntimeException('Folder ZIP tidak writable: ' . dirname($zipPath));
        }

        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipWriter($zipPath);

        foreach ($files as $name => $path) {
            if (is_file($path)) {
                $zip->addFile($path, self::safeEntryName((string) $name));
            }
        }

        $added = $zip->count();
        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            throw new RuntimeException('Tidak ada file yang bisa dimasukkan ke ZIP.');
        }

        return $zipPath;
    }

    /** Nama file di dalam arsip: tanpa path, tanpa karakter aneh. */
    public static function safeEntryName(string $name): string
    {
        $name = str_replace(['/', '\\'], '-', $name);
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $name) ?? $name;

        return trim($name, '-. ') ?: 'file';
    }
}
