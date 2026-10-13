<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Poster CFP dan galeri foto di bagian About landing (per edisi).
 */
final class GalleryRepo
{
    public const PHOTO_MIN_WIDTH  = 800;
    public const POSTER_MIN_WIDTH = 800;
    public const MAX_BYTES        = 5 * 1048576;

    // ── Poster CFP ──────────────────────────────────────────────────────────

    public static function posterFile(int $editionId): ?array
    {
        $file = FileRepo::find((int) SettingRepo::get($editionId, 'cfp_poster_file_id', 0));

        return $file !== null && ($file['deleted_at'] ?? null) === null ? $file : null;
    }

    /**
     * Poster yang tampil: unggahan admin, atau gambar About (perilaku lama)
     * bila belum ada unggahan. null bila tombol poster dimatikan admin.
     */
    public static function posterUrl(int $editionId): ?string
    {
        if (!SettingRepo::getBool($editionId, 'cfp_poster_enabled', true)) {
            return null;
        }

        $file = self::posterFile($editionId);
        if ($file !== null) {
            return public_file_url($file);
        }

        $about = trim(ContentRepo::text($editionId, 'about.image', 'home'));

        return $about !== '' ? asset($about) : null;
    }

    // ── Foto ────────────────────────────────────────────────────────────────

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM gallery_photos WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function photos(int $editionId, bool $onlyVisible = false): array
    {
        return Db::all(
            'SELECT * FROM gallery_photos WHERE edition_id = ?' . ($onlyVisible ? ' AND is_visible = 1' : '') . ' ORDER BY sort, id',
            [$editionId]
        );
    }

    public static function add(int $editionId, int $fileId, string $caption): int
    {
        return Db::insert('gallery_photos', [
            'edition_id' => $editionId,
            'file_id'    => $fileId,
            'caption'    => mb_substr(trim($caption), 0, 190) ?: null,
            'sort'       => (int) Db::value('SELECT COALESCE(MAX(sort), 0) + 10 FROM gallery_photos WHERE edition_id = ?', [$editionId]),
            'created_at' => now(),
        ]);
    }

    public static function update(int $id, array $data): void
    {
        Db::update('gallery_photos', [
            'caption'    => mb_substr(trim((string) ($data['caption'] ?? '')), 0, 190) ?: null,
            'sort'       => (int) ($data['sort'] ?? 0),
            'is_visible' => !empty($data['is_visible']) ? 1 : 0,
            'updated_at' => now(),
        ], 'id = :target_id', ['target_id' => $id]);
    }

    public static function delete(int $id): void
    {
        $row = self::find($id);
        Db::delete('gallery_photos', 'id = ?', [$id]);

        // Berkas hanya dihapus bila tidak dipakai edisi lain (hasil salin edisi).
        $fileId = (int) ($row['file_id'] ?? 0);
        if ($fileId > 0 && Db::count('gallery_photos', 'file_id = ?', [$fileId]) === 0) {
            FileRepo::softDelete($fileId);
        }
    }

    public static function photoUrl(array $row): ?string
    {
        $fileId = (int) ($row['file_id'] ?? 0);
        if ($fileId > 0 && ($file = FileRepo::find($fileId)) !== null && ($file['deleted_at'] ?? null) === null) {
            return public_file_url($file);
        }

        return ($row['image_path'] ?? '') !== '' ? asset((string) $row['image_path']) : null;
    }

    /**
     * Periksa gambar unggahan (PNG/JPG/WebP, lebar minimal, ukuran berkas).
     *
     * @return string|null pesan error
     */
    public static function checkImage(array $upload, int $minWidth): ?string
    {
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Upload failed.';
        }

        $info = @getimagesize((string) $upload['tmp_name']);

        return match (true) {
            $info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) => 'Only PNG or JPG images are accepted.',
            (int) $upload['size'] > upload_max_bytes(self::MAX_BYTES) => 'Maximum file size is ' . upload_max_label(self::MAX_BYTES) . '.',
            $info[0] < $minWidth => sprintf('The image is %d px wide; use at least %d px.', $info[0], $minWidth),
            default => null,
        };
    }
}
