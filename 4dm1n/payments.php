<?php
/**
 * Daftar pembayaran yang perlu dikonfirmasi.
 * ?type=author (bawaan) | non_presenter | workshop | all — berlaku untuk kartu,
 * total, tabel pembayaran, dan daftar invoice belum dibayar.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\View;
use Cenim\Domain\PaymentRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$type      = (string) input('type', 'author');
$type      = $type === 'all' ? '' : $type;
$type      = array_key_exists($type, PaymentRepo::TYPES) ? $type : 'author';

$counts = PaymentRepo::countsByStatus($editionId, $type);

View::render('admin/payments', [
    'title'    => 'Payments',
    'subtitle' => ($edition['name'] ?? 'No edition') . ' — ' . array_sum($counts) . ' payment(s)'
        . ($type !== '' ? ' · ' . PaymentRepo::TYPES[$type] : ''),
    'active'   => 'payments',
    'edition'  => $edition,
    'counts'   => $counts,
    'unpaid'   => PaymentRepo::unpaidInvoices($editionId, $type),
    'totals'   => PaymentRepo::confirmedTotals($editionId, $type),
    'type'     => $type,
    'statuses' => PaymentRepo::STATUSES,
    'assets'   => ['datatables'],
    'scripts'  => View::partial('admin/payments_scripts', ['type' => $type]),
], 'admin');
