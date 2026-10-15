<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Blok konten landing page.
 *
 * Prinsip penting: setiap blok punya **nilai bawaan** di ContentDefaults, jadi
 * halaman tampil persis seperti sebelum ada CMS bila belum pernah diedit —
 * tidak ada risiko halaman jadi kosong. Database hanya menyimpan blok yang
 * benar-benar diubah admin, sehingga:
 *   - editor bisa menampilkan nilai yang SEDANG aktif (bukan kolom kosong),
 *   - mengosongkan kolom = kembali ke bawaan,
 *   - memperbarui bawaan di kode ikut terlihat pada blok yang tidak dikustomisasi.
 *
 * data_json menyimpan satu nilai (`{"value": "..."}`) untuk blok teks, atau
 * struktur bebas (mis. daftar slide hero) untuk blok kompleks.
 */
final class ContentRepo
{
    public const PAGES = ['home', 'registration', 'submission', 'workshop'];

    /** @var array<string,array<string,mixed>> cache per "edition:page" */
    private static array $cache = [];

    /** @return array<string,mixed> section_key => data */
    public static function all(int $editionId, string $page = 'home'): array
    {
        $key = $editionId . ':' . $page;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $blocks = [];

        if (Db::tableExists('content_blocks')) {
            foreach (Db::all(
                'SELECT section_key, data_json, is_visible FROM content_blocks
                 WHERE edition_id = ? AND page = ? ORDER BY sort, id',
                [$editionId, $page]
            ) as $row) {
                if ((int) $row['is_visible'] !== 1) {
                    continue;
                }

                $decoded = json_decode((string) $row['data_json'], true);
                $blocks[(string) $row['section_key']] = is_array($decoded) ? $decoded : ['value' => $row['data_json']];
            }
        }

        return self::$cache[$key] = $blocks;
    }

    /** Nilai teks satu blok siap tampil (bawaan bila belum diedit, token diganti). */
    public static function text(int $editionId, string $key, string $page = 'home'): string
    {
        return self::render($editionId, self::rawText($editionId, $key, $page));
    }

    /** Nilai teks apa adanya (tanpa penggantian token) — dipakai editor. */
    public static function rawText(int $editionId, string $key, string $page = 'home'): string
    {
        $blocks = self::all($editionId, $page);
        $value  = $blocks[$key]['value'] ?? null;

        if ($value === null || !is_scalar($value) || trim((string) $value) === '') {
            $default = ContentDefaults::get($page, $key);
            return is_string($default) ? $default : '';
        }

        return (string) $value;
    }

    /** Data terstruktur satu blok (mis. daftar) siap tampil. */
    public static function data(int $editionId, string $key, string $page = 'home'): array
    {
        return self::renderAll($editionId, self::rawData($editionId, $key, $page));
    }

    /** Data apa adanya (tanpa penggantian token) — dipakai editor. */
    public static function rawData(int $editionId, string $key, string $page = 'home'): array
    {
        $blocks = self::all($editionId, $page);
        $value  = $blocks[$key] ?? null;
        $items  = null;

        if (is_array($value) && $value !== []) {
            // Blok bertipe list disimpan sebagai {"items": [...]}
            $items = isset($value['items']) && is_array($value['items']) ? $value['items'] : $value;

            if ($items === []) {
                $items = null;
            }
        }

        if ($items !== null) {
            return $items;
        }

        $default = ContentDefaults::get($page, $key);

        return is_array($default) ? $default : [];
    }

