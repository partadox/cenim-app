<?php
/**
 * Hapus satu sertifikat (mis. penghargaan yang salah terbit). Link
 * verifikasinya tidak berlaku lagi; sertifikat otomatis (author, presenter,
 * dst.) bisa diterbitkan ulang lewat tombol Issue.
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

CertificateRepo::delete($id);

Session::flash('success', 'Certificate ' . $certificate['code'] . ' (' . $certificate['recipient_name'] . ') deleted.');
Response::redirect(admin_url('certificates.php'));
