<?php
/**
 * Tambah / ubah satu author. id kosong = tambah baru.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\AuthorRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0) {
    Session::flash('error', 'No active edition. Run the seeder first.');
    Response::redirect(admin_url('index.php'));
}

$id     = input_int('id');
$author = $id > 0 ? AuthorRepo::find($id) : null;

if ($id > 0 && ($author === null || (int) $author['edition_id'] !== $editionId)) {
    Response::abort(404, 'Author not found in the active edition.');
}

$values = $author ?? [
    'paper_id'          => '',
    'paper_title'       => '',
    'track'             => '',
    'first_author_name' => '',
    'email'             => '',
    'affiliation'       => '',
    'country'           => '',
    'phone'             => '',
    'total_pages'       => '',
    'is_presenter'      => 1,
    'paper_status'      => 'accepted',
    'note'              => '',
];

$errors = [];

if (is_post()) {
    Csrf::verify();

    $input = [
        'paper_id'          => (string) input('paper_id', ''),
        'paper_title'       => (string) input('paper_title', ''),
        'track'             => (string) input('track', ''),
        'first_author_name' => (string) input('first_author_name', ''),
        'email'             => (string) input('email', ''),
        'affiliation'       => (string) input('affiliation', ''),
        'country'           => (string) input('country', ''),
        'phone'             => (string) input('phone', ''),
        'total_pages'       => (string) input('total_pages', '0'),
        'is_presenter'      => input_bool('is_presenter') ? 1 : 0,
        'paper_status'      => (string) input('paper_status', 'accepted'),
        'note'              => (string) input('note', ''),
    ];

    $values = array_merge($values, $input);
    $errors = AuthorRepo::validateRow($input, $editionId, $id);

    if ($errors === []) {
        if ($id > 0) {
            AuthorRepo::update($id, $input);
            Audit::log('author.updated', ['target_type' => 'author', 'target_id' => $id, 'meta' => ['paper_id' => $input['paper_id']]]);
            Session::flash('success', 'Author "' . $input['first_author_name'] . '" updated.');
        } else {
            $newId = AuthorRepo::create($editionId, $input, 'manual');
            Audit::log('author.created', ['target_type' => 'author', 'target_id' => $newId, 'meta' => ['paper_id' => $input['paper_id']]]);
            Session::flash('success', 'Author "' . $input['first_author_name'] . '" added.');
        }

        Response::redirect(admin_url('authors.php'));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' field(s) below.');
}

// Peringatan (bukan error) bila email dipakai lebih dari satu paper.
$duplicateEmail = 0;
if (($values['email'] ?? '') !== '') {
    $duplicateEmail = AuthorRepo::countByEmail($editionId, (string) $values['email'], $id);
}

View::render('admin/author_edit', [
    'title'          => $id > 0 ? 'Edit author' : 'Add author',
    'subtitle'       => $id > 0 ? 'Paper ' . ($author['paper_id'] ?? '') : ($edition['name'] ?? ''),
    'active'         => 'authors',
    'edition'        => $edition,
    'author'         => $author,
    'values'         => $values,
    'errors'         => $errors,
    'statuses'       => AuthorRepo::PAPER_STATUSES,
    'duplicateEmail' => $duplicateEmail,
    'paperAuthors'   => $author !== null ? Cenim\Domain\PaperAuthorRepo::forPaper((int) $author['id']) : [],
], 'admin');
