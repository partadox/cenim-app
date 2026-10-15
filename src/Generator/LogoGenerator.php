<?php

declare(strict_types=1);

namespace Cenim\Generator;

use Cenim\Core\Db;
use Cenim\Domain\SettingRepo;
use RuntimeException;

/**
 * Generator logo CENIM per tahun.
 *
 * Bentuk logo (bingkai rounded + tulisan "CENIM") diambil apa adanya dari
 * template resmi; yang berubah hanya angka tahunnya — persis seperti pola logo
 * CENIM dari tahun ke tahun.
 *
 * Cara kerjanya:
 *   - PNG  : gambar dasar `reference/logo-base.png` (template TANPA angka tahun,
 *            dirender sekali dari SVG resmi) + angka tahun digambar GD/FreeType
 *            pada kotak yang sama dengan template. Hanya butuh ekstensi gd,
 *            jadi aman di cPanel.
 *   - SVG  : template SVG asli dengan 6 path angka dibuang, diganti <text>
 *            berisi tahun; fontnya ditanam sebagai base64 agar file SVG-nya
 *            tetap tampil sama di komputer lain.
 *
 * Posisi angka (fraksi dari ukuran gambar dasar) diukur dengan membandingkan
 * render template dengan dan tanpa angka — lihat README.internal.md §6.2.
 */
final class LogoGenerator
{
    /** Kotak angka tahun pada logo, sebagai fraksi lebar/tinggi gambar dasar. */
    public const DIGIT_BOX = ['x' => 0.2835, 'y' => 0.5905, 'w' => 0.4354, 'h' => 0.1607];

    /** Indeks path angka tahun di dalam template SVG (hasil audit template). */
    private const SVG_DIGIT_PATHS = [4, 5, 6, 7, 10, 11];

    public const DEFAULT_FONT = 'Poppins-Bold.ttf';

    public const SIZES = [256, 512, 1024, 2048];

    /** Font untuk angka tahun; bisa diganti lewat setting logo_year_font. */
    public static function fontPath(int $editionId = 0): string
    {
        $configured = $editionId > 0 ? SettingRepo::getString($editionId, 'logo_year_font') : '';
        $candidates = [];

        if ($configured !== '') {
            $candidates[] = storage_path('fonts/' . basename($configured));
            $candidates[] = CENIM_ROOT . '/vendor-lite/fonts/' . basename($configured);
        }

        $candidates[] = CENIM_ROOT . '/vendor-lite/fonts/' . self::DEFAULT_FONT;

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Font untuk angka tahun tidak ditemukan: ' . self::DEFAULT_FONT);
    }

    /**
     * PNG bisa digambar di server? Butuh gd dengan FreeType; bila tidak, PNG
     * logo/kop dirender browser admin dari SVG.
     */
    public static function serverRender(): bool
    {
        return ext_available('gd') && function_exists('imagettftext');
    }

    public static function basePath(): string
    {
        $path = CENIM_ROOT . '/reference/logo-base.png';

        if (!is_file($path)) {
            throw new RuntimeException('Gambar dasar logo tidak ada: reference/logo-base.png');
        }

        return $path;
    }

