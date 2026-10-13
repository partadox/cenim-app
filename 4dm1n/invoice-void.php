<?php
/**
 * Batalkan (void) satu invoice, misalnya karena kategori biaya salah.
 * Setelah di-void, registrasi bisa diterbitkan invoice baru.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\InvoiceRepo;

if (!is_post()) {
    Response::redirect(admin_url('invoices.php'));
}

Csrf::verify();

$editionId = (int) (current_edition()['id'] ?? 0);
$id        = input_int('id');
$reason    = trim((string) input('reason', ''));
$invoice   = $id > 0 ? InvoiceRepo::find($id) : null;

if ($invoice === null || (int) $invoice['edition_id'] !== $editionId) {
    Session::flash('error', 'Invoice not found in the active edition.');
    Response::redirect(admin_url('invoices.php'));
}

if ((string) $invoice['status'] === InvoiceRepo::STATUS_PAID) {
    Session::flash('error', 'A paid invoice cannot be voided. Handle the refund manually first.');
    Response::redirect(admin_url('invoices.php'));
}

if ((string) $invoice['status'] === InvoiceRepo::STATUS_VOID) {
    Session::flash('info', 'This invoice is already void.');
    Response::redirect(admin_url('invoices.php'));
}

if (mb_strlen($reason) < 5) {
    Session::flash('error', 'Please state a short reason for voiding the invoice.');
    Response::redirect(admin_url('invoices.php'));
}

InvoiceRepo::void($id, mb_substr($reason, 0, 300));

Session::flash('success', 'Invoice ' . $invoice['number'] . ' voided. You can now issue a corrected one.');
Response::redirect(admin_url('invoices.php'));
