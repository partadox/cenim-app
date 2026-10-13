<?php

declare(strict_types=1);

namespace Cenim\Support;

use Cenim\Core\Db;
use Cenim\Core\Migrator;
use Cenim\Core\Security;
use Cenim\Domain\ContentDefaults;
use Cenim\Domain\ContentSchema;
use Cenim\Storage\S3Client;
use Throwable;

/**
 * Kumpulan pemeriksaan kesiapan environment.
 *
 * Satu sumber untuk dua muka: halaman `admin/health.php` dan CLI
 * `bin/preflight.php` (dipakai saat deploy, mengembalikan exit code).
 *
 * Bentuk hasil per item:
 *   ok      => bool   lulus atau tidak
 *   detail  => string keterangan singkat (tidak pernah berisi nilai rahasia)
 *   hint    => string cara memperbaiki (opsional)
 *   warning => bool   kalau true, kegagalan hanya peringatan (tidak memblokir)
 */
final class HealthChecks
{
    /** Key admin bawaan dokumentasi; tidak boleh masih berlaku di produksi. */
    private const DEV_KEY = 'cenim-dev-2026';

    /** @return array{checks: array<string,array<string,mixed>>, edition: array<string,mixed>|null} */
    public static function run(): array
    {
        $checks = [];

        $checks['PHP version'] = [
            'ok'     => PHP_VERSION_ID >= 80300,
            'detail' => PHP_VERSION,
            'hint'   => 'Butuh PHP 8.3 atau lebih baru.',
        ];

        foreach (['pdo_sqlite', 'sqlite3', 'gd', 'curl', 'mbstring', 'zip', 'openssl', 'fileinfo'] as $ext) {
            $checks['Extension: ' . $ext] = [
                'ok'     => extension_loaded($ext),
                'detail' => extension_loaded($ext) ? 'loaded' : 'missing',
                'hint'   => 'Aktifkan di cPanel > Select PHP Version > Extensions.',
            ];
        }

        $freetype = function_exists('gd_info') && !empty(gd_info()['FreeType Support']);
        $checks['GD FreeType (teks pada logo)'] = [
            'ok'     => $freetype,
            'detail' => $freetype ? 'available' : 'not available',
            'hint'   => 'Dibutuhkan generator logo untuk menulis angka tahun ke PNG.',
        ];

        $checks['Imagick (opsional)'] = [
            'ok'      => extension_loaded('imagick'),
            'detail'  => extension_loaded('imagick') ? 'loaded' : 'not loaded (fallback GD dipakai)',
            'warning' => true,
        ];

        $checks['intl (nama negara import EDAS)'] = [
            'ok'      => extension_loaded('intl'),
            'detail'  => extension_loaded('intl') ? 'loaded' : 'not loaded (kode negara "ID" disimpan apa adanya)',
            'hint'    => 'Aktifkan di cPanel → Select PHP Version → Extensions → intl.',
            'warning' => true,
        ];

        foreach (
            ['' => 'storage root', 'db' => 'db', 'uploads' => 'uploads', 'generated' => 'generated',
             'logs' => 'logs', 'backup' => 'backup', 'tmp' => 'tmp'] as $sub => $label
        ) {
            $dir = storage_path($sub);
            $ok  = ensure_dir($dir);
            $checks['Storage: ' . $label] = [
                'ok'     => $ok,
                'detail' => $ok ? 'writable — ' . $dir : 'NOT writable — ' . $dir,
                'hint'   => 'chmod 755 dan pastikan owner sama dengan user php-fpm.',
            ];
        }

        $driver = (string) env('STORAGE_DRIVER', 'local');
        if ($driver === 's3') {
            try {
                $s3      = S3Client::fromEnv();
                $themeOk = $s3->head('public/theme/css/style.css') !== null;
                $checks['Object storage (Tandikat)'] = [
                    'ok'     => $themeOk,
                    'detail' => 'terhubung — bucket ' . $s3->bucket() . ($themeOk ? ', aset tema ada' : ', aset tema BELUM diunggah'),
                    'hint'   => 'php cenim-app/bin/storage.php push-theme <folder-tema>',
                ];
            } catch (\Throwable $e) {
                $checks['Object storage (Tandikat)'] = [
                    'ok'     => false,
                    'detail' => $e->getMessage(),
                    'hint'   => 'Cek TANDIKAT_* di .env, lalu: php cenim-app/bin/storage.php check',
                ];
            }
        } else {
            $checks['Object storage (Tandikat)'] = [
                'ok'      => false,
                'detail'  => 'STORAGE_DRIVER=' . $driver . ' — unggahan disimpan di disk server',
                'warning' => true,
                'hint'    => 'Set STORAGE_DRIVER=s3 di .env.',
            ];
        }

        $checks['Storage di luar web root'] = [
            'ok'      => !str_starts_with(realpath(storage_path()) ?: storage_path(), realpath(dirname(CENIM_ROOT)) ?: ''),
            'detail'  => storage_path(),
            'warning' => true,
            'hint'    => 'Disarankan STORAGE_PATH di luar public_html (mis. ~/cenim-storage).',
        ];

        $free  = @disk_free_space(storage_path());
        $freeMb = is_float($free) ? (int) round($free / 1048576) : 0;
        $checks['Ruang disk storage'] = [
            'ok'      => $freeMb === 0 || $freeMb > 200,
            'detail'  => $freeMb === 0 ? 'tidak terdeteksi' : $freeMb . ' MB tersisa',
            'warning' => true,
            'hint'    => 'Backup harian butuh ruang; bersihkan arsip lama bila menipis.',
        ];

        // ── Database ────────────────────────────────────────────────────────
        $dbOk     = false;
        $dbDetail = '';
        try {
            Db::pdo();
            $dbOk     = true;
            $tables   = Db::all("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $mode     = (string) Db::value('PRAGMA journal_mode');
            $dbDetail = count($tables) . ' tabel, journal_mode=' . $mode;
        } catch (Throwable $e) {
            $dbDetail = $e->getMessage();
        }
        $checks['Database'] = ['ok' => $dbOk, 'detail' => Db::path() . ' — ' . $dbDetail];

        $integrity = 'tidak diperiksa (db error)';
        if ($dbOk) {
            try {
                $integrity = (string) Db::value('PRAGMA quick_check(1)');
            } catch (Throwable $e) {
                $integrity = $e->getMessage();
            }
        }
        $checks['Integritas database'] = [
            'ok'     => $integrity === 'ok',
            'detail' => $integrity,
            'hint'   => 'Kalau rusak: pulihkan dari arsip terbaru di storage/backup/.',
        ];

        $migrator = new Migrator();
        $pending  = $dbOk ? $migrator->pending() : ['(db error)'];
        $checks['Migrasi'] = [
            'ok'     => $pending === [],
            'detail' => $pending === [] ? 'semua migrasi diterapkan' : 'pending: ' . implode(', ', $pending),
            'hint'   => 'Jalankan: php cenim-app/db/migrate.php',
        ];

        $edition = $dbOk && Db::tableExists('editions')
            ? Db::one('SELECT * FROM editions WHERE is_active = 1 ORDER BY year DESC LIMIT 1')
            : null;
        $checks['Edisi aktif'] = [
            'ok'     => $edition !== null,
            'detail' => $edition !== null ? $edition['name'] : 'belum ada',
            'hint'   => 'Jalankan: php cenim-app/db/seed.php cenim2026',
        ];

        // ── Konfigurasi (hanya status, bukan nilainya) ───────────────────────
        $hash = (string) env('ADMIN_KEY_HASH', '');
        $checks['ADMIN_KEY_HASH'] = [
            'ok'     => $hash !== '' && str_starts_with($hash, '$2'),
            'detail' => $hash === '' ? 'belum diisi' : 'terkonfigurasi (hash)',
            'hint'   => "php -r \"echo password_hash('KEY', PASSWORD_DEFAULT);\"",
        ];

        $devKeyActive = $hash !== '' && password_verify(self::DEV_KEY, $hash);
        $checks['Key admin bukan key contoh'] = [
            'ok'      => !$devKeyActive,
            'detail'  => $devKeyActive ? 'MASIH memakai key dari dokumentasi' : 'key sendiri',
            'warning' => is_local(),
            'hint'    => 'Ganti ADMIN_KEY_HASH sebelum situs dibuka ke publik.',
        ];

        $turnstile = ((string) env('TURNSTILE_SITE_KEY', '')) !== '' && ((string) env('TURNSTILE_SECRET_KEY', '')) !== '';
        $checks['Turnstile'] = [
            'ok'      => $turnstile || !env('TURNSTILE_ENABLED', true),
            'detail'  => $turnstile ? 'site key & secret terkonfigurasi' : 'belum lengkap',
            'warning' => !env('TURNSTILE_ENABLED', true),
        ];

        $checks['Turnstile aktif'] = [
            'ok'      => (bool) env('TURNSTILE_ENABLED', true),
            'detail'  => env('TURNSTILE_ENABLED', true) ? 'aktif' : 'dimatikan',
            'warning' => is_local(),
            'hint'    => 'Wajib aktif di produksi: membatasi bot yang meminta kode OTP berulang.',
        ];

        $sender = \Cenim\Core\Mailer::from()['email'];
        $checks['Email (SMTP)'] = [
            'ok'      => (bool) env('MAIL_ENABLED', false) && ((string) env('SMTP_HOST', '')) !== '' && $sender !== '',
            'detail'  => (env('MAIL_ENABLED', false) ? (string) env('SMTP_HOST', '(host kosong)') : 'dimatikan (MAIL_ENABLED=false)')
                . ' | pengirim: ' . ($sender !== '' ? $sender : '(SMTP_SENDER kosong)'),
            // Login author & panitia memakai kode OTP lewat email: di produksi wajib jalan.
            'warning' => is_local(),
            'hint'    => 'Set MAIL_ENABLED=true, SMTP_HOST/PORT/USER/PASS, dan SMTP_SENDER di .env.',
        ];

        $appUrl = (string) env('APP_URL', '');
        $checks['APP_URL'] = [
            'ok'      => $appUrl !== '' && (is_local() || str_starts_with($appUrl, 'https://')),
            'detail'  => $appUrl === '' ? 'belum diisi' : $appUrl,
            'hint'    => 'Dipakai untuk tautan email dan QR sertifikat; di produksi harus https.',
        ];

        $checks['Timezone'] = ['ok' => true, 'detail' => date_default_timezone_get() . ' — sekarang ' . fmt_dt(now())];
        $checks['Environment'] = [
            'ok'      => !is_local(),
            'detail'  => (string) env('APP_ENV', '-') . ' | display_errors=' . (ini_get('display_errors') ?: '0'),
            'warning' => is_local(),
        ];

        $checks['Header keamanan (CSP)'] = [
            'ok'      => (bool) env('CSP_ENABLED', true),
            'detail'  => env('CSP_ENABLED', true)
                ? (env('CSP_REPORT_ONLY', false) ? 'report-only' : 'aktif') . ' — ' . count(Security::allowedHosts()) . ' host diizinkan'
                : 'dimatikan',
            'warning' => (bool) env('CSP_REPORT_ONLY', false),
        ];

        // Batas upload efektif = nilai terkecil dari batas aplikasi dan batas PHP;
        // form dan validasi otomatis memakai angka ini, jadi bukan kegagalan.
        $appLimit  = (int) cfg('upload.max_bytes', 5242880);
        $effective = upload_max_bytes();

        $checks['Upload limit'] = [
            'ok'      => true,
            'detail'  => 'efektif ' . upload_max_label() . ' per berkas'
                . ' (upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size')
                . ', aplikasi ' . fmt_bytes($appLimit) . ')'
                . (PHP_SAPI === 'cli' ? ' — ini CLI; angka sebenarnya di halaman Health' : ''),
            'warning' => $effective < $appLimit,
            'hint'    => 'Batas mengikuti PHP server secara otomatis; keterangan di setiap form upload memakai angka yang sama.',
        ];

        // ── Proteksi .env ────────────────────────────────────────────────────
        $envPerms   = (int) @fileperms(CENIM_ROOT . '/.env') & 0777;
        $envBlocked = str_contains((string) @file_get_contents(CENIM_ROOT . '/.htaccess'), '^\.env');
        $checks['.env terlindungi'] = [
            'ok'     => $envBlocked && ($envPerms & 0007) === 0,
            'detail' => sprintf('permission %o | diblok .htaccess: %s', $envPerms, $envBlocked ? 'ya' : 'tidak'),
            'hint'   => 'chmod 600 cenim-app/.env, dan pastikan cenim-app/.htaccess memuat FilesMatch "^\.env".',
        ];

        // ── Proteksi folder internal ─────────────────────────────────────────
        $missing = [];
        foreach (['config', 'db', 'src', 'storage', 'vendor-lite', 'views', 'reference', 'bin', 'docs'] as $dir) {
            if (is_dir(CENIM_ROOT . '/' . $dir) && !is_file(CENIM_ROOT . '/' . $dir . '/.htaccess')) {
                $missing[] = $dir;
            }
        }
        $checks['.htaccess folder internal'] = [
            'ok'     => $missing === [],
            'detail' => $missing === [] ? 'lengkap' : 'belum ada di: ' . implode(', ', $missing),
            'hint'   => 'Berisi "Require all denied"; hanya berlaku di Apache/cPanel, bukan nginx.',
        ];

        // ── Konten landing ───────────────────────────────────────────────────
        // Blok yang terdaftar di editor tapi tidak punya nilai bawaan akan tampil
        // kosong di halaman publik — ketahuan di sini, bukan dari laporan pengunjung.
        $missingDefaults = [];
        $orphanDefaults  = [];

        foreach (ContentSchema::pages() as $page => $definition) {
            $schemaKeys   = array_keys(ContentSchema::blocks((string) $page));
            $defaultKeys  = array_keys(ContentDefaults::page((string) $page));

            foreach (array_diff($schemaKeys, $defaultKeys) as $key) {
                $missingDefaults[] = $page . '/' . $key;
            }

            foreach (array_diff($defaultKeys, $schemaKeys) as $key) {
                $orphanDefaults[] = $page . '/' . $key;
            }
        }

        $checks['Bawaan konten landing'] = [
            'ok'     => $missingDefaults === [],
            'detail' => $missingDefaults === []
                ? count(ContentSchema::pages()) . ' halaman, semua blok punya teks bawaan'
                    . ($orphanDefaults === [] ? '' : ' (bawaan tanpa editor: ' . implode(', ', $orphanDefaults) . ')')
                : 'tanpa bawaan: ' . implode(', ', $missingDefaults),
            'hint'   => 'Tambahkan nilainya di src/Domain/ContentDefaults.php, atau hapus key-nya dari ContentSchema.',
        ];

        // ── Backup ───────────────────────────────────────────────────────────
        $latest = Backup::latest();
        $ageH   = $latest === null ? null : (int) floor((time() - $latest['time']) / 3600);
        $checks['Backup terakhir'] = [
            'ok'      => $ageH !== null && $ageH <= 48,
            'detail'  => $latest === null
                ? 'belum ada arsip di ' . storage_path('backup')
                : $latest['name'] . ' — ' . $ageH . ' jam lalu, ' . fmt_bytes($latest['size']),
            'warning' => is_local(),
            'hint'    => 'Pasang cron harian: php cenim-app/bin/cron.php (lihat docs/runbook-deploy.md).',
        ];

        return ['checks' => $checks, 'edition' => $edition];
    }

    /** Nilai ini_get bersuffix (2M, 8K, 1G) dalam byte. */
    private static function iniBytes(string $key): int
    {
        $raw = trim((string) ini_get($key));

        if ($raw === '' || $raw === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }

    /** @return array{pass:int,warn:int,fail:int} */
    public static function summarize(array $checks): array
    {
        $pass = $warn = $fail = 0;

        foreach ($checks as $check) {
            if (!empty($check['ok'])) {
                $pass++;
            } elseif (!empty($check['warning'])) {
                $warn++;
            } else {
                $fail++;
            }
        }

        return ['pass' => $pass, 'warn' => $warn, 'fail' => $fail];
    }
}
