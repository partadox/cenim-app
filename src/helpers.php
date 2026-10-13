<?php
/**
 * Helper global. Di-include sekali oleh bootstrap.php.
 *
 * Konvensi waktu: SEMUA timestamp disimpan ke database dalam UTC
 * format 'Y-m-d H:i:s' (lihat now()). Konversi ke zona waktu lokal
 * hanya dilakukan saat ditampilkan (lihat fmt_dt() / fmt_date()).
 */

declare(strict_types=1);

/** Ambil nilai dari .env (lihat Cenim\Core\Env). */
function env(string $key, mixed $default = null): mixed
{
    return $GLOBALS['CENIM_ENV'][$key] ?? $default;
}

/** Ambil nilai dari config/app.php dengan notasi titik: cfg('cdn.bootstrap_css'). */
function cfg(string $path, mixed $default = null): mixed
{
    $value = $GLOBALS['CENIM_CFG'];
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function is_local(): bool
{
    return env('APP_ENV') === 'local';
}

/** Escape untuk output HTML. Wajib dipakai di semua view. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Timestamp sekarang dalam UTC, format database. */
function now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

/** Format timestamp UTC dari database ke zona waktu tampilan. */
function fmt_dt(?string $utc, string $format = 'd M Y H:i'): string
{
    if ($utc === null || $utc === '') {
        return '-';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    } catch (Exception) {
        return '-';
    }
    return $dt->setTimezone(new DateTimeZone((string) env('APP_TZ', 'Asia/Jakarta')))->format($format);
}

function fmt_date(?string $utc): string
{
    return fmt_dt($utc, 'd M Y');
}

function fmt_money(float|int|string|null $amount, string $currency = 'IDR'): string
{
    $amount = (float) $amount;
    return $currency === 'IDR'
        ? 'Rp ' . number_format($amount, 0, ',', '.')
        : '$ ' . number_format($amount, 2, '.', ',');
}

// ── URL ─────────────────────────────────────────────────────────────────────

/** URL root-relative dengan menghormati APP_BASE. */
function base_url(string $path = ''): string
{
    $base = rtrim((string) env('APP_BASE', ''), '/');
    return $base . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

/** URL absolut, untuk email / QR code / redirect eksternal. */
function abs_url(string $path = ''): string
{
    return rtrim((string) env('APP_URL', ''), '/') . base_url($path);
}

/**
 * URL aset tema. Berkasnya ada di object storage (objek public/theme/...):
 * - TANDIKAT_PUBLIC_URL terisi (bucket boleh dibaca anonim) → langsung ke sana;
 * - kosong (bawaan) → /cenim-app/assets/theme/..., disajikan public/asset.php.
 */
function asset(string $path): string
{
    $public = rtrim((string) env('TANDIKAT_PUBLIC_URL', ''), '/');

    if ($public !== '') {
        return $public . '/public/theme/' . ltrim($path, '/');
    }

    return base_url(trim((string) cfg('assets_dir', '/assets'), '/') . '/' . ltrim($path, '/'));
}

/**
 * URL publik untuk satu baris tabel files yang purpose-nya publik (foto speaker).
 * Objek public/media/... lewat jalur aset; selain itu lewat public/file.php.
 */
function public_file_url(array $file): string
{
    $key = (string) ($file['path'] ?? '');

    if (($file['driver'] ?? '') === 's3' && str_starts_with($key, 'public/media/')) {
        $public = rtrim((string) env('TANDIKAT_PUBLIC_URL', ''), '/');

        return $public !== ''
            ? $public . '/' . $key
            : app_url('assets/' . implode('/', array_map('rawurlencode', explode('/', substr($key, 7)))));
    }

    return app_url('public/file.php?id=' . (int) ($file['id'] ?? 0));
}

/**
 * URL file milik aplikasi. Halaman (panel admin, author, api, file, pratinjau)
 * langsung di bawah web root — /conf/cenim/author/… — lewat rewrite di
 * .htaccess root (deploy/root.htaccess); aset tetap di /conf/cenim/cenim-app/….
 */
function app_url(string $path = ''): string
{
    $path  = ltrim($path, '/');
    $first = explode('/', $path, 2)[0];

    if (in_array($first, app_routes(), true)) {
        return base_url($path);
    }

    return base_url(trim((string) cfg('app_dir', '/cenim-app'), '/') . '/' . $path);
}

/** Segmen yang dilayani langsung di bawah web root (harus sama dengan deploy/root.htaccess). */
function app_routes(): array
{
    return [(string) cfg('admin_dir', 'admin'), (string) cfg('author_dir', 'author'), 'api', 'public', 'site'];
}

/** URL absolut dari URL root-relative yang SUDAH memuat APP_BASE (mis. hasil author_url()). */
function full_url(string $rootRelative): string
{
    return rtrim((string) env('APP_URL', ''), '/') . '/' . ltrim($rootRelative, '/');
}

/** Aset milik aplikasi (cenim-app/assets/...) dengan cache-busting mtime. */
function app_asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = CENIM_ROOT . '/assets/' . $path;
    $url  = app_url('assets/' . $path);
    return is_file($file) ? $url . '?v=' . filemtime($file) : $url;
}

function admin_url(string $path = ''): string
{
    return app_url(cfg('admin_dir', 'admin') . '/' . ltrim($path, '/'));
}

/** URL PDF petunjuk penggunaan portal (Settings → User guide), atau null bila belum diunggah. */
function user_guide_url(int $editionId): ?string
{
    if ($editionId <= 0) {
        return null;
    }
    $file = Cenim\Domain\FileRepo::find((int) Cenim\Domain\SettingRepo::get($editionId, 'user_guide_file_id', 0));

    return $file !== null && ($file['deleted_at'] ?? null) === null ? public_file_url($file) : null;
}

function author_url(string $path = ''): string
{
    return app_url(cfg('author_dir', 'author') . '/' . ltrim($path, '/'));
}

// ── Path storage ────────────────────────────────────────────────────────────

/**
 * Path absolut di dalam folder storage.
 * Fallback ke cenim-app/storage bila STORAGE_PATH tidak diisi.
 */
function storage_path(string $path = ''): string
{
    $root = (string) env('STORAGE_PATH', '');
    if ($root === '') {
        $root = CENIM_ROOT . '/storage';
    }
    $root = rtrim($root, '/');
    return $path === '' ? $root : $root . '/' . ltrim($path, '/');
}

/** Pastikan direktori ada dan writable. */
function ensure_dir(string $dir): bool
{
    if (is_dir($dir)) {
        return is_writable($dir);
    }
    if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    return is_writable($dir);
}

/** Ukuran berkas dalam satuan yang enak dibaca (KB/MB/GB). */
/** Nilai ini PHP berukuran (mis. "2M", "512K") → byte; tanpa batas → PHP_INT_MAX. */
function ini_bytes(string $key): int
{
    $raw = trim((string) ini_get($key));
    if ($raw === '' || $raw === '-1' || $raw === '0') {
        return PHP_INT_MAX;
    }

    $value = (int) $raw;

    return match (strtolower(substr($raw, -1))) {
        'g'     => $value * 1024 * 1024 * 1024,
        'm'     => $value * 1024 * 1024,
        'k'     => $value * 1024,
        default => $value,
    };
}

/**
 * Batas ukuran satu berkas yang benar-benar bisa diterima: nilai terkecil dari
 * batas aplikasi (config upload.max_bytes), batas fitur ($cap, mis. logo 2 MB),
 * dan batas PHP di server (upload_max_filesize, post_max_size). Server yang
 * batas PHP-nya tidak bisa diubah (cPanel tanpa akses INI) tetap konsisten:
 * keterangan di form dan validasi selalu memakai angka yang sama.
 */
function upload_max_bytes(int $cap = 0): int
{
    $limits = [(int) cfg('upload.max_bytes', 5242880), ini_bytes('upload_max_filesize'), ini_bytes('post_max_size')];
    if ($cap > 0) {
        $limits[] = $cap;
    }

    return max(1, min($limits));
}

/** "2 MB", "1.5 MB", "512 KB" — untuk keterangan di form dan pesan error. */
function upload_max_label(int $cap = 0): string
{
    $bytes = upload_max_bytes($cap);
    if ($bytes >= 1048576) {
        $mb = $bytes / 1048576;

        return rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.') . ' MB';
    }

    return (int) ceil($bytes / 1024) . ' KB';
}

/** Keterangan standar di bawah isian berkas: jenis + batas ukuran. */
function upload_hint(string $types = 'PDF, JPG or PNG', int $cap = 0): string
{
    return $types . ' · max ' . upload_max_label($cap) . ' per file';
}

/**
 * Request POST melebihi post_max_size? PHP lalu membuang seluruh $_POST dan
 * $_FILES (termasuk token CSRF), jadi perlu dikenali supaya pesannya jelas.
 */
function post_too_large(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && $_POST === [] && $_FILES === []
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > ini_bytes('post_max_size');
}

function fmt_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    foreach (['KB', 'MB', 'GB'] as $unit) {
        $bytes /= 1024;
        if ($bytes < 1024 || $unit === 'GB') {
            return number_format($bytes, $bytes < 10 ? 1 : 0, ',', '.') . ' ' . $unit;
        }
    }

    return $bytes . ' B';
}

// ── Request ─────────────────────────────────────────────────────────────────

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/** Input POST/GET yang sudah di-trim. Tidak melakukan validasi. */
function input(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    if (is_array($value)) {
        return $default;
    }
    if ($value === null) {
        return $default;
    }
    $value = trim((string) $value);
    return $value === '' ? $default : $value;
}

function input_int(string $key, int $default = 0): int
{
    $value = input($key);
    return $value !== null && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $default;
}

function input_bool(string $key): bool
{
    return in_array(input($key, ''), ['1', 'on', 'true', 'yes'], true);
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

function user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** Token acak untuk kode verifikasi / nama file. */
function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Inisialisasi halaman panel (admin/author): mulai session dan cegah indexing.
 * Guard autentikasi ditambahkan pada Fase 1.
 */
function panel_boot(string $area): void
{
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    Cenim\Core\Session::start();
    if (!defined('PANEL_AREA')) {
        define('PANEL_AREA', $area);
    }
}
