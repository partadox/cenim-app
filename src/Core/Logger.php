<?php

declare(strict_types=1);

namespace Cenim\Core;

use Throwable;

/**
 * Logging file harian + handler error/exception global.
 * Production: pesan teknis tidak pernah ditampilkan ke browser, hanya masuk log.
 */
final class Logger
{
    private static bool $installed = false;

    public static function install(): void
    {
        if (self::$installed) {
            return;
        }
        self::$installed = true;

        error_reporting(E_ALL);
        ini_set('display_errors', is_local() ? '1' : '0');
        ini_set('log_errors', '0'); // ditangani sendiri lewat handler di bawah

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            self::write('error', $message, ['file' => $file, 'line' => $line, 'severity' => $severity]);
            return !is_local(); // di local biarkan PHP menampilkan juga
        });

        set_exception_handler(static function (Throwable $e): void {
            self::exception($e);
            self::renderFatal($e);
        });

        register_shutdown_function(static function (): void {
            $last = error_get_last();
            if ($last === null || !in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            self::write('fatal', $last['message'], ['file' => $last['file'], 'line' => $last['line']]);
        });
    }

    public static function exception(Throwable $e): void
    {
        self::write('exception', $e::class . ': ' . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ]);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = storage_path('logs');
        if (!ensure_dir($dir)) {
            if (is_local()) {
                fwrite(STDERR, "[cenim] log dir not writable: {$dir}\n");
            }
            return;
        }

        $line = sprintf(
            "[%s] %s: %s%s | %s %s | ip=%s\n",
            now(),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            $_SERVER['REQUEST_URI'] ?? (PHP_SAPI === 'cli' ? implode(' ', $_SERVER['argv'] ?? []) : '-'),
            PHP_SAPI === 'cli' ? 'cli' : client_ip()
        );

        @file_put_contents(
            $dir . '/app-' . gmdate('Y-m-d') . '.log',
            $line,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function renderFatal(Throwable $e): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: ' . (is_ajax() ? 'application/json' : 'text/html') . '; charset=utf-8');
        }

        if (is_ajax()) {
            echo json_encode([
                'ok'    => false,
                'error' => is_local() ? $e->getMessage() : 'Internal server error.',
            ]);
            exit;
        }

        if (is_local()) {
            echo '<pre style="padding:16px;font:13px/1.5 monospace;background:#fff4f4;color:#8b1111;white-space:pre-wrap">';
            echo e($e::class . ': ' . $e->getMessage()) . "\n";
            echo e($e->getFile() . ':' . $e->getLine()) . "\n\n";
            echo e($e->getTraceAsString());
            echo '</pre>';
            exit;
        }

        echo '<!doctype html><meta charset="utf-8"><title>Server Error</title>'
            . '<div style="font:16px/1.6 system-ui,sans-serif;max-width:520px;margin:14vh auto;padding:0 24px;text-align:center">'
            . '<h1 style="font-size:20px;color:#003981">Something went wrong</h1>'
            . '<p style="color:#555">The error has been logged. Please try again, or contact the conference secretariat if it persists.</p>'
            . '</div>';
        exit;
    }
}