    /**
     * Nilai yang BERLAKU untuk satu blok, dalam bentuk siap ditaruh di form
     * editor: string untuk text/html, JSON rapi untuk list.
     */
    public static function editorValue(int $editionId, string $key, string $kind, string $page = 'home'): string
    {
        if ($kind === 'list') {
            return (string) json_encode(
                self::rawData($editionId, $key, $page),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        return self::rawText($editionId, $key, $page);
    }

    /**
     * Apakah nilai yang dikirim admin sama dengan bawaan? Kalau sama, blok tidak
     * perlu disimpan — biar tetap ikut kalau bawaannya diperbarui nanti.
     */
    public static function matchesDefault(string $key, string $kind, string $value, string $page = 'home'): bool
    {
        $default = ContentDefaults::get($page, $key);

        if ($kind === 'list') {
            $decoded = json_decode($value, true);

            return is_array($decoded) && is_array($default)
                && json_encode(array_values($decoded)) === json_encode(array_values($default));
        }

        return is_string($default) && trim($value) === trim($default);
    }

    /**
     * Ganti token dengan nilai hidup. Berlaku untuk teks bawaan MAUPUN teks yang
     * sudah diedit admin, jadi mis. "{included_pages} pages" tetap sinkron
     * dengan tabel Fees setelah admin menulis ulang kalimatnya.
     *
     * @return array<string,string>
     */
    public static function tokens(int $editionId): array
    {
        $edition = EditionRepo::find($editionId);

        return [
            '{included_pages}'      => (string) FeeEngine::includedPages($editionId),
            '{extra_paper_percent}' => (string) (int) FeeEngine::extraPaperPercent($editionId),
            '{edition_name}'        => (string) ($edition['name'] ?? cfg('app_name')),
            '{edition_year}'        => (string) ($edition['year'] ?? date('Y')),
            '{conference_dates}'    => self::conferenceDates($edition ?? []),
        ];
    }

    /** "24-25 Nov 2026" / "30 Nov - 1 Dec 2026" dari tanggal konferensi edisi. */
    public static function conferenceDates(array $edition): string
    {
        $start = strtotime((string) ($edition['conference_start'] ?? '') . ' 00:00:00 UTC');
        $end   = strtotime((string) ($edition['conference_end'] ?? '') . ' 00:00:00 UTC');

        if (($edition['conference_start'] ?? '') === '' || $start === false) {
            return 'TBA ' . ($edition['year'] ?? '');
        }
        if (($edition['conference_end'] ?? '') === '' || $end === false || $end === $start) {
            return gmdate('j M Y', $start);
        }

        return gmdate('m', $start) === gmdate('m', $end)
            ? gmdate('j', $start) . '-' . gmdate('j M Y', $end)
            : gmdate('j M', $start) . ' - ' . gmdate('j M Y', $end);
    }

    public static function render(int $editionId, string $value): string
    {
        if (!str_contains($value, '{')) {
            return $value;
        }

        $tokens = self::tokens($editionId);

        return str_replace(array_keys($tokens), array_values($tokens), $value);
    }

    /** Penggantian token untuk semua string di dalam struktur (mis. item list). */
    private static function renderAll(int $editionId, array $items): array
    {
        array_walk_recursive($items, static function (&$value) use ($editionId): void {
            if (is_string($value)) {
                $value = self::render($editionId, $value);
            }
        });

        return $items;
    }

    public static function set(int $editionId, string $key, array $data, string $page = 'home', ?string $actor = 'admin'): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $params = [
            'edition_id'  => $editionId,
            'page'        => $page,
            'section_key' => $key,
            'data_json'   => $json,
            'updated_at'  => now(),
            'updated_by'  => $actor,
        ];

        // Tanpa upsert (ON CONFLICT butuh SQLite 3.24; server bisa lebih lama).
        Db::transaction(static function () use ($params, $data): void {
            Db::run(
                'INSERT OR IGNORE INTO content_blocks (edition_id, page, section_key, sort, type, data_json, is_visible, updated_at, updated_by)
                 VALUES (:edition_id, :page, :section_key, 0, :type, :data_json, 1, :updated_at, :updated_by)',
                $params + ['type' => isset($data['items']) ? 'list' : 'text']
            );
            Db::run(
                'UPDATE content_blocks SET data_json = :data_json, updated_at = :updated_at, updated_by = :updated_by
                 WHERE edition_id = :edition_id AND page = :page AND section_key = :section_key',
                $params
            );
        });

        unset(self::$cache[$editionId . ':' . $page]);
    }

    public static function setText(int $editionId, string $key, string $value, string $page = 'home'): void
    {
        self::set($editionId, $key, ['value' => $value], $page);
    }

    public static function forget(int $editionId, string $key, string $page = 'home'): void
    {
        Db::delete('content_blocks', 'edition_id = ? AND page = ? AND section_key = ?', [$editionId, $page, $key]);
        unset(self::$cache[$editionId . ':' . $page]);
    }

    /** Nilai bawaan blok (string untuk text/html, array untuk list). */
    public static function defaultValue(string $page, string $key): string|array
    {
        return ContentDefaults::get($page, $key);
    }

    /** Apakah blok ini sudah pernah diedit admin? */
    public static function isCustomised(int $editionId, string $key, string $page = 'home'): bool
    {
        return array_key_exists($key, self::all($editionId, $page));
    }

    public static function updatedAt(int $editionId, string $key, string $page = 'home'): ?string
    {
        $value = Db::value(
            'SELECT updated_at FROM content_blocks WHERE edition_id = ? AND page = ? AND section_key = ?',
            [$editionId, $page, $key]
        );

        return $value === null ? null : (string) $value;
    }
}
