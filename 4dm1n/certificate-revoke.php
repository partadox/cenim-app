<?php
/**
 * Batalkan (revoke) atau pulihkan satu sertifikat.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\CertificateRepo;

if (!is_post()) {
    Response::redirect(admin_url('certificates.php'));
}

Csrf::verify();

$editionId   = (int) (current_edition()['id'] ?? 0);
$id          = input_int('id');
$certificate = $id > 0 ? CertificateRepo::find($id) : null;

if ($certificate === null || (int) $certificate['edition_id'] !== $editionId) {
    Session::flash('error', 'Certificate not found in the active edition.');
    Response::redirect(admin_url('certificates.php'));
}

if (input('action') === 'restore') {
    CertificateRepo::restore($id);
    Session::flash('success', 'Certificate ' . $certificate['code'] . ' restored.');
    Response::redirect(admin_url('certificates.php'));
}

$reason = trim((string) input('reason', ''));

if (mb_strlen($reason) < 5) {
    Session::flash('error', 'Please state a short reason for revoking the certificate.');
    Response::redirect(admin_url('certificates.php'));
}

CertificateRepo::revoke($id, $reason);

Session::flash('success', 'Certificate ' . $certificate['code'] . ' revoked — the verification page now shows it as invalid.');
Response::redirect(admin_url('certificates.php'));
