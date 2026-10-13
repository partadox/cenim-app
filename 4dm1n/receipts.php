<?php
/**
 * Daftar receipt yang sudah diterbitkan.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Numbering;
use Cenim\Core\View;
use Cenim\Domain\ReceiptRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

View::render('admin/receipts', [
    'title'    => 'Receipts',
    'subtitle' => ($edition['name'] ?? 'No edition') . ' — ' . ReceiptRepo::countByEdition($editionId)
        . ' receipt(s), last number '
        . Numbering::format((string) cfg('numbering.receipt'), (int) ($edition['year'] ?? date('Y')), Numbering::peek($editionId, 'receipt')),
    'active'   => 'receipts',
    'edition'  => $edition,
    'totals'   => ReceiptRepo::totals($editionId),
    'assets'   => ['datatables'],
    'scripts'  => View::partial('admin/receipts_scripts'),
], 'admin');
