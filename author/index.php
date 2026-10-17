<?php
/**
 * Dashboard author & panitia/reviewer.
 *
 * Tab "Papers": paper terpilih (pemilih bila orang ini author di beberapa
 * paper), daftar semua author-nya, confirmation form (langkah 0), stepper 5
 * langkah (registrasi → invoice → pembayaran → receipt → video), dokumen (LoA, invoice, receipt), dan sertifikat milik
 * orang ini. Tab "Committee": sertifikat panitia/reviewer. Panitia murni yang
 * bukan author hanya melihat tab Committee.
 */

declare(strict_types=1);

const AUTHOR_ALLOW_NO_PAPER = true;

require __DIR__ . '/_boot.php';

use Cenim\Core\AuthorAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\View;
use Cenim\Domain\LoaRepo;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\AttendanceRepo;
use Cenim\Domain\CertificateRepo;
use Cenim\Domain\ConfirmationRepo;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\PaymentRepo;
use Cenim\Domain\ReceiptRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Domain\SettingRepo;

if (is_post() && input('action') === 'select_paper') {
    Csrf::verify();
    AuthorAuth::selectPaper(input_int('paper'));
    Response::redirect(author_url('index.php'));
}

$edition   = current_edition();
$author    = current_author();
$person    = current_person();
$editionId = (int) ($edition['id'] ?? 0);
$committee = AuthorAuth::committee();
$tab       = input('tab') === 'committee' || $author === null ? 'committee' : 'papers';
$released  = CertificateRepo::isReleased($edition);

if ($author === null && $committee === []) {
    // Tidak mungkin terjadi lewat login normal; jaga-jaga bila data dihapus admin.
    AuthorAuth::logout();
    Response::redirect(author_url('login.php'));
}

$registration = $author !== null ? RegistrationRepo::findByAuthor((int) $author['id']) : null;
$status       = (string) ($registration['status'] ?? RegistrationRepo::STATUS_DRAFT);
$isOpen       = SettingRepo::getBool($editionId, 'registration_open', true) && !edition_is_archive();

// ── Langkah 0: confirmation form ────────────────────────────────────────────
$confirmation  = $author !== null ? ConfirmationRepo::findForPaper((int) $author['id']) : null;
$confirmOpen   = ConfirmationRepo::isOpen($editionId) && !edition_is_archive();
$confirmBlocks = $author !== null && ConfirmationRepo::blocksRegistration($editionId, $author, $registration);
$confirmDue    = ConfirmationRepo::deadline($editionId);
$confirmDueText = $confirmDue !== '' ? ' Deadline ' . fmt_date($confirmDue . ' 00:00:00') . '.' : '';

if ($author === null || !LoaRepo::isAvailable($author)) {
    $step0 = null;
} elseif ($confirmation !== null) {
    $editableConfirm = ConfirmationRepo::isEditable($editionId, $registration) && !edition_is_archive();
    $step0 = [
        'state'     => 'done',
        'note'      => ConfirmationRepo::summary($confirmation) . ' · '
            . (int) $confirmation['pages'] . ' pages (reviewed manuscript)'
            . ((int) $confirmation['extra_pages'] > 0 ? ', extra page fee ' . fmt_money((float) $confirmation['extra_page_fee'], (string) $confirmation['currency']) : '')
            . '.',
        'url'       => author_url('confirmation.php'),
        'cta'       => $editableConfirm ? 'View or change' : 'View',
        'filled_by' => $confirmation['filled_by_name'] ?? null,
    ];
} elseif (!$confirmBlocks && ($status !== RegistrationRepo::STATUS_DRAFT || !$confirmOpen)) {
    $step0 = null; // registrasi sudah dikirim sebelum form ini ada, atau form tidak wajib & sudah ditutup
} else {
    $step0 = [
        'state'     => 'active',
        'note'      => $confirmOpen
            ? 'Confirm offline or online presentation, how many people will come, and the number of pages.' . $confirmDueText
            : 'The confirmation form is closed. Please contact the secretariat.',
        'url'       => author_url('confirmation.php'),
        'cta'       => $confirmOpen ? 'Open the form' : null,
        'filled_by' => null,
    ];
}

// ── Langkah 1: form registrasi ──────────────────────────────────────────────
$step1 = $confirmBlocks && $status === RegistrationRepo::STATUS_DRAFT ? [
    'state' => 'locked',
    'note'  => 'Opens after you submit the confirmation form (step 0).',
    'cta'   => null,
    'url'   => null,
] : match ($status) {
    RegistrationRepo::STATUS_SUBMITTED => [
        'state' => 'done',
        'note'  => 'Submitted on ' . fmt_dt($registration['submitted_at'] ?? null) . '. Waiting for verification.',
        'cta'   => 'View submission',
    ],
    RegistrationRepo::STATUS_VERIFIED => [
        'state' => 'done',
        'note'  => 'Verified by the committee.',
        'cta'   => 'View submission',
    ],
    RegistrationRepo::STATUS_REJECTED => [
        'state' => 'active',
        'note'  => 'The committee asked for a correction. Please update and submit again.',
        'cta'   => 'Fix my registration',
    ],
    default => edition_is_archive()
        ? [
            'state' => 'locked',
            'note'  => ($edition['name'] ?? 'This edition') . ' has ended; this paper was not registered.',
            'cta'   => null,
            'url'   => null,
        ]
        : [
            'state' => 'active',
            'note'  => $isOpen
                ? 'Choose the presenter, the registration category and any additional attendees.'
                : 'Registration is currently closed.',
            'cta'   => 'Open the form',
        ],
};

