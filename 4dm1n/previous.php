<?php
/**
 * Kartu "Previous Conference" di landing: judul, gambar, tautan situs lama,
 * tautan IEEE Xplore, tampil/sembunyi. Edisi sistem ini otomatis ditambahkan
 * begitu edisi berikutnya aktif.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FileRepo;
use Cenim\Domain\PreviousConferenceRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if (is_post()) {
    Csrf::verify();

    $id     = input_int('id');
    $action = (string) input('action', 'save');
    $row    = $id > 0 ? PreviousConferenceRepo::find($id) : null;

    if ($action === 'delete' && $row !== null) {
        PreviousConferenceRepo::delete($id);
        Audit::log('previous_conference.deleted', ['meta' => ['year' => $row['year']]]);
        Session::flash('success', $row['title'] . ' removed.');
        Response::redirect(admin_url('previous.php'));
    }

    $year = input_int('year');
    if ($year < 2000 || $year > 2100) {
        Session::flash('error', 'Please enter a valid year.');
        Response::redirect(admin_url('previous.php'));
    }

    $duplicate = Cenim\Core\Db::value('SELECT id FROM previous_conferences WHERE year = ? AND id != ?', [$year, $id]);
    if ($duplicate !== null) {
        Session::flash('error', 'A card for ' . $year . ' already exists.');
        Response::redirect(admin_url('previous.php'));
    }

    $savedId = PreviousConferenceRepo::save($row !== null ? $id : null, [
        'year'       => $year,
        'title'      => (string) input('title', ''),
        'site_url'   => (string) input('site_url', ''),
        'xplore_url' => (string) input('xplore_url', ''),
        'is_visible' => input_bool('is_visible'),
    ]);

    $upload = $_FILES['image'] ?? null;
    if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $info = @getimagesize((string) $upload['tmp_name']);
            if ($info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
                throw new InvalidArgumentException('The image must be PNG or JPG.');
            }
            $fileId = FileRepo::replace($upload, 'previous_conf_image', 'previous_conference', $savedId, $editionId, $year);
            PreviousConferenceRepo::setImage($savedId, $fileId);
        } catch (InvalidArgumentException $e) {
            Session::flash('error', 'Image: ' . $e->getMessage());
        }
    }

    Audit::log('previous_conference.saved', ['meta' => ['year' => $year]]);
    Session::flash('success', 'CENIM ' . $year . ' saved.');
    Response::redirect(admin_url('previous.php'));
}

View::render('admin/previous', [
    'title'    => 'Previous Conferences',
    'subtitle' => 'Cards in the "Previous Conference" section of the landing page',
    'active'   => 'previous',
    'edition'  => $edition,
    'rows'     => PreviousConferenceRepo::all(),
], 'admin');
