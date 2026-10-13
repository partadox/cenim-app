<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

final class EditionRepo
{
    /** @var array<string,mixed>|null */
    private static ?array $activeCache = null;
    private static bool $activeLoaded = false;

    /** Edisi yang sedang aktif; null bila database belum di-seed. */
    public static function active(): ?array
    {
        if (self::$activeLoaded) {
            return self::$activeCache;
        }

        self::$activeLoaded = true;
        self::$activeCache = Db::tableExists('editions')
            ? Db::one('SELECT * FROM editions WHERE is_active = 1 ORDER BY year DESC LIMIT 1')
            : null;

        return self::$activeCache;
    }

    public static function activeId(): int
    {
        return (int) (self::active()['id'] ?? 0);
    }

    // ── Edisi konteks ───────────────────────────────────────────────────────
    // Edisi yang sedang dikerjakan request ini: default edisi aktif, tetapi
    // admin bisa melihat arsip edisi lama dan author membuka paper edisi lama.
    // Email, PDF, audit, logo, dan tabel admin mengikuti edisi konteks.

    private static ?array $context = null;

    public static function setContext(?array $edition): void
    {
        self::$context = $edition;
    }

    public static function context(): ?array
    {
        return self::$context ?? self::active();
    }

    public static function contextId(): int
    {
        return (int) (self::context()['id'] ?? 0);
    }

    /** Edisi konteks bukan edisi aktif (arsip, form dikunci untuk author). */
    public static function isArchive(?array $edition = null): bool
    {
        $edition ??= self::context();

        return $edition !== null && (int) $edition['id'] !== self::activeId();
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM editions WHERE id = ?', [$id]);
    }

    public static function findByYear(int $year): ?array
    {
        return Db::one('SELECT * FROM editions WHERE year = ?', [$year]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::all('SELECT * FROM editions ORDER BY year DESC');
    }

    /** Buat edisi baru (belum aktif). */
    public static function create(int $year, string $name): int
    {
        if ($year < 2018 || $year > 2100) {
            throw new \InvalidArgumentException('Please enter a valid year.');
        }
        if (self::findByYear($year) !== null) {
            throw new \InvalidArgumentException('CENIM ' . $year . ' already exists.');
        }

        $name = trim($name) !== '' ? mb_substr(trim($name), 0, 120) : 'CENIM ' . $year;

        return Db::insert('editions', [
            'year'       => $year,
            'name'       => $name,
            'slug'       => 'cenim-' . $year,
            'is_active'  => 0,
            'timezone'   => (string) env('APP_TZ', 'Asia/Jakarta'),
            'created_at' => now(),
        ]);
    }

    /**
     * Jadikan satu edisi aktif (yang lain nonaktif). Landing, panel, dan login
     * langsung memakai edisi ini; edisi yang lebih lama otomatis masuk
     * Previous Conference.
     */
    public static function activate(int $id): void
    {
        if (self::find($id) === null) {
            throw new \InvalidArgumentException('Edition not found.');
        }

        Db::transaction(static function () use ($id): void {
            Db::run('UPDATE editions SET is_active = 0, updated_at = ? WHERE id != ?', [now(), $id]);
            Db::run('UPDATE editions SET is_active = 1, updated_at = ? WHERE id = ?', [now(), $id]);
        });

        self::$activeCache  = null;
        self::$activeLoaded = false;
    }
}
