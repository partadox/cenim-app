<?php
/**
 * Terbitkan invoice. POST + CSRF.
 *
 *   registration_id=<id>  → satu registrasi
 *   scope=all             → semua registrasi terverifikasi yang belum punya invoice
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\InvoiceRepo;

if (!is_post()) {
    Response::redirect(admin_url('invoices.php'));
}

Cenim\Core\Csrf::verify();

$editionId = (int) (current_edition()['id'] ?? 0);
$back      = (string) input('back', admin_url('invoices.php'));

// Cegah open redirect: hanya izinkan kembali ke halaman admin.
if (!str_starts_with($back, admin_url())) {
    $back = admin_url('invoices.php');
}

if (input('scope') === 'all') {
    $issued  = 0;
    $skipped = 0;
    $errors  = [];

    foreach (InvoiceRepo::pendingIssue($editionId) as $row) {
        $result = InvoiceRepo::issueForRegistration((int) $row['id']);

        if ($result['created']) {
            $issued++;
        } elseif ($result['error'] !== null) {
            $errors[] = $row['paper_id'] . ': ' . $result['error'];
        } else {
            $skipped++;
        }
    }

    if ($issued > 0) {
        Session::flash('success', $issued . ' invoice(s) issued and emailed to the authors.');
    }
    if ($skipped > 0) {
        Session::flash('info', $skipped . ' registration(s) already had an invoice.');
    }
    foreach (array_slice($errors, 0, 5) as $error) {
        Session::flash('error', $error);
    }
    if ($issued === 0 && $skipped === 0 && $errors === []) {
        Session::flash('info', 'Nothing to issue — every verified registration already has an invoice.');
    }

    Response::redirect($back);
}

$registrationId = input_int('registration_id');

if ($registrationId <= 0) {
    Session::flash('error', 'No registration selected.');
    Response::redirect($back);
}

$result = InvoiceRepo::issueForRegistration($registrationId);

if ($result['error'] !== null) {
    Session::flash('error', $result['error']);
} elseif ($result['created']) {
    Session::flash('success', 'Invoice ' . $result['invoice']['number'] . ' issued and emailed to the author.');
} else {
    Session::flash('info', 'This registration already has invoice ' . $result['invoice']['number'] . '.');
}

Response::redirect($back);
