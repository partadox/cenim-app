<?php
/**
 * Unduh / tampilkan PDF invoice (sisi admin).
 * ?id=<invoice id>&refresh=1 untuk membuat ulang dari data terkini.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\InvoiceRepo;
use Cenim\Pdf\InvoicePdf;

$editionId = (int) (current_edition()['id'] ?? 0);
$id        = input_int('id');
$invoice   = $id > 0 ? InvoiceRepo::find($id) : null;

if ($invoice === null || (int) $invoice['edition_id'] !== $editionId) {
    Response::abort(404, 'Invoice not found in the active edition.');
}

try {
    $path = InvoicePdf::file($id, input('refresh') !== null);
} catch (Throwable $e) {
    Logger::error('Gagal membuat PDF invoice', ['invoice_id' => $id, 'error' => $e->getMessage()]);
    Session::flash('error', 'The invoice PDF could not be generated: ' . $e->getMessage());
    Response::redirect(admin_url('invoices.php'));
}

Response::file(
    $path,
    'Invoice-' . Cenim\Pdf\InvoicePdf::safeName((string) $invoice['number']) . '.pdf',
    input('download') === null
);
