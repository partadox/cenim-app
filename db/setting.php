<?php
/**
 * Lihat / ubah pengaturan edisi dari CLI. Berguna sebelum halaman Settings
 * (Fase 7) tersedia, misalnya untuk mengisi rekening bank yang dicetak di invoice.
 *
 *   php cenim-app/db/setting.php list
 *   php cenim-app/db/setting.php get bank_accounts
 *   php cenim-app/db/setting.php set contact_email secretariat@cenim.its.ac.id
 *   php cenim-app/db/setting.php set invoice_due_days 21
 *   php cenim-app/db/setting.php set-json bank_accounts '[{"currency":"IDR","bank_name":"…"}]'
 *
 * Opsional: --year=2027 untuk edisi selain yang aktif.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan dari CLI.\n");
}

require __DIR__ . '/../bootstrap.php';

use Cenim\Domain\EditionRepo;
use Cenim\Domain\SettingRepo;

$args    = array_slice($argv, 1);
$year    = null;
$command = null;
$rest    = [];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--year=')) {
        $year = (int) substr($arg, 7);
    } elseif ($command === null) {
        $command = $arg;
    } else {
        $rest[] = $arg;
    }
}

$edition = $year !== null ? EditionRepo::findByYear($year) : EditionRepo::active();

if ($edition === null) {
    exit("Edisi tidak ditemukan. Jalankan dulu: php cenim-app/db/seed.php cenim2026\n");
}

$editionId = (int) $edition['id'];

$show = static function (string $key, mixed $value): void {
    printf("  %-22s %s\n", $key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
};

switch ($command) {
    case 'list':
        echo "Pengaturan {$edition['name']}:\n";
        foreach (SettingRepo::all($editionId) as $key => $value) {
            $show($key, $value);
        }
        break;

    case 'get':
        $key = $rest[0] ?? '';
        if ($key === '') {
            exit("Pemakaian: setting.php get <key>\n");
        }
        $show($key, SettingRepo::get($editionId, $key));
        break;

    case 'set':
        $key   = $rest[0] ?? '';
        $value = $rest[1] ?? null;
        if ($key === '' || $value === null) {
            exit("Pemakaian: setting.php set <key> <value>\n");
        }

        // Konversi otomatis: true/false → bool, angka → int/float.
        $typed = match (true) {
            in_array(strtolower($value), ['true', 'false'], true) => strtolower($value) === 'true',
            preg_match('/^-?\d+$/', $value) === 1                 => (int) $value,
            preg_match('/^-?\d*\.\d+$/', $value) === 1            => (float) $value,
            default                                               => $value,
        };

        SettingRepo::set($editionId, $key, $typed);
        echo "Tersimpan untuk {$edition['name']}:\n";
        $show($key, SettingRepo::get($editionId, $key));
        break;

    case 'set-json':
        $key  = $rest[0] ?? '';
        $json = $rest[1] ?? '';
        if ($key === '' || $json === '') {
            exit("Pemakaian: setting.php set-json <key> '<json>'\n");
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            exit('JSON tidak valid: ' . json_last_error_msg() . "\n");
        }

        SettingRepo::set($editionId, $key, $decoded);
        echo "Tersimpan untuk {$edition['name']}:\n";
        $show($key, SettingRepo::get($editionId, $key));
        break;

    default:
        echo "Perintah: list | get <key> | set <key> <value> | set-json <key> '<json>'\n";
        echo "Opsional: --year=2027\n";
        exit(1);
}
