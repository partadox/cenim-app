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
$counts = ['accounts' => count($rows), 'submitted' => 0, 'paid' => 0, 'waived' => 0, 'people' => 0];
foreach ($rows as $row) {
    if (($row['registration_status'] ?? RegistrationRepo::STATUS_DRAFT) !== RegistrationRepo::STATUS_DRAFT) {
        $counts['submitted']++;
        $counts['people'] += 1 + (int) $row['guests'];
    }
    $pay = ParticipantRepo::payStatus($row);
    if ($pay === 'Paid') {
        $counts['paid']++;
    } elseif ($pay === 'Waived') {
        $counts['waived']++;
    }
}

// Non-presenter dari paper (dibawa author di registration form).
$guests = ParticipantRepo::paperGuests($editionId);
$counts['paper_guests'] = count($guests);
$counts['paper_guests_paid'] = count(array_filter($guests, static fn (array $g): bool => ParticipantRepo::payStatus($g) === 'Paid'));
$counts['paper_guests_waived'] = count(array_filter($guests, static fn (array $g): bool => ParticipantRepo::payStatus($g) === 'Waived'));
$counts['total'] = $counts['people'] + $counts['paper_guests'];

// ── Export Excel: ?export=all (semua) atau ?export=paid (lunas dibayar, tanpa waived) ──
$export = (string) input('export', '');
if (in_array($export, ['all', 'paid'], true)) {
    $lines = [];
    $add   = static function (array $person, string $category, string $via, array $reg) use (&$lines, $export): void {
        $pay = ParticipantRepo::payStatus($reg);
        if ($export === 'paid' && $pay !== 'Paid') {
            return;
        }
        $lines[] = [
            count($lines) + 1,
            $category,
            (string) $person['name'],
            (string) ($person['email'] ?? ''),
            (string) ($person['affiliation'] ?? ''),
            (string) ($person['country'] ?? ''),
            $via,
            $reg['registration_status'] !== null ? ucfirst((string) $reg['registration_status']) : 'Not started',
            (string) ($reg['invoice_number'] ?? ''),
            $pay !== '' ? $pay : '-',
        ];
    };
    foreach ($rows as $row) {
        $add($row, 'Self-registered participant', (string) $row['paper_id'], $row);
        if ($row['registration_id'] !== null) {
            foreach (RegistrationRepo::attendees((int) $row['registration_id']) as $guest) {
                $add($guest + ['country' => $row['country']], 'Guest of a participant', (string) $row['paper_id'], $row);
            }
        }
    }
    foreach ($guests as $guest) {
        $add($guest, 'Brought by an author', 'Paper ' . $guest['paper_id'], $guest);
    }

    Cenim\Support\Xlsx::download(
        'non-presenters-' . ($edition['slug'] ?? 'cenim') . '-' . ($export === 'paid' ? 'paid-' : '') . gmdate('Ymd') . '.xlsx',
        'Non-presenters',
        ['No', 'Category', 'Name', 'Email', 'Affiliation', 'Country', 'Registered via', 'Registration', 'Invoice', 'Payment'],
        $lines
    );
}

View::render('admin/participants', [
    'title'     => 'Participants (non-presenter)',
    'subtitle'  => ($edition['name'] ?? '') . ' — ' . count($rows) . ' registered',
    'active'    => 'participants',
    'edition'   => $edition,
    'rows'      => $rows,
    'guests'    => $guests,
    'assets'    => ['datatables'],
    'counts'    => $counts,
    'editing'   => $editing,
    'values'    => $values,
    'errors'    => $errors,
    'countries' => Countries::all(),
    'selfOpen'  => ParticipantRepo::registrationOpen($editionId),
], 'admin');
