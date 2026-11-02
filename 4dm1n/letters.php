<?php
/**
 * Letter Generate: daftar surat edisi ini (buat, ubah, hapus, buka PDF).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\LetterRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if (is_post() && input('action') === 'delete') {
    Csrf::verify();
    $letter = LetterRepo::find(input_int('id'));
    if ($letter !== null && (int) $letter['edition_id'] === $editionId) {
        LetterRepo::delete((int) $letter['id']);
        Audit::log('letter.deleted', ['target_type' => 'letter', 'target_id' => (int) $letter['id'], 'meta' => ['title' => $letter['title'], 'number' => $letter['number']]]);
        Session::flash('success', 'Letter "' . $letter['title'] . '" deleted.');
    }
    Response::redirect(admin_url('letters.php'));
}

$letters = LetterRepo::all($editionId);

View::render('admin/letters', [
    'title'    => 'Letters',
    'subtitle' => ($edition['name'] ?? '') . ' — ' . count($letters) . ' letter(s)',
    'active'   => 'letters',
    'edition'  => $edition,
    'letters'  => $letters,
    'assets'   => ['datatables'],
], 'admin');
