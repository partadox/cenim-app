<?php
/**
 * Operasional object storage Tandikat (RustFS, S3-compatible).
 *
 *   php cenim-app/bin/storage.php check                  # koneksi, izin tulis, status baca-publik
 *   php cenim-app/bin/storage.php ls [prefix]            # daftar objek, mis. ls public/theme/css
 *   php cenim-app/bin/storage.php push-theme <folder>    # unggah aset tema ke public/theme/ (yang berubah saja)
 *   php cenim-app/bin/storage.php pull-theme <folder>    # unduh public/theme/ ke folder lokal (untuk diedit)
 *   php cenim-app/bin/storage.php migrate-files          # pindahkan baris files driver=local ke object storage
 *   php cenim-app/bin/storage.php cache-clear            # kosongkan cache lokal (wajib setelah push-theme di server)
 *   php cenim-app/bin/storage.php cache-warm             # isi cache aset tema sekarang, bukan saat pengunjung pertama datang
 *
 * Tambahkan --dry-run pada push-theme / migrate-files untuk melihat rencananya saja.
 * Exit code: 0 sukses, 1 gagal.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan dari CLI.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\Db;
use Cenim\Domain\FileRepo;
use Cenim\Storage\LocalDriver;
use Cenim\Storage\MimeTypes;
use Cenim\Storage\S3Client;
use Cenim\Storage\S3Driver;

$args    = array_values(array_filter(array_slice($argv, 1), static fn ($a) => !str_starts_with($a, '--')));
$dryRun  = in_array('--dry-run', $argv, true);
$command = $args[0] ?? 'help';

/** Semua berkas di bawah $dir (path relatif => absolut), tanpa dotfile. */
$walk = static function (string $dir): array {
    $out = [];
    $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        $relative = substr($file->getPathname(), strlen($dir) + 1);
        if ($file->isFile() && !preg_match('#(^|/)\.#', $relative)) {
            $out[str_replace('\\', '/', $relative)] = $file->getPathname();
        }
    }
    ksort($out);

    return $out;
};

$rrmdir = static function (string $dir) use (&$rrmdir): int {
    if (!is_dir($dir)) {
        return 0;
    }
    $n = 0;
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            $n += $rrmdir($path);
        } elseif (@unlink($path)) {
            $n++;
        }
    }
    @rmdir($dir);

    return $n;
};

