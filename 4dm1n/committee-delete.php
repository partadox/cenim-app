<?php
/**
 * Hapus anggota panitia. Ditolak bila sertifikatnya sudah diterbitkan.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\CommitteeRepo;

if (!is_post()) {
    Response::redirect(admin_url('committee.php'));
}

Csrf::verify();

$editionId = (int) (current_edition()['id'] ?? 0);
$id        = input_int('id');
$member    = $id > 0 ? CommitteeRepo::find($id) : null;

$fail = static function (string $message): never {
    if (is_ajax()) {
        Response::json(['ok' => false, 'error' => $message], 422);
    }
    Session::flash('error', $message);
    Response::redirect(admin_url('committee.php'));
};

if ($member === null || (int) $member['edition_id'] !== $editionId) {
    $fail('Committee member not found in the active edition.');
}

if (CommitteeRepo::hasCertificate($id)) {
    $fail('A certificate has already been issued for this member. Revoke it first if this entry is wrong.');
}

CommitteeRepo::delete($id);

Audit::log('committee.deleted', [
    'target_type' => 'committee',
    'target_id'   => $id,
    'meta'        => ['name' => $member['name']],
]);

$message = 'Committee member "' . $member['name'] . '" deleted.';

if (is_ajax()) {
    Response::json(['ok' => true, 'message' => $message]);
}

Session::flash('success', $message);
Response::redirect(admin_url('committee.php'));
