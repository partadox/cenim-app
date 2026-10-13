<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * CSRF token per session. Semua request POST (termasuk AJAX) wajib menyertakannya.
 */
final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = random_token(32);
            Session::put(self::KEY, $token);
        }
        return $token;
    }

    /** Hidden input siap tempel di dalam <form>. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    public static function isValid(?string $token): bool
    {
        $expected = Session::get(self::KEY);
        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    /**
     * Verifikasi request POST. Menghentikan eksekusi bila token tidak valid.
     */
    public static function verify(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (self::isValid(is_string($token) ? $token : null)) {
            return;
        }

        // Berkas melebihi post_max_size: PHP membuang seluruh form (termasuk token),
        // jadi ini bukan sesi kedaluwarsa — beri tahu batas ukurannya.
        if (post_too_large()) {
            $message = 'The file is too large for the server (max ' . upload_max_label() . ' per upload). Please choose a smaller file.';
            Logger::warn('Upload melebihi post_max_size', ['uri' => $_SERVER['REQUEST_URI'] ?? '-', 'bytes' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0)]);
            if (is_ajax()) {
                Response::json(['ok' => false, 'error' => $message], 413);
            }
            Session::flash('error', $message);
            Response::back();
        }

        Logger::warn('CSRF token invalid', ['uri' => $_SERVER['REQUEST_URI'] ?? '-']);

        if (is_ajax()) {
            Response::json(['ok' => false, 'error' => 'Session expired. Please reload the page.'], 419);
        }

        Session::flash('error', 'Your session expired. Please try again.');
        Response::back();
    }
}
