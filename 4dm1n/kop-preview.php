<?php
/**
 * Pratinjau kop langsung (tidak disimpan): ?year=2027&w=1200
 * Tanpa ekstensi gd hasilnya SVG.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Generator\KopGenerator;
use Cenim\Generator\LogoGenerator;

$editionId = (int) (current_edition()['id'] ?? 0);
$year      = max(2018, min(2100, input_int('year') ?: (int) date('Y')));
$width     = max(600, min(2480, input_int('w') ?: 1200));

try {
    // Tanpa ekstensi gd: SVG yang sama dengan sumber render PNG di browser.
    $svg = LogoGenerator::serverRender() ? null : KopGenerator::svg($year, $editionId);
    $png = $svg === null ? KopGenerator::png($year, $editionId, $width) : '';
} catch (Throwable $e) {
    Logger::error('Pratinjau kop gagal', ['error' => $e->getMessage()]);
    Response::abort(500, 'Letterhead preview failed: ' . $e->getMessage());
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
