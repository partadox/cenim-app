<?php

declare(strict_types=1);

namespace Cenim\Support;

use Cenim\Core\Audit;
use Cenim\Core\Db;
use Cenim\Core\Logger;
use RuntimeException;

/**
 * Backup data ke `storage/backup/`.
 *
 * Dua jenis arsip, sengaja dipisah karena umur dan ukurannya beda jauh:
 *   - `cenim-db-<stamp>.sqlite.gz`  — snapshot database (kecil, isinya semua)
 *   - `cenim-uploads-<stamp>.zip`   — berkas unggahan author (besar, tidak bisa
 *                                     dibuat ulang: bukti student/IEEE & transfer)
 *
 * `generated/` sengaja TIDAK di-backup: invoice, receipt, sertifikat, logo, dan
 * kop semuanya bisa dibuat ulang dari database.
 *
 * Snapshot database memakai online backup API SQLite (SQLite3::backup) atau
 * `VACUUM INTO` supaya konsisten walau ada penulis lain (mode WAL) — menyalin
 * file .sqlite mentah saat ada transaksi berjalan bisa menghasilkan arsip
 * yang rusak. VACUUM INTO baru ada di SQLite 3.27; backup API ada di semua
 * versi, jadi itu yang diutamakan.
 */
final class Backup
{
    public const DEFAULT_KEEP = 14;

    /**
     * @param  array{files?: bool, keep?: int, label?: string} $options
     * @return array<string,mixed> ringkasan untuk ditampilkan/di-log
     */
    public static function run(array $options = []): array
    {
        $withFiles = $options['files'] ?? true;
        $keep      = max(1, (int) ($options['keep'] ?? self::DEFAULT_KEEP));
        $dir       = storage_path('backup');

        if (!ensure_dir($dir)) {
            throw new RuntimeException('Folder backup tidak writable: ' . $dir);
        }

        $stamp   = gmdate('Ymd-His');
        $created = [];

        $created['database'] = self::snapshotDatabase($dir . '/cenim-db-' . $stamp . '.sqlite.gz');

        if ($withFiles) {
            $uploads = self::archiveUploads($dir . '/cenim-uploads-' . $stamp . '.zip');
            if ($uploads !== null) {
                $created['uploads'] = $uploads;
            }
        }

        $removed = array_merge(
            self::prune($dir, 'cenim-db-*.sqlite.gz', $keep),
            self::prune($dir, 'cenim-uploads-*.zip', $keep)
        );

        $summary = [
            'stamp'   => $stamp,
            'created' => $created,
            'removed' => $removed,
            'keep'    => $keep,
            'dir'     => $dir,
        ];

        Logger::info('Backup selesai', [
            'created' => array_map(static fn (array $f): string => basename($f['path']), $created),
            'removed' => count($removed),
        ]);

        Audit::log('backup.created', [
            'meta' => [
                'files'   => array_map(static fn (array $f): string => basename($f['path']), $created),
                'bytes'   => array_sum(array_column($created, 'size')),
                'removed' => $removed,
            ],
        ]);

        return $summary;
    }

    /** @return array{path:string,size:int} */
    private static function snapshotDatabase(string $target): array
    {
        $plain = substr($target, 0, -3); // tanpa .gz

        if (is_file($plain)) {
            @unlink($plain);
        }

        if (class_exists(\SQLite3::class) && method_exists(\SQLite3::class, 'backup')) {
            $source = new \SQLite3(Db::path(), SQLITE3_OPEN_READONLY);
            $source->busyTimeout(5000);
            $copy   = new \SQLite3($plain);

            try {
                if (!$source->backup($copy)) {
                    throw new RuntimeException('Snapshot database gagal: ' . $source->lastErrorMsg());
                }
            } finally {
                $copy->close();
                $source->close();
            }
        } else {
            // VACUUM INTO menolak menulis ke file yang sudah ada.
            Db::pdo()->exec("VACUUM INTO " . Db::pdo()->quote($plain));
        }

        if (!is_file($plain)) {
            throw new RuntimeException('Snapshot database gagal dibuat: ' . $plain);
        }

        self::gzip($plain, $target);
        @unlink($plain);

        return ['path' => $target, 'size' => (int) filesize($target)];
    }

    private static function gzip(string $source, string $target): void
    {
        $in  = fopen($source, 'rb');
        $out = gzopen($target, 'wb9');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            throw new RuntimeException('Gagal mengompres snapshot database.');
        }

