<?php

declare(strict_types=1);

namespace Cenim\Generator;

use Cenim\Core\Db;
use Cenim\Domain\FileRepo;
use Cenim\Domain\SettingRepo;
use RuntimeException;

/**
 * Generator kop surat CENIM per tahun.
 *
 * Tata letaknya DIUKUR dari kop resmi 2026 (`reference/kop-surat-cenim2026.png`,
 * 2480×758) sehingga hasil generate untuk tahun lain tetap sama persis
 * posisinya. Logo pendamping (ITS, Computer Engineering, BME) dipakai dari
 * potongan kop asli (`reference/kop-partners.png`) karena berkas logo masing-
 * masing tidak tersedia terpisah; yang di-generate adalah logo CENIM (tahun
 * berubah) dan seluruh teksnya.
 */
final class KopGenerator
{
    public const WIDTH  = 2480;
    public const HEIGHT = 758;

    /** Semua koordinat di bawah dalam piksel pada kanvas 2480×758. */
    private const LOGO_ROW_TOP    = 70;
    private const LOGO_ROW_HEIGHT = 254;
    private const LOGO_CENIM_X    = 520;
    private const PARTNERS_X      = 906;

    private const TITLE_LINE1_TOP = 398;
    private const TITLE_LINE2_TOP = 478;
    private const TITLE_HEIGHT    = 57;
    private const ORGANIZED_TOP   = 560;
    private const ORGANIZED_H     = 34;
    private const INSTITUTE_TOP   = 617;
    private const INSTITUTE_H     = 34;

    private const RULE_X1        = 170;
    private const RULE_X2        = 2310;
    private const RULE_NAVY_TOP  = 698;
    private const RULE_NAVY_H    = 10;
    private const RULE_GOLD_TOP  = 715;
    private const RULE_GOLD_H    = 7;

    private const NAVY = [1, 56, 128];
    private const GOLD = [255, 189, 7];
    private const INK  = [26, 26, 26];

    private const MAX_TEXT_WIDTH = 2180;

    /**
     * Lebar teks pada kop asli (piksel). Ukuran font dicari agar lebarnya sama,
     * bukan tingginya — karena inilah yang membuat hasil generate terlihat
     * sepadan dengan kop 2026 meski fontnya (PT Serif) bukan font aslinya.
     */
    private const TITLE_TARGET_WIDTH     = 2122;
    private const ORGANIZED_TARGET_WIDTH = 1834;
    private const INSTITUTE_TARGET_WIDTH = 1148;

    public static function partnersPath(): string
    {
        $path = CENIM_ROOT . '/reference/kop-partners.png';

        if (!is_file($path)) {
            throw new RuntimeException('Potongan logo pendamping tidak ada: reference/kop-partners.png');
        }

        return $path;
    }

    private static function font(string $variant): string
    {
        $files = [
            'bold'    => 'PT_Serif-Web-Bold.ttf',
            'regular' => 'PT_Serif-Web-Regular.ttf',
            'italic'  => 'PT_Serif-Web-Italic.ttf',
        ];

        $path = CENIM_ROOT . '/vendor-lite/fonts/' . ($files[$variant] ?? $files['regular']);

        if (!is_file($path)) {
            throw new RuntimeException('Font kop tidak ditemukan: ' . basename($path));
        }

        return $path;
    }

    /** Teks kop: judul, "organized by", dan nama institusi. */
    public static function texts(int $year, int $editionId): array
    {
        $title = strtoupper((string) cfg('app_long_name')) . ' (CENIM) ' . $year;

        return [
            'title'     => strtoupper(SettingRepo::getString($editionId, 'kop_title', $title)),
            'organized' => SettingRepo::getString(
                $editionId,
                'kop_organized_by',
                'Organized by the Department of Computer Engineering and the Department of Biomedical Engineering'
            ),
            'institute' => SettingRepo::getString(
                $editionId,
                'kop_institute',
                'Institut Teknologi Sepuluh Nopember (ITS), Surabaya, Indonesia'
            ),
        ];
    }

