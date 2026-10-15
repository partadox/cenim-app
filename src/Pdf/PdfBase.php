<?php

declare(strict_types=1);

namespace Cenim\Pdf;

// tFPDF mencari font di sini lebih dulu (PT Serif untuk kop); DejaVu tetap di folder bawaannya.
if (!defined('_SYSTEM_TTFONTS')) {
    define('_SYSTEM_TTFONTS', CENIM_ROOT . '/vendor-lite/fonts/');
}

use Cenim\Core\Db;
use Cenim\Domain\BrandingRepo;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\SettingRepo;
use Cenim\Generator\KopGenerator;
use Cenim\Storage\StorageManager;
use Cenim\Support\Money;

require_once CENIM_ROOT . '/vendor-lite/tfpdf/tfpdf.php';
// tFPDF upstream mengandalkan autoload composer untuk kelas TTFontFile
// (baris require-nya dikomentari di sana), jadi kita muat manual di sini.
require_once CENIM_ROOT . '/vendor-lite/tfpdf/font/unifont/ttfonts.php';

/**
 * Dasar semua dokumen PDF (invoice, receipt, sertifikat).
 *
 * Memakai tFPDF supaya nama author non-ASCII (aksen Latin, Vietnam, Kiril,
 * Yunani) tercetak benar — font DejaVu Sans Condensed di-subset otomatis
 * sehingga ukuran file tetap kecil.
 */
class PdfBase extends \tFPDF
{
    public const NAVY = [0, 57, 129];
    public const GOLD = [254, 189, 6];
    public const GREY = [107, 120, 147];
    public const LINE = [227, 232, 240];

    protected array $edition;
    protected string $documentTitle = '';
    protected bool $showLetterhead  = true;