        while (!feof($in)) {
            $chunk = fread($in, 1 << 20);
            if ($chunk === false) {
                break;
            }
            gzwrite($out, $chunk);
        }

        fclose($in);
        gzclose($out);
    }

    /** @return array{path:string,size:int,entries:int}|null null bila tidak ada berkas unggahan */
    private static function archiveUploads(string $target): ?array
    {
        $root = storage_path('uploads');

        if (!is_dir($root)) {
            return null;
        }

        $files = [];
        $stack = [$root];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach (scandir($current) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $current . '/' . $entry;

                if (is_dir($path)) {
                    $stack[] = $path;
                } elseif (is_file($path)) {
                    $files[] = $path;
                }
            }
        }

        if ($files === []) {
            return null;
        }

        $zip = new ZipWriter($target);

        sort($files);

        foreach ($files as $path) {
            $zip->addFile($path, 'uploads/' . ltrim(substr($path, strlen($root)), '/'));
        }

        $entries = $zip->count();
        $zip->close();

        return ['path' => $target, 'size' => (int) filesize($target), 'entries' => $entries];
    }

    /**
     * Hapus arsip lama, sisakan $keep yang terbaru.
     *
     * @return list<string> nama berkas yang dihapus
     */
    private static function prune(string $dir, string $pattern, int $keep): array
    {
        $files = glob($dir . '/' . $pattern) ?: [];

        if (count($files) <= $keep) {
            return [];
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $removed = [];

        foreach (array_slice($files, $keep) as $path) {
            if (@unlink($path)) {
                $removed[] = basename($path);
            }
        }

        return $removed;
    }

    /**
     * Arsip terbaru (jenis apa pun), untuk health check.
     *
     * @return array{name:string,path:string,size:int,time:int}|null
     */
    public static function latest(): ?array
    {
        $files = array_merge(
            glob(storage_path('backup') . '/cenim-db-*.sqlite.gz') ?: [],
            glob(storage_path('backup') . '/cenim-uploads-*.zip') ?: []
        );

        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return [
            'name' => basename($files[0]),
            'path' => $files[0],
            'size' => (int) filesize($files[0]),
            'time' => (int) filemtime($files[0]),
        ];
    }

    /** @return list<array{name:string,path:string,size:int,time:int,kind:string}> */
    public static function all(): array
    {
        $out = [];

        foreach (['db' => 'cenim-db-*.sqlite.gz', 'uploads' => 'cenim-uploads-*.zip'] as $kind => $pattern) {
            foreach (glob(storage_path('backup') . '/' . $pattern) ?: [] as $path) {
                $out[] = [
                    'name' => basename($path),
                    'path' => $path,
                    'size' => (int) filesize($path),
                    'time' => (int) filemtime($path),
                    'kind' => $kind,
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);

        return $out;
    }

    /**
     * Buka snapshot dan pastikan isinya utuh — dipakai `bin/backup.php --verify`
     * supaya kerusakan arsip ketahuan sebelum dibutuhkan saat darurat.
     *
     * @return array{ok:bool,detail:string}
     */
    public static function verify(string $path): array
    {
        if (!is_file($path)) {
            return ['ok' => false, 'detail' => 'berkas tidak ada: ' . $path];
        }

        $tmpDir = storage_path('tmp');

        if (!ensure_dir($tmpDir)) {
            return ['ok' => false, 'detail' => 'folder tmp tidak writable'];
        }

        $tmp = $tmpDir . '/verify-' . bin2hex(random_bytes(6)) . '.sqlite';
        $in  = gzopen($path, 'rb');
        $out = fopen($tmp, 'wb');

        if ($in === false || $out === false) {
            return ['ok' => false, 'detail' => 'arsip tidak bisa dibuka'];
        }

        while (!gzeof($in)) {
            fwrite($out, (string) gzread($in, 1 << 20));
        }

        gzclose($in);
        fclose($out);

        try {
            $pdo   = new \PDO('sqlite:' . $tmp, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $check = (string) $pdo->query('PRAGMA quick_check(1)')->fetchColumn();
            $tables = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
            $authors = (int) $pdo->query('SELECT COUNT(*) FROM authors')->fetchColumn();

            return [
                'ok'     => $check === 'ok',
                'detail' => $check === 'ok'
                    ? sprintf('quick_check ok, %d tabel, %d author', $tables, $authors)
                    : 'quick_check: ' . $check,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        } finally {
            @unlink($tmp);
        }
    }
}
