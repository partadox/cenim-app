<?php
/**
 * Sajikan berkas hasil generate (logo/kop) dari storage.
 * Path dibatasi ke folder generated/logo dan generated/kop.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;

$path = (string) input('path', '');

if (preg_match('#^generated/(logo|kop)/\d{4}/[A-Za-z0-9._-]+$#', $path) !== 1) {
    Response::abort(404, 'File not found.');
}

$absolute = storage_path($path);
$real     = realpath($absolute);
$root     = realpath(storage_path('generated'));

if ($real === false || $root === false || !str_starts_with($real, $root) || !is_file($real)) {
    Response::abort(404, 'File not found.');
}

Response::file($real, basename($real), input('download') === null);