    public function __construct(?array $edition = null, string $orientation = 'P', string $size = 'A4')
    {
        parent::__construct($orientation, 'mm', $size);

        $this->edition = $edition ?? (EditionRepo::context() ?? []);

        $this->SetMargins(15, 14, 15);
        $this->SetAutoPageBreak(true, 22);
        $this->AliasNbPages();
        $this->SetCreator('CENIM App');
        $this->SetAuthor((string) cfg('app_name'));

        $this->AddFont('dejavu', '', 'DejaVuSansCondensed.ttf', true);
        $this->AddFont('dejavu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
        $this->SetFont('dejavu', '', 10);
    }

    // ── Header & footer ─────────────────────────────────────────────────────

    /**
     * Kop surat yang DISUSUN di PDF (bukan satu gambar): logo CENIM di kiri,
     * teks kop di tengah sebagai teks PDF (tajam, ukuran menyesuaikan lebar),
     * logo ITS + Computer Engineering + BME di kanan, lalu garis navy/gold.
     * Teks diambil dari setting kop yang sama dengan generator kop
     * (kop_title, kop_organized_by, kop_institute).
     */
    public function Header(): void
    {
        if (!$this->showLetterhead) {
            return;
        }

        $editionId = (int) ($this->edition['id'] ?? 0);
        $year      = (int) ($this->edition['year'] ?? date('Y'));
        $texts     = KopGenerator::texts($year, $editionId);

        $top    = 10.0;
        $left   = $this->lMargin;
        $right  = $this->GetPageWidth() - $this->rMargin;
        $gap    = 3.0;

        // Logo kop per edisi (Kop & Logo → kop PDF): kiri & kanan, urut, tinggi masing-masing.
        $logos = ['left' => [], 'right' => []];
        foreach (BrandingRepo::kopLogos($editionId) as $logo) {
            $size = $logo['path'] !== null ? @getimagesize($logo['path']) : false;
            if ($size === false || $size[1] <= 0) {
                continue;
            }
            $h = (float) $logo['height_mm'];
            $logos[$logo['side'] === 'left' ? 'left' : 'right'][] = ['path' => $logo['path'], 'h' => $h, 'w' => $h * $size[0] / $size[1]];
        }

        // Tinggi baris kop = logo tertinggi (minimal 16 mm untuk blok teks).
        $height = 16.0;
        foreach (array_merge($logos['left'], $logos['right']) as $logo) {
            $height = max($height, $logo['h']);
        }

        $x = $left;
        foreach ($logos['left'] as $logo) {
            $this->Image($logo['path'], $x, $top + ($height - $logo['h']) / 2, $logo['w'], $logo['h']);
            $x += $logo['w'] + $gap;
        }
        $logoW = $x - $left;           // termasuk jarak setelah logo terakhir

        $rightW = 0.0;
        foreach ($logos['right'] as $logo) {
            $rightW += $logo['w'] + $gap;
        }
        $x = $right - $rightW + $gap;
        foreach ($logos['right'] as $logo) {
            $this->Image($logo['path'], $x, $top + ($height - $logo['h']) / 2, $logo['w'], $logo['h']);
            $x += $logo['w'] + $gap;
        }
        $partnersW = $rightW;

        // ── Tengah: teks kop ────────────────────────────────────────────────
        $textX  = $left + $logoW + ($logoW > 0 ? 1.0 : 0);
        $textW  = ($right - ($partnersW > 0 ? $partnersW + 1.0 : 0)) - $textX;
        $serif  = self::kopFontsReady($this);

        // Judul: baris baru yang ditulis admin dipakai apa adanya, kalau tidak dipecah otomatis.
        $titleLines = preg_split('/\R/', trim($texts['title'])) ?: [''];
        [$line1, $line2] = count($titleLines) > 1
            ? [trim($titleLines[0]), trim(implode(' ', array_slice($titleLines, 1)))]
            : self::splitForWidth($this, $texts['title'], $serif ? 'ptserif' : 'dejavu', 'B', 10.5, $textW);
        $titleSize = min(
            self::fitSize($this, $line1, $serif ? 'ptserif' : 'dejavu', 'B', 10.5, $textW),
            $line2 !== '' ? self::fitSize($this, $line2, $serif ? 'ptserif' : 'dejavu', 'B', 10.5, $textW) : 10.5
        );

        $lines = [[$line1, 'B', $titleSize, self::NAVY]];
        if ($line2 !== '') {
            $lines[] = [$line2, 'B', $titleSize, self::NAVY];
        }
        $lines[] = [$texts['organized'], '', self::fitSize($this, $texts['organized'], $serif ? 'ptserif' : 'dejavu', '', 7.2, $textW), [40, 40, 40]];
        $lines[] = [$texts['institute'], $serif ? 'I' : '', self::fitSize($this, $texts['institute'], $serif ? 'ptserif' : 'dejavu', $serif ? 'I' : '', 7.2, $textW), [40, 40, 40]];

        $lines = array_values(array_filter($lines, static fn (array $line): bool => trim((string) $line[0]) !== ''));

        // Tinggi blok teks → dipusatkan vertikal terhadap logo.
        $blockH = 0.0;
        foreach ($lines as [, , $size]) {
            $blockH += $size * 0.3528 * 1.25;
        }
        $y = $top + max(0.0, ($height - $blockH) / 2);

        foreach ($lines as [$text, $style, $size, $rgb]) {
            $this->SetFont($serif ? 'ptserif' : 'dejavu', $style, $size);
            $this->SetTextColor(...$rgb);
            $this->SetXY($textX, $y);
            $this->Cell($textW, $size * 0.3528 * 1.25, $text, 0, 0, 'C');
            $y += $size * 0.3528 * 1.25;
        }

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavu', '', 10);

        // ── Garis navy + gold ───────────────────────────────────────────────
        $this->SetY($top + $height + 2.5);
        $this->rule(0.8);
        $this->Ln(5);
    }

    /** Daftarkan PT Serif (font kop asli) bila tersedia; false = pakai DejaVu. */
    private static function kopFontsReady(self $pdf): bool
    {
        $dir = CENIM_ROOT . '/vendor-lite/fonts/';
        if (!is_file($dir . 'PT_Serif-Web-Bold.ttf')) {
            return false;
        }

        if (!isset($pdf->fonts['ptserifB'])) {
            $pdf->AddFont('ptserif', '', 'PT_Serif-Web-Regular.ttf', true);
            $pdf->AddFont('ptserif', 'B', 'PT_Serif-Web-Bold.ttf', true);
            $pdf->AddFont('ptserif', 'I', 'PT_Serif-Web-Italic.ttf', true);
        }

        return true;
    }

    /** Ukuran font (≤ $max) agar $text muat dalam $width mm. */
    private static function fitSize(self $pdf, string $text, string $family, string $style, float $max, float $width): float
    {
        $pdf->SetFont($family, $style, $max);
        $w = $pdf->GetStringWidth($text);

        return $w <= $width || $w <= 0 ? $max : max(5.0, floor($max * $width / $w * 10) / 10);
    }

    /** Pecah judul menjadi dua baris bila tidak muat satu baris pada ukuran $size. */
    private static function splitForWidth(self $pdf, string $text, string $family, string $style, float $size, float $width): array
    {
        $pdf->SetFont($family, $style, $size);
        if ($pdf->GetStringWidth($text) <= $width) {
            return [$text, ''];
        }

        // Titik pecah setelah koma bila ada, kalau tidak di kata yang paling seimbang.
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $best  = [$text, ''];
        $score = PHP_FLOAT_MAX;
        for ($i = 1; $i < count($words); $i++) {
            $a = implode(' ', array_slice($words, 0, $i));
            $b = implode(' ', array_slice($words, $i));
            $diff = abs($pdf->GetStringWidth($a) - $pdf->GetStringWidth($b)) - (str_ends_with($a, ',') ? 15 : 0);
            if ($diff < $score) {
                $score = $diff;
                $best  = [$a, $b];
            }
        }

        return $best;
    }

    public function Footer(): void
    {
        $this->SetY(-16);
        $this->rule(0.2);
        $this->Ln(1.5);

        $this->SetFont('dejavu', '', 7.5);
        $this->SetTextColor(...self::GREY);

        $left = ($this->edition['name'] ?? (string) cfg('app_name'))
            . ' · generated ' . fmt_dt(now(), 'd M Y H:i') . ' (' . (string) env('APP_TZ', 'UTC') . ')';

        $this->Cell(0, 4, $left, 0, 0, 'L');
        $this->Cell(0, 4, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    /** Garis pemisah navy + gold seperti pada kop surat. */
    protected function rule(float $thickness = 0.6): void
    {
        $x1 = $this->lMargin;
        $x2 = $this->GetPageWidth() - $this->rMargin;
        $y  = $this->GetY();

        $this->SetLineWidth($thickness);
        $this->SetDrawColor(...self::NAVY);
        $this->Line($x1, $y, $x2, $y);

        $this->SetDrawColor(...self::GOLD);
        $this->Line($x1, $y + $thickness + 0.3, $x2, $y + $thickness + 0.3);

        $this->SetLineWidth(0.2);
        $this->SetDrawColor(...self::LINE);
        $this->SetY($y + $thickness + 1.2);
    }

    // ── Blok konten ─────────────────────────────────────────────────────────

    /** Judul dokumen besar + kotak metadata di kanan. @param array<string,string> $meta */
    protected function documentHeader(string $title, array $meta, ?array $stamp = null): void
    {
        $top = $this->GetY();

        $this->SetFont('dejavu', 'B', 20);
        $this->SetTextColor(...self::NAVY);
        $this->Cell(90, 10, $title, 0, 1, 'L');

        if ($stamp !== null) {
            $this->stamp($stamp['text'], $stamp['color'] ?? self::GREY, 105, $top - 1);
        }

        $this->SetY($top + 12);
        $bodyY = $this->GetY();

        // Kotak metadata di kanan.
        $this->SetXY($this->GetPageWidth() - $this->rMargin - 78, $top + 12);
        $this->SetFont('dejavu', '', 9);

        foreach ($meta as $label => $value) {
            $x = $this->GetX();
            $this->SetTextColor(...self::GREY);
            $this->Cell(30, 5.4, (string) $label, 0, 0, 'L');
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('dejavu', 'B', 9);
            $this->Cell(48, 5.4, (string) $value, 0, 1, 'R');
            $this->SetFont('dejavu', '', 9);
            $this->SetX($x);
        }

        $this->SetTextColor(0, 0, 0);
        $this->SetY(max($bodyY, $this->GetY()));
    }

    /** Cap status (PAID / VOID) sebagai kotak berwarna. */
    protected function stamp(string $text, array $rgb, float $x, float $y): void
    {
        $this->SetFont('dejavu', 'B', 13);
        $width = $this->GetStringWidth($text) + 10;

        $this->SetDrawColor(...$rgb);
        $this->SetTextColor(...$rgb);
        $this->SetLineWidth(0.7);
        $this->Rect($x, $y, $width, 9, 'D');
        $this->SetXY($x, $y);
        $this->Cell($width, 9, $text, 0, 1, 'C');

        $this->SetLineWidth(0.2);
        $this->SetDrawColor(...self::LINE);
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * Dua kolom informasi berdampingan.
     *
     * @param array{title:string,lines:array<int,string>} $left
     * @param array{title:string,lines:array<int,string>} $right
     */
    protected function twoColumns(array $left, array $right): void
    {
        $startY = $this->GetY();
        $width  = ($this->GetPageWidth() - $this->lMargin - $this->rMargin) / 2 - 4;

        $this->column($left, $this->lMargin, $startY, $width);
        $endLeft = $this->GetY();

        $this->column($right, $this->lMargin + $width + 8, $startY, $width);

        $this->SetY(max($endLeft, $this->GetY()) + 2);
    }

    /** @param array{title:string,lines:array<int,string>} $block */
    private function column(array $block, float $x, float $y, float $width): void
    {
        $this->SetXY($x, $y);
        $this->SetFont('dejavu', 'B', 8);
        $this->SetTextColor(...self::GREY);
        $this->Cell($width, 4.6, strtoupper($block['title']), 0, 2, 'L');

        $this->SetFont('dejavu', '', 9.5);
        $this->SetTextColor(0, 0, 0);

        foreach ($block['lines'] as $index => $line) {
            if (trim((string) $line) === '') {
                continue;
            }
            $this->SetX($x);
            $this->SetFont('dejavu', $index === 0 ? 'B' : '', 9.5);
            $this->MultiCell($width, 4.8, (string) $line, 0, 'L');
        }

        $this->SetFont('dejavu', '', 10);
    }

    /**
     * Tabel rincian biaya.
     *
     * @param array<int,array{description:string,qty:float,unit_price:float,amount:float}> $items
     */
    protected function itemsTable(array $items, string $currency): void
    {
        $full  = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
        $wDesc = $full - 20 - 32 - 32;

        // Kepala tabel
        $this->SetFillColor(...self::NAVY);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('dejavu', 'B', 8.5);
        $this->Cell($wDesc, 7, '  DESCRIPTION', 0, 0, 'L', true);
        $this->Cell(20, 7, 'QTY', 0, 0, 'C', true);
        $this->Cell(32, 7, 'UNIT PRICE', 0, 0, 'R', true);
        $this->Cell(32, 7, 'AMOUNT  ', 0, 1, 'R', true);

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavu', '', 9.5);

        $fill = false;
        foreach ($items as $item) {
            $description = (string) $item['description'];
            $lines       = $this->wrappedLines($description, $wDesc - 6);
            $height      = max(7.0, $lines * 5.0 + 2);

            $this->SetFillColor(247, 249, 252);
            $x = $this->GetX();
            $y = $this->GetY();

            $this->Cell($wDesc, $height, '', 0, 0, 'L', $fill);
            $this->Cell(20, $height, (string) ((float) $item['qty'] == floor((float) $item['qty'])
                ? (int) $item['qty']
                : $item['qty']), 0, 0, 'C', $fill);
            $this->Cell(32, $height, fmt_money((float) $item['unit_price'], $currency), 0, 0, 'R', $fill);
            $this->Cell(32, $height, fmt_money((float) $item['amount'], $currency), 0, 1, 'R', $fill);

            // Deskripsi ditulis terpisah agar bisa membungkus beberapa baris.
            $this->SetXY($x + 3, $y + ($height - $lines * 5.0) / 2);
            $this->MultiCell($wDesc - 6, 5, $description, 0, 'L');
            $this->SetXY($x, $y + $height);

            $this->SetDrawColor(...self::LINE);
            $this->Line($this->lMargin, $this->GetY(), $this->lMargin + $full, $this->GetY());

            $fill = !$fill;
        }
    }

    /** Baris total di kanan tabel. @param array<int,array{0:string,1:string,2?:bool}> $rows */
    protected function totalsBlock(array $rows): void
    {
        $full = $this->GetPageWidth() - $this->lMargin - $this->rMargin;

        foreach ($rows as $row) {
            $isTotal = (bool) ($row[2] ?? false);

            $this->SetX($this->lMargin + $full - 84);
            $this->SetFont('dejavu', $isTotal ? 'B' : '', $isTotal ? 11 : 9.5);
            $this->SetTextColor(...($isTotal ? self::NAVY : self::GREY));
            $this->Cell(52, $isTotal ? 8 : 6, (string) $row[0], 0, 0, 'R');

            $this->SetTextColor(...($isTotal ? self::NAVY : [0, 0, 0]));
            $this->Cell(32, $isTotal ? 8 : 6, (string) $row[1], 0, 1, 'R');
        }

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavu', '', 10);
    }

    /** Kotak besar "amount received" untuk kuitansi, lengkap dengan nominal dalam kata. */
    protected function amountReceived(float $amount, string $currency): void
    {
        $full = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
        $y    = $this->GetY();

        $this->SetFillColor(230, 246, 237);
        $this->SetDrawColor(203, 234, 218);
        $this->SetLineWidth(0.3);
        $this->Rect($this->lMargin, $y, $full, 22, 'DF');

        $this->SetXY($this->lMargin + 5, $y + 3.5);
        $this->SetFont('dejavu', 'B', 8.5);
        $this->SetTextColor(18, 112, 74);
        $this->Cell($full - 10, 4.5, 'AMOUNT RECEIVED', 0, 2, 'L');

        $this->SetFont('dejavu', 'B', 17);
        $this->SetTextColor(18, 112, 74);
        $this->Cell($full - 10, 9, fmt_money($amount, $currency), 0, 2, 'L');

        $this->SetFont('dejavu', '', 8.5);
        $this->SetTextColor(...self::GREY);
        $this->Cell($full - 10, 4, ucfirst(Money::words($amount, $currency)), 0, 2, 'L');

        $this->SetXY($this->lMargin, $y + 24);
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(...self::LINE);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavu', '', 10);
    }

    /** Tabel label/nilai dua kolom. @param array<string,string> $rows */
    protected function detailTable(string $title, array $rows): void
    {
        $full = $this->GetPageWidth() - $this->lMargin - $this->rMargin;

        $this->SetFont('dejavu', 'B', 8.5);
        $this->SetTextColor(...self::GREY);
        $this->Cell(0, 5, strtoupper($title), 0, 1, 'L');

        $this->SetFont('dejavu', '', 9.5);
        $fill = false;

        foreach ($rows as $label => $value) {
            $this->SetFillColor(247, 249, 252);
            $this->SetTextColor(...self::GREY);
            $this->Cell(52, 6.4, '  ' . $label, 0, 0, 'L', $fill);
            $this->SetTextColor(0, 0, 0);
            $this->Cell($full - 52, 6.4, (string) $value, 0, 1, 'L', $fill);
            $fill = !$fill;
        }

        $this->Ln(1);
    }

    /** Blok tanda tangan di kanan bawah. */
    protected function signatureBlock(string $name, string $title, ?string $imagePath = null, string $caption = 'Conference secretariat'): void
    {
        if (trim($name) === '' && $imagePath === null) {
            return;
        }

        $this->Ln(4);

        // Blok (keterangan + gambar + nama + jabatan) jangan sampai terpotong ke halaman berikut.
        if ($this->GetY() + 52 > $this->GetPageHeight() - $this->bMargin) {
            $this->AddPage();
        }

        $width = 70;
        $x     = $this->GetPageWidth() - $this->rMargin - $width;
        $y     = $this->GetY();

        $this->SetXY($x, $y);
        $this->SetFont('dejavu', '', 9);
        $this->SetTextColor(...self::GREY);
        $this->Cell($width, 5, $caption, 0, 2, 'C');

        if ($imagePath !== null && is_file($imagePath)) {
            // Tanda tangan + stempel: muat dalam kotak 56×30 mm, rasio dijaga.
            $size  = @getimagesize($imagePath);
            $ratio = ($size !== false && $size[0] > 0) ? $size[1] / $size[0] : 0.4;
            $imgW  = 56.0;
            $imgH  = $imgW * $ratio;
            if ($imgH > 30.0) {
                $imgH = 30.0;
                $imgW = $imgH / $ratio;
            }
            $this->Image($imagePath, $x + ($width - $imgW) / 2, $this->GetY() + 1, $imgW, $imgH);
            $this->SetY($this->GetY() + $imgH + 2);
        } else {
            $this->Ln(16);
        }

        $this->SetX($x);
        $this->SetDrawColor(...self::GREY);
        $this->Line($x + 8, $this->GetY(), $x + $width - 8, $this->GetY());
        $this->Ln(1);

        $this->SetX($x);
        $this->SetFont('dejavu', 'B', 9.5);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($width, 5, $name !== '' ? $name : '', 0, 2, 'C');

        if (trim($title) !== '') {
            $this->SetFont('dejavu', '', 8.5);
            $this->SetTextColor(...self::GREY);
            $this->SetX($x);
            $this->Cell($width, 4.5, $title, 0, 2, 'C');
        }

        $this->SetDrawColor(...self::LINE);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('dejavu', '', 10);
    }

    /** Kotak catatan / instruksi. */
    protected function noteBlock(string $title, string $body, array $bg = [247, 249, 252]): void
    {
        if (trim($body) === '') {
            return;
        }

        $full = $this->GetPageWidth() - $this->lMargin - $this->rMargin;

        $this->SetFont('dejavu', 'B', 8.5);
        $this->SetTextColor(...self::GREY);
        $this->Cell(0, 5, strtoupper($title), 0, 1, 'L');

        $this->SetFont('dejavu', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(...$bg);
        $this->MultiCell($full, 4.8, $body, 0, 'L', true);
        $this->Ln(2);
    }

    // ── Utilitas ────────────────────────────────────────────────────────────

    /**
     * Kop surat: pakai hasil generator (tabel media, Fase 8) bila ada,
     * kalau belum pakai file referensi PNG yang sudah tersedia.
     */
    public static function letterheadPath(): ?string
    {
        $editionId = EditionRepo::contextId();

        if ($editionId > 0 && Db::tableExists('media')) {
            $row = Db::one(
                "SELECT f.* FROM media m
                 JOIN files f ON f.id = m.file_id
                 WHERE m.edition_id = ? AND m.kind = 'kop' AND f.deleted_at IS NULL
                 ORDER BY m.id DESC LIMIT 1",
                [$editionId]
            );

            if ($row !== null) {
                $path = StorageManager::forFile($row)->localPath($row);
                if ($path !== null && is_file($path)) {
                    return $path;
                }
            }
        }

        $year      = (int) (EditionRepo::context()['year'] ?? 0);
        $candidates = [
            storage_path('generated/kop/' . $year . '/kop.png'),
            CENIM_ROOT . '/reference/kop-surat-cenim' . $year . '.png',
            CENIM_ROOT . '/reference/kop-surat-cenim2026.png',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Nama file aman dari nomor dokumen (CENIM26/INV/0001 → CENIM26-INV-0001). */
    public static function safeName(string $number): string
    {
        return (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $number);
    }

    /** Simpan isi PDF ke folder cache dan kembalikan path relatifnya. */
    protected static function cache(string $relativePath, string $bytes): string
    {
        $absolute = storage_path($relativePath);

        if (!ensure_dir(dirname($absolute))) {
            throw new \RuntimeException('Folder cache PDF tidak writable: ' . dirname($absolute));
        }

        if (file_put_contents($absolute, $bytes) === false) {
            throw new \RuntimeException('Gagal menulis PDF ke ' . $absolute);
        }

        return $relativePath;
    }
    /**
     * Jumlah baris yang dipakai MultiCell untuk teks ini (pemenggalan per kata,
     * sedikit lebih ketat dari MultiCell supaya tinggi baris tidak pernah kurang).
     */
    protected function wrappedLines(string $text, float $width): int
    {
        $limit = $width - 2 * $this->cMargin - 0.5;
        $lines = 0;
        foreach (explode("\n", $text) as $paragraph) {
            $lines++;
            $current = 0.0;
            foreach (preg_split('/\s+/u', trim($paragraph)) ?: [] as $word) {
                $w = $this->GetStringWidth($word . ' ');
                if ($current > 0 && $current + $this->GetStringWidth($word) > $limit) {
                    $lines++;
                    $current = 0.0;
                }
                $current += $w;
            }
        }

        return max(1, $lines);
    }
}
