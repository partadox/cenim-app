<?php

declare(strict_types=1);

namespace Cenim\Storage;

/**
 * Content-Type aset publik berdasarkan ekstensi. Dipakai saat mengunggah ke
 * object storage (bin/storage.php) dan saat menyajikannya (public/asset.php) —
 * mime_content_type() menebak CSS/JS sebagai text/plain, yang ditolak browser
 * karena header nosniff.
 */
final class MimeTypes
{
    public const ASSETS = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'text/javascript; charset=utf-8',
        'json'  => 'application/json',
        'map'   => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'eot'   => 'application/vnd.ms-fontobject',
        'mp4'   => 'video/mp4',
        'pdf'   => 'application/pdf',
    ];

    /** null bila ekstensi bukan aset publik yang diizinkan. */
    public static function forPath(string $path): ?string
    {
        return self::ASSETS[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }
}
