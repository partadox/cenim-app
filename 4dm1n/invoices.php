<?php
/**
 * Daftar invoice + panel registrasi terverifikasi yang belum diterbitkan invoicenya.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Numbering;
use Cenim\Core\View;
use Cenim\Domain\InvoiceRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$counts = InvoiceRepo::countsByStatus($editionId);

View::render('admin/invoices', [
    'title'      => 'Invoices',
    'subtitle'   => ($edition['name'] ?? 'No edition') . ' — ' . array_sum($counts) . ' invoice(s), last number '
        . Numbering::format((string) cfg('numbering.invoice'), (int) ($edition['year'] ?? date('Y')), Numbering::peek($editionId, 'invoice')),
    'active'     => 'invoices',
    'edition'    => $edition,
    'counts'     => $counts,
    'totals'     => InvoiceRepo::totals($editionId),
    'pending'    => InvoiceRepo::pendingIssue($editionId),
    'statuses'   => InvoiceRepo::STATUSES,
    'assets'     => ['datatables'],
    'scripts'    => View::partial('admin/invoices_scripts'),
], 'admin');
