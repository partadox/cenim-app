<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * Pembatas percobaan login berbasis tabel login_attempts.
 *
 * Dua lapis:
 *   1. per (kind, identifier, ip) — max_attempts kegagalan dalam window
 *   2. per (kind, ip)             — max_attempts × 3, mencegah penyerang
 *                                   memutar-mutar identifier dari satu IP
 */
final class RateLimit
{
    public static function tooManyAttempts(string $kind, string $identifier): bool
    {
        return self::retryAfter($kind, $identifier) > 0;
    }

    /** Sisa detik lockout; 0 berarti boleh mencoba. */
    public static function retryAfter(string $kind, string $identifier): int
    {
        if (!Db::tableExists('login_attempts')) {
            return 0;
        }

        $max     = max(1, (int) cfg('rate_limit.max_attempts', 5));
        $lockout = max(1, (int) cfg('rate_limit.lockout_minutes', 15));
        $since   = self::since($lockout);
        $ip      = client_ip();

        $byIdentifier = (int) Db::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE kind = ? AND identifier = ? AND ip = ? AND success = 0 AND created_at >= ?',
            [$kind, self::normalize($identifier), $ip, $since]
        );

        $byIp = (int) Db::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE kind = ? AND ip = ? AND success = 0 AND created_at >= ?',
            [$kind, $ip, $since]
        );

        if ($byIdentifier < $max && $byIp < $max * 3) {
            return 0;
        }

        $last = (string) Db::value(
            'SELECT MAX(created_at) FROM login_attempts
             WHERE kind = ? AND ip = ? AND success = 0 AND created_at >= ?',
            [$kind, $ip, $since]
        );

        $unlockAt = strtotime($last . ' UTC') + $lockout * 60;

        return max(1, $unlockAt - time());
    }

    /** Sisa percobaan sebelum terkunci (untuk pesan ke pengguna). */
    public static function remaining(string $kind, string $identifier): int
    {
        if (!Db::tableExists('login_attempts')) {
            return (int) cfg('rate_limit.max_attempts', 5);
        }

        $max   = max(1, (int) cfg('rate_limit.max_attempts', 5));
        $since = self::since(max(1, (int) cfg('rate_limit.lockout_minutes', 15)));

        $failures = (int) Db::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE kind = ? AND identifier = ? AND ip = ? AND success = 0 AND created_at >= ?',
            [$kind, self::normalize($identifier), client_ip(), $since]
        );

        return max(0, $max - $failures);
    }

    public static function record(string $kind, string $identifier, bool $success): void
    {
        if (!Db::tableExists('login_attempts')) {
            return;
        }

        Db::insert('login_attempts', [
            'kind'       => $kind,
            'identifier' => self::normalize($identifier),
            'ip'         => client_ip(),
            'success'    => $success,
            'created_at' => now(),
        ]);

        // Bersihkan riwayat lama secara acak agar tabel tidak tumbuh selamanya.
        if (random_int(1, 25) === 1) {
            Db::delete('login_attempts', 'created_at < ?', [self::since(60 * 24 * 30)]);
        }
    }

    /** Hapus riwayat gagal setelah login berhasil, agar lockout tidak menyisa. */
    public static function clear(string $kind, string $identifier): void
    {
        if (!Db::tableExists('login_attempts')) {
            return;
        }

        Db::delete(
            'login_attempts',
            'kind = ? AND identifier = ? AND ip = ? AND success = 0',
            [$kind, self::normalize($identifier), client_ip()]
        );
    }

    public static function humanRetryAfter(int $seconds): string
    {
        if ($seconds <= 60) {
            return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        }
        $minutes = (int) ceil($seconds / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    private static function since(int $minutes): string
    {
        return gmdate('Y-m-d H:i:s', time() - $minutes * 60);
    }

    private static function normalize(string $identifier): string
    {
        return mb_strtolower(mb_substr(trim($identifier), 0, 190));
    }
}
