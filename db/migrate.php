<?php
/**
 * Jalankan migrasi database.
 *
 *   php cenim-app/db/migrate.php            # jalankan migrasi yang belum diterapkan
 *   php cenim-app/db/migrate.php --status   # lihat status saja
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan dari CLI.\n");
}

require __DIR__ . '/../bootstrap.php';

use Cenim\Core\Db;
use Cenim\Core\Migrator;

$migrator = new Migrator();
$statusOnly = in_array('--status', $argv, true);

echo "Database : " . Db::path() . PHP_EOL;
echo "Storage  : " . storage_path() . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;

if ($statusOnly) {
    foreach ($migrator->status() as $row) {
        printf("  [applied] %-28s %s%s", $row['filename'], $row['applied_at'], PHP_EOL);
    }
    foreach ($migrator->pending() as $name) {
        printf("  [pending] %s%s", $name, PHP_EOL);
    }
    exit(0);
}

$ran = $migrator->migrate();

if ($ran === []) {
    echo "Tidak ada migrasi baru. Database sudah terkini." . PHP_EOL;
} else {
    foreach ($ran as $name) {
        echo "  applied: {$name}" . PHP_EOL;
    }
    echo count($ran) . " migrasi diterapkan." . PHP_EOL;
}

$tables = Db::all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
echo count($tables) . " tabel di database." . PHP_EOL;
