<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * Autentikasi admin: hanya satu "key" yang tersimpan sebagai hash di
 * .env (ADMIN_KEY_HASH). Tidak ada tabel user.
 *
 * Pengamanan: Turnstile, rate limit per IP, session di-regenerate saat login,
 * session diikat ke user-agent, plus batas usia session absolut.
 */
final class AdminAuth
{
    public const KIND = 'admin';

    private const OK       = 'admin_ok';
    private const FP       = 'admin_fp';
    private const AT       = 'admin_login_at';
    private const INTENDED = 'admin_intended';

    /** Edisi arsip yang sedang dibuka admin (pemilih edisi di header). */
    public const VIEW_EDITION = 'admin_view_edition';

    public static function isLoggedIn(): bool
    {
        if (Session::get(self::OK) !== true) {
            return false;
        }

        if (Session::get(self::FP) !== self::fingerprint()) {
            self::clearSession();
            Audit::log('admin.session_rejected', ['actor_type' => 'system', 'meta' => ['reason' => 'fingerprint mismatch']]);
            return false;
        }

        $loginAt = (string) Session::get(self::AT, '');
        $maxAge  = max(1, (int) cfg('session.admin_max_hours', 12)) * 3600;

        if ($loginAt !== '' && (strtotime($loginAt . ' UTC') + $maxAge) < time()) {
            self::clearSession();
            Session::flash('warning', 'Your admin session has expired. Please sign in again.');
            return false;
        }

        return true;
    }

    /**
     * Coba login. Mengembalikan null bila berhasil, atau pesan error.
     */
    public static function attempt(string $key): ?string
    {
        $retryAfter = RateLimit::retryAfter(self::KIND, self::KIND);
        if ($retryAfter > 0) {
            return 'Too many failed attempts. Please try again in ' . RateLimit::humanRetryAfter($retryAfter) . '.';
        }

        if ($turnstileError = Turnstile::check()) {
            return $turnstileError;
        }

        if ($key === '') {
            return 'Please enter the admin key.';
        }

        $hash = (string) env('ADMIN_KEY_HASH', '');
        if ($hash === '' || !str_starts_with($hash, '$2')) {
            Logger::error('AdminAuth: ADMIN_KEY_HASH belum dikonfigurasi dengan benar');
            return 'Admin access is not configured on this server.';
        }

        if (!password_verify($key, $hash)) {
            RateLimit::record(self::KIND, self::KIND, false);
            Audit::log('admin.login_failed', ['actor_type' => 'system']);

            $left = RateLimit::remaining(self::KIND, self::KIND);
            return $left > 0
                ? 'Invalid admin key. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left before temporary lockout.'
                : 'Invalid admin key. Too many attempts — access temporarily locked.';
        }

        Session::regenerate();
        Session::put(self::OK, true);
        Session::put(self::FP, self::fingerprint());
        Session::put(self::AT, now());

        RateLimit::record(self::KIND, self::KIND, true);
        RateLimit::clear(self::KIND, self::KIND);
        Audit::log('admin.login', ['actor_type' => 'admin', 'actor_id' => 'admin']);

        return null;
    }

    /** Guard halaman admin. Menghentikan eksekusi bila belum login. */
    public static function requireLogin(): void
    {
        if (self::isLoggedIn()) {
            return;
        }

        if (is_ajax()) {
            Response::json(['ok' => false, 'error' => 'Session expired. Please sign in again.'], 401);
        }

        if (!is_post()) {
            self::rememberIntended();
        }

        Response::redirect(admin_url('login.php'));
    }

    public static function logout(): void
    {
        if (Session::get(self::OK) === true) {
            Audit::log('admin.logout', ['actor_type' => 'admin', 'actor_id' => 'admin']);
        }
        self::clearSession();
        Session::destroy();
    }

    public static function loginAt(): ?string
    {
        $at = (string) Session::get(self::AT, '');
        return $at === '' ? null : $at;
    }

    /** Ambil dan hapus URL tujuan sebelum diarahkan ke halaman login. */
    public static function takeIntended(): string
    {
        $url = (string) Session::get(self::INTENDED, '');
        Session::forget(self::INTENDED);

        $prefix = admin_url();
        return ($url !== '' && str_starts_with($url, $prefix)) ? $url : admin_url('index.php');
    }

    private static function rememberIntended(): void
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && str_starts_with($uri, admin_url()) && !str_contains($uri, 'login.php')) {
            Session::put(self::INTENDED, $uri);
        }
    }

    private static function clearSession(): void
    {
        Session::forget(self::OK);
        Session::forget(self::FP);
        Session::forget(self::AT);
    }

    /**
     * Pengikat session. Sengaja hanya user-agent, bukan IP, karena IP pengguna
     * mobile/roaming berubah-ubah dan akan memutus session yang sah.
     */
    private static function fingerprint(): string
    {
        return hash('sha256', 'admin|' . user_agent());
    }

    /** Jadikan edisi yang dipilih admin sebagai edisi konteks (halaman admin & API). */
    public static function applyEditionView(): void
    {
        $id = (int) Session::get(self::VIEW_EDITION, 0);
        if ($id <= 0) {
            return;
        }

        $edition = $id !== \Cenim\Domain\EditionRepo::activeId() ? \Cenim\Domain\EditionRepo::find($id) : null;
        if ($edition === null) {
            Session::forget(self::VIEW_EDITION);
            return;
        }

        \Cenim\Domain\EditionRepo::setContext($edition);
    }
}
