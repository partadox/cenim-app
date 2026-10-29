<?php
/**
 * Penyaji file privat: /cenim-app/public/file.php?id=<file id>
 *
 * File upload disimpan di luar web root, jadi satu-satunya jalan membukanya
 * adalah lewat skrip ini — dan hanya setelah otorisasi diperiksa:
 *   - admin yang sudah login boleh membuka semua file
 *   - author (first author maupun co-author) hanya boleh membuka file paper miliknya
 *   - purpose publik (FileRepo::PUBLIC_PURPOSES, mis. foto speaker) terbuka untuk semua
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AdminAuth;
use Cenim\Core\AuthorAuth;
use Cenim\Core\Db;
use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Domain\FileRepo;
use Cenim\Storage\StorageManager;

panel_boot('file');

$file = FileRepo::find(input_int('id'));

if ($file === null) {
    Response::abort(404, 'File not found.');
}

/** Apakah file ini milik author yang sedang login? */
$belongsToAuthor = static function (array $file, int $authorId): bool {
    $ownerType = (string) ($file['owner_type'] ?? '');
    $ownerId   = (int) ($file['owner_id'] ?? 0);

    return match ($ownerType) {
        'registration' => Db::value(
            'SELECT 1 FROM registrations WHERE id = ? AND author_id = ?',
            [$ownerId, $authorId]
        ) !== null,
        'payment' => Db::value(
            'SELECT 1 FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN registrations r ON r.id = i.registration_id
             WHERE p.id = ? AND r.author_id = ?',
            [$ownerId, $authorId]
        ) !== null,
        // Dokumen tambahan dari panitia (AuthorDocumentRepo): milik paper/peserta itu.
        'author' => $ownerId === $authorId,
        default => false,
    };
};

// Gambar yang memang tampil di landing (foto speaker) boleh dilihat siapa saja.
$allowed = in_array((string) $file['purpose'], FileRepo::PUBLIC_PURPOSES, true);

if ($allowed) {
    // lanjut tanpa sesi
} elseif (AdminAuth::isLoggedIn()) {
    $allowed = true;
} elseif (
    // Latar & tanda tangan template sertifikat: dimuat oleh halaman sertifikat
    // HTML milik author/panitia yang sedang login.
    in_array((string) $file['purpose'], ['certificate_background', 'certificate_signature'], true)
    && AuthorAuth::isLoggedIn()
) {
    $allowed = true;
} else {
    // Semua author sebuah paper boleh membuka berkas paper itu.
    foreach (AuthorAuth::papers() as $paper) {
        if ($belongsToAuthor($file, (int) $paper['id'])) {
            $allowed = true;
            break;
        }
    }
}

if (!$allowed) {
    Logger::warn('Akses file ditolak', ['file_id' => (int) $file['id'], 'ip' => client_ip()]);
    Response::abort(403, 'You are not allowed to open this file.');
}

try {
    $path = StorageManager::forFile($file)->localPath($file);
} catch (Throwable $e) {
    Logger::error('Gagal mengambil berkas dari storage', ['file_id' => (int) $file['id'], 'error' => $e->getMessage()]);
    Response::abort(503, 'The file storage is temporarily unavailable. Please try again later.');
}

if ($path === null) {
    Response::abort(404, 'The stored file is no longer available.');
}

$inline = in_array((string) $file['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true);

Response::file($path, (string) ($file['original_name'] ?? basename($path)), $inline);
