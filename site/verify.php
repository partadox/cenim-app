<?php
/**
 * Halaman verifikasi sertifikat (publik, tanpa login).
 *
 * Dipanggil oleh index.php di web root untuk URL /verify.php — lihat deploy/index.php.
 * Hanya menampilkan data yang perlu untuk memastikan keaslian: jenis, nama
 * penerima, subjek, edisi, tanggal terbit, dan statusnya.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Cenim\Core\Logger;
use Cenim\Core\RateLimit;
use Cenim\Core\View;
use Cenim\Domain\CertificateRepo;

/** Render halaman verifikasi. Dipanggil dari stub root. */
function cenim_render_verify(): void
{
    $code = trim((string) (input('code', '') ?? ''));

    $certificate = null;
    $status      = 'empty';
    $locked      = 0;

    if ($code !== '') {
        // Pembatas agar halaman ini tidak dipakai menebak-nebak kode.
        $locked = RateLimit::retryAfter('verify', 'verify');

        if ($locked > 0) {
            $status = 'locked';
        } else {
            $certificate = CertificateRepo::findByCode($code);

            if ($certificate === null) {
                RateLimit::record('verify', 'verify', false);
                Logger::info('Verifikasi sertifikat: kode tidak ditemukan', ['code' => mb_substr($code, 0, 40)]);
                $status = 'not_found';
            } else {
                RateLimit::record('verify', 'verify', true);
                $status = CertificateRepo::isRevoked($certificate) ? 'revoked' : 'valid';
            }
        }
    }

    View::render('site/verify', [
        'code'        => $code,
        'certificate' => $certificate,
        'status'      => $status,
        'locked'      => $locked,
    ]);
}

// Bila diakses langsung (bukan lewat stub), tetap bisa dipakai.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    cenim_render_verify();
}
