<?php
/**
 * Login author & panitia/reviewer — email lalu kode OTP 6 digit.
 * Halaman ini sengaja terpisah total dari login admin.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AuthorAuth;
use Cenim\Core\Csrf;
use Cenim\Core\LoginCode;
use Cenim\Core\RateLimit;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\Turnstile;
use Cenim\Core\View;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\SettingRepo;

panel_boot('author');

if (AuthorAuth::isLoggedIn()) {
    Response::redirect(AuthorAuth::takeIntended());
}

$edition = EditionRepo::active();
$error   = null;
$action  = (string) input('action', '');

if (input('signed_out') !== null) {
    Session::flash('success', 'You have been signed out.');
}

if (is_post()) {
    Csrf::verify();

    if ($action === 'send') {
        $error = AuthorAuth::requestCode((string) input('email', ''));
    } elseif ($action === 'resend') {
        $pending = AuthorAuth::pendingEmail();
        $error   = $pending === '' ? 'Please enter your email first.' : AuthorAuth::requestCode($pending, true);
        if ($error === null) {
            Session::flash('success', 'A new code has been sent to ' . $pending . '.');
            Response::redirect(author_url('login.php'));
        }
    } elseif ($action === 'change') {
        AuthorAuth::cancelPending();
        Response::redirect(author_url('login.php'));
    } elseif ($action === 'verify') {
        $error = AuthorAuth::verifyCode((string) input('code', ''));
        if ($error === null) {
            Session::flash('success', 'Welcome. You are signed in.');
            Response::redirect(AuthorAuth::takeIntended());
        }
    }
}

$pending = AuthorAuth::pendingEmail();

View::render('author/login', [
    'title'       => 'Portal',
    'error'       => $error,
    'step'        => $pending !== '' ? 'code' : 'email',
    'email'       => $pending !== '' ? $pending : (string) input('email', ''),
    'edition'     => $edition,
    'locked'      => $pending !== '' ? RateLimit::retryAfter(AuthorAuth::KIND, $pending) : 0,
    'resendIn'    => $pending !== '' ? min(LoginCode::waitSeconds($pending), LoginCode::RESEND_SECONDS) : 0,
    'ttl'         => LoginCode::TTL_MINUTES,
    'showCaptcha' => Turnstile::enabled(),
    'contact'     => $edition !== null ? SettingRepo::getString((int) $edition['id'], 'contact_email') : '',
    'guideUrl'    => user_guide_url((int) ($edition['id'] ?? 0)),
], 'auth');
