<?php

declare(strict_types=1);

namespace Cenim\Core;

use RuntimeException;

/**
 * Migrasi berbasis file SQL di db/migrations, dijalankan berurutan sekali saja.
 * Nama file: 001_init.sql, 002_xxx.sql, ...
 */
final class Migrator
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? CENIM_ROOT . '/db/migrations';
    }

    /**
     * @return array<int,string> daftar migrasi yang baru dijalankan
     */
    public function migrate(): array
    {
        $this->ensureTable();

        $applied = array_map(
            static fn (array $row): string => (string) $row['filename'],
            Db::all('SELECT filename FROM migrations')
        );

        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException("Migrasi tidak bisa dibaca atau kosong: {$name}");
            }

            // PRAGMA foreign_keys tidak boleh diubah di dalam transaksi, jadi dibuang di sini.
            $sql = preg_replace('/^\s*PRAGMA\s+foreign_keys[^;]*;/im', '', $sql) ?? $sql;

            Db::transaction(static function () use ($sql, $name): void {
                Db::pdo()->exec($sql);
                Db::insert('migrations', ['filename' => $name, 'applied_at' => now()]);
            });

            $ran[] = $name;
        }

        return $ran;
    }

    /** @return array<int,array{filename:string,applied_at:string}> */
    public function status(): array
    {
        $this->ensureTable();
        /** @var array<int,array{filename:string,applied_at:string}> $rows */
        $rows = Db::all('SELECT filename, applied_at FROM migrations ORDER BY id');
        return $rows;
    }

    public function pending(): array
    {
        $this->ensureTable();
        $applied = array_column($this->status(), 'filename');
        $files   = array_map('basename', glob($this->dir . '/*.sql') ?: []);
        sort($files, SORT_STRING);
        return array_values(array_diff($files, $applied));
    }

    private function ensureTable(): void
    {
        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                filename   TEXT NOT NULL UNIQUE,
                applied_at TEXT NOT NULL
            )'
        );
    }
}
