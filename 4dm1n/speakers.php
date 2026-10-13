<?php
/**
 * Keynote speaker untuk landing page: tambah, ubah, hapus, atur urutan.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FileRepo;
use Cenim\Domain\SpeakerRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$year      = (int) ($edition['year'] ?? date('Y'));

if (is_post()) {
    Csrf::verify();

    $action = (string) input('action', '');
    $id     = input_int('id');

    $input = [
        'name'        => (string) input('name', ''),
        'title'       => (string) input('title', ''),
        'affiliation' => (string) input('affiliation', ''),
        'photo_url'   => (string) input('photo_url', ''),
        'profile_url' => (string) input('profile_url', ''),
        'scholar'     => (string) input('scholar', ''),
        'scopus'      => (string) input('scopus', ''),
        'website'     => (string) input('website', ''),
        'bio'         => (string) input('bio', ''),
        'sort'        => input_int('sort'),
        'is_visible'  => $action === 'create' ? true : input_bool('is_visible'),
    ];

    if ($action === 'delete') {
        $speaker = SpeakerRepo::find($id);

        if ($speaker !== null && (int) $speaker['edition_id'] === $editionId) {
            SpeakerRepo::delete($id);
            Audit::log('speaker.deleted', ['target_type' => 'speaker', 'target_id' => $id, 'meta' => ['name' => $speaker['name']]]);
            Session::flash('success', 'Speaker deleted.');
        }

        Response::redirect(admin_url('speakers.php'));
    }

    $errors = SpeakerRepo::validate($input);

    if ($errors !== []) {
        Session::flash('error', implode(' ', $errors));
        Response::redirect(admin_url('speakers.php'));
    }

    if ($action === 'create') {
        $id = SpeakerRepo::create($editionId, $input);
        Audit::log('speaker.created', ['target_type' => 'speaker', 'target_id' => $id, 'meta' => ['name' => $input['name']]]);
        Session::flash('success', 'Speaker added.');
    } else {
        $speaker = SpeakerRepo::find($id);

        if ($speaker === null || (int) $speaker['edition_id'] !== $editionId) {
            Session::flash('error', 'Speaker not found.');
            Response::redirect(admin_url('speakers.php'));
        }

        SpeakerRepo::update($id, $input);
        Session::flash('success', 'Speaker updated.');
    }

    // Foto opsional: unggahan menggantikan photo_url.
    $file = $_FILES['photo'] ?? null;

    if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $fileId = FileRepo::replace($file, 'speaker_photo', 'speaker', $id, $editionId, $year);
            SpeakerRepo::setPhoto($id, $fileId);
        } catch (InvalidArgumentException $e) {
            Session::flash('error', 'Photo: ' . $e->getMessage());
        }
    }

    Response::redirect(admin_url('speakers.php'));
}

View::render('admin/speakers', [
    'title'    => 'Keynote speakers',
    'subtitle' => ($edition['name'] ?? '') . ' — ' . SpeakerRepo::countByEdition($editionId) . ' speaker(s)',
    'active'   => 'speakers',
    'edition'  => $edition,
    'speakers' => SpeakerRepo::all($editionId),
], 'admin');
