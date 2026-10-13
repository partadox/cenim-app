<?php
/**
 * Langkah 4 — receipt author: rincian + unduh PDF.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Core\View;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\PaymentRepo;
use Cenim\Domain\ReceiptRepo;

$edition = current_edition();
$author  = current_author();

if ($author === null) {
    Response::abort(403, 'Not signed in.');
}

$receipt = ReceiptRepo::findForAuthor((int) $author['id']);

View::render('author/receipt', [
    'title'   => 'Receipt',
    'edition' => $edition,
    'author'  => $author,
    'receipt' => $receipt,
    'invoice' => InvoiceRepo::findForAuthor((int) $author['id']),
    'payment' => PaymentRepo::findForAuthor((int) $author['id']),
], 'author');
