<?php
/**
 * Backup database + berkas unggahan ke storage/backup/.
 *
 *   php cenim-app/bin/backup.php                  # db + uploads, simpan 14 arsip terbaru
 *   php cenim-app/bin/backup.php --keep=30        # ubah retensi
 *   php cenim-app/bin/backup.php --db-only        # snapshot database saja
 *   php cenim-app/bin/backup.php --verify         # backup, lalu buka snapshot & cek isinya
 *   php cenim-app/bin/backup.php --list           # daftar arsip yang ada
 *   php cenim-app/bin/backup.php --quiet          # untuk cron (hanya cetak bila ada masalah)
 *
 * Exit code: 0 sukses, 1 gagal.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan dari CLI.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Support\Backup;

$args  = array_slice($argv, 1);
$quiet = in_array('--quiet', $args, true);
$keep  = Backup::DEFAULT_KEEP;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--keep=')) {
        $keep = (int) substr($arg, 7);
    }
}

$say = static function (string $line) use ($quiet): void {
    if (!$quiet) {
        echo $line, PHP_EOL;
    }
};

if (in_array('--list', $args, true)) {
    $all = Backup::all();

    if ($all === []) {
        echo 'Belum ada arsip di ' . storage_path('backup') . PHP_EOL;
        exit(0);
    }

    printf("%-42s %-8s %10s  %s\n", 'BERKAS', 'JENIS', 'UKURAN', 'DIBUAT (UTC)');
    foreach ($all as $file) {
        printf(
            "%-42s %-8s %10s  %s\n",
            $file['name'],
            $file['kind'],
            fmt_bytes($file['size']),
            gmdate('Y-m-d H:i:s', $file['time'])
        );
    }
    exit(0);
}

try {
    $summary = Backup::run([
        'files' => !in_array('--db-only', $args, true),
        'keep'  => $keep,
    ]);

    foreach ($summary['created'] as $kind => $file) {
        $say(sprintf('  [ok] %-9s %s (%s)', $kind, basename($file['path']), fmt_bytes($file['size'])));
    }

    if ($summary['removed'] !== []) {
        $say('  [--] arsip lama dihapus: ' . implode(', ', $summary['removed']));
    }

    if (in_array('--verify', $args, true)) {
        $check = Backup::verify($summary['created']['database']['path']);
        $say(sprintf('  [%s] verify   %s', $check['ok'] ? 'ok' : '!!', $check['detail']));

        if (!$check['ok']) {
            fwrite(STDERR, "Backup dibuat tapi verifikasi GAGAL: " . $check['detail'] . PHP_EOL);
            exit(1);
        }
    }

    $say('Backup selesai di ' . $summary['dir']);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Backup GAGAL: ' . $e->getMessage() . PHP_EOL);
    Cenim\Core\Logger::error('Backup gagal', ['error' => $e->getMessage()]);
    exit(1);
}
