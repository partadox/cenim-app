<?php
/**
 * Login admin — HANYA key (disimpan sebagai hash di .env).
 * Halaman ini sengaja terpisah total dari login author.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AdminAuth;
use Cenim\Core\Csrf;
use Cenim\Core\RateLimit;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\Turnstile;
use Cenim\Core\View;

panel_boot('admin');

if (AdminAuth::isLoggedIn()) {
    Response::redirect(AdminAuth::takeIntended());
}

$error = null;

if (input('signed_out') !== null) {
    Session::flash('success', 'You have been signed out.');
}

if (is_post()) {
    Csrf::verify();

    $error = AdminAuth::attempt((string) ($_POST['admin_key'] ?? ''));

    if ($error === null) {
        Session::flash('success', 'Signed in as administrator.');
        Response::redirect(AdminAuth::takeIntended());
    }
}

View::render('admin/login', [
    'title'    => 'Admin sign in',
    'error'    => $error,
    'locked'   => RateLimit::retryAfter(AdminAuth::KIND, AdminAuth::KIND),
    'showCaptcha' => Turnstile::enabled(),
], 'auth');
