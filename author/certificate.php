<?php
/**
 * Lihat satu sertifikat (HTML) milik orang yang login — sebagai author di paper
 * mana pun atau sebagai panitia/reviewer. PDF dibuat di browser dari halaman ini.
 */

declare(strict_types=1);

const AUTHOR_ALLOW_NO_PAPER = true;

require __DIR__ . '/_boot.php';

use Cenim\Core\AuthorAuth;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\CertificateRepo;
use Cenim\Support\CertificateHtml;

$edition     = current_edition();
$id          = input_int('id');
$certificate = $id > 0 ? CertificateRepo::find($id) : null;
$back        = author_url('index.php' . (input('from') === 'committee' ? '?tab=committee' : ''));

if (
    $certificate === null
    || !CertificateRepo::belongsTo($certificate, AuthorAuth::personIds(), AuthorAuth::committeeIds())
) {
    Response::abort(403, 'You are not allowed to open this certificate.');
}

// Sertifikat bisa dari edisi lain (mis. peran panitia tahun lalu): pakai edisinya sendiri.
$edition = Cenim\Domain\EditionRepo::find((int) $certificate['edition_id']) ?? $edition;
Cenim\Domain\EditionRepo::setContext($edition);

if (CertificateRepo::isRevoked($certificate)) {
    Session::flash('error', 'This certificate has been revoked. Please contact the secretariat.');
    Response::redirect($back);
}

if (!CertificateRepo::isReleased($edition)) {
    Session::flash('info', 'Certificates are not released yet.');
    Response::redirect($back);
}

CertificateRepo::markDownloaded($id);

$sheet = CertificateHtml::prepare($certificate);

View::render('certificate/document', [
    'sheets'   => [$sheet],
    'title'    => $sheet['title'],
    'backUrl'  => $back,
    'filename' => $sheet['filename'],
]);
