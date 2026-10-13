<?php
/**
 * Tambah / ubah satu anggota panitia.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\CommitteeRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$id     = input_int('id');
$member = $id > 0 ? CommitteeRepo::find($id) : null;

if ($id > 0 && ($member === null || (int) $member['edition_id'] !== $editionId)) {
    Response::abort(404, 'Committee member not found in the active edition.');
}

$values = $member ?? [
    'name'            => '',
    'email'           => '',
    'affiliation'     => '',
    'role'            => '',
    'division'        => '',
    'sort'            => 0,
    'show_on_landing' => 1,
];

$errors = [];

if (is_post()) {
    Csrf::verify();

    $input = [
        'name'            => (string) input('name', ''),
        'email'           => (string) input('email', ''),
        'affiliation'     => (string) input('affiliation', ''),
        'role'            => (string) input('role', ''),
        'division'        => (string) input('division', ''),
        'sort'            => input_int('sort'),
        'show_on_landing' => input_bool('show_on_landing'),
    ];

    $values = array_merge($values, $input);
    $errors = CommitteeRepo::validate($input);

    if ($errors === []) {
        if ($id > 0) {
            CommitteeRepo::update($id, $input);
            Audit::log('committee.updated', ['target_type' => 'committee', 'target_id' => $id, 'meta' => ['name' => $input['name']]]);
            Session::flash('success', 'Committee member updated.');
        } else {
            $newId = CommitteeRepo::create($editionId, $input);
            Audit::log('committee.created', ['target_type' => 'committee', 'target_id' => $newId, 'meta' => ['name' => $input['name']]]);
            Session::flash('success', 'Committee member added.');
        }

        Response::redirect(admin_url('committee.php'));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' field(s) below.');
}

View::render('admin/committee_edit', [
    'title'    => $id > 0 ? 'Edit committee member' : 'Add committee member',
    'subtitle' => $edition['name'] ?? '',
    'active'   => 'committee',
    'edition'  => $edition,
    'member'   => $member,
    'values'   => $values,
    'errors'   => $errors,
], 'admin');
