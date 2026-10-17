<?php
/**
 * Form 0 — Confirmation form: rencana presentasi offline/online, perkiraan
 * jumlah orang yang datang, jumlah halaman manuskrip review beserta biaya
 * halaman tambahan, ukuran baju (opsional, Settings), dan catatan.
 * Registration form terbuka setelah form ini dikirim. Bisa diubah selama dibuka (Settings) dan registrasi belum dikirim.
 * Semua author paper boleh mengisi.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\AuthorAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\ConfirmationRepo;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\LoaRepo;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\RegistrationRepo;

$edition   = current_edition();
$paper     = current_author();
$editionId = (int) ($edition['id'] ?? 0);

if ($paper === null || $editionId === 0) {
    Response::abort(503, 'The confirmation form is not available right now.');
}

if (!LoaRepo::isAvailable($paper)) {
    Session::flash('info', 'The confirmation form is available once the paper is accepted.');
    Response::redirect(author_url('index.php'));
}

$confirmation = ConfirmationRepo::findForPaper((int) $paper['id']);
$registration = RegistrationRepo::findByAuthor((int) $paper['id']);
$editable     = ConfirmationRepo::isEditable($editionId, $registration) && !edition_is_archive();
$fee          = ConfirmationRepo::pageFee($editionId, $paper);
$shirtOn      = RegistrationRepo::shirtEnabled($editionId);
$shirtSizes   = $shirtOn ? RegistrationRepo::shirtSizes($editionId) : [];
$errors       = [];

$values = [
    'presentation_mode' => (string) ($confirmation['presentation_mode'] ?? ''),
    'attendees'         => (int) ($confirmation['attendees'] ?? 0) ?: 1,
    'note'              => (string) ($confirmation['note'] ?? ''),
    'shirts'            => ConfirmationRepo::shirts($confirmation),
];

if (is_post()) {
    Csrf::verify();

    if (!$editable) {
        Session::flash('error', $registration !== null && (string) $registration['status'] !== RegistrationRepo::STATUS_DRAFT
            ? 'The registration has been submitted, so the confirmation can no longer be changed.'
            : 'The confirmation form is closed. Please contact the secretariat.');
        Response::redirect(author_url('confirmation.php'));
    }

    $check  = ConfirmationRepo::validate($_POST, $shirtSizes);
    $errors = $check['errors'];
    // Jumlah orang tetap ditampilkan seperti diisi, walau mode online (disimpan 0).
    $values = [
        'attendees' => max(1, (int) ($_POST['attendees'] ?? 1)),
        'shirts'    => array_map('strval', is_array($_POST['shirts'] ?? null) ? array_values($_POST['shirts']) : []),
    ] + $check['values'];

    if ($errors === []) {
        $first = $confirmation === null;
        $id    = ConfirmationRepo::save($editionId, $paper, $check['values'], current_person());

        Audit::log($first ? 'confirmation.submitted' : 'confirmation.updated', [
            'actor_type'  => 'author',
            'actor_id'    => (string) AuthorAuth::email(),
            'target_type' => 'confirmation',
            'target_id'   => $id,
            'meta'        => [
                'paper_id'  => $paper['paper_id'],
                'mode'      => $check['values']['presentation_mode'],
                'attendees' => $check['values']['attendees'],
            ],
        ]);

        Session::flash('success', $first
            ? 'Thank you — your confirmation has been recorded. The registration form is now open.'
            : 'Your confirmation has been updated.');
        Response::redirect(author_url('index.php'));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' item(s) below.');
}

View::render('author/confirmation', [
    'title'        => 'Confirmation form',
    'edition'      => $edition,
    'author'       => $paper,
    'confirmation' => $confirmation,
    'registration' => $registration,
    'paperAuthors' => PaperAuthorRepo::forPaper((int) $paper['id']),
    'editable'     => $editable,
    'isOpen'       => ConfirmationRepo::isOpen($editionId),
    'deadline'     => ConfirmationRepo::deadline($editionId),
    'fee'          => $fee,
    'attendeeFee'  => FeeEngine::attendeeFee($editionId, $fee['currency']),
    'maxAttendees' => ConfirmationRepo::maxAttendees(),
    'shirtSizes'   => $shirtSizes,
    'values'       => $values,
    'errors'       => $errors,
], 'author');
