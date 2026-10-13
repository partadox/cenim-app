<?php
/**
 * Penyaji aset publik dari object storage Tandikat:
 *   /cenim-app/assets/theme/<path>  → objek public/theme/<path>  (aset tema landing)
 *   /cenim-app/assets/media/<path>  → objek public/media/<path>  (foto speaker, dll)
 *
 * URL di atas diteruskan ke sini oleh cenim-app/.htaccess (hanya bila berkasnya
 * tidak ada di disk), jadi path relatif di CSS tema — url(../fonts/...) — tetap
 * benar tanpa mengubah satu file CSS pun.
 *
 * Bucket-nya privat, jadi skrip ini yang mengambil objek (dengan kredensial),
 * menyimpannya di cache lokal, lalu menyajikannya dengan header cache panjang.
 * Request berikutnya langsung dari cache — object storage hanya disentuh sekali
 * per berkas. Kosongkan cache setelah mengganti aset: php bin/storage.php cache-clear
 *
 * Bila suatu saat bucket dibuka baca-anonim untuk "public/", isi
 * TANDIKAT_PUBLIC_URL di .env: asset() langsung menunjuk ke object storage dan
 * skrip ini tidak terpakai lagi.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\Logger;
use Cenim\Storage\S3Client;
use Cenim\Storage\MimeTypes;
use Cenim\Storage\S3Driver;

$notFound = static function (): never {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    exit("Not found.\n");
};

// ── Validasi path: hanya theme/ & media/, tanpa traversal, ekstensi dikenal ──
$path = (string) ($_GET['p'] ?? '');
$mime = MimeTypes::forPath($path);

if (
    !preg_match('#^(theme|media)/[^\x00-\x1f\\\\]+$#', $path)
    || str_contains($path, '..')
    || str_contains($path, '//')
    || $mime === null
) {
    $notFound();
}

$key    = 'public/' . $path;
$cached = S3Driver::cachePath($key);
$miss   = storage_path('cache/s3-missing/' . sha1($key));

// ── Ambil dari object storage bila belum ada di cache ───────────────────────
if (!is_file($cached)) {
    // Objek yang baru saja dipastikan tidak ada tidak ditanyakan ulang selama
    // 5 menit — bot yang menebak URL tidak membanjiri object storage.
    if (is_file($miss) && filemtime($miss) > time() - 300) {
        $notFound();
    }

    try {
        if (!ensure_dir(dirname($cached))) {
            throw new RuntimeException('Folder cache tidak writable: ' . dirname($cached));
        }
        S3Client::fromEnv()->getToFile($key, $cached);
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'HTTP 404')) {
            ensure_dir(dirname($miss)) && @touch($miss);
            $notFound();
        }

        Logger::error('Aset gagal diambil dari object storage', ['key' => $key, 'error' => $e->getMessage()]);
        http_response_code(503);
        header('Retry-After: 30');
        header('Cache-Control: no-store');
        exit("Asset storage unavailable.\n");
    }
}

// ── Sajikan dengan cache browser + revalidasi ───────────────────────────────
$mtime = (int) filemtime($cached);
$size  = (int) filesize($cached);
$etag  = '"' . dechex($mtime) . '-' . dechex($size) . '"';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=604800');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('X-Content-Type-Options: nosniff');

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Length: ' . $size);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    readfile($cached);
}