    /**
     * Hasilkan PNG logo satu tahun.
     *
     * @param bool $transparent false = latar putih (untuk dokumen), true = transparan
     */
    public static function png(int $year, int $width = 1024, bool $transparent = true, int $editionId = 0): string
    {
        if (!self::serverRender()) {
            throw new RuntimeException('gd/FreeType tidak tersedia; PNG logo dibuat di browser (lihat build()).');
        }

        $base = @imagecreatefrompng(self::basePath());

        if ($base === false) {
            throw new RuntimeException('Gambar dasar logo tidak bisa dibaca.');
        }

        $baseW = imagesx($base);
        $baseH = imagesy($base);
        $width = max(64, min(4096, $width));
        $height = (int) round($width * $baseH / $baseW);

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $background = $transparent
            ? imagecolorallocatealpha($canvas, 0, 0, 0, 127)
            : imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $background);

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $base, 0, 0, 0, 0, $width, $height, $baseW, $baseH);

        self::drawYear($canvas, $year, $width, $height, $editionId);

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    /** Tulis angka tahun di dalam kotak angka, tinggi disamakan dengan template. */
    private static function drawYear($canvas, int $year, int $width, int $height, int $editionId): void
    {
        $font = self::fontPath($editionId);
        $text = (string) $year;

        $boxX = self::DIGIT_BOX['x'] * $width;
        $boxY = self::DIGIT_BOX['y'] * $height;
        $boxW = self::DIGIT_BOX['w'] * $width;
        $boxH = self::DIGIT_BOX['h'] * $height;

        // Cari ukuran font yang membuat tinggi angka sama dengan kotak template.
        $size = self::fitFontSize($font, $text, $boxH, $boxW);
        $box  = TtfMetrics::bbox($size, $font, $text);

        $textW = abs($box[2] - $box[0]);
        $textH = abs($box[7] - $box[1]);

        // Titik acuan imagettftext adalah baseline kiri.
        $x = (int) round($boxX + ($boxW - $textW) / 2 - min($box[0], $box[6]));
        $y = (int) round($boxY + ($boxH + $textH) / 2 - max($box[1], $box[3]));

        $white = imagecolorallocate($canvas, 254, 254, 254);
        imagettftext($canvas, $size, 0, $x, $y, $white, $font, $text);
    }

    /** Ukuran font (pt) agar teks setinggi $targetH dan tidak melebihi $maxW. */
    public static function fitFontSize(string $font, string $text, float $targetH, float $maxW): float
    {
        $size = max(4.0, $targetH); // titik awal yang wajar
        for ($i = 0; $i < 12; $i++) {
            $box = TtfMetrics::bbox($size, $font, $text);
            $h   = abs($box[7] - $box[1]);

            if ($h <= 0) {
                break;
            }

            $size *= $targetH / $h;
        }

        // Jangan sampai melebar keluar kotak (mis. tahun dengan angka lebar).
        $box = TtfMetrics::bbox($size, $font, $text);
        $w   = abs($box[2] - $box[0]);

        if ($w > $maxW && $w > 0) {
            $size *= $maxW / $w;
        }

        return round($size, 2);
    }

    /** Logo sebagai SVG (vektor) — template asli dengan angka tahun sebagai <text>. */
    public static function svg(int $year, bool $embedFont = true, int $editionId = 0): string
    {
        $template = CENIM_ROOT . '/reference/logo-cenim-template.svg';

        if (!is_file($template)) {
            throw new RuntimeException('Template SVG logo tidak ditemukan.');
        }

        $svg = (string) file_get_contents($template);

        // Metadata C2PA dari generator gambar tidak perlu dibawa-bawa.
        $svg = (string) preg_replace('#<metadata>.*?</metadata>#s', '', $svg);

        // Buang path angka tahun template.
        preg_match_all('#<path\b[^>]*/>#', $svg, $matches);

        foreach (self::SVG_DIGIT_PATHS as $index) {
            if (isset($matches[0][$index])) {
                $svg = str_replace($matches[0][$index], '', $svg);
            }
        }

        // Ukuran kanvas SVG (viewBox) untuk menghitung posisi teks.
        preg_match('#viewBox="0 0 ([\d.]+) ([\d.]+)"#', $svg, $view);
        $viewW = (float) ($view[1] ?? 490);
        $viewH = (float) ($view[2] ?? 418);

        // Gambar dasar sedikit lebih sempit dari viewBox (ada margin di kanan).
        $base   = @getimagesize(self::basePath());
        $ratio  = $base !== false && $base[1] > 0 ? $base[0] / $base[1] : 1.1025;
        $baseW  = $viewH * $ratio;

        $boxX = self::DIGIT_BOX['x'] * $baseW;
        $boxY = self::DIGIT_BOX['y'] * $viewH;
        $boxW = self::DIGIT_BOX['w'] * $baseW;
        $boxH = self::DIGIT_BOX['h'] * $viewH;

        $font     = self::fontPath($editionId);
        $fontSize = self::fitFontSize($font, (string) $year, $boxH, $boxW);
        $bbox     = TtfMetrics::bbox($fontSize, $font, (string) $year);
        $textH    = abs($bbox[7] - $bbox[1]);

        $centerX  = $boxX + $boxW / 2;
        $baseline = $boxY + ($boxH + $textH) / 2;

        $fontFace = '';
        $family   = 'CenimYear';

        if ($embedFont) {
            $data = base64_encode((string) file_get_contents($font));
            $fontFace = '<defs><style type="text/css">@font-face{font-family:"' . $family . '";'
                . 'src:url(data:font/ttf;base64,' . $data . ') format("truetype");}</style></defs>';
        } else {
            // Tanpa penanaman font, pakai daftar cadangan yang mirip.
            $family = 'Poppins, Montserrat, Helvetica, Arial, sans-serif';
        }

        $text = sprintf(
            '<text x="%.2f" y="%.2f" font-family="%s" font-size="%.2f" font-weight="700" fill="#FEFEFE" text-anchor="middle">%d</text>',
            $centerX,
            $baseline,
            $embedFont ? "'" . $family . "'" : $family,
            $fontSize * 96 / 72, // ukuran GD dalam pt (96 dpi) → satuan px SVG
            $year
        );

        // Sisipkan sebelum </svg>.
        return (string) preg_replace('#</svg>\s*$#', $fontFace . $text . '</svg>', $svg);
    }

    /**
     * Berkas PNG satu paket logo: nama => [lebar, transparan].
     *
     * @return array<string,array{0:int,1:bool}>
     */
    public static function pngTargets(int $year): array
    {
        $targets = [];

        foreach (self::SIZES as $size) {
            $targets['logo-cenim-' . $year . '-' . $size . '.png'] = [$size, true];
        }

        $targets['logo-cenim-' . $year . '-white-bg.png'] = [1024, false];
        $targets['favicon-' . $year . '.png']             = [192, true];

        return $targets;
    }

    /**
     * Ukuran PNG untuk lebar tertentu — mengikuti gambar dasar, yang sedikit
     * lebih sempit dari viewBox SVG (render browser memotong sisanya).
     *
     * @return array{width:int,height:int,vbW:float,vbH:float}
     */
    public static function rasterGeometry(int $width): array
    {
        $base = @getimagesize(self::basePath());
        $ratio = $base !== false && $base[1] > 0 ? $base[0] / $base[1] : 1.1025;

        preg_match('#viewBox="0 0 ([\d.]+) ([\d.]+)"#', (string) file_get_contents(CENIM_ROOT . '/reference/logo-cenim-template.svg'), $view);

        return [
            'width'  => $width,
            'height' => (int) round($width / $ratio),
            'vbW'    => (float) ($view[1] ?? 490),
            'vbH'    => (float) ($view[2] ?? 418),
        ];
    }

    /**
     * Simpan satu paket logo (SVG + PNG beberapa ukuran + favicon) ke storage
     * dan catat di tabel media.
     *
     * Tanpa ekstensi gd, PNG-nya dirender browser admin dari SVG yang sama
     * lalu dikirim lewat $browserPngs (nama berkas => isi PNG).
     *
     * @param  array<string,string> $browserPngs
     * @return array<string,string> label => path relatif di storage
     */
    public static function build(int $year, int $editionId, array $browserPngs = []): array
    {
        $dir     = 'generated/logo/' . $year;
        $created = [];
        $gd      = self::serverRender();

        foreach (self::pngTargets($year) as $name => [$width, $transparent]) {
            $bytes = $gd
                ? self::png($year, $width, $transparent, $editionId)
                : self::browserPng($browserPngs, $name, self::rasterGeometry($width));

            $relative = $dir . '/' . $name;
            self::store($relative, $bytes);

            $label = match (true) {
                str_starts_with($name, 'favicon-')  => 'favicon',
                str_ends_with($name, '-white-bg.png') => 'png_white',
                default                               => 'png' . $width,
            };
            $created[$label] = $relative;
        }

        $relative = $dir . '/logo-cenim-' . $year . '.svg';
        self::store($relative, self::svg($year, true, $editionId));
        $created['svg'] = $relative;

        self::registerMedia($editionId, $year, $created);

        return $created;
    }

    /**
     * PNG kiriman browser: harus PNG asli dengan ukuran yang diminta.
     *
     * @param array<string,string>          $pngs
     * @param array{width:int,height:int}   $size
     */
    public static function browserPng(array $pngs, string $name, array $size): string
    {
        $bytes = $pngs[$name] ?? '';

        if ($bytes === '') {
            throw new RuntimeException('PNG ' . $name . ' belum dirender browser. Muat ulang halaman lalu coba lagi.');
        }

        $info = @getimagesizefromstring($bytes);

        if (!str_starts_with($bytes, "\x89PNG\r\n\x1a\n") || $info === false || $info[2] !== IMAGETYPE_PNG
            || $info[0] !== $size['width'] || $info[1] !== $size['height']) {
            throw new RuntimeException('PNG ' . $name . ' dari browser tidak valid.');
        }

        return $bytes;
    }

    /**
     * Ambil PNG kiriman form (field png[nama] berisi data URL).
     *
     * @return array<string,string>
     */
    public static function postedPngs(): array
    {
        $out = [];

        foreach ((array) ($_POST['png'] ?? []) as $name => $dataUrl) {
            if (is_string($dataUrl) && preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m) === 1) {
                $out[basename((string) $name)] = (string) base64_decode($m[1], true);
            }
        }

        return $out;
    }

    private static function store(string $relative, string $bytes): void
    {
        $absolute = storage_path($relative);

        if (!ensure_dir(dirname($absolute))) {
            throw new RuntimeException('Folder tidak writable: ' . dirname($absolute));
        }

        if (file_put_contents($absolute, $bytes) === false) {
            throw new RuntimeException('Gagal menulis ' . $absolute);
        }
    }

    /** Catat hasil generate di tabel media supaya bisa dilacak & dipakai modul lain. */
    private static function registerMedia(int $editionId, int $year, array $files): void
    {
        if ($editionId <= 0 || !Db::tableExists('media')) {
            return;
        }

        Db::delete('media', "edition_id = ? AND kind = 'logo'", [$editionId]);

        Db::insert('media', [
            'edition_id' => $editionId,
            'kind'       => 'logo',
            'label'      => 'CENIM ' . $year . ' logo',
            'meta_json'  => json_encode(['year' => $year, 'files' => $files], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    /** Daftar berkas logo yang sudah dibuat untuk satu tahun. */
    public static function existing(int $year): array
    {
        $dir   = storage_path('generated/logo/' . $year);
        $files = [];

        foreach (glob($dir . '/*') ?: [] as $path) {
            $files[basename($path)] = [
                'path' => 'generated/logo/' . $year . '/' . basename($path),
                'size' => (int) filesize($path),
                'time' => (int) filemtime($path),
            ];
        }

        ksort($files);

        return $files;
    }
}
