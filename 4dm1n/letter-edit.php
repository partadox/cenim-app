<?php
/**
 * Buat / ubah satu surat (Letter Generate). ?id kosong = surat baru.
 * Isi ditulis di editor WYSIWYG dan boleh memuat variabel paper
 * ({paper_id}, {paper_title}, {first_author}, ...; LetterRepo::VARIABLES).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\BrandingRepo;
use Cenim\Domain\LetterRepo;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0) {
    Session::flash('error', 'No active edition.');
    Response::redirect(admin_url('index.php'));
}

$id     = input_int('id');
$letter = $id > 0 ? LetterRepo::find($id) : null;
if ($id > 0 && ($letter === null || (int) $letter['edition_id'] !== $editionId)) {
    Response::abort(404, 'Letter not found.');
}

$values = $letter !== null ? [
    'title'       => (string) $letter['title'],
    'number'      => (string) ($letter['number'] ?? ''),
    'letter_date' => (string) ($letter['letter_date'] ?? ''),
    'author_id'   => (int) ($letter['author_id'] ?? 0),
    'body'        => (string) $letter['body'],
    'signer'      => (string) $letter['signer'],
    'show_title'  => (int) $letter['show_title'] === 1,
] : [
    'title'       => '',
    'number'      => '',
    'letter_date' => gmdate('Y-m-d'),
    'author_id'   => input_int('paper'),
    'body'        => '<p>Dear {first_author},</p>'
        . '<p>We are pleased to inform you that your paper with ID <strong>{paper_id}</strong> entitled '
        . '<strong>"{paper_title}"</strong> by {authors} ...</p>'
        . '<p>Thank you for your participation in {conference}.</p>',
    'signer'      => 'chair',
    'show_title'  => true,
];
$errors = [];

if (is_post()) {
    Csrf::verify();

    $values = [
        'title'       => trim((string) input('title', '')),
        'number'      => trim((string) input('number', '')),
        'letter_date' => trim((string) input('letter_date', '')),
        'author_id'   => input_int('author_id'),
        'body'        => is_string($_POST['body'] ?? null) ? $_POST['body'] : '',
        'signer'      => (string) input('signer', ''),
        'show_title'  => input_bool('show_title'),
    ];
    $errors = LetterRepo::validate($values, $editionId);

    if ($errors === []) {
        $savedId = LetterRepo::save($editionId, $letter !== null ? (int) $letter['id'] : null, $values);
        Audit::log($letter !== null ? 'letter.updated' : 'letter.created', [
            'target_type' => 'letter',
            'target_id'   => $savedId,
            'meta'        => ['title' => $values['title'], 'number' => $values['number']],
        ]);
        Session::flash('success', 'Letter "' . $values['title'] . '" saved. Open the PDF to check it.');
        Response::redirect(admin_url('letter-edit.php?id=' . $savedId));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' field(s).');
}

$chair     = SettingRepo::chair($editionId);
$treasurer = SettingRepo::treasurer($editionId);

View::render('admin/letter_edit', [
    'title'    => $letter !== null ? 'Edit letter' : 'New letter',
    'subtitle' => $letter !== null ? (string) $letter['title'] : ($edition['name'] ?? ''),
    'active'   => 'letters',
    'edition'  => $edition,
    'letter'   => $letter,
    'values'   => $values,
    'errors'   => $errors,
    'papers'   => Db::all(
        "SELECT id, paper_id, paper_title, first_author_name, paper_status FROM authors WHERE edition_id = ? ORDER BY paper_id",
        [$editionId]
    ),
    'signers'  => [
        'chair'     => $chair + ['has_image' => BrandingRepo::signatureFile($editionId, 'chair') !== null],
        'treasurer' => $treasurer + ['has_image' => BrandingRepo::signatureFile($editionId, 'treasurer') !== null],
    ],
], 'admin');
