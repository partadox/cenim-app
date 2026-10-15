<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Pengaturan per edisi. Nilai disimpan sebagai JSON sehingga bisa berupa
 * skalar, list, maupun objek (lihat db/seed.php).
 */
final class SettingRepo
{
    public const DEFAULT_CHAIR_TITLE     = 'General Chair {edition_name}';
    public const DEFAULT_TREASURER_TITLE = 'Treasurer {edition_name}';

    /** @var array<int,array<string,mixed>> cache per edition_id */
    private static array $cache = [];

    public static function get(int $editionId, string $key, mixed $default = null): mixed
    {
        $all = self::all($editionId);
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Nilai teks satu setting.
     *
     * Nilai tersimpan yang KOSONG dianggap "belum diisi", jadi teks bawaan yang
     * dipakai — konsisten dengan editor konten landing: mengosongkan kolom
     * berarti kembali ke bawaan, bukan menghilangkan teksnya.
     */
    public static function getString(int $editionId, string $key, string $default = ''): string
    {
        $value = self::get($editionId, $key, $default);

        if (!is_scalar($value)) {
            return $default;
        }

        $text = trim((string) $value);

        return $text === '' ? $default : (string) $value;
    }

    public static function getBool(int $editionId, string $key, bool $default = false): bool
    {
        $value = self::get($editionId, $key, $default);
        return is_bool($value) ? $value : in_array($value, [1, '1', 'true', 'on'], true);
    }

    public static function getArray(int $editionId, string $key, array $default = []): array
    {
        $value = self::get($editionId, $key, $default);
        return is_array($value) ? $value : $default;
    }

    /** @return array<string,mixed> */
    public static function all(int $editionId): array
    {
        if (isset(self::$cache[$editionId])) {
            return self::$cache[$editionId];
        }

        $values = [];
        if (Db::tableExists('settings')) {
            foreach (Db::all('SELECT "key", value_json FROM settings WHERE edition_id = ?', [$editionId]) as $row) {
                $values[(string) $row['key']] = json_decode((string) $row['value_json'], true);
            }
        }

        return self::$cache[$editionId] = $values;
    }

    public static function set(int $editionId, string $key, mixed $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $params = [
            'edition_id' => $editionId,
            'key'        => $key,
            'value_json' => $json,
            'updated_at' => now(),
        ];

        // Tanpa upsert (ON CONFLICT butuh SQLite 3.24; server bisa lebih lama).
        Db::transaction(static function () use ($params): void {
            Db::run(
                'INSERT OR IGNORE INTO settings (edition_id, "key", value_json, updated_at)
                 VALUES (:edition_id, :key, :value_json, :updated_at)',
                $params
            );
            Db::run(
                'UPDATE settings SET value_json = :value_json, updated_at = :updated_at
                 WHERE edition_id = :edition_id AND "key" = :key',
                $params
            );
        });

        unset(self::$cache[$editionId]);
    }

    /**
     * Chair konferensi — satu sumber untuk LoA, receipt, sertifikat, dan
     * penutup email. Nilai lama dari Settings → Email (mail_signature_*)
     * dipakai sebagai cadangan agar data yang sudah diisi tidak hilang.
     *
     * @return array{name:string,title:string}
     */
    public static function chair(int $editionId): array
    {
        $edition = EditionRepo::find($editionId) ?? [];
        $name    = self::getString($editionId, 'signatory_name');
        $title   = self::getString($editionId, 'signatory_title');

        if ($name === '') {
            $name = self::getString($editionId, 'mail_signature_name');
        }
        if ($title === '') {
            $title = self::getString($editionId, 'mail_signature_role', self::DEFAULT_CHAIR_TITLE);
        }

        return ['name' => $name, 'title' => self::editionTokens($edition, $title)];
    }

    /**
     * Treasurer konferensi — menandatangani invoice dan receipt.
     *
     * @return array{name:string,title:string}
     */
    public static function treasurer(int $editionId): array
    {
        $edition = EditionRepo::find($editionId) ?? [];

        return [
            'name'  => self::getString($editionId, 'treasurer_name'),
            'title' => self::editionTokens($edition, self::getString($editionId, 'treasurer_title', self::DEFAULT_TREASURER_TITLE)),
        ];
    }

    private static function editionTokens(array $edition, string $text): string
    {
        return trim(strtr($text, [
            '{edition_name}' => (string) ($edition['name'] ?? cfg('app_name')),
            '{edition_year}' => (string) ($edition['year'] ?? date('Y')),
        ]));
    }
}
