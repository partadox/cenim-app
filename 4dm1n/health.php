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
use Cenim\Core\RateLimit;
use Cenim\Core\Response;
use Cenim\Core\View;
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
], 'admin');
