<?php
/**
 * Workshop: kelola daftar workshop (judul, pembicara, jadwal, kuota, aktif),
 * buka/tutup pendaftaran, dan daftar pendaftar per workshop (+ Excel).
 * Pendaftaran dilakukan author/peserta di Portal → Workshop Registration.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\CertificateRepo;
use Cenim\Domain\CertificateTemplateRepo;
use Cenim\Domain\ParticipantRepo;
use Cenim\Domain\SettingRepo;
use Cenim\Domain\WorkshopRepo;
use Cenim\Support\Xlsx;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0) {
    Session::flash('error', 'No active edition.');
    Response::redirect(admin_url('index.php'));
}

$filter  = input_int('workshop') ?: null;
$editId  = input_int('edit');
$editing = $editId > 0 ? WorkshopRepo::find($editId) : null;
if ($editing !== null && (int) $editing['edition_id'] !== $editionId) {
    Response::abort(404, 'Workshop not found.');
}

if (is_post()) {
    Csrf::verify();
    $action = (string) input('action', '');

    if ($action === 'settings') {
        SettingRepo::set($editionId, 'workshop_enabled', input_bool('workshop_enabled'));
        SettingRepo::set($editionId, 'workshop_open', input_bool('workshop_open'));
        CertificateTemplateRepo::setActive($editionId, CertificateRepo::TYPE_WORKSHOP, input_bool('workshop_certificates'));
        SettingRepo::set($editionId, 'workshop_requires_payment', input_bool('workshop_requires_payment'));
        Session::flash('success', 'Workshop registration settings saved.');
    } elseif ($action === 'save') {
        $id   = input_int('id') ?: null;
        $data = [
            'title'       => (string) input('title', ''),
            'speaker'     => (string) input('speaker', ''),
            'description' => (string) input('description', ''),
            'schedule'    => (string) input('schedule', ''),
            'quota'       => input_int('quota'),
            'sort'        => input_int('sort'),
            'is_active'   => input_bool('is_active'),
        ];
        if (trim($data['title']) === '') {
            Session::flash('error', 'The workshop title is required.');
            Response::redirect(admin_url('workshops.php' . ($id !== null ? '?edit=' . $id : '')));
        }
        if ($id !== null && (($w = WorkshopRepo::find($id)) === null || (int) $w['edition_id'] !== $editionId)) {
            Response::abort(404, 'Workshop not found.');
        }
        $saved = WorkshopRepo::save($editionId, $id, $data);
        Audit::log($id !== null ? 'workshop.updated' : 'workshop.created', ['target_type' => 'workshop', 'target_id' => $saved]);
        Session::flash('success', 'Workshop "' . trim($data['title']) . '" saved.');
    } elseif ($action === 'delete') {
        $w = WorkshopRepo::find(input_int('id'));
        if ($w !== null && (int) $w['edition_id'] === $editionId) {
            WorkshopRepo::delete((int) $w['id']);
            Audit::log('workshop.deleted', ['target_type' => 'workshop', 'target_id' => (int) $w['id'], 'meta' => ['title' => $w['title']]]);
            Session::flash('success', 'Workshop "' . $w['title'] . '" and its registrations deleted.');
        }
    } elseif ($action === 'remove_registration') {
        WorkshopRepo::removeRegistration(input_int('id'));
        Audit::log('workshop.registration_removed', ['meta' => ['id' => input_int('id')]]);
        Session::flash('success', 'Registration removed.');
    }

    Response::redirect(admin_url('workshops.php' . ($filter !== null ? '?workshop=' . $filter : '')));
}

$registrants = WorkshopRepo::registrants($editionId, $filter);

if (input('export') !== null) {
    $rows = [];
    foreach ($registrants as $i => $r) {
        $rows[] = [
            $i + 1,
            (string) $r['workshop_title'],
            (string) $r['name'],
            (string) $r['email'],
            (string) ($r['affiliation'] ?? ''),
            ParticipantRepo::isParticipant($r) ? 'Participant (non-presenter)' : 'Author',
            (string) ($r['paper_id'] ?? ''),
            fmt_dt((string) $r['created_at']),
        ];
    }
    Xlsx::download(
        'workshop-registrations-' . ($edition['slug'] ?? 'cenim') . '-' . gmdate('Ymd') . '.xlsx',
        'Workshops',
        ['No', 'Workshop', 'Name', 'Email', 'Affiliation', 'Type', 'Paper / participant ID', 'Registered at'],
        $rows
    );
}

View::render('admin/workshops', [
    'title'       => 'Workshops',
    'subtitle'    => ($edition['name'] ?? '') . ' — ' . count(WorkshopRepo::registrants($editionId)) . ' registration(s)',
    'active'      => 'workshops',
    'edition'     => $edition,
    'workshops'   => WorkshopRepo::all($editionId),
    'registrants' => $registrants,
    'filter'      => $filter,
    'editing'     => $editing,
    'enabled'     => WorkshopRepo::enabled($editionId),
    'open'        => SettingRepo::getBool($editionId, 'workshop_open', true),
    'certOn'      => WorkshopRepo::certificatesEnabled($editionId),
    'certIssued'  => count(CertificateRepo::byType($editionId, CertificateRepo::TYPE_WORKSHOP)),
    'requiresPay' => WorkshopRepo::requiresPayment($editionId),
], 'admin');
