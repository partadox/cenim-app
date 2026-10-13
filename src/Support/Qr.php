<?php

declare(strict_types=1);

namespace Cenim\Support;

use RuntimeException;

/**
 * Pembuat QR code untuk verifikasi sertifikat.
 *
 * Membungkus vendor-lite/phpqrcode. Library itu ditulis untuk PHP 5 dan
 * memicu notice E_DEPRECATED pada PHP 8.4 (urutan parameter opsional).
 * Kodenya TIDAK kita ubah; notice-nya dibungkam hanya selama library dimuat
 * dan dipakai, dan keluaran tak terduga ditangkap output buffer agar tidak
 * pernah menyusup ke dalam PDF.
 */
final class Qr
{
    private static bool $loaded = false;

    /**
     * Tulis QR code sebagai PNG dan kembalikan path-nya.
     *
     * @param string $text        isi QR (URL verifikasi)
     * @param string $absolutePath tujuan file .png
     * @param int    $scale       ukuran piksel per modul (4 ≈ 100 px untuk URL pendek)
     * @param int    $margin      lebar margin putih dalam modul
     */
    public static function png(string $text, string $absolutePath, int $scale = 6, int $margin = 1): string
    {
        if (!ensure_dir(dirname($absolutePath))) {
            throw new RuntimeException('Folder QR tidak writable: ' . dirname($absolutePath));
        }

        self::withSilencedDeprecations(static function () use ($text, $absolutePath, $scale, $margin): void {
            // QR_ECLEVEL_M: cukup tahan terhadap cetakan yang kurang tajam.
            \QRcode::png($text, $absolutePath, QR_ECLEVEL_M, $scale, $margin);
        });

        if (!is_file($absolutePath) || filesize($absolutePath) === 0) {
            throw new RuntimeException('QR code gagal dibuat.');
        }

        return $absolutePath;
    }

    /** QR sebagai data URI, untuk ditampilkan di halaman HTML. */
    public static function dataUri(string $text, int $scale = 5): string
    {
        $temp = storage_path('tmp/qr-' . random_token(8) . '.png');

        try {
            self::png($text, $temp, $scale);
            $bytes = (string) file_get_contents($temp);
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }

        return 'data:image/png;base64,' . base64_encode($bytes);
    }

    private static function withSilencedDeprecations(callable $callback): void
    {
        $previous = error_reporting();
        error_reporting($previous & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        ob_start();

        try {
            if (!self::$loaded) {
                require_once CENIM_ROOT . '/vendor-lite/phpqrcode/phpqrcode.php';
                self::$loaded = true;
            }

            $callback();
        } finally {
            ob_end_clean();
            error_reporting($previous);
        }
    }
}
