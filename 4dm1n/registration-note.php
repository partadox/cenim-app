<?php
/**
 * Simpan keterangan internal admin untuk satu registrasi (kolom "Note" di
 * daftar Registrations). POST + CSRF, dibalas JSON. Kosong = hapus keterangan.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Domain\RegistrationRepo;

if (!is_post()) {
    Response::redirect(admin_url('registrations.php'));
}

Csrf::verify();

$editionId    = (int) (current_edition()['id'] ?? 0);
$registration = RegistrationRepo::find(input_int('id'));

if ($registration === null || (int) $registration['edition_id'] !== $editionId) {
    Response::json(['ok' => false, 'error' => 'Registration not found in the active edition.'], 404);
}

$note = trim((string) input('note', ''));
if (mb_strlen($note) > 1000) {
    Response::json(['ok' => false, 'error' => 'The note must not exceed 1000 characters.'], 422);
}

Db::update('registrations', [
    'admin_note'    => $note !== '' ? $note : null,
    'admin_note_at' => $note !== '' ? now() : null,
], 'id = :target_id', ['target_id' => (int) $registration['id']]);

Audit::log('registration.note_updated', [
    'target_type' => 'registration',
    'target_id'   => (int) $registration['id'],
    'meta'        => ['note' => $note],
]);

Response::json(['ok' => true, 'message' => $note !== '' ? 'Note saved.' : 'Note removed.']);