$verified = $status === RegistrationRepo::STATUS_VERIFIED;
$invoice  = $author !== null ? InvoiceRepo::findForAuthor((int) $author['id']) : null;

// ── Langkah 2: invoice ──────────────────────────────────────────────────────
if ($invoice !== null) {
    $step2 = [
        'state' => (string) $invoice['status'] === InvoiceRepo::STATUS_PAID ? 'done' : 'active',
        'note'  => 'Invoice ' . $invoice['number'] . ' — '
            . fmt_money((float) $invoice['total'], (string) $invoice['currency'])
            . ((string) $invoice['status'] === InvoiceRepo::STATUS_PAID
                ? ', paid.'
                : ', due ' . ($invoice['due_date'] !== null ? fmt_date((string) $invoice['due_date'] . ' 00:00:00') : 'soon') . '.'),
        'url'   => author_url('invoice.php'),
        'cta'   => 'View invoice',
    ];
} elseif ($verified) {
    $step2 = [
        'state' => 'active',
        'note'  => 'Your registration is verified. The committee is preparing your invoice.',
        'url'   => author_url('invoice.php'),
        'cta'   => 'Check status',
    ];
} else {
    $step2 = [
        'state' => 'locked',
        'note'  => 'Issued after the committee verifies your registration.',
        'url'   => null,
        'cta'   => null,
    ];
}

// ── Langkah 3: pembayaran ───────────────────────────────────────────────────
$payment    = $author !== null ? PaymentRepo::findForAuthor((int) $author['id']) : null;
$receipt    = $author !== null ? ReceiptRepo::findForAuthor((int) $author['id']) : null;
$payOpen    = SettingRepo::getBool($editionId, 'payment_open', true) && !edition_is_archive();
$payStatus  = (string) ($payment['status'] ?? '');

if ($invoice === null) {
    $step3 = [
        'state' => 'locked',
        'note'  => 'Available once your invoice is issued.',
        'url'   => null,
        'cta'   => null,
    ];
} elseif ($payStatus === PaymentRepo::STATUS_CONFIRMED) {
    $step3 = [
        'state' => 'done',
        'note'  => 'Payment confirmed on ' . fmt_dt($payment['confirmed_at'] ?? null) . '.',
        'url'   => author_url('payment.php'),
        'cta'   => 'View submission',
    ];
} elseif ($payStatus === PaymentRepo::STATUS_SUBMITTED) {
    $step3 = [
        'state' => 'active',
        'note'  => 'Submitted on ' . fmt_dt($payment['submitted_at'] ?? null) . '. Waiting for verification.',
        'url'   => author_url('payment.php'),
        'cta'   => 'View submission',
    ];
} elseif ($payStatus === PaymentRepo::STATUS_REJECTED) {
    $step3 = [
        'state' => 'active',
        'note'  => 'The committee asked for a correction to your payment.',
        'url'   => author_url('payment.php'),
        'cta'   => 'Fix my payment',
    ];
} else {
    $step3 = [
        'state' => 'active',
        'note'  => $payOpen
            ? 'Transfer the invoice amount, then submit your proof of payment here.'
            : 'Payment submission is currently closed.',
        'url'   => author_url('payment.php'),
        'cta'   => 'Open the payment form',
    ];
}

// ── Langkah 4: receipt ──────────────────────────────────────────────────────
$step4 = $receipt !== null
    ? [
        'state' => 'done',
        'note'  => 'Receipt ' . $receipt['number'] . ' — '
            . fmt_money((float) $receipt['amount'], (string) $receipt['currency']) . '.',
        'url'   => author_url('receipt.php'),
        'cta'   => 'View receipt',
    ]
    : [
        'state' => 'locked',
        'note'  => 'Issued automatically once your payment is confirmed.',
        'url'   => null,
        'cta'   => null,
    ];

// ── Langkah 5: video presentasi ─────────────────────────────────────────────
$attendance = $author !== null ? AttendanceRepo::findForPaper((int) $author['id']) : null;
$attendOpen = AttendanceRepo::isOpen($editionId) && !edition_is_archive();
$isOnline   = (string) ($registration['presentation_mode'] ?? 'offline') === 'online';
$hasVideo   = ($attendance['video_url'] ?? '') !== '';

