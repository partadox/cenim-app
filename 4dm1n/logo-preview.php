<?php
/**
 * Pratinjau logo langsung (tidak disimpan): ?year=2027&size=512&bg=white
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
    $png = LogoGenerator::png($year, $size, input('bg') !== 'white', $editionId);
} catch (Throwable $e) {
    Logger::error('Pratinjau logo gagal', ['error' => $e->getMessage()]);
    Response::abort(500, 'Logo preview failed: ' . $e->getMessage());
}

if (!headers_sent()) {
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    header('Cache-Control: no-store');
}

echo $png;
