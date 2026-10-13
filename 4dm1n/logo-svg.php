<?php
/**
 * Unduh logo sebagai SVG: ?year=2027 (font ditanam agar tampil sama di mana pun)
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Generator\LogoGenerator;

$editionId = (int) (current_edition()['id'] ?? 0);
$year      = max(2018, min(2100, input_int('year') ?: (int) date('Y')));

try {
    $svg = LogoGenerator::svg($year, input('nofont') === null, $editionId);
} catch (Throwable $e) {
    Logger::error('SVG logo gagal', ['error' => $e->getMessage()]);
    Response::abort(500, 'SVG could not be generated: ' . $e->getMessage());
}

if (!headers_sent()) {
    header('Content-Type: image/svg+xml');
    header('Content-Disposition: attachment; filename="logo-cenim-' . $year . '.svg"');
}

echo $svg;
