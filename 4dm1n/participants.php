<?php
/**
 * Peserta non-presenter (mendaftar sendiri lewat Portal → Register, atau
 * ditambahkan admin): daftar beserta status registrasi & pembayaran, tambah,
 * ubah, hapus. Registrasi, invoice, dan pembayarannya diproses di menu
 * Registrations / Invoices / Payments seperti paper.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\ParticipantRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Support\Countries;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0) {
    Session::flash('error', 'No active edition.');
    Response::redirect(admin_url('index.php'));
}

$editId  = input_int('edit');
$editing = $editId > 0 ? ParticipantRepo::find($editId) : null;
if ($editId > 0 && ($editing === null || (int) $editing['edition_id'] !== $editionId)) {
    Response::abort(404, 'Participant not found.');
}

$values = $editing !== null
    ? ['name' => (string) $editing['first_author_name'], 'email' => (string) $editing['email'],
       'affiliation' => (string) ($editing['affiliation'] ?? ''), 'country' => (string) ($editing['country'] ?? ''),
       'region' => strcasecmp((string) ($editing['country'] ?? ''), 'Indonesia') === 0 ? 'indonesia' : 'international']
    : ['name' => '', 'email' => '', 'affiliation' => '', 'region' => 'indonesia', 'country' => 'Indonesia'];
$errors = [];

if (is_post()) {
    Csrf::verify();
    $action = (string) input('action', '');

    if ($action === 'delete') {
        $target = ParticipantRepo::find(input_int('id'));
        if ($target !== null && (int) $target['edition_id'] === $editionId) {
            $dependents = Cenim\Domain\AuthorRepo::dependents((int) $target['id']);
            ParticipantRepo::delete((int) $target['id']);
            Audit::log('participant.deleted', ['target_type' => 'author', 'target_id' => (int) $target['id'], 'meta' => ['email' => $target['email'], 'with_data' => $dependents]]);
            Session::flash('success', 'Participant ' . $target['paper_id'] . ' deleted' . ($dependents['registration'] !== null ? ' together with the registration, invoice, payment, receipt and certificates.' : '.'));
        }
        Response::redirect(admin_url('participants.php'));
    }

    $values = [
        'name'        => trim((string) input('name', '')),
        'email'       => mb_strtolower(trim((string) input('email', ''))),
        'affiliation' => trim((string) input('affiliation', '')),
        'region'      => (string) input('region', ''),
        'country'     => ParticipantRepo::countryFromInput((string) input('region', ''), (string) input('country', '')),
    ];
    $errors = ParticipantRepo::validate($values, $editionId, (int) ($editing['id'] ?? 0));

    if ($errors === []) {
        if ($editing !== null) {
            ParticipantRepo::update((int) $editing['id'], $values);
            Audit::log('participant.updated', ['target_type' => 'author', 'target_id' => (int) $editing['id']]);
            Session::flash('success', 'Participant ' . $editing['paper_id'] . ' updated.');
        } else {
            $id = ParticipantRepo::create($editionId, $values, 'admin');
            Audit::log('participant.created', ['target_type' => 'author', 'target_id' => $id, 'meta' => ['email' => $values['email']]]);
            Session::flash('success', 'Participant added. They can sign in to the portal with ' . $values['email'] . '.');
        }
        Response::redirect(admin_url('participants.php'));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' field(s).');
}

$rows   = ParticipantRepo::all($editionId);
$counts = ['accounts' => count($rows), 'submitted' => 0, 'paid' => 0, 'people' => 0];
foreach ($rows as $row) {
    if (($row['registration_status'] ?? RegistrationRepo::STATUS_DRAFT) !== RegistrationRepo::STATUS_DRAFT) {
        $counts['submitted']++;
        $counts['people'] += 1 + (int) $row['guests'];
    }
    if (($row['invoice_status'] ?? '') === 'paid') {
        $counts['paid']++;
    }
}

View::render('admin/participants', [
    'title'     => 'Participants (non-presenter)',
    'subtitle'  => ($edition['name'] ?? '') . ' — ' . count($rows) . ' registered',
    'active'    => 'participants',
    'edition'   => $edition,
    'rows'      => $rows,
    'counts'    => $counts,
    'editing'   => $editing,
    'values'    => $values,
    'errors'    => $errors,
    'countries' => Countries::all(),
    'selfOpen'  => ParticipantRepo::registrationOpen($editionId),
], 'admin');
