<?php
/**
 * Detail satu registrasi + aksi verify / reject.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Mailer;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\FileRepo;
use Cenim\Domain\FormRepo;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\RegistrationRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$id           = input_int('id');
$registration = $id > 0 ? RegistrationRepo::findWithAuthor($id) : null;

if ($registration === null || (int) $registration['edition_id'] !== $editionId) {
    Response::abort(404, 'Registration not found in the active edition.');
}

if (is_post()) {
    Csrf::verify();

    $action = (string) input('action', '');
    $status = (string) $registration['status'];

    if (!in_array($status, [RegistrationRepo::STATUS_SUBMITTED, RegistrationRepo::STATUS_VERIFIED, RegistrationRepo::STATUS_REJECTED], true)) {
        Session::flash('error', 'This registration has not been submitted yet, so it cannot be reviewed.');
        Response::redirect(admin_url('registration-view.php?id=' . $id));
    }

    $mailArgs = [
        'paperId'       => (string) $registration['paper_id'],
        'paperTitle'    => (string) ($registration['paper_title'] ?? ''),
        'categoryLabel' => (string) ($registration['fee_label'] ?? ''),
        'amount'        => fmt_money((float) $registration['total'], (string) $registration['currency']),
        'loginUrl'      => full_url(author_url('login.php')),
    ];

    if ($action === 'verify') {
        RegistrationRepo::verify($id);

        Audit::log('registration.verified', [
            'actor_type'  => 'admin',
            'actor_id'    => 'admin',
            'target_type' => 'registration',
            'target_id'   => $id,
            'meta'        => ['paper_id' => $registration['paper_id']],
        ]);

        Mailer::send(
            (string) $registration['email'],
            (string) $registration['first_author_name'],
            'Registration verified — ' . ($edition['name'] ?? 'CENIM') . ' (paper ' . $registration['paper_id'] . ')',
            'registration_verified',
            $mailArgs
        );

        // Invoice langsung diterbitkan begitu registrasi diverifikasi.
        $issued = InvoiceRepo::issueForRegistration($id);

        if ($issued['error'] !== null) {
            Session::flash('warning', 'Registration verified, but the invoice could not be issued: ' . $issued['error']);
        } elseif ($issued['created']) {
            Session::flash('success', 'Registration verified. Invoice ' . $issued['invoice']['number']
                . ' issued and emailed to the author.');
        } else {
            Session::flash('success', 'Registration verified. The author already had invoice '
                . $issued['invoice']['number'] . '.');
        }

        Response::redirect(admin_url('registration-view.php?id=' . $id));
    }

    if ($action === 'reject') {
        $reason = trim((string) input('reason', ''));

        if (mb_strlen($reason) < 10) {
            Session::flash('error', 'Please write at least 10 characters explaining what the author must fix.');
            Response::redirect(admin_url('registration-view.php?id=' . $id));
        }

        RegistrationRepo::reject($id, mb_substr($reason, 0, 1000));

        Audit::log('registration.rejected', [
            'actor_type'  => 'admin',
            'actor_id'    => 'admin',
            'target_type' => 'registration',
            'target_id'   => $id,
            'meta'        => ['paper_id' => $registration['paper_id'], 'reason' => $reason],
        ]);

        Mailer::send(
            (string) $registration['email'],
            (string) $registration['first_author_name'],
            'Action needed on your registration — ' . ($edition['name'] ?? 'CENIM'),
            'registration_rejected',
            $mailArgs + ['reason' => $reason]
        );

        Session::flash('success', 'Registration returned to the author with your note.');
        Response::redirect(admin_url('registration-view.php?id=' . $id));
    }

    Session::flash('error', 'Unknown action.');
    Response::redirect(admin_url('registration-view.php?id=' . $id));
}

// Hitung ulang biaya dari data terkini, untuk dibandingkan dengan nominal tersimpan.
$recalculated = FeeEngine::quote($editionId, ['discount' => 0] + RegistrationRepo::quoteInput($registration, [
    'country'     => $registration['author_country'] ?? null,
    'total_pages' => (int) ($registration['total_pages'] ?? 0),
]));

$mainPaper = ($registration['main_author_id'] ?? null) !== null ? AuthorRepo::find((int) $registration['main_author_id']) : null;

$extraFields = FormRepo::fields($editionId, FormRepo::FORM_REGISTRATION);

View::render('admin/registration_view', [
    'title'        => 'Review registration',
    'subtitle'     => 'Paper ' . $registration['paper_id'] . ' — ' . $registration['first_author_name'],
    'active'       => 'registrations',
    'edition'      => $edition,
    'registration' => $registration,
    'recalculated' => $recalculated,
    'files'        => FileRepo::forOwner('registration', $id),
    'invoice'      => InvoiceRepo::findForRegistration($id),
    'presenter'    => ($registration['presenter_id'] ?? null) !== null ? Cenim\Domain\PaperAuthorRepo::find((int) $registration['presenter_id']) : null,
    'attendees'    => RegistrationRepo::attendees($id),
    'mainPaper'    => $mainPaper,
    'mainRegistration' => $mainPaper !== null ? RegistrationRepo::findByAuthor((int) $mainPaper['id']) : null,
    'additionalPapers' => Cenim\Core\Db::all(
        'SELECT a.paper_id, a.paper_title, r.id AS registration_id, r.status FROM registrations r JOIN authors a ON a.id = r.author_id WHERE r.main_author_id = ? ORDER BY a.paper_id',
        [(int) $registration['author_id']]
    ),
    'shirtOn'      => RegistrationRepo::shirtEnabled($editionId),
    'extraAnswers' => FormRepo::readable($extraFields, FormRepo::decode($registration['extra_json'] ?? null)),
], 'admin');
