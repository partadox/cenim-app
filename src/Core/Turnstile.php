<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * Verifikasi Cloudflare Turnstile di sisi server.
 *
 * Sikap terhadap kegagalan: FAIL CLOSED — bila Cloudflare tidak bisa dihubungi,
 * login ditolak dan kejadian dicatat ke log. Bila terjadi gangguan Cloudflare
 * berkepanjangan, admin bisa mematikan sementara lewat TURNSTILE_ENABLED=false
 * di .env.
 */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    public const FIELD = 'cf-turnstile-response';

    public static function enabled(): bool
    {
        return (bool) env('TURNSTILE_ENABLED', true)
            && (string) env('TURNSTILE_SITE_KEY', '') !== ''
            && (string) env('TURNSTILE_SECRET_KEY', '') !== '';
    }

    public static function siteKey(): string
    {
        return (string) env('TURNSTILE_SITE_KEY', '');
    }

    /** Token dari request POST. */
    public static function token(): string
    {
        $token = $_POST[self::FIELD] ?? '';
        return is_string($token) ? $token : '';
    }

    /**
     * @return string|null null bila lolos, atau pesan error untuk ditampilkan
     */
    public static function check(?string $token = null): ?string
    {
        if (!self::enabled()) {
            return null;
        }

        $token ??= self::token();

        if ($token === '') {
            return 'Please complete the verification challenge.';
        }

        if (!extension_loaded('curl')) {
            Logger::error('Turnstile: ekstensi curl tidak tersedia');
            return 'Verification is temporarily unavailable. Please contact the secretariat.';
        }

        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => (string) env('TURNSTILE_SECRET_KEY', ''),
                'response' => $token,
                'remoteip' => client_ip(),
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            Logger::error('Turnstile: verifikasi gagal dihubungi', ['http' => $status, 'curl' => $error]);
            return 'Verification service is unreachable. Please try again in a moment.';
        }

        $result = json_decode((string) $body, true);
        if (!is_array($result)) {
            Logger::error('Turnstile: respons tidak bisa dibaca', ['body' => mb_substr((string) $body, 0, 200)]);
            return 'Verification failed. Please try again.';
        }

        if (($result['success'] ?? false) === true) {
            return null;
        }

        $codes = $result['error-codes'] ?? [];
        Logger::warn('Turnstile: token ditolak', ['codes' => $codes]);

        // Token kedaluwarsa/terpakai adalah kasus wajar (halaman dibiarkan terbuka lama).
        if (array_intersect(['timeout-or-duplicate', 'invalid-input-response'], (array) $codes) !== []) {
            return 'The verification expired. Please try again.';
        }

        return 'Verification failed. Please reload the page and try again.';
    }
}
