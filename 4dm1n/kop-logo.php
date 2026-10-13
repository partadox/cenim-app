<?php
/**
 * Admin: gambar satu logo kop (?id=<kop_logos.id>) untuk pratinjau di halaman Kop.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Domain\BrandingRepo;

$editionId = (int) (current_edition()['id'] ?? 0);
$row       = Db::one('SELECT * FROM kop_logos WHERE id = ? AND edition_id = ?', [input_int('id'), $editionId]);
$path      = $row !== null ? BrandingRepo::kopLogoPath($row, $editionId, (int) (current_edition()['year'] ?? date('Y'))) : null;

if ($path === null) {
    Response::abort(404, 'Logo not found.');
}

header('Cache-Control: private, max-age=300');
Response::file($path, basename($path), true);
