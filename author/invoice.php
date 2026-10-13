<?php
/**
 * Langkah 2 — invoice author: rincian + unduh PDF.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Core\View;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$author    = current_author();
$editionId = (int) ($edition['id'] ?? 0);

if ($author === null) {
    Response::abort(403, 'Not signed in.');
}

$registration = RegistrationRepo::findByAuthor((int) $author['id']);
$invoice      = InvoiceRepo::findForAuthor((int) $author['id']);

View::render('author/invoice', [
    'title'        => 'Invoice',
    'edition'      => $edition,
    'author'       => $author,
    'registration' => $registration,
    'invoice'      => $invoice,
    'items'        => $invoice !== null ? InvoiceRepo::items((int) $invoice['id']) : [],
    'banks'        => SettingRepo::getArray($editionId, 'bank_accounts', []),
    'contact'      => SettingRepo::getString($editionId, 'contact_email'),
], 'author');