if (!AttendanceRepo::isAvailable($invoice)) {
    $step5 = ['state' => 'locked', 'note' => 'Opens once your payment is confirmed (online presentations send the video here).', 'url' => null, 'cta' => null];
} elseif ($hasVideo) {
    $step5 = [
        'state' => 'done',
        'note'  => 'Video link sent' . ($isOnline ? ' for the online presentation.' : ' as a backup.'),
        'url'   => author_url('attendance.php'),
        'cta'   => $attendOpen ? 'View or change' : 'View',
    ];
} elseif (!$isOnline) {
    $step5 = [
        'state' => 'done',
        'note'  => 'Presenting offline — no video needed. You may still send one as a backup.',
        'url'   => author_url('attendance.php'),
        'cta'   => 'Send a backup video',
    ];
} else {
    $step5 = [
        'state' => 'active',
        'note'  => $attendOpen
            ? 'Online presentation: send the link to the pre-recorded video (Google Drive or YouTube).'
            : 'The video presentation form is currently closed.',
        'url'   => author_url('attendance.php'),
        'cta'   => 'Send the video',
    ];
}

$steps = [
    [
        'no'    => 1,
        'title' => 'Registration Form',
        'icon'  => 'fa-clipboard-list',
        'state' => $step1['state'],
        'note'  => $step1['note'],
        'url'   => array_key_exists('url', $step1) ? $step1['url'] : author_url('registration.php'),
        'cta'   => $step1['cta'],
        'filled_by' => $registration['filled_by_name'] ?? null,
    ],
    [
        'no'    => 2,
        'title' => 'Invoice',
        'icon'  => 'fa-file-invoice',
        'state' => $step2['state'],
        'note'  => $step2['note'],
        'url'   => $step2['url'],
        'cta'   => $step2['cta'],
    ],
    [
        'no'    => 3,
        'title' => 'Payment Form',
        'icon'  => 'fa-money-check-dollar',
        'state' => $step3['state'],
        'note'  => $step3['note'],
        'url'   => $step3['url'],
        'cta'   => $step3['cta'],
        'filled_by' => $payment['filled_by_name'] ?? null,
    ],
    [
        'no'    => 4,
        'title' => 'Receipt',
        'icon'  => 'fa-receipt',
        'state' => $step4['state'],
        'note'  => $step4['note'],
        'url'   => $step4['url'],
        'cta'   => $step4['cta'],
    ],
    [
        'no'    => 5,
        'title' => 'Video Presentation Form',
        'icon'  => 'fa-video',
        'state' => $step5['state'],
        'note'  => $step5['note'],
        'url'   => $step5['url'],
        'cta'   => $step5['cta'],
        'filled_by' => $attendance['filled_by_name'] ?? null,
    ],
];

// ── Sertifikat: terbit otomatis setelah tanggal rilis (idempoten) ────────────
// Semua author paper ini, supaya satu author bisa mengunduhkan sertifikat rekannya.
$paperAuthors = $author !== null ? PaperAuthorRepo::forPaper((int) $author['id']) : [];
$certificates = [];
if ($author !== null) {
    if ($released) {
        foreach ($paperAuthors as $paperAuthor) {
            CertificateRepo::ensureForPerson(
                $editionId,
                $paperAuthor,
                CertificateRepo::eligibleTypesForPerson($editionId, $author, $paperAuthor, $registration, $invoice)
            );
        }
    }
    $certificates = CertificateRepo::forPaperAuthors($editionId, array_column($paperAuthors, 'id'));
}

// Peran panitia bisa dari beberapa edisi; tiap edisi punya template & tanggal rilis sendiri.
$committeeCertificates = [];
$byEdition = [];
foreach ($committee as $row) {
    $byEdition[(int) $row['edition_id']][] = $row;
}
foreach ($byEdition as $rowEditionId => $rows) {
    $rowEdition = Cenim\Domain\EditionRepo::find($rowEditionId);
    if (CertificateRepo::isReleased($rowEdition)) {
        CertificateRepo::ensureForCommittee($rowEditionId, $rows);
    }
    $committeeCertificates = array_merge(
        $committeeCertificates,
        CertificateRepo::forCommittee($rowEditionId, array_map(static fn (array $r): int => (int) $r['id'], $rows))
    );
}

View::render('author/dashboard', [
    'title'        => $author !== null ? 'Author Area' : 'Committee Area',
    'edition'      => $edition,
    'author'       => $author,
    'person'       => $person,
    'email'        => AuthorAuth::email(),
    'papers'       => AuthorAuth::papers(),
    'paperAuthors' => $paperAuthors,
    'committee'    => $committee,
    'tab'          => $tab,
    'registration' => $registration,
    'invoice'      => $invoice,
    'payment'      => $payment,
    'receipt'      => $receipt,
    'loaAvailable' => $author !== null && LoaRepo::isAvailable($author),
    'certificates' => $certificates,
    'committeeCertificates' => $committeeCertificates,
    'certificatesReleased'  => $released,
    'steps'        => $steps,
    'step0'        => $step0,
], 'author');
