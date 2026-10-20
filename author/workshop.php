<?php
/**
 * Workshop Registration: orang yang login memilih workshop yang diikuti
 * (boleh lebih dari satu, kuota per workshop). Untuk author paper accepted dan
 * peserta non-presenter; terbuka setelah pembayaran lunas bila Settings →
 * workshop_requires_payment aktif. Pilihan bisa diubah selama form dibuka.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\AuthorAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\LoaRepo;
use Cenim\Domain\ParticipantRepo;
use Cenim\Domain\WorkshopRepo;

$edition   = current_edition();
$paper     = current_author();
$person    = current_person();
$editionId = (int) ($edition['id'] ?? 0);

if ($paper === null || $person === null || $editionId === 0) {
    Response::abort(503, 'Workshop registration is not available right now.');
}
if (!LoaRepo::isAvailable($paper) && !ParticipantRepo::isParticipant($paper)) {
    Session::flash('info', 'Workshop registration is available for accepted papers and registered participants.');
    Response::redirect(author_url('index.php'));
}
if (!WorkshopRepo::enabled($editionId)) {
    Session::flash('info', 'There is no workshop in this edition.');
    Response::redirect(author_url('index.php'));
}
if (($person['email'] ?? '') === '') {
    Response::abort(403, 'Your author record has no email address. Please contact the secretariat.');
}

$paid      = WorkshopRepo::paymentDone(array_map(static fn (array $p): int => (int) $p['id'], AuthorAuth::papers()));
$needsPay  = WorkshopRepo::requiresPayment($editionId) && !$paid;
$open      = WorkshopRepo::isOpen($editionId) && !edition_is_archive();
$editable  = $open && !$needsPay;
$workshops = WorkshopRepo::all($editionId, true);
$joined    = WorkshopRepo::joinedIds($editionId, (string) $person['email']);

if (is_post()) {
    Csrf::verify();

    if (!$editable) {
        Session::flash('error', $needsPay
            ? 'Workshop registration opens once your registration payment is confirmed.'
            : 'Workshop registration is closed. Please contact the secretariat.');
        Response::redirect(author_url('workshop.php'));
    }

    $chosen = array_map('intval', is_array($_POST['workshops'] ?? null) ? $_POST['workshops'] : []);
    $result = WorkshopRepo::saveChoice($editionId, $person, $paper, $chosen);

    Audit::log('workshop.registration', [
        'actor_type' => ParticipantRepo::isParticipant($paper) ? 'participant' : 'author',
        'actor_id'   => (string) AuthorAuth::email(),
        'meta'       => ['workshops' => $chosen, 'added' => $result['added'], 'removed' => $result['removed'], 'full' => $result['full']],
    ]);

    if ($result['full'] !== []) {
        Session::flash('error', 'Sorry, these workshops are already full: ' . implode(', ', $result['full']) . '.');
    } else {
        Session::flash('success', WorkshopRepo::joinedIds($editionId, (string) $person['email']) === []
            ? 'You are not registered for any workshop.'
            : 'Your workshop registration has been saved.');
    }
    Response::redirect(author_url($result['full'] !== [] ? 'workshop.php' : 'index.php'));
}

View::render('author/workshop', [
    'title'     => 'Workshop registration',
    'edition'   => $edition,
    'author'    => $paper,
    'person'    => $person,
    'workshops' => $workshops,
    'joined'    => $joined,
    'editable'  => $editable,
    'open'      => $open,
    'needsPay'  => $needsPay,
], 'author');
