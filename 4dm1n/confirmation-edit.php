<?php
/**
 * Admin mengisi / mengubah Confirmation form (form 0) suatu paper atas nama
 * author — tanpa batas deadline dan walau registrasinya sudah dikirim
 * (registrasi yang sudah ada tidak ikut berubah). Memakai tampilan form author.
 * ?id=<authors.id>
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\ConfirmationRepo;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\RegistrationRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$paper     = AuthorRepo::find(input_int('id'));

if ($paper === null || (int) $paper['edition_id'] !== $editionId || (string) $paper['paper_status'] !== 'accepted') {
    Response::abort(404, 'Accepted paper not found.');
}

$back         = admin_url('confirmations.php');
$confirmation = ConfirmationRepo::findForPaper((int) $paper['id']);
$registration = RegistrationRepo::findByAuthor((int) $paper['id']);
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

    $check  = ConfirmationRepo::validate($_POST, $shirtSizes);
    $errors = $check['errors'];
    $values = [
        'attendees' => max(1, (int) ($_POST['attendees'] ?? 1)),
        'shirts'    => array_map('strval', is_array($_POST['shirts'] ?? null) ? array_values($_POST['shirts']) : []),
    ] + $check['values'];

    if ($errors === []) {
        $first = $confirmation === null;
        $id    = ConfirmationRepo::save($editionId, $paper, $check['values'], ['name' => 'Secretariat (admin)']);

        Audit::log($first ? 'confirmation.submitted' : 'confirmation.updated', [
            'target_type' => 'confirmation',
            'target_id'   => $id,
            'meta'        => [
                'paper_id'  => $paper['paper_id'],
                'mode'      => $check['values']['presentation_mode'],
                'attendees' => $check['values']['attendees'],
                'by_admin'  => true,
            ],
        ]);

        Session::flash('success', 'Confirmation of ' . $paper['paper_id'] . ($first ? ' recorded.' : ' updated.'));
        Response::redirect($back);
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' item(s) below.');
}

View::render('author/confirmation', [
    'title'        => 'Edit confirmation',
    'subtitle'     => $paper['paper_id'] . ' — ' . $paper['first_author_name'],
    'active'       => 'confirmations',
    'edition'      => $edition,
    'author'       => $paper,
    'confirmation' => $confirmation,
    'registration' => $registration,
    'paperAuthors' => PaperAuthorRepo::forPaper((int) $paper['id']),
    'editable'     => true,
    'isOpen'       => true,
    'deadline'     => ConfirmationRepo::deadline($editionId),
    'fee'          => $fee,
    'attendeeFee'  => FeeEngine::attendeeFee($editionId, $fee['currency']),
    'maxAttendees' => ConfirmationRepo::maxAttendees(),
    'shirtSizes'   => $shirtSizes,
    'values'       => $values,
    'errors'       => $errors,
    'adminMode'    => true,
    'backUrl'      => $back,
], 'admin');
