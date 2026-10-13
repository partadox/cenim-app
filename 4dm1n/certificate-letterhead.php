<?php
/**
 * Sajikan gambar kop yang dipakai PDF, supaya pratinjau editor template
 * memperlihatkan hal yang sama dengan hasil cetaknya.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Pdf\PdfBase;

$path = PdfBase::letterheadPath();

if ($path === null || !is_file($path)) {
    Response::abort(404, 'No letterhead available yet.');
}

Response::file($path, basename($path), true);
