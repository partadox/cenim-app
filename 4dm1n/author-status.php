<?php
/**
 * Ubah cepat status paper: withdrawn (ditarik) atau kembali accepted. POST + CSRF.
 * Paper withdrawn tidak ikut confirmation, registrasi, pembayaran, dan video
 * di Portal; registrasi/invoice yang sudah ada tidak diubah (void manual bila perlu).
 * AJAX → JSON; selain itu redirect ke `back`.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\ParticipantRepo;

if (!is_post()) {
    Response::redirect(admin_url('authors.php'));
}

Csrf::verify();

$editionId = (int) (current_edition()['id'] ?? 0);
$author    = AuthorRepo::find(input_int('id'));
$status    = (string) input('status', '');
$back      = (string) input('back', '');
$back      = str_starts_with($back, admin_url()) ? $back : admin_url('authors.php');

$reply = static function (bool $ok, string $message) use ($back): never {
    if (is_ajax()) {
        Response::json($ok ? ['ok' => true, 'message' => $message] : ['ok' => false, 'error' => $message], $ok ? 200 : 422);
    }
    Session::flash($ok ? 'success' : 'error', $message);
    Response::redirect($back);
};

if ($author === null || (int) $author['edition_id'] !== $editionId || ParticipantRepo::isParticipant($author)) {
    $reply(false, 'Paper not found in the active edition.');
}
if (!in_array($status, [AuthorRepo::STATUS_WITHDRAWN, 'accepted'], true)) {
    $reply(false, 'Unknown status.');
}

Db::update('authors', ['paper_status' => $status, 'updated_at' => now()], 'id = :target_id', ['target_id' => (int) $author['id']]);
Audit::log('author.status_changed', [
    'target_type' => 'author',
    'target_id'   => (int) $author['id'],
    'meta'        => ['paper_id' => $author['paper_id'], 'from' => $author['paper_status'], 'to' => $status],
]);

$message = 'Paper ' . $author['paper_id'] . ($status === AuthorRepo::STATUS_WITHDRAWN ? ' marked as withdrawn.' : ' restored to accepted.');
if ($status === AuthorRepo::STATUS_WITHDRAWN) {
    $invoice = InvoiceRepo::findForAuthor((int) $author['id']);
    if ($invoice !== null && (string) $invoice['status'] === InvoiceRepo::STATUS_UNPAID) {
        $message .= ' Its unpaid invoice ' . $invoice['number'] . ' is still open: void it in Invoices if it is no longer needed.';
    }
}

$reply(true, $message);
