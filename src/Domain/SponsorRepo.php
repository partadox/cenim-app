<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Sponsor & institusi pendukung per edisi (slider logo di bawah landing).
 */
final class SponsorRepo
{
    /** Syarat logo sponsor (ditampilkan di halaman admin). */
    public const LOGO_MIN_HEIGHT = 80;
    public const LOGO_MAX_BYTES  = 2 * 1048576;

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM sponsors WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(int $editionId, bool $onlyVisible = false): array
    {
        return Db::all(
            'SELECT * FROM sponsors WHERE edition_id = ?' . ($onlyVisible ? ' AND is_visible = 1' : '') . ' ORDER BY sort, id',
            [$editionId]
        );
    }

    public static function save(int $editionId, ?int $id, array $data): int
    {
        $row = [
            'name'       => mb_substr(trim((string) ($data['name'] ?? '')), 0, 190),
            'role'       => self::nullable($data['role'] ?? null, 120),
            'url'        => self::nullable($data['url'] ?? null, 300),
            'sort'       => (int) ($data['sort'] ?? 0),
            'is_visible' => !empty($data['is_visible']) ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($row['url'] !== null && !preg_match('#^https?://#i', $row['url'])) {
            $row['url'] = 'https://' . $row['url'];
        }

        if ($id !== null && $id > 0) {
            Db::update('sponsors', $row, 'id = :target_id AND edition_id = :edition_id', ['target_id' => $id, 'edition_id' => $editionId]);
            return $id;
        }

        if ($row['sort'] === 0) {
            $row['sort'] = (int) Db::value('SELECT COALESCE(MAX(sort), 0) + 10 FROM sponsors WHERE edition_id = ?', [$editionId]);
        }

        return Db::insert('sponsors', $row + ['edition_id' => $editionId, 'created_at' => now()]);
    }

    public static function setLogo(int $id, int $fileId): void
    {
        Db::update('sponsors', ['logo_file_id' => $fileId, 'updated_at' => now()], 'id = :target_id', ['target_id' => $id]);
    }

    public static function delete(int $id): void
    {
        Db::delete('sponsors', 'id = ?', [$id]);
    }

    /** Logo: unggahan admin → aset tema → null. */
    public static function logoUrl(array $row): ?string
    {
        $fileId = (int) ($row['logo_file_id'] ?? 0);
        if ($fileId > 0 && ($file = FileRepo::find($fileId)) !== null && ($file['deleted_at'] ?? null) === null) {
            return public_file_url($file);
        }

        return ($row['logo_path'] ?? '') !== '' ? asset((string) $row['logo_path']) : null;
    }

    private static function nullable(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
