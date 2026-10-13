<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Kartu "Previous Conference" di landing.
 *
 * Edisi lama berbasis situs statis (2018–2025) tersimpan sebagai baris biasa.
 * Edisi yang dikelola sistem ini otomatis ditambahkan begitu sudah lewat:
 * saat CENIM 2027 aktif, CENIM 2026 muncul sendiri (sync()), memakai logo
 * edisinya. Admin tinggal melengkapi tautan IEEE Xplore / situs bila ada.
 */
final class PreviousConferenceRepo
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM previous_conferences WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> semua baris, tahun naik */
    public static function all(): array
    {
        self::sync();

        return Db::all('SELECT * FROM previous_conferences ORDER BY year');
    }

    /** Yang tampil di landing: tahun < edisi aktif, terlihat, tahun naik. */
    public static function forLanding(): array
    {
        self::sync();
        $year = (int) (EditionRepo::active()['year'] ?? date('Y'));

        return Db::all('SELECT * FROM previous_conferences WHERE year < ? AND is_visible = 1 ORDER BY year', [$year]);
    }

    /**
     * Tambahkan edisi yang sudah lewat (tahun < edisi aktif) yang belum punya
     * kartu. Idempoten; murah dipanggil di setiap render.
     */
    public static function sync(): int
    {
        $active = EditionRepo::active();
        if ($active === null || !Db::tableExists('previous_conferences')) {
            return 0;
        }

        $missing = Db::all(
            'SELECT e.* FROM editions e
             LEFT JOIN previous_conferences p ON p.year = e.year
             WHERE e.year < ? AND p.id IS NULL',
            [(int) $active['year']]
        );

        foreach ($missing as $edition) {
            Db::insert('previous_conferences', [
                'year'       => (int) $edition['year'],
                'title'      => (string) $edition['name'],
                'site_url'   => null,
                'xplore_url' => null,
                'created_at' => now(),
            ]);
        }

        return count($missing);
    }

    public static function save(?int $id, array $data): int
    {
        $row = [
            'year'       => max(2000, min(2100, (int) ($data['year'] ?? 0))),
            'title'      => mb_substr(trim((string) ($data['title'] ?? '')), 0, 120),
            'site_url'   => self::nullable($data['site_url'] ?? null),
            'xplore_url' => self::nullable($data['xplore_url'] ?? null),
            'is_visible' => !empty($data['is_visible']) ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($row['title'] === '') {
            $row['title'] = 'CENIM ' . $row['year'];
        }

        if ($id !== null && $id > 0) {
            Db::update('previous_conferences', $row, 'id = :target_id', ['target_id' => $id]);
            return $id;
        }

        return Db::insert('previous_conferences', $row + ['created_at' => now()]);
    }

    public static function setImage(int $id, ?int $fileId): void
    {
        Db::update('previous_conferences', ['image_file_id' => $fileId, 'updated_at' => now()], 'id = :target_id', ['target_id' => $id]);
    }

    public static function delete(int $id): void
    {
        Db::delete('previous_conferences', 'id = ?', [$id]);
    }

    /** Gambar kartu: unggahan admin → aset tema → logo edisi tahun itu → logo bawaan. */
    public static function imageUrl(array $row): string
    {
        $fileId = (int) ($row['image_file_id'] ?? 0);
        if ($fileId > 0 && ($file = FileRepo::find($fileId)) !== null) {
            return public_file_url($file);
        }

        if (($row['image_path'] ?? '') !== '') {
            return asset((string) $row['image_path']);
        }

        $edition = Db::one('SELECT * FROM editions WHERE year = ?', [(int) $row['year']]);

        return BrandingRepo::logoUrl($edition ?? ['id' => 0]);
    }

    /** "2024/" (relatif web root) → /conf/cenim/2024/; URL penuh dipakai apa adanya. */
    public static function siteUrl(array $row): ?string
    {
        $url = trim((string) ($row['site_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : base_url($url);
    }

    private static function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, 300);
    }
}
