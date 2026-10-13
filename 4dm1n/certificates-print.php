<?php
/**
 * Admin: semua sertifikat valid satu jenis dalam satu halaman — untuk dicetak
 * atau disimpan sebagai satu PDF (pengganti ekspor ZIP).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Core\View;
use Cenim\Domain\CertificateRepo;
use Cenim\Support\CertificateHtml;

$editionId = (int) (current_edition()['id'] ?? 0);
$type      = (string) input('type', '');

if (!in_array($type, CertificateRepo::types(), true)) {
    Response::abort(404, 'Unknown certificate type.');
}

$rows = array_values(array_filter(
    CertificateRepo::byType($editionId, $type),
    static fn (array $row): bool => !CertificateRepo::isRevoked($row)
));

if ($rows === []) {
    Response::abort(404, 'No valid certificate of this type yet.');
}

// Satu halaman dibatasi 300 lembar supaya browser tetap sanggup merender.
$rows = array_slice($rows, 0, 300);

View::render('certificate/document', [
    'sheets'   => array_map([CertificateHtml::class, 'prepare'], $rows),
    'title'    => CertificateRepo::label($type) . ' certificates (' . count($rows) . ')',
    'backUrl'  => admin_url('certificates.php'),
    'filename' => 'Certificates-' . CertificateRepo::label($type) . '.pdf',
]);