    /** Hasilkan PNG kop surat. */
    public static function png(int $year, int $editionId = 0, int $width = self::WIDTH): string
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('Ekstensi gd tidak tersedia di server ini.');
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);

        // ── Baris logo ──────────────────────────────────────────────────────
        $logoPng = LogoGenerator::png($year, 1024, true, $editionId);
        $logo    = imagecreatefromstring($logoPng);

        if ($logo !== false) {
            $logoH = self::LOGO_ROW_HEIGHT;
            $logoW = (int) round($logoH * imagesx($logo) / imagesy($logo));
            imagecopyresampled(
                $canvas,
                $logo,
                self::LOGO_CENIM_X,
                self::LOGO_ROW_TOP,
                0,
                0,
                $logoW,
                $logoH,
                imagesx($logo),
                imagesy($logo)
            );
        }

        $partners = @imagecreatefrompng(self::partnersPath());

        if ($partners !== false) {
            imagecopy($canvas, $partners, self::PARTNERS_X, self::LOGO_ROW_TOP, 0, 0, imagesx($partners), imagesy($partners));
        }

        // ── Teks ────────────────────────────────────────────────────────────
        $texts = self::texts($year, $editionId);
        $navy  = imagecolorallocate($canvas, ...self::NAVY);
        $ink   = imagecolorallocate($canvas, ...self::INK);

        [$line1, $line2] = self::splitTitle($texts['title']);

        // Kedua baris judul memakai SATU ukuran font, seperti kop aslinya.
        $titleSize = self::fitWidth(self::font('bold'), $line1, self::TITLE_TARGET_WIDTH, self::TITLE_HEIGHT * 1.12);

        self::text($canvas, $line1, self::font('bold'), $titleSize, self::TITLE_LINE1_TOP, self::TITLE_HEIGHT, $navy);

        if ($line2 !== '') {
            self::text($canvas, $line2, self::font('bold'), $titleSize, self::TITLE_LINE2_TOP, self::TITLE_HEIGHT, $navy);
        }

        self::text(
            $canvas,
            $texts['organized'],
            self::font('regular'),
            self::fitWidth(self::font('regular'), $texts['organized'], self::ORGANIZED_TARGET_WIDTH, self::ORGANIZED_H * 1.2),
            self::ORGANIZED_TOP,
            self::ORGANIZED_H,
            $ink
        );

        self::text(
            $canvas,
            $texts['institute'],
            self::font('italic'),
            self::fitWidth(self::font('italic'), $texts['institute'], self::INSTITUTE_TARGET_WIDTH, self::INSTITUTE_H * 1.2),
            self::INSTITUTE_TOP,
            self::INSTITUTE_H,
            $ink
        );

        // ── Garis navy + gold ───────────────────────────────────────────────
        imagefilledrectangle(
            $canvas,
            self::RULE_X1,
            self::RULE_NAVY_TOP,
            self::RULE_X2,
            self::RULE_NAVY_TOP + self::RULE_NAVY_H - 1,
            $navy
        );
        imagefilledrectangle(
            $canvas,
            self::RULE_X1,
            self::RULE_GOLD_TOP,
            self::RULE_X2,
            self::RULE_GOLD_TOP + self::RULE_GOLD_H - 1,
            imagecolorallocate($canvas, ...self::GOLD)
        );

        // ── Skala akhir bila diminta lebih kecil ────────────────────────────
        if ($width !== self::WIDTH) {
            $width  = max(600, min(4000, $width));
            $height = (int) round($width * self::HEIGHT / self::WIDTH);
            $scaled = imagecreatetruecolor($width, $height);
            imagefilledrectangle($scaled, 0, 0, $width, $height, imagecolorallocate($scaled, 255, 255, 255));
            imagecopyresampled($scaled, $canvas, 0, 0, 0, 0, $width, $height, self::WIDTH, self::HEIGHT);
            $canvas = $scaled;
        }

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    /**
     * Bagi judul menjadi dua baris seperti kop aslinya: baris pertama diisi
     * sebanyak mungkin kata tanpa melebihi lebar aman.
     *
     * @return array{0:string,1:string}
     */
    public static function splitTitle(string $title): array
    {
        $font  = self::font('bold');
        $words = preg_split('/\s+/', trim($title)) ?: [];

        // Bila satu baris sudah cukup lebar, tidak perlu dipecah.
        $size = LogoGenerator::fitFontSize($font, $title, (float) self::TITLE_HEIGHT, self::MAX_TEXT_WIDTH * 2);
        $box  = imagettfbbox($size, 0, $font, $title);

        if (abs($box[2] - $box[0]) <= self::MAX_TEXT_WIDTH) {
            return [$title, ''];
        }

        $line1 = '';
        $rest  = [];

        foreach ($words as $word) {
            $candidate = trim($line1 . ' ' . $word);
            $box       = imagettfbbox($size, 0, $font, $candidate);

            if ($rest === [] && abs($box[2] - $box[0]) <= self::MAX_TEXT_WIDTH) {
                $line1 = $candidate;
                continue;
            }

            $rest[] = $word;
        }

        return [$line1, implode(' ', $rest)];
    }

    /**
     * Ukuran font agar lebar teks = $targetW, dengan batas tinggi $maxH
     * (teks yang diedit lebih panjang otomatis mengecil, tidak melebar keluar).
     */
    public static function fitWidth(string $font, string $text, float $targetW, float $maxH): float
    {
        if (trim($text) === '') {
            return 10.0;
        }

        $size = 40.0;

        for ($i = 0; $i < 12; $i++) {
            $box = imagettfbbox($size, 0, $font, $text);
            $w   = abs($box[2] - $box[0]);

            if ($w <= 0) {
                break;
            }

            $size *= $targetW / $w;
        }

        $box = imagettfbbox($size, 0, $font, $text);
        $h   = abs($box[7] - $box[1]);

        if ($h > $maxH && $h > 0) {
            $size *= $maxH / $h;
        }

        return round($size, 2);
    }

    /** Tulis teks rata tengah; baseline dipatok agar sejajar kop asli. */
    private static function text($canvas, string $text, string $font, float $size, int $top, int $targetHeight, int $color): void
    {
        if (trim($text) === '') {
            return;
        }

        $box   = imagettfbbox($size, 0, $font, $text);
        $textW = abs($box[2] - $box[0]);

        $x = (int) round((self::WIDTH - $textW) / 2 - min($box[0], $box[6]));
        $y = (int) round($top + $targetHeight - max($box[1], $box[3]));

        imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);
    }

    /** Kop di atas halaman A4 (untuk template surat). */
    public static function pdf(int $year, int $editionId = 0): string
    {
        $pngPath = storage_path('tmp/kop-' . $year . '-' . random_token(6) . '.png');

        if (!ensure_dir(dirname($pngPath))) {
            throw new RuntimeException('Folder tmp tidak writable.');
        }

        file_put_contents($pngPath, self::png($year, $editionId));

        try {
            $pdf = new KopPdf();
            $pdf->SetTitle('Letterhead CENIM ' . $year);
            $pdf->AddPage();
            // A4 lebar 210mm, margin 15mm → kop 180mm.
            $pdf->Image($pngPath, 15, 12, 180);

            return (string) $pdf->Output('S');
        } finally {
            @unlink($pngPath);
        }
    }

    /**
     * Simpan kop ke storage, catat di tabel files + media supaya otomatis
     * dipakai sebagai letterhead invoice/receipt/sertifikat.
     *
     * @return array<string,string>
     */
    public static function build(int $year, int $editionId): array
    {
        $dir     = 'generated/kop/' . $year;
        $created = [];

        $relative = $dir . '/kop.png';
        self::store($relative, self::png($year, $editionId));
        $created['png'] = $relative;

        $relative = $dir . '/kop-web.png';
        self::store($relative, self::png($year, $editionId, 1200));
        $created['png_web'] = $relative;

        $relative = $dir . '/kop-a4.pdf';
        self::store($relative, self::pdf($year, $editionId));
        $created['pdf'] = $relative;

        self::registerMedia($editionId, $year, $created);

        return $created;
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

    /**
     * Catat kop di tabel files + media dengan kind = 'kop'. PdfBase::letterheadPath()
     * membaca entri ini, jadi invoice/receipt/sertifikat langsung memakai kop
     * hasil generate tanpa perubahan kode.
     */
    private static function registerMedia(int $editionId, int $year, array $files): void
    {
        if ($editionId <= 0 || !Db::tableExists('media')) {
            return;
        }

        $path     = $files['png'] ?? '';
        $absolute = storage_path($path);

        if ($path === '' || !is_file($absolute)) {
            return;
        }

        // Bersihkan entri kop lama pada edisi ini.
        foreach (Db::all("SELECT file_id FROM media WHERE edition_id = ? AND kind = 'kop'", [$editionId]) as $row) {
            if ($row['file_id'] !== null) {
                FileRepo::softDelete((int) $row['file_id']);
            }
        }
        Db::delete('media', "edition_id = ? AND kind = 'kop'", [$editionId]);

        $fileId = Db::insert('files', [
            'edition_id'    => $editionId,
            'owner_type'    => 'media',
            'owner_id'      => 0,
            'purpose'       => 'kop',
            'driver'        => 'local',
            'path'          => $path,
            'original_name' => 'kop-cenim-' . $year . '.png',
            'mime'          => 'image/png',
            'size'          => (int) filesize($absolute),
            'sha256'        => (string) hash_file('sha256', $absolute),
            'uploaded_at'   => now(),
        ]);

        Db::insert('media', [
            'edition_id' => $editionId,
            'kind'       => 'kop',
            'file_id'    => $fileId,
            'label'      => 'CENIM ' . $year . ' letterhead',
            'meta_json'  => json_encode(['year' => $year, 'files' => $files], JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    /** @return array<string,array{path:string,size:int,time:int}> */
    public static function existing(int $year): array
    {
        $dir   = storage_path('generated/kop/' . $year);
        $files = [];

        foreach (glob($dir . '/*') ?: [] as $path) {
            $files[basename($path)] = [
                'path' => 'generated/kop/' . $year . '/' . basename($path),
                'size' => (int) filesize($path),
                'time' => (int) filemtime($path),
            ];
        }

        ksort($files);

        return $files;
    }
}
