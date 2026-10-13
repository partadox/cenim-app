<?php

declare(strict_types=1);

namespace Cenim\Support;

use RuntimeException;
use ZipArchive;

/**
 * Pembungkus ZipArchive untuk ekspor massal (sertifikat per jenis).
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
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi zip PHP tidak tersedia di server ini.');
        }

        if (!ensure_dir(dirname($zipPath))) {
            throw new RuntimeException('Folder ZIP tidak writable: ' . dirname($zipPath));
        }

        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak bisa membuat arsip ZIP.');
        }

        $added = 0;
        foreach ($files as $name => $path) {
            if (!is_file($path)) {
                continue;
            }
            $zip->addFile($path, self::safeEntryName((string) $name));
            $added++;
        }

        if ($added === 0) {
            $zip->close();
            @unlink($zipPath);
            throw new RuntimeException('Tidak ada file yang bisa dimasukkan ke ZIP.');
        }

        $zip->close();

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
