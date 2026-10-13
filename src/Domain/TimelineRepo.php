<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Audit;
use Cenim\Core\Db;

/**
 * Timeline / jadwal konferensi.
 *
 * Ciri khas CENIM: tenggat sering diperpanjang, dan tanggal lama TIDAK dihapus
 * melainkan ditampilkan ber-strikethrough. Karena itu setiap perpanjangan
 * memindahkan tanggal berjalan ke history_json, bukan menimpanya.
 */
final class TimelineRepo
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM timeline_items WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(int $editionId, bool $onlyVisible = false): array
    {
        $sql = 'SELECT * FROM timeline_items WHERE edition_id = ?';
        if ($onlyVisible) {
            $sql .= ' AND is_visible = 1';
        }

        return Db::all($sql . ' ORDER BY sort, id', [$editionId]);
    }

    /** @return array<int,string> tanggal-tanggal lama yang sudah dilewati */
    public static function history(array $item): array
    {
        $decoded = json_decode((string) ($item['history_json'] ?? '[]'), true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $decoded
        ), static fn (string $v): bool => $v !== ''));
    }

    public static function create(int $editionId, array $data): int
    {
        return Db::insert('timeline_items', [
            'edition_id'        => $editionId,
            'sort'              => (int) ($data['sort'] ?? 0),
            'title'             => mb_substr(trim((string) ($data['title'] ?? '')), 0, 190),
            'current_date_text' => mb_substr(trim((string) ($data['current_date_text'] ?? '')), 0, 190),
            'history_json'      => '[]',
            'note'             => self::nullable($data['note'] ?? null),
            'is_visible'        => !empty($data['is_visible']) ? 1 : 0,
            'created_at'        => now(),
        ]);
    }

    /** Ubah tanpa menyentuh riwayat (untuk koreksi salah ketik). */
    public static function update(int $id, array $data): void
    {
        Db::update('timeline_items', [
            'sort'              => (int) ($data['sort'] ?? 0),
            'title'             => mb_substr(trim((string) ($data['title'] ?? '')), 0, 190),
            'current_date_text' => mb_substr(trim((string) ($data['current_date_text'] ?? '')), 0, 190),
            'note'              => self::nullable($data['note'] ?? null),
            'is_visible'        => !empty($data['is_visible']) ? 1 : 0,
            'updated_at'        => now(),
        ], 'id = :target_id', ['target_id' => $id]);
    }

    /**
     * Perpanjang tenggat: tanggal berjalan masuk ke riwayat (dicoret di landing),
     * tanggal baru menjadi tanggal berjalan.
     */
    public static function extend(int $id, string $newDate, string $actor = 'admin'): bool
    {
        $item = self::find($id);

        if ($item === null || trim($newDate) === '') {
            return false;
        }

        $history   = self::history($item);
        $history[] = (string) $item['current_date_text'];

        Db::update('timeline_items', [
            'current_date_text' => mb_substr(trim($newDate), 0, 190),
            'history_json'      => json_encode(array_values($history), JSON_UNESCAPED_UNICODE),
            'updated_at'        => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        Audit::log('timeline.extended', [
            'actor_type'  => 'admin',
            'actor_id'    => $actor,
            'target_type' => 'timeline',
            'target_id'   => $id,
            'meta'        => ['title' => $item['title'], 'from' => $item['current_date_text'], 'to' => $newDate],
        ]);

        return true;
    }

    /** Batalkan perpanjangan terakhir (kalau salah input). */
    public static function undoExtend(int $id): bool
    {
        $item = self::find($id);

        if ($item === null) {
            return false;
        }

        $history = self::history($item);

        if ($history === []) {
            return false;
        }

        $previous = array_pop($history);

        Db::update('timeline_items', [
            'current_date_text' => $previous,
            'history_json'      => json_encode(array_values($history), JSON_UNESCAPED_UNICODE),
            'updated_at'        => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        return true;
    }

    public static function delete(int $id): void
    {
        Db::delete('timeline_items', 'id = ?', [$id]);
    }

    /** @return array<string,string> */
    public static function validate(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors['title'] = 'Title is required.';
        }

        if (trim((string) ($data['current_date_text'] ?? '')) === '') {
            $errors['current_date_text'] = 'Date is required (free text, e.g. "12 August 2026").';
        }

        return $errors;
    }

    private static function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : mb_substr($text, 0, 500);
    }
}
