<?php
/**
 * Form 1 — registrasi satu paper (terbuka setelah confirmation form / form 0):
 *   - presenter (dipilih dari author paper) + mode offline/online
 *   - kategori biaya presenter (student / IEEE) + bukti
 *   - paper tambahan dari first author yang sama → biaya registrasi 50%
 *     (invoice, LoA, receipt tetap per paper)
 *   - peserta tambahan non-presenter ("keranjang"): co-author atau orang luar
 *   - ukuran baju per orang yang datang (bila diaktifkan di Settings)
 *   - pertanyaan tambahan dari Form Builder
 *
 * Author bisa menyimpan sebagai draft (save) atau mengirim (submit).
 * Setelah submitted/verified form menjadi read-only; bila ditolak, form
 * terbuka kembali beserta alasan penolakannya.
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
use Cenim\Domain\AttendanceRepo;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\ConfirmationRepo;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\FileRepo;
use Cenim\Domain\FormRepo;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\ParticipantRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$author    = current_author();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0 || $author === null) {
    Response::abort(503, 'Registration is not available right now.');
}

// Paper edisi lama tanpa registrasi: tidak ada yang bisa ditampilkan/dibuat.
if (edition_is_archive() && RegistrationRepo::findByAuthor((int) $author['id']) === null) {
    Session::flash('info', ($edition['name'] ?? 'This edition') . ' has ended; this paper was not registered.');
    Response::redirect(author_url('index.php'));
}

// Peserta non-presenter (bukan paper): form sendiri yang lebih sederhana.
if (ParticipantRepo::isParticipant($author)) {
    define('CENIM_PARTICIPANT_REGISTRATION', true);
    require __DIR__ . '/_registration_participant.php';
    exit;
}

// Confirmation form (form 0) dulu; registrasi yang sudah dikirim tidak ditahan.
if (ConfirmationRepo::blocksRegistration($editionId, $author, RegistrationRepo::findByAuthor((int) $author['id']))) {
    Session::flash('info', 'Please submit the confirmation form first. The registration form opens right after it.');
    Response::redirect(author_url('confirmation.php'));
}

$registration = RegistrationRepo::ensureDraft($editionId, $author);
$confirmation = ConfirmationRepo::findForPaper((int) $author['id']);
$regId        = (int) $registration['id'];
$editable     = RegistrationRepo::isEditable($registration);
$isOpen       = SettingRepo::getBool($editionId, 'registration_open', true) && !edition_is_archive();
$extraFields  = FormRepo::fields($editionId, FormRepo::FORM_REGISTRATION);
$packages     = FeeEngine::packages($editionId);
$paperAuthors = PaperAuthorRepo::forPaper((int) $author['id']);
$authorIds    = array_map(static fn (array $a): int => (int) $a['id'], $paperAuthors);
$mainOptions  = RegistrationRepo::mainPaperOptions($editionId, $author);
$isMainOfOthers = RegistrationRepo::isMainPaperOfOthers((int) $author['id']);
$shirtOn      = RegistrationRepo::shirtEnabled($editionId);
$shirtSizes   = RegistrationRepo::shirtSizes($editionId);

// Presenter awal: tersimpan → penanda presenter di daftar author → first author.
$defaultPresenter = (int) ($registration['presenter_id'] ?? 0);
if (!in_array($defaultPresenter, $authorIds, true)) {
    $defaultPresenter = 0;
    foreach ($paperAuthors as $pa) {
        if ((int) $pa['is_presenter'] === 1) {
            $defaultPresenter = (int) $pa['id'];
            break;
        }
    }
    $defaultPresenter = $defaultPresenter ?: ($authorIds[0] ?? 0);
}

$values = [
    'participant_type'  => (string) $registration['participant_type'],
    'presenter_id'      => $defaultPresenter,
    'presentation_mode' => (string) ($registration['presentation_mode'] ?? 'offline'),
    'presenter_shirt'   => (string) ($registration['presenter_shirt'] ?? ''),
    'is_student'        => (int) $registration['is_student'] === 1,
    'is_ieee_member'    => (int) $registration['is_ieee_member'] === 1,
    'ieee_number'       => (string) ($registration['ieee_number'] ?? ''),
    'extra_papers'      => (int) $registration['extra_papers'],
    'package_id'        => $registration['package_id'] !== null ? (int) $registration['package_id'] : null,
    'main_author_id'    => ($registration['main_author_id'] ?? null) !== null ? (int) $registration['main_author_id'] : null,
    'attendees'         => array_map(static fn (array $a): array => [
        'source'          => $a['paper_author_id'] !== null ? 'author' : 'other',
        'paper_author_id' => $a['paper_author_id'] !== null ? (int) $a['paper_author_id'] : null,
        'name'            => (string) $a['name'],
        'affiliation'     => (string) ($a['affiliation'] ?? ''),
        'email'           => (string) ($a['email'] ?? ''),
        'shirt_size'      => (string) ($a['shirt_size'] ?? ''),
    ], RegistrationRepo::attendees($regId)),
    'extra'             => FormRepo::decode($registration['extra_json'] ?? null),
];

// Draft yang belum pernah disimpan: mode presentasi (dan ukuran baju presenter) dari confirmation form.
if ($confirmation !== null && ($registration['updated_at'] ?? null) === null && $editable) {
    $values['presentation_mode'] = (string) $confirmation['presentation_mode'];
    // Ukuran baju presenter = orang pertama di confirmation form.
    $confirmedShirt = ConfirmationRepo::shirts($confirmation)[0] ?? '';
    if ($shirtOn && $values['presenter_shirt'] === '' && in_array($confirmedShirt, $shirtSizes, true)) {
        $values['presenter_shirt'] = $confirmedShirt;
    }
}

$errors = [];

if (is_post()) {
    Csrf::verify();

    if (!$editable) {
        Session::flash('error', 'Your registration has already been submitted and can no longer be edited.');
        Response::redirect(author_url('registration.php'));
    }

    if (!$isOpen) {
        Session::flash('error', 'Registration is currently closed.');
        Response::redirect(author_url('registration.php'));
    }

    $submit = input('action') === 'submit';

    // Registrasi paper selalu sebagai presenter; peserta tanpa presentasi masuk keranjang.
    $values['participant_type']  = 'presenter';
    $values['presenter_id']      = input_int('presenter_id');
    $values['presentation_mode'] = isset(RegistrationRepo::MODES[(string) input('presentation_mode', '')]) ? (string) input('presentation_mode') : 'offline';
    $values['presenter_shirt']   = $shirtOn && in_array((string) input('presenter_shirt', ''), $shirtSizes, true) ? (string) input('presenter_shirt') : '';
    $values['is_student']        = input_bool('is_student');
    $values['is_ieee_member']    = input_bool('is_ieee_member');
    $values['ieee_number']       = (string) input('ieee_number', '');
    $values['extra_papers']      = 0;
    $values['package_id']        = input_int('package_id') > 0 ? input_int('package_id') : null;
    $values['main_author_id']    = input_bool('is_additional') && input_int('main_author_id') > 0 ? input_int('main_author_id') : null;

    if (!in_array($values['presenter_id'], $authorIds, true)) {
        $errors['presenter_id'] = 'Please choose who will present this paper.';
    }
    if ($shirtOn && $submit && $values['presenter_shirt'] === '') {
        $errors['presenter_shirt'] = 'Please choose the shirt size of the presenter.';
    }

    // ── Paper tambahan ──────────────────────────────────────────────────────
    $mainPaper = null;
    if (input_bool('is_additional')) {
        foreach ($mainOptions as $option) {
            if ((int) $option['id'] === (int) $values['main_author_id']) {
                $mainPaper = $option;
            }
        }
        if ($isMainOfOthers) {
            $errors['main_author_id'] = 'Another paper already uses this paper as its main paper, so this one pays the full fee.';
            $values['main_author_id'] = null;
        } elseif ($mainPaper === null) {
            $errors['main_author_id'] = 'Choose the main paper (the one that pays the full registration fee).';
        }
    }

    // ── Peserta tambahan ────────────────────────────────────────────────────
    $cart = RegistrationRepo::validateAttendees(
        is_array($_POST['attendees'] ?? null) ? $_POST['attendees'] : [],
        $paperAuthors,
        $values['presenter_id'],
        $shirtSizes,
        $shirtOn,
        $submit
    );
    $errors += $cart['errors'];
    $values['attendees'] = array_map(static fn (array $r): array => $r + [
        'source' => $r['paper_author_id'] !== null ? 'author' : 'other',
    ], $cart['rows']);

    // ── Field dinamis (form builder) ────────────────────────────────────────
    $extraInput  = is_array($_POST['extra'] ?? null) ? $_POST['extra'] : [];
    $extraResult = FormRepo::validate($extraFields, $extraInput);
    $values['extra'] = $extraResult['values'];
    foreach ($extraResult['errors'] as $key => $message) {
        $errors['extra_' . $key] = $message;
    }

    // ── Unggah bukti ────────────────────────────────────────────────────────
    $year    = (int) ($edition['year'] ?? date('Y'));
    $uploads = [
        'student_proof' => $_FILES['student_proof'] ?? null,
        'ieee_proof'    => $_FILES['ieee_proof'] ?? null,
    ];

    foreach ($uploads as $purpose => $file) {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        try {
            FileRepo::replace($file, $purpose, 'registration', $regId, $editionId, $year);
        } catch (InvalidArgumentException $e) {
            $errors[$purpose] = $e->getMessage();
        }
    }

    $studentProof = FileRepo::latest('registration', $regId, 'student_proof');
    $ieeeProof    = FileRepo::latest('registration', $regId, 'ieee_proof');

    if ($values['is_ieee_member'] && trim($values['ieee_number']) === '') {
        $errors['ieee_number'] = 'Please enter your IEEE membership number.';
    }

    // Bukti hanya wajib saat submit, supaya draft tetap bisa disimpan bertahap.
    if ($submit) {
        if ($values['is_student'] && $studentProof === null && !isset($errors['student_proof'])) {
            $errors['student_proof'] = 'Please upload the student ID or enrolment letter of the presenter.';
        }
        if ($values['is_ieee_member'] && $ieeeProof === null && !isset($errors['ieee_proof'])) {
            $errors['ieee_proof'] = 'Please upload proof of the IEEE membership.';
        }
    }

    $quote = FeeEngine::quote($editionId, [
        'country'          => $author['country'] ?? null,
        'participant_type' => $values['participant_type'],
        'is_student'       => $values['is_student'],
        'is_ieee_member'   => $values['is_ieee_member'],
        'total_pages'      => AuthorRepo::pages($author),
        'extra_papers'     => 0,
        'package_id'       => $values['package_id'],
        'main_paper_id'    => $mainPaper['paper_id'] ?? null,
        'attendees'        => array_column($cart['rows'], 'name'),
    ]);

    if ($quote['error'] !== null) {
        $errors['fee'] = $quote['error'];
    }

    if ($errors === []) {
        Db::transaction(static function () use ($regId, $editionId, $values, $quote, $submit, $cart, $author): void {
            RegistrationRepo::save($regId, [
                'participant_type'  => $values['participant_type'],
                'is_student'        => $values['is_student'],
                'is_ieee_member'    => $values['is_ieee_member'],
                'ieee_number'       => $values['ieee_number'],
                'extra_papers'      => 0,
                'package_id'        => $values['package_id'],
                'extra'             => $values['extra'],
                'presenter_id'      => $values['presenter_id'],
                'presentation_mode' => $values['presentation_mode'],
                'presenter_shirt'   => $values['presenter_shirt'],
                'main_author_id'    => $values['main_author_id'],
            ], $quote, $submit);
            RegistrationRepo::saveAttendees($editionId, $regId, $cart['rows']);

            // Presenter terpilih = penerima sertifikat Presenter (selama belum terbit).
            if ($submit && !AttendanceRepo::presenterLocked((int) $author['id'])) {
                Db::run('UPDATE paper_authors SET is_presenter = CASE WHEN id = ? THEN 1 ELSE 0 END, updated_at = ? WHERE author_id = ?', [
                    $values['presenter_id'], now(), (int) $author['id'],
                ]);
            }
        });
        mark_filled_by('registrations', $regId);

        if ($submit) {
            Audit::log('registration.submitted', [
                'actor_type'  => 'author',
                'actor_id'    => (string) Cenim\Core\AuthorAuth::email(),
                'target_type' => 'registration',
                'target_id'   => $regId,
                'meta'        => [
                    'total'      => $quote['total'],
                    'currency'   => $quote['currency'],
                    'attendees'  => count($cart['rows']),
                    'main_paper' => $mainPaper['paper_id'] ?? null,
                ],
            ]);

            Mailer::send(
                (string) (current_person()['email'] ?? $author['email']),
                (string) (current_person()['name'] ?? $author['first_author_name']),
                'Registration received — ' . ($edition['name'] ?? 'CENIM') . ' (paper ' . $author['paper_id'] . ')',
                'registration_submitted',
                [
                    'paperId'       => (string) $author['paper_id'],
                    'paperTitle'    => (string) ($author['paper_title'] ?? ''),
                    'categoryLabel' => (string) ($quote['category']['label'] ?? ($quote['package']['name'] ?? '-'))
                        . ($cart['rows'] !== [] ? ' + ' . count($cart['rows']) . ' additional attendee(s)' : ''),
                    'amount'        => fmt_money($quote['total'], (string) $quote['currency']),
                    'loginUrl'      => full_url(author_url('login.php')),
                ]
            );

            Session::flash('success', 'Your registration has been submitted. We will notify you once it is verified.');
        } else {
            Session::flash('success', 'Draft saved. You can come back and finish it later.');
        }

        Response::redirect(author_url($submit ? 'index.php' : 'registration.php'));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' item(s) below.');
}

// Kutipan biaya untuk ditampilkan (memakai nilai terbaru di form).
$mainPaperId = null;
foreach ($mainOptions as $option) {
    if ((int) $option['id'] === (int) $values['main_author_id']) {
        $mainPaperId = (string) $option['paper_id'];
    }
}
$quote = FeeEngine::quote($editionId, [
    'country'          => $author['country'] ?? null,
    'participant_type' => $values['participant_type'],
    'is_student'       => $values['is_student'],
    'is_ieee_member'   => $values['is_ieee_member'],
    'total_pages'      => AuthorRepo::pages($author),
    'extra_papers'     => $values['extra_papers'],
    'package_id'       => $values['package_id'],
    'main_paper_id'    => $mainPaperId,
    'attendees'        => array_column($values['attendees'], 'name'),
]);

// Tarif untuk hitung ulang langsung di browser (sumbernya sama dengan FeeEngine).
$currency = FeeEngine::currencyForCountry($author['country'] ?? null);
$rates    = [];
foreach ([[false, false], [false, true], [true, false], [true, true]] as [$student, $ieee]) {
    $category = FeeEngine::pickCategory($editionId, $currency, 'presenter', $student, $ieee);
    $rates[($student ? 's' : 'n') . ($ieee ? 'i' : 'n')] = $category !== null
        ? ['label' => (string) $category['label'], 'amount' => (float) $category['amount']]
        : null;
}

View::render('author/registration', [
    'title'          => 'Registration form',
    'edition'        => $edition,
    'author'         => $author,
    'registration'   => $registration,
    'editable'       => $editable,
    'isOpen'         => $isOpen,
    'values'         => $values,
    'errors'         => $errors,
    'quote'          => $quote,
    'packages'       => $packages,
    'extraFields'    => $extraFields,
    'paperAuthors'   => $paperAuthors,
    'mainOptions'    => $mainOptions,
    'isMainOfOthers' => $isMainOfOthers,
    'shirtOn'        => $shirtOn,
    'shirtSizes'     => $shirtSizes,
    'feeData'        => [
        'currency'      => $currency,
        'rates'         => $rates,
        'attendeeFee'   => FeeEngine::attendeeFee($editionId, $currency),
        'percent'       => FeeEngine::extraPaperPercent($editionId),
        'extraPages'    => FeeEngine::extraPages($editionId, AuthorRepo::pages($author)),
        'extraPageFee'  => FeeEngine::extraPageFee($editionId, $currency),
        'includedPages' => FeeEngine::includedPages($editionId),
    ],
    'studentProof'   => FileRepo::latest('registration', $regId, 'student_proof'),
    'ieeeProof'      => FileRepo::latest('registration', $regId, 'ieee_proof'),
    'includedPages'  => FeeEngine::includedPages($editionId),
    'confirmation'   => $confirmation,
], 'author');
