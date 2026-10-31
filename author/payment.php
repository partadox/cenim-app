<?php
/**
 * Form 2 — pengajuan pembayaran: nominal, tanggal transfer, pengirim, bukti transfer.
 *
 * Form terbuka bila invoice sudah terbit dan belum lunas, atau bila pembayaran
 * sebelumnya ditolak. Setelah dikirim, form menjadi read-only sampai admin
 * memutuskan.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Mailer;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FileRepo;
use Cenim\Domain\FormRepo;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\PaymentRepo;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$author    = current_author();
$editionId = (int) ($edition['id'] ?? 0);

if ($author === null || $editionId === 0) {
    Response::abort(503, 'Payment is not available right now.');
}

if (Cenim\Domain\AuthorRepo::isWithdrawn($author)) {
    Session::flash('info', 'This paper has been withdrawn, so no payment is needed.');
    Response::redirect(author_url('index.php'));
}

$invoice = InvoiceRepo::findForAuthor((int) $author['id']);

if ($invoice === null) {
    Session::flash('info', 'Your invoice has not been issued yet, so there is nothing to pay.');
    Response::redirect(author_url('invoice.php'));
}

$payment  = PaymentRepo::findForInvoice((int) $invoice['id']);
$isOpen   = SettingRepo::getBool($editionId, 'payment_open', true) && !edition_is_archive();
$isPaid   = (string) $invoice['status'] === InvoiceRepo::STATUS_PAID;
$editable = !$isPaid && PaymentRepo::isEditable($payment);

$values = [
    'method'      => (string) ($payment['method'] ?? 'bank_transfer'),
    'amount_paid' => $payment !== null ? (float) $payment['amount_paid'] : (float) $invoice['total'],
    'paid_at'     => (string) ($payment['paid_at'] ?? ''),
    'bank_name'   => (string) ($payment['bank_name'] ?? ''),
    'sender_name' => (string) ($payment['sender_name'] ?? $author['first_author_name']),
    'reference'   => (string) ($payment['reference'] ?? ''),
    'note'        => (string) ($payment['note'] ?? ''),
    'extra'       => FormRepo::decode($payment['extra_json'] ?? null),
];

$errors      = [];
$extraFields = FormRepo::fields($editionId, FormRepo::FORM_PAYMENT);

if (is_post()) {
    Csrf::verify();

    if (!$editable) {
        Session::flash('error', $isPaid
            ? 'Your payment has already been confirmed.'
            : 'Your payment is being reviewed and can no longer be edited.');
        Response::redirect(author_url('payment.php'));
    }

    if (!$isOpen) {
        Session::flash('error', 'Payment submission is currently closed.');
        Response::redirect(author_url('payment.php'));
    }

    $values['method']      = in_array(input('method', ''), PaymentRepo::METHODS, true) ? (string) input('method') : 'bank_transfer';
    $values['amount_paid'] = (float) str_replace([',', ' '], '', (string) input('amount_paid', '0'));
    $values['paid_at']     = (string) input('paid_at', '');
    $values['bank_name']   = (string) input('bank_name', '');
    $values['sender_name'] = (string) input('sender_name', '');
    $values['reference']   = (string) input('reference', '');
    $values['note']        = (string) input('note', '');

    if ($values['amount_paid'] <= 0) {
        $errors['amount_paid'] = 'Please enter the amount you transferred.';
    }

    if ($values['paid_at'] === '') {
        $errors['paid_at'] = 'Please enter the transfer date.';
    } elseif (strtotime($values['paid_at']) === false) {
        $errors['paid_at'] = 'The transfer date is not a valid date.';
    } elseif (strtotime($values['paid_at']) > time() + 86400) {
        $errors['paid_at'] = 'The transfer date cannot be in the future.';
    }

    if (trim($values['sender_name']) === '') {
        $errors['sender_name'] = 'Please enter the name on the sending account.';
    }

    // ── Pertanyaan tambahan (Form Builder → Payment form) ───────────────────
    $extraResult     = FormRepo::validate($extraFields, is_array($_POST['extra'] ?? null) ? $_POST['extra'] : []);
    $values['extra'] = $extraResult['values'];
    foreach ($extraResult['errors'] as $key => $message) {
        $errors['extra_' . $key] = $message;
    }

    // ── Bukti transfer ──────────────────────────────────────────────────────
    $paymentId = $payment !== null ? (int) $payment['id'] : 0;
    $existingProof = $paymentId > 0 ? FileRepo::latest('payment', $paymentId, 'payment_proof') : null;
    $uploaded  = $_FILES['payment_proof'] ?? null;
    $hasUpload = is_array($uploaded) && (int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (!$hasUpload && $existingProof === null) {
        $errors['payment_proof'] = 'Please attach your transfer receipt (PDF or image).';
    }

    if ($errors === []) {
        $paymentId = PaymentRepo::submit($editionId, (int) $invoice['id'], [
            'method'      => $values['method'],
            'amount_paid' => $values['amount_paid'],
            'currency'    => (string) $invoice['currency'],
            'paid_at'     => $values['paid_at'],
            'bank_name'   => trim($values['bank_name']) !== '' ? $values['bank_name'] : null,
            'sender_name' => $values['sender_name'],
            'reference'   => trim($values['reference']) !== '' ? $values['reference'] : null,
            'note'        => trim($values['note']) !== '' ? $values['note'] : null,
            'extra'       => $values['extra'],
        ]);
        mark_filled_by('payments', $paymentId);

        if ($hasUpload) {
            try {
                $fileId = FileRepo::replace(
                    $uploaded,
                    'payment_proof',
                    'payment',
                    $paymentId,
                    $editionId,
                    (int) ($edition['year'] ?? date('Y'))
                );
                PaymentRepo::attachProof($paymentId, $fileId);
            } catch (InvalidArgumentException $e) {
                $errors['payment_proof'] = $e->getMessage();
            }
        }
    }

    if ($errors === []) {
        Audit::log('payment.submitted', [
            'actor_type'  => 'author',
            'actor_id'    => (string) Cenim\Core\AuthorAuth::email(),
            'target_type' => 'payment',
            'target_id'   => $paymentId,
            'meta'        => [
                'invoice' => $invoice['number'],
                'amount'  => $values['amount_paid'],
            ],
        ]);

        Mailer::send(
            (string) (current_person()['email'] ?? $author['email']),
            (string) (current_person()['name'] ?? $author['first_author_name']),
            'Proof of payment received — ' . ($edition['name'] ?? 'CENIM'),
            'payment_received',
            [
                'paperId'       => (string) $author['paper_id'],
                'invoiceNumber' => (string) $invoice['number'],
                'amount'        => fmt_money($values['amount_paid'], (string) $invoice['currency']),
                'loginUrl'      => full_url(author_url('login.php')),
            ]
        );

        Session::flash('success', 'Thank you. Your proof of payment has been submitted for verification.');
        Response::redirect(author_url('index.php'));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' item(s) below.');
    $payment = PaymentRepo::findForInvoice((int) $invoice['id']);
}

$paymentId = $payment !== null ? (int) $payment['id'] : 0;

View::render('author/payment', [
    'title'    => 'Payment',
    'edition'  => $edition,
    'author'   => $author,
    'invoice'  => $invoice,
    'payment'  => $payment,
    'editable' => $editable,
    'isOpen'   => $isOpen,
    'isPaid'   => $isPaid,
    'values'   => $values,
    'errors'   => $errors,
    'extraFields' => $extraFields,
    'proof'    => $paymentId > 0 ? FileRepo::latest('payment', $paymentId, 'payment_proof') : null,
    'banks'    => SettingRepo::getArray($editionId, 'bank_accounts', []),
], 'author');
