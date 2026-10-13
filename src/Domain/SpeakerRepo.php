<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;
use Cenim\Storage\StorageManager;

/**
 * Keynote speaker untuk landing page.
 *
 * Foto boleh berupa berkas yang diunggah (photo_file_id) atau URL/relative path
 * ke aset tema yang sudah ada (photo_url), supaya foto lama di /assets/image/keynote
 * tetap bisa dipakai tanpa mengunggah ulang.
 */
final class SpeakerRepo
{
    public const LINK_KEYS = ['scholar', 'scopus', 'website', 'linkedin'];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM speakers WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(int $editionId, bool $onlyVisible = false): array
    {
        $sql = 'SELECT * FROM speakers WHERE edition_id = ?';
        if ($onlyVisible) {
            $sql .= ' AND is_visible = 1';
        }

        return Db::all($sql . ' ORDER BY sort, id', [$editionId]);
    }

    public static function findByName(int $editionId, string $name): ?array
    {
        return Db::one(
            'SELECT * FROM speakers WHERE edition_id = ? AND name = ? LIMIT 1',
            [$editionId, trim($name)]
        );
    }

    public static function countByEdition(int $editionId): int
    {
        return Db::count('speakers', 'edition_id = ?', [$editionId]);
    }

    /** @return array<string,string> tautan profil yang terisi */
    public static function links(array $speaker): array
    {
        $decoded = json_decode((string) ($speaker['links_json'] ?? '{}'), true);

        if (!is_array($decoded)) {
            return [];
        }

        $links = [];
        foreach (self::LINK_KEYS as $key) {
            $url = trim((string) ($decoded[$key] ?? ''));
            if ($url !== '') {
                $links[$key] = $url;
            }
        }

        return $links;
    }

    /** URL foto: berkas unggahan bila ada, kalau tidak photo_url. */
    public static function photoUrl(array $speaker): ?string
    {
        $fileId = (int) ($speaker['photo_file_id'] ?? 0);

        if ($fileId > 0) {
            $file = FileRepo::find($fileId);
            if ($file !== null) {
                return public_file_url($file);
            }
        }

        $url = trim((string) ($speaker['photo_url'] ?? ''));

        if ($url === '') {
            return null;
        }

        // Path relatif dianggap berada di dalam folder aset tema.
        return str_starts_with($url, 'http') || str_starts_with($url, '/') ? $url : asset($url);
    }

    public static function create(int $editionId, array $data): int
    {
        $row = self::normalize($data);
        $row['edition_id'] = $editionId;
        $row['created_at'] = now();

        return Db::insert('speakers', $row);
    }

    public static function update(int $id, array $data): void
    {
        $row = self::normalize($data);
        $row['updated_at'] = now();

        Db::update('speakers', $row, 'id = :target_id', ['target_id' => $id]);
    }

    public static function setPhoto(int $id, ?int $fileId): void
    {
        Db::update('speakers', [
            'photo_file_id' => $fileId,
            'updated_at'    => now(),
        ], 'id = :target_id', ['target_id' => $id]);
    }

    public static function delete(int $id): void
    {
        Db::delete('speakers', 'id = ?', [$id]);
    }

    /** @return array<string,string> */
    public static function validate(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'Name is required.';
        }

        foreach (['profile_url' => 'Profile URL'] + array_combine(
            self::LINK_KEYS,
            array_map(static fn (string $k): string => ucfirst($k) . ' URL', self::LINK_KEYS)
        ) as $field => $label) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                $errors[$field] = $label . ' is not a valid URL.';
            }
        }

        return $errors;
    }

    private static function normalize(array $data): array
    {
        $links = [];
        foreach (self::LINK_KEYS as $key) {
            $url = trim((string) ($data[$key] ?? ''));
            if ($url !== '') {
                $links[$key] = mb_substr($url, 0, 300);
            }
        }

        return [
            'name'        => mb_substr(trim((string) ($data['name'] ?? '')), 0, 190),
            'title'       => self::nullable($data['title'] ?? null, 190),
            'affiliation' => self::nullable($data['affiliation'] ?? null, 255),
            'photo_url'   => self::nullable($data['photo_url'] ?? null, 300),
            'profile_url' => self::nullable($data['profile_url'] ?? null, 300),
            'bio'         => self::nullable($data['bio'] ?? null, 2000),
            'links_json'  => $links === [] ? null : json_encode($links, JSON_UNESCAPED_SLASHES),
            'sort'        => (int) ($data['sort'] ?? 0),
            'is_visible'  => !empty($data['is_visible']) ? 1 : 0,
        ];
    }

    private static function nullable(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
