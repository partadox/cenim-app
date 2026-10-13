<?php
/**
 * Terbitkan sertifikat.
 *   type=<jenis>&scope=all       → massal untuk semua penerima jenis itu
 *   type=award&author_id=..      → penghargaan manual untuk satu orang (id paper_authors)
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\CertificateRepo;

if (!is_post()) {
    Response::redirect(admin_url('certificates.php'));
}

Csrf::verify();

$editionId = (int) (current_edition()['id'] ?? 0);
$type      = (string) input('type', '');

if (!in_array($type, CertificateRepo::types(), true)) {
    Session::flash('error', 'Unknown certificate type.');
    Response::redirect(admin_url('certificates.php'));
}

// ── Penghargaan: satu penerima, judul ditulis admin ─────────────────────────
if ($type === CertificateRepo::TYPE_AWARD) {
    $personId = input_int('author_id');   // id paper_authors (lihat api/author-search.php)
    $title    = trim((string) input('award_title', ''));
    $person   = $personId > 0 ? PaperAuthorRepo::find($personId) : null;

    if ($person === null || (int) $person['edition_id'] !== $editionId) {
        Session::flash('error', 'Choose a valid recipient for the award.');
        Response::redirect(admin_url('certificates.php'));
    }

    if (mb_strlen($title) < 3) {
        Session::flash('error', 'Please write the award title (at least 3 characters).');
        Response::redirect(admin_url('certificates.php'));
    }

    $certificate = CertificateRepo::ensure([
        'edition_id'            => $editionId,
        'type'                  => CertificateRepo::TYPE_AWARD,
        'recipient_name'        => (string) $person['name'],
        'recipient_email'       => $person['email'] ?? null,
        'recipient_affiliation' => $person['affiliation'] ?? null,
        'recipient_ref_type'    => 'paper_author',
        'recipient_ref_id'      => (int) $person['id'],
        'subject_title'         => $title,
    ]);

    Session::flash('success', 'Award certificate ' . ($certificate['code'] ?? '') . ' issued for '
        . $person['name'] . '.');
    Response::redirect(admin_url('certificates.php'));
}

$result = CertificateRepo::issueBulk($editionId, $type);

if ($result['issued'] === 0 && $result['existing'] === 0) {
    Session::flash('info', 'No eligible recipient for ' . CertificateRepo::label($type)
        . ' certificates yet (paid registrations only).');
} else {
    Session::flash('success', sprintf(
        '%d %s certificate(s) issued, %d already existed.',
        $result['issued'],
        CertificateRepo::label($type),
        $result['existing']
    ));
}

Response::redirect(admin_url('certificates.php'));
