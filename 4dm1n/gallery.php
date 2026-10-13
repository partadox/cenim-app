<?php
/**
 * Poster CFP & galeri foto di bagian About landing.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FileRepo;
use Cenim\Domain\GalleryRepo;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$year      = (int) ($edition['year'] ?? date('Y'));
$back      = admin_url('gallery.php');

if (is_post()) {
    Csrf::verify();

    $action = (string) input('action', '');
    $id     = input_int('id');
    $photo  = $id > 0 ? GalleryRepo::find($id) : null;

    if ($photo !== null && (int) $photo['edition_id'] !== $editionId) {
        Response::abort(404, 'Photo not found.');
    }

    switch ($action) {
        case 'poster_upload':
            $error = GalleryRepo::checkImage($_FILES['poster'] ?? [], GalleryRepo::POSTER_MIN_WIDTH);
            if ($error !== null) {
                Session::flash('error', 'Poster: ' . $error);
                break;
            }
            $fileId = FileRepo::replace($_FILES['poster'], 'cfp_poster', 'edition', $editionId, $editionId, $year);
            SettingRepo::set($editionId, 'cfp_poster_file_id', $fileId);
            SettingRepo::set($editionId, 'cfp_poster_enabled', true);
            Audit::log('gallery.poster_uploaded', ['meta' => ['file_id' => $fileId]]);
            Session::flash('success', 'Poster uploaded.');
            break;

        case 'poster_toggle':
            SettingRepo::set($editionId, 'cfp_poster_enabled', input_bool('enabled'));
            Session::flash('success', input_bool('enabled') ? 'Poster button shown on the landing page.' : 'Poster button hidden.');
            break;

        case 'poster_remove':
            SettingRepo::set($editionId, 'cfp_poster_file_id', 0);
            Session::flash('success', 'Uploaded poster removed — the About image is used until a new poster is uploaded.');
            break;

        case 'photos_upload':
            $files = $_FILES['photos'] ?? null;
            $added = 0;
            $errors = [];
            if (is_array($files) && is_array($files['name'] ?? null)) {
                foreach (array_keys($files['name']) as $i) {
                    $one = [
                        'name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i], 'size' => $files['size'][$i],
                    ];
                    if ((int) $one['error'] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $error = GalleryRepo::checkImage($one, GalleryRepo::PHOTO_MIN_WIDTH);
                    if ($error !== null) {
                        $errors[] = $one['name'] . ': ' . $error;
                        continue;
                    }
                    $fileId = FileRepo::store($one, 'gallery_photo', 'edition', $editionId, $editionId, $year);
                    GalleryRepo::add($editionId, $fileId, (string) input('caption', ''));
                    $added++;
                }
            }
            if ($added > 0) {
                Audit::log('gallery.photos_added', ['meta' => ['count' => $added]]);
                Session::flash('success', $added . ' photo(s) added.');
            }
            if ($errors !== []) {
                Session::flash('error', implode(' ', $errors));
            }
            if ($added === 0 && $errors === []) {
                Session::flash('error', 'Please choose at least one photo.');
            }
            break;

        case 'photo_save':
            if ($photo !== null) {
                GalleryRepo::update($id, ['caption' => input('caption', ''), 'sort' => input_int('sort'), 'is_visible' => input_bool('is_visible')]);
                Session::flash('success', 'Photo saved.');
            }
            break;

        case 'photo_delete':
            if ($photo !== null) {
                GalleryRepo::delete($id);
                Audit::log('gallery.photo_deleted', ['meta' => ['id' => $id]]);
                Session::flash('success', 'Photo removed.');
            }
            break;
    }

    Response::redirect($back);
}

View::render('admin/gallery', [
    'title'         => 'Poster & Gallery',
    'subtitle'      => 'Buttons in the About section of the landing page — ' . ($edition['name'] ?? ''),
    'active'        => 'gallery',
    'edition'       => $edition,
    'posterFile'    => GalleryRepo::posterFile($editionId),
    'posterUrl'     => GalleryRepo::posterUrl($editionId),
    'posterEnabled' => SettingRepo::getBool($editionId, 'cfp_poster_enabled', true),
    'photos'        => GalleryRepo::photos($editionId),
    'labels'        => [
        'poster'  => Cenim\Domain\ContentRepo::text($editionId, 'about.poster_label', 'home'),
        'gallery' => Cenim\Domain\ContentRepo::text($editionId, 'about.gallery_label', 'home'),
        'title'   => Cenim\Domain\ContentRepo::text($editionId, 'about.gallery_title', 'home'),
    ],
], 'admin');
