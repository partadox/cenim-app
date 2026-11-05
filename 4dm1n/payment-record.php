<?php
/**
 * Catat pembayaran atas nama author (author sudah transfer tetapi tidak
 * mengunggah bukti): data transfer + keterangan wajib + bukti opsional,
 * lalu langsung dikonfirmasi → invoice lunas & receipt terbit.
 * ?invoice=<invoices.id>
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Mailer;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FileRepo;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\PaymentRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$invoice   = InvoiceRepo::findWithContext(input_int('invoice'));

if ($invoice === null || (int) $invoice['edition_id'] !== $editionId) {
    Response::abort(404, 'Invoice not found in the active edition.');
}
if ((string) $invoice['status'] !== InvoiceRepo::STATUS_UNPAID) {
    Session::flash('error', 'Invoice ' . $invoice['number'] . ' is ' . $invoice['status'] . ', so no payment can be recorded.');
    Response::redirect(admin_url('payments.php'));
}

$existing = PaymentRepo::findForInvoice((int) $invoice['id']);
if ($existing !== null && (string) $existing['status'] === PaymentRepo::STATUS_SUBMITTED) {
    Session::flash('info', 'The author already uploaded a payment for this invoice. Review it here.');
    Response::redirect(admin_url('payment-view.php?id=' . (int) $existing['id']));
}

$currency = (string) $invoice['currency'];
$values   = [
    'amount'      => $currency === 'IDR' ? number_format((float) $invoice['total'], 0, ',', '.') : number_format((float) $invoice['total'], 2, '.', ','),
    'paid_at'     => gmdate('Y-m-d'),
    'sender_name' => (string) $invoice['first_author_name'],
    'bank_name'   => '',
    'reference'   => '',
    'note'        => '',
    'notify'      => true,
];
$errors = [];

if (is_post()) {
    Csrf::verify();

    $values = [
        'amount'      => trim((string) input('amount', '')),
        'paid_at'     => trim((string) input('paid_at', '')),
        'sender_name' => trim((string) input('sender_name', '')),
        'bank_name'   => trim((string) input('bank_name', '')),
        'reference'   => trim((string) input('reference', '')),
        'note'        => trim((string) input('note', '')),
        'notify'      => input_bool('notify'),
    ];

    $clean = $currency === 'IDR' ? preg_replace('/[^0-9]/', '', $values['amount']) : str_replace([',', ' '], '', $values['amount']);
    if ($clean === '' || !is_numeric($clean) || (float) $clean <= 0) {
        $errors['amount'] = 'Please enter the amount received.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['paid_at'])) {
        $errors['paid_at'] = 'Please choose the transfer date.';
    }
    if (mb_strlen($values['note']) < 5) {
        $errors['note'] = 'Please explain how the payment was checked (at least 5 characters).';
    }

    $upload = $_FILES['payment_proof'] ?? null;
    $hasUpload = is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($errors === []) {
        $amount    = round((float) $clean, $currency === 'IDR' ? 0 : 2);
        $paymentId = PaymentRepo::submit($editionId, (int) $invoice['id'], [
            'method'      => 'bank_transfer',
            'amount_paid' => $amount,
            'currency'    => $currency,
            'paid_at'     => $values['paid_at'],
            'bank_name'   => $values['bank_name'] !== '' ? mb_substr($values['bank_name'], 0, 120) : null,
            'sender_name' => $values['sender_name'] !== '' ? mb_substr($values['sender_name'], 0, 190) : null,
            'reference'   => $values['reference'] !== '' ? mb_substr($values['reference'], 0, 120) : null,
            'note'        => 'Recorded by the secretariat: ' . mb_substr($values['note'], 0, 900),
        ]);
        Db::update('payments', ['filled_by_id' => null, 'filled_by_name' => 'Secretariat (admin)'], 'id = :target_id', ['target_id' => $paymentId]);

        if ($hasUpload) {
            try {
                $fileId = FileRepo::replace($upload, 'payment_proof', 'payment', $paymentId, $editionId, (int) ($edition['year'] ?? date('Y')));
                PaymentRepo::attachProof($paymentId, $fileId);
            } catch (InvalidArgumentException $e) {
                Session::flash('warning', 'The proof could not be stored (' . $e->getMessage() . '); the payment is recorded without it.');
            }
        }

        $result  = PaymentRepo::confirm($paymentId);
        $receipt = $result['receipt'] ?? [];

        Audit::log('payment.recorded_by_admin', [
            'target_type' => 'payment',
            'target_id'   => $paymentId,
            'meta'        => ['invoice' => $invoice['number'], 'amount' => $amount, 'currency' => $currency, 'note' => $values['note'], 'notify' => $values['notify']],
        ]);

        if ($values['notify']) {
            Mailer::send(
                (string) $invoice['email'],
                (string) $invoice['first_author_name'],
                'Payment confirmed — receipt ' . ($receipt['number'] ?? '') . ' (' . ($edition['name'] ?? 'CENIM') . ')',
                'payment_confirmed',
                [
                    'paperId'       => (string) $invoice['paper_id'],
                    'invoiceNumber' => (string) $invoice['number'],
                    'amount'        => fmt_money($amount, $currency),
                    'loginUrl'      => full_url(author_url('login.php')),
                    'receiptNumber' => (string) ($receipt['number'] ?? ''),
                ]
            );
        }

        Session::flash('success', 'Payment recorded. Invoice ' . $invoice['number'] . ' is paid and receipt ' . ($receipt['number'] ?? '') . ' was issued'
            . ($values['notify'] ? ' and emailed to the author.' : '.'));
        Response::redirect(admin_url('payment-view.php?id=' . $paymentId));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' field(s).');
}

View::render('admin/payment_record', [
    'title'    => 'Record payment',
    'subtitle' => 'Invoice ' . $invoice['number'] . ' — ' . $invoice['first_author_name'],
    'active'   => 'payments',
    'edition'  => $edition,
    'invoice'  => $invoice,
    'existing' => $existing,
    'values'   => $values,
    'errors'   => $errors,
], 'admin');
