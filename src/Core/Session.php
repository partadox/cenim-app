<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * Session dengan cookie yang di-hardening + flash message + idle timeout.
 */
final class Session
{
    private const LAST_SEEN = '_last_seen';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = ($_SERVER['HTTPS'] ?? '') === 'on'
            || ($_SERVER['REQUEST_SCHEME'] ?? '') === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_name((string) cfg('session.name', 'cenim_sess'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_url(),
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_start();
        self::enforceIdleTimeout();
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    // ── Flash message (dibaca sekali lalu hilang) ───────────────────────────

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        self::start();
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    /**
     * Simpan input form + error agar bisa ditampilkan kembali setelah redirect.
     */
    public static function putOld(array $input, array $errors = []): void
    {
        self::start();
        unset($input['csrf_token'], $input['cf-turnstile-response']);
        $_SESSION['_old']    = $input;
        $_SESSION['_errors'] = $errors;
    }

    public static function takeOld(): array
    {
        self::start();
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return $old;
    }

    public static function takeErrors(): array
    {
        self::start();
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $errors;
    }

    private static function enforceIdleTimeout(): void
    {
        $timeout = (int) cfg('session.idle_timeout', 7200);
        $last    = (int) ($_SESSION[self::LAST_SEEN] ?? 0);

        if ($last > 0 && $timeout > 0 && (time() - $last) > $timeout) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_flash'][] = ['type' => 'warning', 'message' => 'Your session expired. Please sign in again.'];
        }

        $_SESSION[self::LAST_SEEN] = time();
    }
}
