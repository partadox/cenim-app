<?php

declare(strict_types=1);

namespace Cenim\Support;

use Cenim\Core\Db;
use Cenim\Core\Logger;

/**
 * Pembersihan rutin yang dijalankan cron harian.
 *
 * Semuanya konservatif: yang dihapus hanya data operasional yang sudah lewat
 * masa gunanya (riwayat percobaan login, berkas log, berkas sementara).
 * Data bisnis — author, registrasi, invoice, receipt, sertifikat, audit log —
 * tidak pernah dihapus otomatis.
 */
final class Maintenance
{
    /** @return array<string,int|string> jumlah yang dibersihkan per tugas */
    public static function run(array $options = []): array
    {
        $result = [
            'login_attempts' => self::pruneLoginAttempts((int) ($options['login_days'] ?? 30)),
            'login_codes'    => \Cenim\Core\LoginCode::prune(7),
            'mail_html'      => self::pruneFiles(storage_path('logs/mail'), '*.html', (int) ($options['mail_days'] ?? 30))
                + self::pruneFiles(storage_path('logs/mail'), '*.txt', (int) ($options['mail_days'] ?? 30)),
            'app_logs'       => self::pruneFiles(storage_path('logs'), 'app-*.log', (int) ($options['log_days'] ?? 90)),
            'tmp'            => self::pruneFiles(storage_path('tmp'), '*', 1),
            's3_cache'       => self::pruneTree(storage_path('cache/s3/private'), (int) ($options['s3_cache_days'] ?? 7)),
            'optimize'       => self::optimize(),
        ];

        Logger::info('Maintenance selesai', $result);

        return $result;
    }

    public static function pruneLoginAttempts(int $days = 30): int
    {
        if (!Db::tableExists('login_attempts')) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        return Db::run('DELETE FROM login_attempts WHERE created_at < ?', [$cutoff])->rowCount();
    }

    /** Hapus berkas yang lebih tua dari $days hari; mengembalikan jumlah berkas. */
    public static function pruneFiles(string $dir, string $pattern, int $days): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff  = time() - $days * 86400;
        $removed = 0;

        foreach (glob(rtrim($dir, '/') . '/' . $pattern) ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $cutoff && @unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Hapus berkas lebih tua dari $days di seluruh subfolder. Dipakai untuk
     * salinan lokal unggahan privat dari object storage: aslinya tetap di
     * bucket dan diunduh ulang otomatis saat dibuka. Cache aset tema
     * (cache/s3/public) tidak disentuh — itu yang membuat landing cepat.
     */
    public static function pruneTree(string $dir, int $days): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff  = time() - $days * 86400;
        $removed = 0;
        $it      = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoff && @unlink($file->getPathname())) {
                $removed++;
            }
        }

        return $removed;
    }

    /** PRAGMA optimize — menjaga statistik query tetap segar (murah, aman). */
    public static function optimize(): string
    {
        try {
            Db::pdo()->exec('PRAGMA optimize');
            return 'ok';
        } catch (\Throwable $e) {
            return 'gagal: ' . $e->getMessage();
        }
    }
}
