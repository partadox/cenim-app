<?php
/**
 * PDF invoice milik author yang sedang login: tampil di tab browser (PDF viewer),
 * ?download=1 untuk mengunduh.
 * Author hanya boleh mengunduh invoice-nya sendiri.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\InvoiceRepo;
use Cenim\Pdf\InvoicePdf;

$author = current_author();

if ($author === null) {
    Response::abort(403, 'Not signed in.');
}

$invoice = InvoiceRepo::findForAuthor((int) $author['id']);

if ($invoice === null) {
    Session::flash('error', 'Your invoice has not been issued yet.');
    Response::redirect(author_url('invoice.php'));
}

// Bila id dikirim, pastikan tetap invoice milik author ini.
$requested = input_int('id');
if ($requested > 0 && $requested !== (int) $invoice['id']) {
    Response::abort(403, 'You are not allowed to open this invoice.');
}

try {
    $path = InvoicePdf::file((int) $invoice['id']);
} catch (Throwable $e) {
    Logger::error('Gagal membuat PDF invoice (author)', ['invoice_id' => (int) $invoice['id'], 'error' => $e->getMessage()]);
    Session::flash('error', 'The invoice PDF could not be generated. Please contact the secretariat.');
    Response::redirect(author_url('invoice.php'));
}

Response::file(
    $path,
    'Invoice-' . InvoicePdf::safeName((string) $invoice['number']) . '.pdf',
    input('download') === null
);
