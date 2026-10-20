<?php
/**
 * Registration form peserta non-presenter (dipanggil dari registration.php).
 *
 * Satu registrasi per peserta: dirinya sendiri (biaya non-presenter) plus
 * orang lain yang diajak (keranjang "Additional attendees", biaya non-presenter
 * per orang), ukuran baju bila diaktifkan, dan pertanyaan Form Builder.
 * Status, invoice, pembayaran, dan receipt sama dengan registrasi paper.
 */

declare(strict_types=1);

defined('CENIM_PARTICIPANT_REGISTRATION') || exit;

use Cenim\Core\Audit;
use Cenim\Core\AuthorAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Mailer;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\FormRepo;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Domain\SettingRepo;

/** @var array $edition @var array $author @var int $editionId */

$registration = RegistrationRepo::ensureDraft($editionId, $author);
$regId        = (int) $registration['id'];
$editable     = RegistrationRepo::isEditable($registration);
$isOpen       = SettingRepo::getBool($editionId, 'registration_open', true) && !edition_is_archive();
$extraFields  = FormRepo::fields($editionId, FormRepo::FORM_REGISTRATION);
$person       = PaperAuthorRepo::forPaper((int) $author['id'])[0] ?? null;
$shirtOn      = RegistrationRepo::shirtEnabled($editionId);
$shirtSizes   = RegistrationRepo::shirtSizes($editionId);
$currency     = FeeEngine::currencyForCountry($author['country'] ?? null);

if ($person === null) {
    Response::abort(500, 'This participant has no person record. Please contact the secretariat.');
}

// Draft baru dibuat sebagai presenter (bawaan); peserta selalu non-presenter.
if ($editable && (string) $registration['participant_type'] !== 'non_presenter') {
    Db::update('registrations', ['participant_type' => 'non_presenter'], 'id = :target_id', ['target_id' => $regId]);
    $registration['participant_type'] = 'non_presenter';
}

$values = [
    'presenter_shirt' => (string) ($registration['presenter_shirt'] ?? ''),
    'attendees'       => array_map(static fn (array $a): array => [
        'name'        => (string) $a['name'],
        'affiliation' => (string) ($a['affiliation'] ?? ''),
        'email'       => (string) ($a['email'] ?? ''),
        'shirt_size'  => (string) ($a['shirt_size'] ?? ''),
    ], RegistrationRepo::attendees($regId)),
    'extra'           => FormRepo::decode($registration['extra_json'] ?? null),
];

$quoteFor = static fn (array $guests): array => FeeEngine::quote($editionId, [
    'country'          => $author['country'] ?? null,
    'participant_type' => 'non_presenter',
    'total_pages'      => 0,
    'attendees'        => array_column($guests, 'name'),
]);

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
    $values['presenter_shirt'] = $shirtOn && in_array((string) input('presenter_shirt', ''), $shirtSizes, true) ? (string) input('presenter_shirt') : '';
    if ($shirtOn && $submit && $values['presenter_shirt'] === '') {
        $errors['presenter_shirt'] = 'Please choose your shirt size.';
    }

    // Orang yang diajak: selalu "orang lain" (peserta tidak punya co-author).
    $rawGuests = array_map(
        static fn ($row): array => ['source' => 'other'] + (is_array($row) ? $row : []),
        is_array($_POST['attendees'] ?? null) ? array_values($_POST['attendees']) : []
    );
    $cart = RegistrationRepo::validateAttendees($rawGuests, [$person], (int) $person['id'], $shirtSizes, $shirtOn, $submit);
    foreach ($cart['rows'] as $guest) {
        if ($guest['email'] !== null && mb_strtolower((string) $guest['email']) === mb_strtolower((string) $person['email'])) {
            $errors['attendees'] = 'You are already included; add only the other people who will come with you.';
        }
    }
    $errors += $cart['errors'];
    $values['attendees'] = array_map(static fn (array $r): array => [
        'name' => (string) $r['name'], 'affiliation' => (string) ($r['affiliation'] ?? ''),
        'email' => (string) ($r['email'] ?? ''), 'shirt_size' => (string) ($r['shirt_size'] ?? ''),
    ], $cart['rows']);

    $extraResult     = FormRepo::validate($extraFields, is_array($_POST['extra'] ?? null) ? $_POST['extra'] : []);
    $values['extra'] = $extraResult['values'];
    foreach ($extraResult['errors'] as $key => $message) {
        $errors['extra_' . $key] = $message;
    }

    $quote = $quoteFor($cart['rows']);
    if ($quote['error'] !== null) {
        $errors['fee'] = $quote['error'];
    }

    if ($errors === []) {
        Db::transaction(static function () use ($regId, $editionId, $values, $quote, $submit, $cart, $person): void {
            RegistrationRepo::save($regId, [
                'participant_type'  => 'non_presenter',
                'is_student'        => false,
                'is_ieee_member'    => false,
                'ieee_number'       => '',
                'extra_papers'      => 0,
                'package_id'        => null,
                'extra'             => $values['extra'],
                'presenter_id'      => (int) $person['id'],
                'presentation_mode' => 'offline',
                'presenter_shirt'   => $values['presenter_shirt'],
                'main_author_id'    => null,
            ], $quote, $submit);
            RegistrationRepo::saveAttendees($editionId, $regId, $cart['rows']);
        });
        mark_filled_by('registrations', $regId);

        if ($submit) {
            Audit::log('registration.submitted', [
                'actor_type'  => 'participant',
                'actor_id'    => (string) AuthorAuth::email(),
                'target_type' => 'registration',
                'target_id'   => $regId,
                'meta'        => ['total' => $quote['total'], 'currency' => $quote['currency'], 'attendees' => count($cart['rows'])],
            ]);

            Mailer::send(
                (string) $person['email'],
                (string) $person['name'],
                'Registration received — ' . ($edition['name'] ?? 'CENIM') . ' (participant ' . $author['paper_id'] . ')',
                'registration_submitted',
                [
                    'paperId'       => (string) $author['paper_id'],
                    'paperTitle'    => 'Participant (non-presenter)',
                    'categoryLabel' => (string) ($quote['category']['label'] ?? 'Non-presenter')
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

$quote    = $quoteFor($values['attendees']);
$category = FeeEngine::pickCategory($editionId, $currency, 'non_presenter', false, false);

View::render('author/registration_participant', [
    'title'        => 'Registration form',
    'edition'      => $edition,
    'author'       => $author,
    'person'       => $person,
    'registration' => $registration,
    'editable'     => $editable,
    'isOpen'       => $isOpen,
    'values'       => $values,
    'errors'       => $errors,
    'quote'        => $quote,
    'extraFields'  => $extraFields,
    'shirtOn'      => $shirtOn,
    'shirtSizes'   => $shirtSizes,
    'feeData'      => [
        'currency'    => $currency,
        'baseLabel'   => (string) ($category['label'] ?? 'Registration fee (non-presenter)'),
        'base'        => $category !== null ? (float) $category['amount'] : null,
        'attendeeFee' => FeeEngine::attendeeFee($editionId, $currency),
    ],
    'maxAttendees' => RegistrationRepo::MAX_ATTENDEES,
], 'author');
