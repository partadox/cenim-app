<?php
/**
 * Unduh PDF receipt milik author yang sedang login.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\ReceiptRepo;
use Cenim\Pdf\ReceiptPdf;

$author = current_author();

if ($author === null) {
    Response::abort(403, 'Not signed in.');
}

$receipt = ReceiptRepo::findForAuthor((int) $author['id']);

if ($receipt === null) {
    Session::flash('info', 'Your receipt will be available once the payment is confirmed.');
    Response::redirect(author_url('receipt.php'));
}

// Bila id dikirim, pastikan tetap receipt milik author ini.
$requested = input_int('id');
if ($requested > 0 && $requested !== (int) $receipt['id']) {
    Response::abort(403, 'You are not allowed to open this receipt.');
}

try {
    $path = ReceiptPdf::file((int) $receipt['id']);
} catch (Throwable $e) {
    Logger::error('Gagal membuat PDF receipt (author)', ['receipt_id' => (int) $receipt['id'], 'error' => $e->getMessage()]);
    Session::flash('error', 'The receipt PDF could not be generated. Please contact the secretariat.');
    Response::redirect(author_url('receipt.php'));
}

Response::file(
    $path,
    'Receipt-' . ReceiptPdf::safeName((string) $receipt['number']) . '.pdf',
    input('download') === null
);
