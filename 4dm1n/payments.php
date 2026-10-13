<?php
/**
 * Daftar pembayaran yang perlu dikonfirmasi.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\View;
use Cenim\Domain\PaymentRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$counts = PaymentRepo::countsByStatus($editionId);

View::render('admin/payments', [
    'title'    => 'Payments',
    'subtitle' => ($edition['name'] ?? 'No edition') . ' — ' . array_sum($counts) . ' payment(s)',
    'active'   => 'payments',
    'edition'  => $edition,
    'counts'   => $counts,
    'totals'   => PaymentRepo::confirmedTotals($editionId),
    'statuses' => PaymentRepo::STATUSES,
    'assets'   => ['datatables'],
    'scripts'  => View::partial('admin/payments_scripts'),
], 'admin');
