<?php
/**
 * Sponsor & institusi pendukung di landing: logo, nama, peran, link, urutan.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FileRepo;
use Cenim\Domain\SponsorRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if (is_post()) {
    Csrf::verify();

    $id  = input_int('id');
    $row = $id > 0 ? SponsorRepo::find($id) : null;

    if ($id > 0 && ($row === null || (int) $row['edition_id'] !== $editionId)) {
        Response::abort(404, 'Sponsor not found.');
    }

    if (input('action') === 'delete' && $row !== null) {
        SponsorRepo::delete($id);
        Audit::log('sponsor.deleted', ['meta' => ['name' => $row['name']]]);
        Session::flash('success', $row['name'] . ' removed.');
        Response::redirect(admin_url('sponsors.php'));
    }

    if (trim((string) input('name', '')) === '') {
        Session::flash('error', 'Please enter the sponsor name.');
        Response::redirect(admin_url('sponsors.php'));
    }

    $upload  = $_FILES['logo'] ?? null;
    $hasLogo = is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($row === null && !$hasLogo) {
        Session::flash('error', 'Please choose a logo for the new sponsor.');
        Response::redirect(admin_url('sponsors.php'));
    }

    // Validasi logo DULU, supaya sponsor baru tidak tersimpan tanpa logo bila ditolak.
    if ($hasLogo) {
        $info  = @getimagesize((string) $upload['tmp_name']);
        $error = match (true) {
            $info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) => 'The logo must be PNG or JPG.',
            $info[1] < SponsorRepo::LOGO_MIN_HEIGHT => sprintf('The logo is %d px tall; use at least %d px.', $info[1], SponsorRepo::LOGO_MIN_HEIGHT),
            (int) $upload['size'] > upload_max_bytes(SponsorRepo::LOGO_MAX_BYTES) => 'The logo must not exceed ' . upload_max_label(SponsorRepo::LOGO_MAX_BYTES) . '.',
            default => null,
        };
        if ($error !== null) {
            Session::flash('error', 'Logo: ' . $error);
            Response::redirect(admin_url('sponsors.php'));
        }
    }

    $savedId = SponsorRepo::save($editionId, $row !== null ? $id : null, [
        'name'       => (string) input('name', ''),
        'role'       => (string) input('role', ''),
        'url'        => (string) input('url', ''),
        'sort'       => input_int('sort'),
        'is_visible' => input_bool('is_visible'),
    ]);

    if ($hasLogo) {
        try {
            $fileId = FileRepo::replace($upload, 'sponsor_logo', 'sponsor', $savedId, $editionId, (int) ($edition['year'] ?? date('Y')));
            SponsorRepo::setLogo($savedId, $fileId);
        } catch (InvalidArgumentException $e) {
            Session::flash('error', 'Logo: ' . $e->getMessage());
            Response::redirect(admin_url('sponsors.php'));
        }
    }

    Audit::log('sponsor.saved', ['meta' => ['id' => $savedId, 'name' => input('name')]]);
    Session::flash('success', trim((string) input('name')) . ' saved.');
    Response::redirect(admin_url('sponsors.php'));
}

View::render('admin/sponsors', [
    'title'    => 'Sponsors',
    'subtitle' => 'Logo slider at the bottom of the landing page — ' . ($edition['name'] ?? ''),
    'active'   => 'sponsors',
    'edition'  => $edition,
    'rows'     => SponsorRepo::all($editionId),
], 'admin');
