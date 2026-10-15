<?php
/**
 * Pratinjau logo langsung (tidak disimpan): ?year=2027&size=512&bg=white
 * Tanpa ekstensi gd hasilnya SVG (ukuran diatur oleh <img>).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Generator\LogoGenerator;

$editionId = (int) (current_edition()['id'] ?? 0);
$year      = max(2018, min(2100, input_int('year') ?: (int) date('Y')));
$size      = max(64, min(2048, input_int('size') ?: 512));

try {
    // Tanpa ekstensi gd: SVG yang sama dengan sumber render PNG di browser.
    $svg = LogoGenerator::serverRender() ? null : LogoGenerator::svg($year, true, $editionId);
    $png = $svg === null ? LogoGenerator::png($year, $size, input('bg') !== 'white', $editionId) : '';
} catch (Throwable $e) {
    Logger::error('Pratinjau logo gagal', ['error' => $e->getMessage()]);
    Response::abort(500, 'Logo preview failed: ' . $e->getMessage());
}

if ($svg !== null) {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-store');
    echo $svg;
    exit;
}

if (!headers_sent()) {
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    header('Cache-Control: no-store');
}

echo $png;
