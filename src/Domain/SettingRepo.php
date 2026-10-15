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
}
