<?php
/**
 * Admin: lihat satu sertifikat (HTML) — termasuk yang dicabut (bertanda REVOKED)
 * dan sebelum tanggal rilis. PDF diunduh dari halaman ini (dibuat di browser).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Core\View;
use Cenim\Domain\CertificateRepo;
use Cenim\Support\CertificateHtml;

$certificate = CertificateRepo::find(input_int('id'));

if ($certificate === null || (int) $certificate['edition_id'] !== (int) (current_edition()['id'] ?? 0)) {
    Response::abort(404, 'Certificate not found.');
}

$sheet = CertificateHtml::prepare($certificate);

View::render('certificate/document', [
    'sheets'   => [$sheet],
    'title'    => $sheet['title'],
    'backUrl'  => admin_url('certificates.php'),
    'filename' => $sheet['filename'],
]);
