<?php

declare(strict_types=1);

namespace Cenim\Core;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Koneksi SQLite tunggal + helper query.
 * Semua query WAJIB lewat prepared statement (parameter $params).
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = (string) env('DB_PATH', '');
        if ($path === '') {
            $path = storage_path('db/cenim.sqlite');
        }

        $dir = dirname($path);
        if (!ensure_dir($dir)) {
            throw new RuntimeException("Direktori database tidak writable: {$dir}");
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // WAL agar baca tidak terblokir tulis; busy_timeout untuk antrian tulis.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        return self::$pdo = $pdo;
    }

    public static function path(): string
    {
        $path = (string) env('DB_PATH', '');
        return $path !== '' ? $path : storage_path('db/cenim.sqlite');
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalize($params));
        return $stmt;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function count(string $table, string $where = '1', array $params = []): int
    {
        return (int) self::value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        self::run(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', array_map(static fn (string $c): string => '"' . $c . '"', $columns)),
                implode(', ', $placeholders)
            ),
            $data
        );

        return (int) self::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = '"' . $column . '" = :' . $column;
        }

        $stmt = self::run(
            sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where),
            array_merge($data, $whereParams)
        );

        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run("DELETE FROM {$table} WHERE {$where}", $params)->rowCount();
    }

    /**
     * Jalankan callable di dalam transaksi. Rollback otomatis bila melempar exception.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transaksi dengan lock tulis diambil sejak awal (BEGIN IMMEDIATE).
     *
     * Dipakai untuk operasi baca-lalu-tulis yang tidak boleh balapan, mis.
     * penomoran invoice: tanpa ini dua proses bisa membaca nomor yang sama
     * sebelum salah satu menulis.
     */
    public static function immediateTransaction(callable $callback): mixed
    {
        $pdo = self::pdo();

        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        // BEGIN IMMEDIATE tidak lewat PDO::beginTransaction() agar levelnya tepat,
        // jadi commit/rollback juga dijalankan manual.
        $pdo->exec('BEGIN IMMEDIATE');

        try {
            $result = $callback($pdo);
            $pdo->exec('COMMIT');
            return $result;
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    public static function tableExists(string $table): bool
    {
        return self::value(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table]
        ) !== null;
    }

    /** SQLite tidak menerima bool; normalkan ke int/null. */
    private static function normalize(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $value ? 1 : 0;
            }
        }
        return $params;
    }
}
