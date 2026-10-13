<?php
/**
 * Detail satu pembayaran + aksi confirm / reject.
 * Konfirmasi menjadikan invoice lunas dan menerbitkan receipt.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Csrf;
use Cenim\Core\Mailer;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FileRepo;
use Cenim\Domain\PaymentRepo;
use Cenim\Domain\ReceiptRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$id      = input_int('id');
$payment = $id > 0 ? PaymentRepo::findWithContext($id) : null;

if ($payment === null || (int) $payment['edition_id'] !== $editionId) {
    Response::abort(404, 'Payment not found in the active edition.');
}

if (is_post()) {
    Csrf::verify();

    $action   = (string) input('action', '');
    $mailArgs = [
        'paperId'       => (string) $payment['paper_id'],
        'invoiceNumber' => (string) $payment['invoice_number'],
        'amount'        => fmt_money((float) $payment['amount_paid'], (string) $payment['currency']),
        'loginUrl'      => full_url(author_url('login.php')),
    ];

    if ($action === 'confirm') {
        if ((string) $payment['status'] === PaymentRepo::STATUS_CONFIRMED) {
            Session::flash('info', 'This payment was already confirmed.');
            Response::redirect(admin_url('payment-view.php?id=' . $id));
        }

        $result = PaymentRepo::confirm($id);

        if ($result['error'] !== null) {
            Session::flash('error', $result['error']);
            Response::redirect(admin_url('payment-view.php?id=' . $id));
        }

        $receipt = $result['receipt'] ?? [];

        Mailer::send(
            (string) $payment['email'],
            (string) $payment['first_author_name'],
            'Payment confirmed — receipt ' . ($receipt['number'] ?? '') . ' (' . ($edition['name'] ?? 'CENIM') . ')',
            'payment_confirmed',
            $mailArgs + ['receiptNumber' => (string) ($receipt['number'] ?? '')]
        );

        Session::flash('success', 'Payment confirmed. Receipt ' . ($receipt['number'] ?? '')
            . ' issued and emailed to the author.');
        Response::redirect(admin_url('payment-view.php?id=' . $id));
    }

    if ($action === 'reject') {
        if ((string) $payment['status'] === PaymentRepo::STATUS_CONFIRMED) {
            Session::flash('error', 'A confirmed payment cannot be returned. Handle it manually with the author.');
            Response::redirect(admin_url('payment-view.php?id=' . $id));
        }

        $reason = trim((string) input('reason', ''));

        if (mb_strlen($reason) < 10) {
            Session::flash('error', 'Please write at least 10 characters explaining what the author must fix.');
            Response::redirect(admin_url('payment-view.php?id=' . $id));
        }

        PaymentRepo::reject($id, mb_substr($reason, 0, 1000));

        Mailer::send(
            (string) $payment['email'],
            (string) $payment['first_author_name'],
            'Action needed on your payment — ' . ($edition['name'] ?? 'CENIM'),
            'payment_rejected',
            $mailArgs + ['reason' => $reason]
        );

        Session::flash('success', 'Payment returned to the author with your note.');
        Response::redirect(admin_url('payment-view.php?id=' . $id));
    }

    Session::flash('error', 'Unknown action.');
    Response::redirect(admin_url('payment-view.php?id=' . $id));
}

View::render('admin/payment_view', [
    'title'      => 'Review payment',
    'subtitle'   => 'Invoice ' . $payment['invoice_number'] . ' — ' . $payment['first_author_name'],
    'active'     => 'payments',
    'edition'    => $edition,
    'payment'    => $payment,
    'proof'      => FileRepo::latest('payment', $id, 'payment_proof'),
    'receipt'    => ReceiptRepo::findForPayment($id),
    'difference' => PaymentRepo::difference($payment),
    'extraAnswers' => Cenim\Domain\FormRepo::readable(
        Cenim\Domain\FormRepo::fields($editionId, Cenim\Domain\FormRepo::FORM_PAYMENT),
        Cenim\Domain\FormRepo::decode($payment['extra_json'] ?? null)
    ),
], 'admin');