try {
    $s3 = S3Client::fromEnv();

    switch ($command) {
        case 'check':
            echo 'Endpoint : ', env('TANDIKAT_ENDPOINT'), PHP_EOL;
            echo 'Bucket   : ', $s3->bucket(), PHP_EOL;

            $probe = '_probe/check-' . bin2hex(random_bytes(4)) . '.txt';
            $s3->putString($probe, 'ok', 'text/plain');
            $s3->delete($probe);
            echo "Tulis    : ok (put + delete)\n";

            $theme = $s3->list('public/theme/');
            echo 'Tema     : ', count($theme), ' objek, ', number_format(array_sum(array_column($theme, 'size')) / 1048576, 1), " MB\n";

            $sample = $theme[0]['key'] ?? null;
            if ($sample !== null) {
                $ch = curl_init($s3->publicUrl($sample));
                curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
                curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                echo 'Publik   : ', $code === 200
                    ? "BISA dibaca anonim — boleh isi TANDIKAT_PUBLIC_URL agar aset langsung dari object storage\n"
                    : "privat (HTTP {$code}) — aset disajikan lewat public/asset.php (normal)\n";
            }
            echo 'Mode aset: ', env('TANDIKAT_PUBLIC_URL', '') !== '' ? 'langsung (TANDIKAT_PUBLIC_URL)' : 'proxy public/asset.php', PHP_EOL;
            break;

        case 'ls':
            $items = $s3->list($args[1] ?? '');
            foreach ($items as $item) {
                printf("%10s  %s\n", number_format($item['size']), $item['key']);
            }
            printf("%d objek, %.1f MB\n", count($items), array_sum(array_column($items, 'size')) / 1048576);
            break;

        case 'push-theme':
            $dir = rtrim((string) ($args[1] ?? ''), '/');
            if ($dir === '' || !is_dir($dir)) {
                throw new RuntimeException('Folder sumber tidak ada. Contoh: php bin/storage.php push-theme ../cenim-old/assets');
            }
            $dir = (string) realpath($dir);

            $remote = [];
            foreach ($s3->list('public/theme/') as $item) {
                $remote[$item['key']] = $item['etag'];
            }

            $sent = $same = $skipped = 0;
            $bytes = 0;
            foreach ($walk($dir) as $relative => $absolute) {
                $mime = MimeTypes::forPath($relative);
                if ($mime === null) {
                    $skipped++;   // sass, dokumen, dll: bukan aset web
                    continue;
                }

                $key = 'public/theme/' . $relative;
                if (($remote[$key] ?? null) === md5_file($absolute)) {
                    $same++;
                    continue;
                }

                echo ($dryRun ? '[dry] ' : '') . 'PUT ' . $key . PHP_EOL;
                if (!$dryRun) {
                    $s3->putFile($key, $absolute, $mime);
                }
                $sent++;
                $bytes += (int) filesize($absolute);
            }

            printf("%d diunggah (%.1f MB), %d sama, %d dilewati (bukan aset web)\n", $sent, $bytes / 1048576, $same, $skipped);
            if ($sent > 0 && !$dryRun) {
                echo "Di server produksi jalankan: php cenim-app/bin/storage.php cache-clear && php cenim-app/bin/storage.php cache-warm\n";
            }
            break;

        case 'pull-theme':
            $dir = rtrim((string) ($args[1] ?? ''), '/');
            if ($dir === '') {
                throw new RuntimeException('Sebutkan folder tujuan. Contoh: php bin/storage.php pull-theme /tmp/theme');
            }

            $n = 0;
            foreach ($s3->list('public/theme/') as $item) {
                $target = $dir . '/' . substr($item['key'], strlen('public/theme/'));
                if (!ensure_dir(dirname($target))) {
                    throw new RuntimeException('Tidak bisa membuat ' . dirname($target));
                }
                $s3->getToFile($item['key'], $target);
                $n++;
            }
            echo "{$n} berkas diunduh ke {$dir}\n";
            break;

        case 'migrate-files':
            // generated/ (paket hasil generator logo & kop) sengaja tetap lokal:
            // generator membaca foldernya langsung dan semuanya bisa dibuat ulang.
            $rows  = Db::all("SELECT * FROM files WHERE driver = 'local' AND deleted_at IS NULL AND path NOT LIKE 'generated/%' ORDER BY id");
            $local = new LocalDriver();
            $moved = $missing = 0;

            foreach ($rows as $row) {
                $path = $local->localPath($row);
                if ($path === null) {
                    echo "  ! #{$row['id']} berkas lokal hilang: {$row['path']}\n";
                    $missing++;
                    continue;
                }

                // Purpose publik pindah ke media/ supaya bisa disajikan tanpa login.
                $relative = (string) $row['path'];
                if (in_array((string) $row['purpose'], FileRepo::PUBLIC_PURPOSES, true) && str_starts_with($relative, 'uploads/')) {
                    $relative = 'media/' . substr($relative, strlen('uploads/'));
                }
                $key = S3Driver::keyFor($relative);

                echo ($dryRun ? '[dry] ' : '') . "  #{$row['id']} {$row['path']} -> {$key}\n";
                if ($dryRun) {
                    continue;
                }

                $s3->putFile($key, $path, (string) ($row['mime'] ?: 'application/octet-stream'));
                $head = $s3->head($key);
                if ($head === null || (int) ($head['content-length'] ?? -1) !== (int) filesize($path)) {
                    throw new RuntimeException("Verifikasi gagal untuk #{$row['id']} — berkas lokal tidak dihapus.");
                }

                Db::update('files', ['driver' => 's3', 'path' => $key], 'id = :target_id', ['target_id' => (int) $row['id']]);
                @unlink($path);
                $moved++;
            }

            printf("%d dipindah, %d hilang, %d total baris local\n", $moved, $missing, count($rows));
            break;

        case 'cache-clear':
            $n = $rrmdir(storage_path('cache/s3')) + $rrmdir(storage_path('cache/s3-missing'));
            echo "{$n} berkas cache dihapus\n";
            break;

        case 'cache-warm':
            $got = $have = 0;
            foreach ($s3->list('public/theme/') as $item) {
                $target = S3Driver::cachePath($item['key']);
                if (is_file($target) && filesize($target) === $item['size']) {
                    $have++;
                    continue;
                }
                if (!ensure_dir(dirname($target))) {
                    throw new RuntimeException('Tidak bisa membuat ' . dirname($target));
                }
                $s3->getToFile($item['key'], $target);
                $got++;
            }
            echo "{$got} diunduh ke cache, {$have} sudah ada\n";
            break;

        default:
            $doc = (string) file_get_contents(__FILE__);
            preg_match('#/\*\*(.*?)\*/#s', $doc, $m);
            echo preg_replace('#^\s*\* ?#m', '', trim($m[1] ?? '')), PHP_EOL;
            exit($command === 'help' ? 0 : 1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'GAGAL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
