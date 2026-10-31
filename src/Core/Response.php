<?php

declare(strict_types=1);

namespace Cenim\Core;

final class Response
{
    /** @param array<string,mixed> $data */
    public static function json(array $data, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $status);
        }
        echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url='
            . e($url) . '"><a href="' . e($url) . '">Continue</a>';
        exit;
    }

    /** Kembali ke halaman sebelumnya (hanya menerima referer dari host yang sama). */
    public static function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host    = $_SERVER['HTTP_HOST'] ?? '';

        if ($referer !== '' && $host !== '') {
            $parts = parse_url($referer);
            if (($parts['host'] ?? '') === parse_url('http://' . $host, PHP_URL_HOST)) {
                self::redirect($referer);
            }
        }

        self::redirect(base_url($fallback));
    }

    public static function abort(int $status, string $message = ''): never
    {
        if (!headers_sent()) {
            http_response_code($status);
        }

        if (is_ajax()) {
            self::json(['ok' => false, 'error' => $message !== '' ? $message : 'Request failed.'], $status);
        }

        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            419 => 'Session Expired',
            429 => 'Too Many Requests',
            500 => 'Server Error',
        ];
        $title = $titles[$status] ?? 'Error';

        echo '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title>'
            . '<div style="font:16px/1.6 system-ui,sans-serif;max-width:520px;margin:14vh auto;padding:0 24px;text-align:center">'
            . '<h1 style="font-size:20px;color:#003981">' . e($title) . '</h1>'
            . '<p style="color:#555">' . e($message !== '' ? $message : 'The page cannot be displayed.') . '</p>'
            . '<p><a style="color:#003981" href="' . e(base_url()) . '">Back to homepage</a></p>'
            . '</div>';
        exit;
    }

    /** Kirim file dari storage privat sebagai unduhan / inline. */
    /** Kirim isi berkas yang dibuat di memori (mis. PDF yang tidak di-cache). */
    public static function bytes(string $bytes, string $downloadName, string $mime = 'application/pdf', bool $inline = true): never
    {
        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) strlen($bytes));
            header(sprintf('Content-Disposition: %s; filename="%s"', $inline ? 'inline' : 'attachment', str_replace('"', '', $downloadName)));
            header('X-Content-Type-Options: nosniff');
        }

        echo $bytes;
        exit;
    }

    /** @param string|null $mime jenis file yang sudah diketahui (mis. tercatat saat upload); null = deteksi dari isi */
    public static function file(string $absolutePath, ?string $downloadName = null, bool $inline = false, ?string $mime = null): never
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            self::abort(404, 'File not found.');
        }

        $mime = $mime !== null && $mime !== '' ? $mime : (mime_content_type($absolutePath) ?: 'application/octet-stream');
        $name = $downloadName ?? basename($absolutePath);

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($absolutePath));
            header(sprintf(
                '%s; filename="%s"',
                'Content-Disposition: ' . ($inline ? 'inline' : 'attachment'),
                str_replace('"', '', $name)
            ));
            header('X-Content-Type-Options: nosniff');
        }

        readfile($absolutePath);
        exit;
    }
}
