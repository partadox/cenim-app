<?php
/**
 * Detail satu registrasi + aksi verify / reject.
 * action=adjust: admin membetulkan jumlah halaman (final pages paper) dan
 * kategori biaya (student / IEEE), biaya dihitung ulang dan disimpan; invoice
 * yang belum dibayar (dan belum ada bukti bayar) di-void lalu diterbitkan ulang.
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

    if ($action === 'adjust') {
        $back    = admin_url('registration-view.php?id=' . $id);
        $invoice = InvoiceRepo::findForRegistration($id);

        if ($invoice !== null && (string) $invoice['status'] === InvoiceRepo::STATUS_PAID) {
            Session::flash('error', 'Invoice ' . $invoice['number'] . ' is already paid, so the fee can no longer be changed here.');
            Response::redirect($back);
        }
        $hasPayment = $invoice !== null
            && Cenim\Core\Db::value('SELECT 1 FROM payments WHERE invoice_id = ? LIMIT 1', [(int) $invoice['id']]) !== null;
        if ($hasPayment) {
            Session::flash('error', 'A payment has already been uploaded for invoice ' . $invoice['number'] . '. Reject or resolve the payment first.');
            Response::redirect($back);
        }

        // Halaman: disimpan sebagai final pages paper (dipakai semua hitungan biaya).
        // Peserta non-presenter (NP-) tidak punya paper → halaman tidak diubah.
        $isParticipantReg = Cenim\Domain\ParticipantRepo::isParticipant(['paper_status' => AuthorRepo::find((int) $registration['author_id'])['paper_status'] ?? '']);
        $pages            = (int) $registration['total_pages'];
        if (!$isParticipantReg) {
            $pagesRaw = trim((string) input('pages', ''));
            if (!ctype_digit($pagesRaw) || (int) $pagesRaw < 1 || (int) $pagesRaw > 200) {
                Session::flash('error', 'Pages must be a number between 1 and 200.');
                Response::redirect($back);
            }
            $pages = (int) $pagesRaw;
        }

        // Kategori: hanya untuk presenter tanpa paket; menentukan student / IEEE.
        $flags = ['is_student' => (int) $registration['is_student'], 'is_ieee_member' => (int) $registration['is_ieee_member']];
        if ((string) $registration['participant_type'] !== 'non_presenter' && ($registration['package_id'] ?? null) === null) {
            $category = Cenim\Core\Db::one(
                'SELECT * FROM fee_categories WHERE id = ? AND edition_id = ? AND is_presenter = 1',
                [input_int('fee_category_id'), $editionId]
            );
            if ($category === null) {
                Session::flash('error', 'Please choose a fee category.');
                Response::redirect($back);
            }
            $flags = ['is_student' => (int) $category['is_student'], 'is_ieee_member' => (int) $category['is_ieee']];
        }

        $before = ['pages' => (int) $registration['total_pages'], 'category' => (string) ($registration['fee_label'] ?? ''), 'total' => (float) $registration['total']];

        $quote = FeeEngine::quote($editionId, RegistrationRepo::quoteInput($flags + $registration, [
            'country'     => $registration['author_country'] ?? null,
            'total_pages' => $pages,
        ]));
        if ($quote['error'] !== null) {
            Session::flash('error', (string) $quote['error']);
            Response::redirect($back);
        }

        if (!$isParticipantReg) {
            AuthorRepo::setFinalPages((int) $registration['author_id'], $pages);
        }
        Cenim\Core\Db::update('registrations', $flags + [
            'fee_category_id' => $quote['fee_category_id'],
            'extra_pages'     => (int) $quote['extra_pages'],
            'currency'        => (string) $quote['currency'],
            'subtotal'        => (float) $quote['subtotal'],
            'discount'        => (float) $quote['discount'],
            'total'           => (float) $quote['total'],
            'updated_at'      => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        Audit::log('registration.adjusted', [
            'actor_type'  => 'admin',
            'actor_id'    => 'admin',
            'target_type' => 'registration',
            'target_id'   => $id,
            'meta'        => ['paper_id' => $registration['paper_id'], 'before' => $before,
                'after' => ['pages' => $pages, 'category' => $quote['category']['label'] ?? '', 'total' => (float) $quote['total']]],
        ]);

        $message = 'Fee updated: ' . fmt_money((float) $quote['total'], (string) $quote['currency']) . '.';

        // Invoice belum dibayar dengan nominal lama → void dan terbitkan ulang (author mendapat email invoice baru).
        if ($invoice !== null && abs((float) $invoice['total'] - (float) $quote['total']) > 0.009) {
            if (input('reissue') !== null) {
                InvoiceRepo::void((int) $invoice['id'], 'Fee adjusted by admin (pages/category).');
                $issued   = InvoiceRepo::issueForRegistration($id);
                $message .= $issued['error'] !== null
                    ? ' Invoice ' . $invoice['number'] . ' was voided, but a new one could not be issued: ' . $issued['error']
                    : ' Invoice ' . $invoice['number'] . ' was voided and ' . $issued['invoice']['number'] . ' was issued and emailed to the author.';
            } else {
                $message .= ' Invoice ' . $invoice['number'] . ' still shows the old amount; void it and issue a new one in Invoices.';
            }
        }

        Session::flash('success', $message);
        Response::redirect($back);
    }

    if ($action === 'waive') {
        $result = InvoiceRepo::waive($id, (string) input('note', ''));

        if ($result['error'] !== null && $result['receipt'] === null) {
            Session::flash('error', 'The fee could not be waived: ' . $result['error']);
        } else {
            Session::flash('success', 'Registration fee waived. Invoice ' . ($result['invoice']['number'] ?? '')
                . ' is marked as paid and receipt ' . ($result['receipt']['number'] ?? '') . ' was issued (0, not counted as income).');
        }
        Response::redirect(admin_url('registration-view.php?id=' . $id));
    }

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
    'categories'   => array_values(array_filter(
        FeeEngine::categories($editionId, (string) $recalculated['currency']),
        static fn (array $c): bool => (int) $c['is_presenter'] === 1
    )),
    'included'     => FeeEngine::includedPages($editionId),
    'confirmation' => Cenim\Domain\ConfirmationRepo::findForPaper((int) $registration['author_id']),
    'extraAnswers' => FormRepo::readable($extraFields, FormRepo::decode($registration['extra_json'] ?? null)),
], 'admin');
