<?php
/**
 * Hapus satu paper. POST + CSRF saja.
 * Bila sudah punya registrasi, pertama dibalas needs_confirm beserta ringkasan
 * datanya; dengan force=1 paper dihapus beserta registrasi, invoice,
 * pembayaran, receipt, sertifikat, dan berkasnya (AuthorRepo::purge).
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

// Sudah ada registrasi: minta konfirmasi kedua, lalu hapus beserta semua datanya.
$dependents = AuthorRepo::dependents($id);
if ($dependents['registration'] !== null && input('force') !== '1') {
    $parts = ['the registration (' . $dependents['registration'] . ')'];
    foreach (['invoices' => 'invoice', 'payments' => 'payment', 'receipts' => 'receipt', 'certificates' => 'certificate'] as $key => $label) {
        if ($dependents[$key] > 0) {
            $parts[] = $dependents[$key] . ' ' . $label . ($dependents[$key] > 1 ? 's' : '');
        }
    }
    $summary = 'This paper already has data: ' . implode(', ', $parts) . '. Everything will be deleted together with the paper.';
    if (is_ajax()) {
        Response::json(['ok' => false, 'needs_confirm' => true, 'summary' => $summary], 409);
    }
    $fail($summary . ' Use the delete button in the author list to confirm.');
}

AuthorRepo::purge($id);

Audit::log('author.deleted', [
    'target_type' => 'author',
    'target_id'   => $id,
    'meta'        => ['paper_id' => $author['paper_id'], 'email' => $author['email'], 'with_data' => $dependents],
]);

$message = 'Author "' . $author['first_author_name'] . '" deleted.';

if (is_ajax()) {
    Response::json(['ok' => true, 'message' => $message]);
}

Session::flash('success', $message);
Response::redirect(admin_url('authors.php'));
