<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * Header keamanan untuk semua response HTML.
 *
 * Content-Security-Policy dibangun dari daftar CDN di config/app.php — jadi
 * kalau nanti ada library baru, cukup tambahkan URL-nya di sana dan policy ikut
 * menyesuaikan; tidak ada dua tempat yang harus disamakan manual.
 *
 * Catatan sadar-risiko: `'unsafe-inline'` tetap diizinkan untuk script & style
 * karena tema landing dan beberapa halaman panel memakai inline handler/style.
 * Jadi CSP di sini bukan pertahanan terhadap XSS berbasis inline, tapi tetap
 * memblokir pemuatan skrip dari host yang tidak dikenal, `eval()`, plugin objek,
 * form yang menembak domain lain, dan framing oleh situs asing.
 */
final class Security
{
    public static function sendHeaders(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');

        // HSTS berlaku untuk SELURUH host, bukan hanya folder aplikasi. Di
        // produksi aplikasi ini menumpang di elib.its.ac.id/conf/cenim, jadi
        // default-nya mati; nyalakan hanya bila pemilik host menyetujui.
        if (env('HSTS_ENABLED', false) && self::isHttps() && !is_local()) {
            header('Strict-Transport-Security: max-age=31536000');
        }

        if (env('CSP_ENABLED', true)) {
            $name = env('CSP_REPORT_ONLY', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            header($name . ': ' . self::csp());
        }
    }

    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') {
            return true;
        }

        return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    /** Nilai Content-Security-Policy. */
    public static function csp(): string
    {
        $cdn    = self::cdnHosts();
        $frames = self::hosts((array) cfg('security.frame_src', []));
        $extra  = array_merge(
            self::hosts((array) cfg('security.extra_hosts', [])),
            // Aset tema langsung dari object storage (hanya bila mode publik aktif).
            self::hosts(array_filter([(string) env('TANDIKAT_PUBLIC_URL', '')]))
        );

        $script = self::join(["'self'", "'unsafe-inline'"], $cdn, $extra);
        $style  = self::join(["'self'", "'unsafe-inline'"], $cdn, $extra);
        $font   = self::join(["'self'", 'data:'], $cdn, $extra);
        $frame  = self::join(["'self'"], $frames);
        $connect = self::join(["'self'"], $cdn, $extra);

        return implode('; ', [
            "default-src 'self'",
            'script-src ' . $script,
            'style-src ' . $style,
            "img-src 'self' data: blob: https:",
            'font-src ' . $font,
            'frame-src ' . $frame,
            'connect-src ' . $connect,
            "media-src 'self' data:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    /**
     * Host yang dipakai halaman ini, untuk dicek saat uji: gabungan CDN,
     * frame yang diizinkan, dan host tambahan.
     *
     * @return list<string>
     */
    public static function allowedHosts(): array
    {
        return array_values(array_unique(array_merge(
            self::cdnHosts(),
            self::hosts((array) cfg('security.frame_src', [])),
            self::hosts((array) cfg('security.extra_hosts', [])),
            // Aset tema langsung dari object storage (hanya bila mode publik aktif).
            self::hosts(array_filter([(string) env('TANDIKAT_PUBLIC_URL', '')]))
        )));
    }

    /** @return list<string> */
    private static function cdnHosts(): array
    {
        return self::hosts(array_values((array) cfg('cdn', [])));
    }

    /**
     * @param  array<int|string,mixed> $urls
     * @return list<string>
     */
    private static function hosts(array $urls): array
    {
        $hosts = [];

        foreach ($urls as $url) {
            $url = (string) $url;

            // Sudah berupa host / wildcard, bukan URL penuh.
            if (!str_contains($url, '//')) {
                if ($url !== '') {
                    $hosts[] = $url;
                }
                continue;
            }

            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $hosts[] = 'https://' . $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    /** @param list<string> ...$parts */
    private static function join(array ...$parts): string
    {
        return implode(' ', array_values(array_unique(array_merge(...$parts))));
    }
}
