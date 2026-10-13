<?php
/**
 * Hapus satu author. POST + CSRF saja.
 * Ditolak bila author sudah punya registrasi, agar data invoice/pembayaran
 * tidak ikut terhapus tanpa sengaja (FK registrations ON DELETE CASCADE).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\AuthorRepo;

if (!is_post()) {
    Response::redirect(admin_url('authors.php'));
}

Csrf::verify();

$editionId = (int) (current_edition()['id'] ?? 0);
$id        = input_int('id');
$author    = $id > 0 ? AuthorRepo::find($id) : null;

$fail = static function (string $message): never {
    if (is_ajax()) {
        Response::json(['ok' => false, 'error' => $message], 422);
    }
    Session::flash('error', $message);
    Response::redirect(admin_url('authors.php'));
};

if ($author === null || (int) $author['edition_id'] !== $editionId) {
    $fail('Author not found in the active edition.');
}

if (AuthorRepo::hasRegistration($id)) {
    $fail('This author already has a registration and cannot be deleted. Reject the registration instead.');
}

AuthorRepo::delete($id);

Audit::log('author.deleted', [
    'target_type' => 'author',
    'target_id'   => $id,
    'meta'        => ['paper_id' => $author['paper_id'], 'email' => $author['email']],
]);

$message = 'Author "' . $author['first_author_name'] . '" deleted.';

if (is_ajax()) {
    Response::json(['ok' => true, 'message' => $message]);
}

Session::flash('success', $message);
Response::redirect(admin_url('authors.php'));
