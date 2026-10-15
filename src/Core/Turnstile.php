<?php

declare(strict_types=1);

namespace Cenim\Core;

use Cenim\Support\Http;

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

        try {
            $res = Http::request('POST', self::VERIFY_URL, [
                'headers'         => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body'            => http_build_query([
                    'secret'   => (string) env('TURNSTILE_SECRET_KEY', ''),
                    'response' => $token,
                    'remoteip' => client_ip(),
                ]),
                'timeout'         => 8,
                'connect_timeout' => 5,
            ]);
        } catch (\RuntimeException $e) {
            Logger::error('Turnstile: verifikasi gagal dihubungi', ['error' => $e->getMessage()]);
            return 'Verification service is unreachable. Please try again in a moment.';
        }

        $body = $res['body'];

        if ($res['status'] !== 200) {
            Logger::error('Turnstile: verifikasi gagal dihubungi', ['http' => $res['status']]);
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
