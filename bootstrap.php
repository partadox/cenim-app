<?php
/**
 * Entry point semua request & script CLI.
 * Front controller cukup memanggil: require __DIR__ . '/../bootstrap.php';
 */

declare(strict_types=1);

define('CENIM_ROOT', __DIR__);
define('CENIM_START', microtime(true));

// ── Konfigurasi ─────────────────────────────────────────────────────────────
// Kredensial & setelan per-server ada di .env (gitignored); templatnya .env.example.
require CENIM_ROOT . '/src/Core/Env.php';

$cenimEnvFile = CENIM_ROOT . '/.env';
$cenimEnvError = is_file($cenimEnvFile)
    ? null
    : "CENIM: file .env tidak ditemukan.\nCopy .env.example menjadi .env lalu isi nilainya.\n";

if ($cenimEnvError === null) {
    try {
        $GLOBALS['CENIM_ENV'] = Cenim\Core\Env::load($cenimEnvFile);
    } catch (RuntimeException $ex) {
        $cenimEnvError = 'CENIM: ' . $ex->getMessage() . "\n";
    }
}

if ($cenimEnvError !== null) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $cenimEnvError);
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Configuration missing. Hubungi administrator.\n";
    }
    exit(1);
}

$GLOBALS['CENIM_CFG'] = require CENIM_ROOT . '/config/app.php';
unset($cenimEnvFile, $cenimEnvError);

// ── Autoloader (tanpa composer): Cenim\Core\Db → src/Core/Db.php ────────────
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Cenim\\')) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen('Cenim\\')));
    $file = CENIM_ROOT . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require CENIM_ROOT . '/src/helpers.php';

// ── Runtime ─────────────────────────────────────────────────────────────────
date_default_timezone_set((string) env('APP_TZ', 'Asia/Jakarta'));
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'C');

Cenim\Core\Logger::install();

Cenim\Core\Security::sendHeaders();
