<?php
/**
 * Tambah / ubah satu author. id kosong = tambah baru.
 * Paper yang sudah ada juga punya bagian "Additional documents": dokumen yang
 * diminta author (nama + keterangan, bisa beberapa sekaligus) diunggah ke
 * object storage dan bisa diunduh author di Portal (AuthorDocumentRepo).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\AuthorDocumentRepo;
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

// ── Dokumen tambahan (form terpisah, action=doc_*) ─────────────────────────
$docAction = is_post() ? (string) input('action', '') : '';
if ($author !== null && str_starts_with($docAction, 'doc_')) {
    Csrf::verify();
    $back = admin_url('author-edit.php?id=' . $id . '#documents');

    if ($docAction === 'doc_upload') {
        $titles = (array) ($_POST['doc_title'] ?? []);
        $descs  = (array) ($_POST['doc_description'] ?? []);
        $files  = $_FILES['doc_file'] ?? null;
        $saved  = 0;
        $failed = [];

        foreach ($titles as $i => $title) {
            $title  = trim((string) $title);
            $upload = is_array($files) && isset($files['name'][$i]) ? [
                'name'     => (string) $files['name'][$i],
                'type'     => (string) $files['type'][$i],
                'tmp_name' => (string) $files['tmp_name'][$i],
                'error'    => (int) $files['error'][$i],
                'size'     => (int) $files['size'][$i],
            ] : null;
            $hasFile = $upload !== null && $upload['error'] !== UPLOAD_ERR_NO_FILE;

            if ($title === '' && !$hasFile) {
                continue; // baris kosong
            }
            if ($title === '' || !$hasFile) {
                $failed[] = ($title !== '' ? $title : 'Row ' . ((int) $i + 1)) . ': ' . ($title === '' ? 'enter the document name.' : 'choose a file.');
                continue;
            }

            try {
                $fileId = AuthorDocumentRepo::add($author, $upload, $title, (string) ($descs[$i] ?? ''));
                Audit::log('author.document_uploaded', ['target_type' => 'author', 'target_id' => $id,
                    'meta' => ['paper_id' => $author['paper_id'], 'file_id' => $fileId, 'title' => $title]]);
                $saved++;
            } catch (InvalidArgumentException $e) {
                $failed[] = $title . ': ' . $e->getMessage();
            } catch (Throwable $e) {
                Cenim\Core\Logger::error('Upload dokumen author gagal', ['author_id' => $id, 'error' => $e->getMessage()]);
                $failed[] = $title . ': the file could not be stored. Please try again.';
            }
        }

        if ($saved > 0) {
            Session::flash('success', $saved . ' document(s) uploaded.');
        }
        foreach ($failed as $message) {
            Session::flash('error', $message);
        }
        if ($saved === 0 && $failed === []) {
            Session::flash('error', 'Enter a document name and choose a file.');
        }
    } elseif (($doc = AuthorDocumentRepo::find($id, input_int('file_id'))) !== null) {
        if ($docAction === 'doc_update') {
            $title = trim((string) input('title', ''));
            if ($title === '') {
                Session::flash('error', 'The document name cannot be empty.');
            } else {
                AuthorDocumentRepo::updateInfo((int) $doc['id'], $title, (string) input('description', ''));
                Session::flash('success', 'Document "' . $title . '" updated.');
            }
        } elseif ($docAction === 'doc_delete') {
            Cenim\Domain\FileRepo::softDelete((int) $doc['id']);
            Audit::log('author.document_deleted', ['target_type' => 'author', 'target_id' => $id,
                'meta' => ['paper_id' => $author['paper_id'], 'file_id' => (int) $doc['id'], 'title' => $doc['title']]]);
            Session::flash('success', 'Document "' . ($doc['title'] ?? $doc['original_name']) . '" deleted.');
        }
    }

    Response::redirect($back);
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
    'final_pages'       => null,
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
        // Bukan isian admin: presenter dipilih author di Registration form.
        'is_presenter'      => (int) ($values['is_presenter'] ?? 1),
        'paper_status'      => (string) input('paper_status', 'accepted'),
        'note'              => (string) input('note', ''),
    ];

    $values = array_merge($values, $input);
    $errors = AuthorRepo::validateRow($input, $editionId, $id);

    // Halaman final manuscript: kosong = belum ada (biaya memakai halaman review).
    $finalRaw   = trim((string) input('final_pages', ''));
    $finalPages = $finalRaw === '' ? null : (int) $finalRaw;
    $values['final_pages'] = $finalPages;
    if ($finalPages !== null && ($finalPages < 1 || $finalPages > 200 || !ctype_digit($finalRaw))) {
        $errors['final_pages'] = 'Final pages must be a number between 1 and 200, or empty.';
    }

    if ($errors === []) {
        if ($id > 0) {
            AuthorRepo::update($id, $input);
            AuthorRepo::setFinalPages($id, $finalPages);
            Audit::log('author.updated', ['target_type' => 'author', 'target_id' => $id, 'meta' => ['paper_id' => $input['paper_id']]]);
            Session::flash('success', 'Author "' . $input['first_author_name'] . '" updated.');
        } else {
            $newId = AuthorRepo::create($editionId, $input, 'manual');
            AuthorRepo::setFinalPages($newId, $finalPages);
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
    'documents'      => $author !== null ? AuthorDocumentRepo::forAuthor((int) $author['id']) : [],
], 'admin');
