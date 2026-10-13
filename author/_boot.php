<?php
/**
 * Bootstrap bersama semua halaman author/panitia (kecuali login.php dan logout.php).
 *
 * Bawaan: halaman butuh paper aktif (registrasi, invoice, pembayaran, ...).
 * Halaman yang juga untuk panitia murni (dashboard, sertifikat) mendefinisikan
 * AUTHOR_ALLOW_NO_PAPER sebelum me-require file ini.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AuthorAuth;
use Cenim\Domain\EditionRepo;

panel_boot('author');

defined('AUTHOR_ALLOW_NO_PAPER') ? AuthorAuth::requireLogin() : AuthorAuth::requirePaper();

// Edisi konteks = edisi paper yang dipilih. Paper edisi lama dibuka sebagai
// arsip: dokumen & sertifikat tetap bisa diunduh, form tidak bisa diubah.
if (($cenimPaper = AuthorAuth::current()) !== null && (int) $cenimPaper['edition_id'] !== EditionRepo::activeId()) {
    EditionRepo::setContext(EditionRepo::find((int) $cenimPaper['edition_id']));
}

/** Edisi paper yang sedang dibuka (edisi aktif bila panitia murni). */
function current_edition(): ?array
{
    return EditionRepo::context();
}

/** Paper yang dibuka milik edisi yang sudah lewat → semua form read-only. */
function edition_is_archive(): bool
{
    return EditionRepo::isArchive();
}

/** Paper yang sedang dipilih (baris tabel authors), atau null untuk panitia murni. */
function current_author(): ?array
{
    return AuthorAuth::current();
}

/** Baris paper_authors milik orang yang login pada paper terpilih. */
function current_person(): ?array
{
    return AuthorAuth::me();
}

/**
 * Catat siapa yang mengisi form (registrasi/pembayaran) — semua author paper
 * boleh mengisi, dan semuanya melihat label "Filled by".
 */
function mark_filled_by(string $table, int $id): void
{
    $person = current_person();
    if ($person === null || !in_array($table, ['registrations', 'payments'], true)) {
        return;
    }

    Cenim\Core\Db::update($table, [
        'filled_by_id'   => (int) $person['id'],
        'filled_by_name' => mb_substr($person['name'] . ' (' . ($person['email'] ?? '-') . ')', 0, 255),
    ], 'id = :target_id', ['target_id' => $id]);
}
