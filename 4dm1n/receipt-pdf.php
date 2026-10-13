<?php
/**
 * Unduh / tampilkan PDF receipt (sisi admin).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\ReceiptRepo;
use Cenim\Pdf\ReceiptPdf;

$editionId = (int) (current_edition()['id'] ?? 0);
$id        = input_int('id');
$receipt   = $id > 0 ? ReceiptRepo::find($id) : null;

if ($receipt === null || (int) $receipt['edition_id'] !== $editionId) {
    Response::abort(404, 'Receipt not found in the active edition.');
}

try {
    $path = ReceiptPdf::file($id, input('refresh') !== null);
} catch (Throwable $e) {
    Logger::error('Gagal membuat PDF receipt', ['receipt_id' => $id, 'error' => $e->getMessage()]);
    Session::flash('error', 'The receipt PDF could not be generated: ' . $e->getMessage());
    Response::redirect(admin_url('receipts.php'));
}

Response::file(
    $path,
    'Receipt-' . ReceiptPdf::safeName((string) $receipt['number']) . '.pdf',
    input('download') === null
);
