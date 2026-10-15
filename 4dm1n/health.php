<?php
/**
 * Cek kesiapan environment (dipakai saat deploy ke cPanel).
 *
 * Akses: admin yang sudah login, atau jalur darurat ?key=<key admin> untuk
 * diagnosa ketika login belum bisa dipakai (session/DB bermasalah). Jalur
 * darurat ikut dibatasi rate limit. Halaman ini tidak pernah menampilkan
 * nilai rahasia, hanya status terkonfigurasi.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AdminAuth;
use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Logger;
use Cenim\Core\Migrator;
use Cenim\Core\RateLimit;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Support\Backup;
use Cenim\Support\HealthChecks;

panel_boot('admin');

$hash = (string) env('ADMIN_KEY_HASH', '');
$key  = (string) input('key', '');

if (!AdminAuth::isLoggedIn()) {
    $allowed = $key !== ''
        && $hash !== ''
        && RateLimit::retryAfter('health', 'health') === 0
        && password_verify($key, $hash);

    if (!$allowed) {
        if ($key !== '') {
            RateLimit::record('health', 'health', false);
        }
        Response::abort(403, 'Forbidden.');
    }

    RateLimit::record('health', 'health', true);
}

// ── Jalankan migrasi tertunda (tanpa terminal di cPanel) ─────────────────────
// Hanya admin yang login (bukan jalur darurat ?key=), dengan CSRF; database
// di-snapshot dulu supaya ada titik kembali bila migrasi gagal.
if (is_post() && input('action') === 'migrate') {
    if (!AdminAuth::isLoggedIn()) {
        Response::abort(403, 'Sign in to the admin panel to run migrations.');
    }
    Csrf::verify();

    $pending = (new Migrator())->pending();

    if ($pending === []) {
        Session::flash('info', 'Nothing to run — the database is up to date.');
    } else {
        try {
            $snapshot = Backup::snapshotNow('before-migrate');
            $ran      = (new Migrator())->migrate();

            Audit::log('db.migrated', ['meta' => ['migrations' => $ran, 'snapshot' => basename($snapshot['path'])]]);
            Session::flash('success', count($ran) . ' migration(s) applied: ' . implode(', ', $ran)
                . '. Snapshot before migrating: ' . basename($snapshot['path']) . '.');
        } catch (Throwable $e) {
            Logger::error('Migrasi dari halaman Health gagal', ['error' => $e->getMessage()]);
            Session::flash('error', 'Migration failed: ' . $e->getMessage()
                . ' — the failed file was rolled back; a database snapshot is in storage/backup.');
        }
    }

    Response::redirect(admin_url('health.php'));
}

try {
    $pendingMigrations = (new Migrator())->pending();
} catch (Throwable) {
    $pendingMigrations = [];
}

$result  = HealthChecks::run();
$checks  = $result['checks'];
$summary = HealthChecks::summarize($checks);

View::render('admin/health', [
    'title'    => 'Health check',
    'subtitle' => sprintf(
        'Kesiapan environment & konfigurasi — %d lulus, %d peringatan, %d gagal',
        $summary['pass'],
        $summary['warn'],
        $summary['fail']
    ),
    'active'   => 'health',
    'edition'  => $result['edition'],
    'checks'   => $checks,
    'pending'  => $pendingMigrations,
    'canRun'   => AdminAuth::isLoggedIn(),
], 'admin');
