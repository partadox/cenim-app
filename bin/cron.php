<?php
/**
 * Satu pintu untuk cron harian cPanel.
 *
 *   0 2 * * *  /usr/local/bin/php $HOME/public_html/conf/cenim/cenim-app/bin/cron.php --quiet
 *
 * Yang dikerjakan:
 *   1. Backup database + unggahan (retensi 14 arsip), snapshot langsung diverifikasi.
 *   2. Pembersihan rutin: riwayat login > 30 hari, berkas email HTML > 30 hari,
 *      log aplikasi > 90 hari, berkas tmp > 1 hari, PRAGMA optimize.
 *   3. Peringatan bila ada migrasi yang belum dijalankan.
 *
 * Dengan --quiet, output hanya muncul bila ada masalah — jadi cron cPanel tidak
 * mengirim email setiap hari, tapi tetap mengirim saat ada yang perlu dilihat.
 *
 * Exit code: 0 sukses, 1 ada tugas yang gagal.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan dari CLI.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\Logger;
use Cenim\Core\Migrator;
use Cenim\Support\Backup;
use Cenim\Support\Maintenance;

$args  = array_slice($argv, 1);
$quiet = in_array('--quiet', $args, true);
$lines = [];
$fail  = false;

$log = static function (string $line) use (&$lines): void {
    $lines[] = $line;
};

$log('CENIM cron — ' . now() . ' UTC');

// 1. Backup
try {
    $summary = Backup::run(['files' => true, 'keep' => 14]);

    foreach ($summary['created'] as $kind => $file) {
        $log(sprintf('  [ok] backup %-9s %s (%s)', $kind, basename($file['path']), fmt_bytes($file['size'])));
    }

    if ($summary['removed'] !== []) {
        $log('  [ok] arsip lama dihapus: ' . count($summary['removed']));
    }

    $check = Backup::verify($summary['created']['database']['path']);
    $log(sprintf('  [%s] verifikasi snapshot: %s', $check['ok'] ? 'ok' : '!!', $check['detail']));
    $fail = $fail || !$check['ok'];
} catch (Throwable $e) {
    $fail = true;
    $log('  [!!] backup gagal: ' . $e->getMessage());
    Logger::error('Cron: backup gagal', ['error' => $e->getMessage()]);
}

// 2. Pembersihan
try {
    foreach (Maintenance::run() as $task => $result) {
        $log(sprintf('  [ok] %-15s %s', $task, is_int($result) ? $result . ' dibersihkan' : $result));
    }
} catch (Throwable $e) {
    $fail = true;
    $log('  [!!] maintenance gagal: ' . $e->getMessage());
    Logger::error('Cron: maintenance gagal', ['error' => $e->getMessage()]);
}

// 3. Migrasi tertunda — tanda deploy belum tuntas.
try {
    $pending = (new Migrator())->pending();

    if ($pending !== []) {
        $fail = true;
        $log('  [!!] migrasi belum dijalankan: ' . implode(', ', $pending));
    }
} catch (Throwable $e) {
    $fail = true;
    $log('  [!!] cek migrasi gagal: ' . $e->getMessage());
}

if (!$quiet || $fail) {
    echo implode(PHP_EOL, $lines), PHP_EOL;
}

exit($fail ? 1 : 0);
