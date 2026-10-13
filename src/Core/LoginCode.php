<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * Kode OTP 6 digit untuk login author & panitia.
 *
 * - Disimpan sebagai hash (password_hash), tidak pernah plaintext.
 * - Berlaku 10 menit, sekali pakai; kode baru membatalkan kode lama.
 * - Maksimal 5 tebakan per kode; setelah itu kode hangus.
 * - Pengiriman dibatasi: jeda 60 detik dan 5 kode per jam per email,
 *   serta 20 kode per jam per IP — melindungi kuota SMTP dan mencegah spam.
 */
final class LoginCode
{
    public const TTL_MINUTES      = 10;
    public const MAX_TRIES        = 5;
    public const RESEND_SECONDS   = 60;
    public const MAX_PER_HOUR     = 5;
    public const MAX_PER_HOUR_IP  = 20;

    /**
     * Detik yang harus ditunggu sebelum boleh meminta kode baru (0 = boleh).
     */
    public static function waitSeconds(string $email): int
    {
        $email = mb_strtolower(trim($email));
        $hourAgo = gmdate('Y-m-d H:i:s', time() - 3600);

        $last = (string) Db::value('SELECT MAX(created_at) FROM login_codes WHERE email = ?', [$email]);
        if ($last !== '') {
            $wait = strtotime($last . ' UTC') + self::RESEND_SECONDS - time();
            if ($wait > 0) {
                return $wait;
            }
        }

        $perEmail = (int) Db::value('SELECT COUNT(*) FROM login_codes WHERE email = ? AND created_at >= ?', [$email, $hourAgo]);
        $perIp    = (int) Db::value('SELECT COUNT(*) FROM login_codes WHERE ip = ? AND created_at >= ?', [client_ip(), $hourAgo]);

        if ($perEmail >= self::MAX_PER_HOUR || $perIp >= self::MAX_PER_HOUR_IP) {
            $oldest = (string) Db::value(
                'SELECT MIN(created_at) FROM login_codes WHERE (email = ? OR ip = ?) AND created_at >= ?',
                [$email, client_ip(), $hourAgo]
            );

            return max(60, strtotime($oldest . ' UTC') + 3600 - time());
        }

        return 0;
    }

    /** Buat kode baru (kode lama yang belum dipakai dibatalkan). Mengembalikan kode mentah. */
    public static function issue(string $email, ?int $editionId): string
    {
        $email = mb_strtolower(trim($email));
        $code  = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Db::run('UPDATE login_codes SET used_at = ? WHERE email = ? AND used_at IS NULL', [now(), $email]);

        Db::insert('login_codes', [
            'edition_id' => $editionId,
            'email'      => $email,
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60),
            'ip'         => mb_substr(client_ip(), 0, 64),
            'created_at' => now(),
        ]);

        return $code;
    }

    /**
     * Cocokkan kode. Sukses → kode ditandai terpakai.
     *
     * @return 'ok'|'invalid'|'expired'
     */
    public static function verify(string $email, string $code): string
    {
        $email = mb_strtolower(trim($email));
        $code  = preg_replace('/\D+/', '', $code) ?? '';

        $row = Db::one(
            'SELECT * FROM login_codes WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1',
            [$email]
        );

        if ($row === null || strtotime((string) $row['expires_at'] . ' UTC') < time() || (int) $row['attempts'] >= self::MAX_TRIES) {
            return 'expired';
        }

        if (strlen($code) !== 6 || !password_verify($code, (string) $row['code_hash'])) {
            $attempts = (int) $row['attempts'] + 1;
            Db::update('login_codes', [
                'attempts' => $attempts,
                'used_at'  => $attempts >= self::MAX_TRIES ? now() : null,
            ], 'id = :target_id', ['target_id' => (int) $row['id']]);

            return $attempts >= self::MAX_TRIES ? 'expired' : 'invalid';
        }

        Db::update('login_codes', ['used_at' => now()], 'id = :target_id', ['target_id' => (int) $row['id']]);

        return 'ok';
    }

    /** Sisa tebakan untuk kode aktif. */
    public static function triesLeft(string $email): int
    {
        $attempts = Db::value(
            'SELECT attempts FROM login_codes WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1',
            [mb_strtolower(trim($email))]
        );

        return $attempts === null ? 0 : max(0, self::MAX_TRIES - (int) $attempts);
    }

    /** Hapus riwayat kode lebih tua dari $days hari (dipanggil cron). */
    public static function prune(int $days = 7): int
    {
        if (!Db::tableExists('login_codes')) {
            return 0;
        }

        return Db::delete('login_codes', 'created_at < ?', [gmdate('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
