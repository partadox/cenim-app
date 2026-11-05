<?php
/**
 * Panitia mengisi / mengubah Registration form suatu paper atas nama author
 * (paper yang belum mengisi, draft, atau koreksi). Memakai inti form author
 * (author/_registration_form.php) dengan $adminMode = true.
 * ?author=<authors.id>  atau  ?id=<registrations.id>
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\ParticipantRepo;
use Cenim\Domain\RegistrationRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$authorId = input_int('author');
if ($authorId === 0 && input_int('id') > 0) {
    $authorId = (int) (RegistrationRepo::find(input_int('id'))['author_id'] ?? 0);
}
$author = $authorId > 0 ? AuthorRepo::find($authorId) : null;

if ($author === null || (int) $author['edition_id'] !== $editionId) {
    Response::abort(404, 'Paper not found in the active edition.');
}
if (ParticipantRepo::isParticipant($author)) {
    Session::flash('info', 'Participant (non-presenter) registrations are changed from the Non-presenters page.');
    Response::redirect(admin_url('participants.php'));
}
if ((string) $author['paper_status'] !== 'accepted') {
    Session::flash('error', 'Only accepted papers can be registered (this paper is ' . $author['paper_status'] . ').');
    Response::redirect(admin_url('registrations.php'));
}

$existing  = RegistrationRepo::findByAuthor((int) $author['id']);
$adminMode = true;
$selfUrl   = admin_url('registration-edit.php?author=' . (int) $author['id']);
$backUrl   = $existing !== null ? admin_url('registration-view.php?id=' . (int) $existing['id']) : admin_url('registrations.php');

require dirname(__DIR__) . '/author/_registration_form.php';
