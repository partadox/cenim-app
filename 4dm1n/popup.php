<?php
/**
 * Pop-up pengumuman halaman publik (home, workshop, registration): satu per
 * edisi, bisa diaktifkan/nonaktifkan, isinya ditulis dengan editor WYSIWYG.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\PopupRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0) {
    Session::flash('error', 'No active edition.');
    Response::redirect(admin_url('index.php'));
}

if (is_post()) {
    Csrf::verify();

    $values = [
        'enabled'   => input_bool('enabled'),
        'title'     => (string) ($_POST['title'] ?? ''),
        'body'      => is_string($_POST['body'] ?? null) ? $_POST['body'] : '',
        'button'    => (string) ($_POST['button'] ?? ''),
        'frequency' => (string) ($_POST['frequency'] ?? 'session'),
    ];

    if ($values['enabled'] && trim(strip_tags($values['body'], '<img><iframe><video>')) === '') {
        Session::flash('error', 'Write the announcement before turning the pop-up on.');
        $values['enabled'] = false;
    } else {
        Session::flash('success', $values['enabled'] ? 'Pop-up saved and shown on the website.' : 'Pop-up saved (turned off).');
    }

    PopupRepo::save($editionId, $values);
    Audit::log('popup.saved', ['meta' => ['enabled' => $values['enabled'], 'frequency' => $values['frequency']]]);
    Response::redirect(admin_url('popup.php'));
}

View::render('admin/popup', [
    'title'    => 'Announcement pop-up',
    'subtitle' => $edition['name'] ?? '',
    'active'   => 'popup',
    'edition'  => $edition,
    'popup'    => PopupRepo::get($editionId),
], 'admin');
