<?php
/**
 * Penampil berkas log:
 *   ?kind=app&name=app-2026-08-13.log[&lines=300]  — ekor log aplikasi (text/plain)
 *   ?kind=mail&name=20260813-004512-invoice-....html — email yang tersimpan
 *
 * Nama berkas dicocokkan ke pola ketat, lalu path hasil realpath() wajib berada
 * di dalam folder log — jadi tidak ada jalan menuju berkas lain lewat `name`.
 *
 * Email yang tersimpan berisi HTML; disajikan dengan header CSP `sandbox`
 * sehingga tidak bisa menjalankan script atau form di origin panel.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;

$kind = (string) input('kind', 'app');
$name = basename((string) input('name', ''));

if ($kind === 'mail') {
    if (!preg_match('/^[A-Za-z0-9._-]+\.(html|txt)$/', $name)) {
        Response::abort(404, 'Not found.');
    }

    $dir  = storage_path('logs/mail');
    $path = realpath($dir . '/' . $name);

    if ($path === false || !str_starts_with($path, (string) realpath($dir)) || !is_file($path)) {
        Response::abort(404, 'Not found.');
    }

    header('Content-Type: ' . (str_ends_with($name, '.txt') ? 'text/plain' : 'text/html') . '; charset=utf-8');
    header('Content-Security-Policy: sandbox');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex');
    echo file_get_contents($path);
    exit;
}

if (!preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $name)) {
    Response::abort(404, 'Not found.');
}

$dir  = storage_path('logs');
$path = realpath($dir . '/' . $name);

if ($path === false || !str_starts_with($path, (string) realpath($dir)) || !is_file($path)) {
    Response::abort(404, 'Not found.');
}

$lines = max(20, min(2000, (int) input('lines', '300')));
$tail  = [];

// Baca dari belakang supaya log besar tidak perlu dimuat seluruhnya.
$handle = fopen($path, 'rb');

if ($handle === false) {
    Response::abort(500, 'Log tidak bisa dibaca.');
}

$size   = (int) filesize($path);
$chunk  = 8192;
$buffer = '';
$offset = $size;

while ($offset > 0 && substr_count($buffer, "\n") <= $lines) {
    $read   = min($chunk, $offset);
    $offset -= $read;
    fseek($handle, $offset);
    $buffer = (string) fread($handle, $read) . $buffer;
}

fclose($handle);

$tail = array_slice(preg_split('/\R/', rtrim($buffer, "\n")) ?: [], -$lines);

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');

printf(
    "# %s — %s, %s (menampilkan %d baris terakhir)\n%s\n",
    $name,
    fmt_bytes($size),
    fmt_dt(gmdate('Y-m-d H:i:s', (int) filemtime($path))),
    count($tail),
    str_repeat('-', 78)
);

echo implode("\n", $tail), "\n";
