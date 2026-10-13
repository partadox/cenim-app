<?php
/**
 * Aksi daftar author satu paper (dari halaman author-edit.php):
 *   action=add | update | delete | presenter
 * Selalu kembali ke author-edit.php?id=<paper>#authors.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\PaperAuthorRepo;

if (!is_post()) {
    Response::redirect(admin_url('authors.php'));
}

Csrf::verify();

$editionId = (int) (current_edition()['id'] ?? 0);
$paper     = AuthorRepo::find(input_int('paper'));

if ($paper === null || (int) $paper['edition_id'] !== $editionId) {
    Response::abort(404, 'Paper not found.');
}

$paperId = (int) $paper['id'];
$back    = admin_url('author-edit.php?id=' . $paperId) . '#authors';
$person  = input_int('id') > 0 ? PaperAuthorRepo::find(input_int('id')) : null;

if ($person !== null && (int) $person['author_id'] !== $paperId) {
    Response::abort(404, 'Author not found on this paper.');
}

$data = [
    'name'         => (string) input('name', ''),
    'email'        => (string) input('email', ''),
    'affiliation'  => (string) input('affiliation', ''),
    'country'      => (string) input('country', ''),
    'position'     => (string) input('position', ''),
    'is_presenter' => input_bool('is_presenter') ? 1 : 0,
];

switch ((string) input('action', '')) {
    case 'add':
    case 'update':
        $errors = PaperAuthorRepo::validate($data);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
            break;
        }

        if ($person !== null) {
            PaperAuthorRepo::update((int) $person['id'], $data);
            Audit::log('paper_author.updated', ['target_type' => 'paper_author', 'target_id' => (int) $person['id'], 'meta' => ['paper_id' => $paper['paper_id']]]);
            Session::flash('success', 'Author "' . trim($data['name']) . '" updated.');
        } else {
            $newId = PaperAuthorRepo::create($paperId, $data);
            Audit::log('paper_author.created', ['target_type' => 'paper_author', 'target_id' => $newId, 'meta' => ['paper_id' => $paper['paper_id']]]);
            Session::flash('success', 'Author "' . trim($data['name']) . '" added.');
        }
        break;

    case 'delete':
        if ($person === null) {
            break;
        }
        $error = PaperAuthorRepo::delete((int) $person['id']);
        if ($error !== null) {
            Session::flash('error', $error);
            break;
        }
        Audit::log('paper_author.deleted', ['target_type' => 'paper_author', 'target_id' => (int) $person['id'], 'meta' => ['name' => $person['name']]]);
        Session::flash('success', 'Author "' . $person['name'] . '" removed.');
        break;

    default:
        Session::flash('error', 'Unknown action.');
}

Response::redirect($back);
