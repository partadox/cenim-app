<?php
/**
 * Cek kesiapan sebelum/sesudah deploy, versi CLI dari admin/health.php.
 *
 *   php cenim-app/bin/preflight.php           # tabel hasil + exit code
 *   php cenim-app/bin/preflight.php --json    # untuk dipipe ke tool lain
 *   php cenim-app/bin/preflight.php --strict  # peringatan dihitung sebagai gagal
 *
 * Exit code: 0 semua lulus, 1 ada yang gagal (atau ada peringatan bila --strict).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan dari CLI.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Support\HealthChecks;

$args   = array_slice($argv, 1);
$strict = in_array('--strict', $args, true);

$result  = HealthChecks::run();
$checks  = $result['checks'];
$summary = HealthChecks::summarize($checks);

if (in_array('--json', $args, true)) {
    echo json_encode(
        ['summary' => $summary, 'checks' => $checks],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ), PHP_EOL;
    exit($summary['fail'] > 0 || ($strict && $summary['warn'] > 0) ? 1 : 0);
}

echo 'CENIM preflight — ' . (string) env('APP_ENV', '?') . ' — ' . now() . ' UTC' . PHP_EOL;
echo 'Database : ' . Cenim\Core\Db::path() . PHP_EOL;
echo 'Storage  : ' . storage_path() . PHP_EOL;
echo str_repeat('-', 78) . PHP_EOL;

foreach ($checks as $label => $check) {
    $state = !empty($check['ok']) ? 'ok  ' : (!empty($check['warning']) ? 'warn' : 'FAIL');

    printf("  [%s] %-32s %s\n", $state, $label, (string) ($check['detail'] ?? ''));

    if (empty($check['ok']) && !empty($check['hint'])) {
        printf("         %s %s\n", '↳', (string) $check['hint']);
    }
}

echo str_repeat('-', 78) . PHP_EOL;
printf("%d lulus, %d peringatan, %d gagal\n", $summary['pass'], $summary['warn'], $summary['fail']);

if ($summary['fail'] > 0) {
    echo 'BELUM SIAP: perbaiki item FAIL di atas.' . PHP_EOL;
    exit(1);
}

if ($strict && $summary['warn'] > 0) {
    echo 'BELUM SIAP (--strict): masih ada peringatan.' . PHP_EOL;
    exit(1);
}

echo 'SIAP.' . PHP_EOL;
exit(0);
