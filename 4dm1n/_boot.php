<?php
/**
 * Bootstrap bersama semua halaman admin (kecuali login.php, logout.php,
 * dan health.php yang mengatur aksesnya sendiri). Selalu di-require paling atas.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AdminAuth;
use Cenim\Domain\EditionRepo;

panel_boot('admin');

AdminAuth::requireLogin();

// ── Melihat arsip edisi lain (pemilih edisi di header) ──────────────────────
// Data edisi lama tidak pernah hilang; admin bisa membukanya kapan saja.
// Semua halaman admin lalu bekerja pada edisi yang dipilih.
if (is_post() && input('action') === 'view_edition') {
    Cenim\Core\Csrf::verify();
    $target = input_int('edition_view');
    if ($target > 0 && $target !== EditionRepo::activeId() && EditionRepo::find($target) !== null) {
        Cenim\Core\Session::put(AdminAuth::VIEW_EDITION, $target);
    } else {
        Cenim\Core\Session::forget(AdminAuth::VIEW_EDITION);
    }
    Cenim\Core\Response::redirect(admin_url('index.php'));
}

AdminAuth::applyEditionView();

/** Edisi yang sedang dikelola: edisi aktif, atau arsip yang dipilih di header. */
function current_edition(): ?array
{
    return EditionRepo::context();
}
