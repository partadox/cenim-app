<?php

declare(strict_types=1);

namespace Cenim\Pdf;

use Cenim\Domain\BrandingRepo;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\LetterRepo;
use Cenim\Domain\LoaRepo;
use Cenim\Domain\SettingRepo;
use DOMElement;
use DOMNode;

/**
 * PDF surat dari Letter Generate: kop, judul + nomor (opsional), kota &
 * tanggal, isi dari editor WYSIWYG, lalu tanda tangan chair/treasurer.
 *
 * Isi HTML diterjemahkan sederhana: paragraf & heading (rata kiri/tengah/
 * kanan/justify), tebal/miring/garis bawah per kata, daftar berpoin/
 * bernomor, tabel, garis, dan baris baru. Gambar dan warna diabaikan.
 * Tidak di-cache.
 */
final class LetterPdf extends PdfBase
{
    private const BODY_LINE = 5.6;  // tinggi baris isi (mm)
    private const BODY_SIZE = 11.0; // ukuran huruf isi (pt)

    private string $family = 'dejavu';
    private bool $italicOk = false;
    /** @var array{b:int,i:int,u:int} */
    private array $styleDepth = ['b' => 0, 'i' => 0, 'u' => 0];

    public static function render(array $letter): string
    {
        $editionId = (int) $letter['edition_id'];
        $edition   = EditionRepo::find($editionId) ?? [];

        $pdf = new self($edition);
        $pdf->SetTitle((string) $letter['title']);
        $pdf->SetSubject((string) $letter['title']);
        $pdf->useBodyFont();
        $pdf->AddPage();

        // ── Judul + nomor ───────────────────────────────────────────────────
        if ((int) ($letter['show_title'] ?? 1) === 1) {
            $pdf->SetFont('dejavu', 'B', 15);
            $pdf->SetTextColor(...self::NAVY);
            $pdf->MultiCell(0, 8, mb_strtoupper((string) $letter['title']), 0, 'C');
            if (($letter['number'] ?? '') !== '') {
                $pdf->SetFont('dejavu', '', 9.5);
                $pdf->SetTextColor(...self::GREY);
                $pdf->Cell(0, 5, 'No. ' . $letter['number'], 0, 1, 'C');
            }
            $pdf->Ln(4);
        } elseif (($letter['number'] ?? '') !== '') {
            $pdf->SetFont('dejavu', '', 10);
            $pdf->Cell(0, 5, 'No. ' . $letter['number'], 0, 1, 'L');
        }

        // ── Kota & tanggal ──────────────────────────────────────────────────
        if (($letter['letter_date'] ?? null) !== null) {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('dejavu', '', 10);
            $city = SettingRepo::getString($editionId, 'loa_city', LoaRepo::DEFAULT_CITY);
            $pdf->Cell(0, 5, ($city !== '' ? $city . ', ' : '') . fmt_date($letter['letter_date'] . ' 00:00:00'), 0, 1, 'R');
            $pdf->Ln(3);
        }

        // ── Isi ─────────────────────────────────────────────────────────────
        $pdf->SetTextColor(0, 0, 0);
        $pdf->body(LetterRepo::render($letter));

        // ── Tanda tangan ────────────────────────────────────────────────────
        $signer = (string) ($letter['signer'] ?? 'chair');
        if ($signer === 'chair' || $signer === 'treasurer') {
            $person = $signer === 'chair' ? SettingRepo::chair($editionId) : SettingRepo::treasurer($editionId);
            $pdf->signatureBlock(
                $person['name'],
                $person['title'],
                BrandingRepo::signaturePath($editionId, $signer),
                'Sincerely,',
                self::stampFor($editionId)
            );
        }

        return (string) $pdf->Output('S');
    }

    public static function filename(array $letter): string
    {
        $base = trim((string) ($letter['number'] ?? '')) !== '' ? (string) $letter['number'] : (string) $letter['title'];

        return 'Letter-' . self::safeName(mb_substr($base, 0, 80)) . '.pdf';
    }

    // ── Font isi: PT Serif (biasa/tebal/miring) bila ada, kalau tidak DejaVu ──

    private function useBodyFont(): void
    {
        $dir = CENIM_ROOT . '/vendor-lite/fonts/';
        if (is_file($dir . 'PT_Serif-Web-Regular.ttf') && is_file($dir . 'PT_Serif-Web-Bold.ttf') && is_file($dir . 'PT_Serif-Web-Italic.ttf')) {
            if (!isset($this->fonts['ptserif'])) {
                $this->AddFont('ptserif', '', 'PT_Serif-Web-Regular.ttf', true);
                $this->AddFont('ptserif', 'B', 'PT_Serif-Web-Bold.ttf', true);
                $this->AddFont('ptserif', 'I', 'PT_Serif-Web-Italic.ttf', true);
            }
            $this->family   = 'ptserif';
            $this->italicOk = true;
        }
    }

    private function applyStyle(float $size = self::BODY_SIZE): void
    {
        $style = '';
        if ($this->styleDepth['b'] > 0) {
            $style .= 'B';
        } elseif ($this->styleDepth['i'] > 0 && $this->italicOk) {
            $style .= 'I'; // tidak ada font tebal-miring: tebal didahulukan
        }
        if ($this->styleDepth['u'] > 0) {
            $style .= 'U';
        }
        $this->SetFont($this->family, $style, $size);
    }

    // ── Penerjemah HTML ─────────────────────────────────────────────────────

    private function body(string $html): void
    {
        if (!ext_available('dom')) {
            $this->applyStyle();
            $this->MultiCell(0, self::BODY_LINE, trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br />'], "\n", $html)), ENT_QUOTES, 'UTF-8')), 0, 'L');

            return;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="cn-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $doc->getElementById('cn-root');
        if ($root !== null) {
            $this->blocks($root, 0);
        }
    }

    /** Anak-anak sebuah elemen sebagai blok berurutan; teks lepas dikumpulkan jadi paragraf. */
    private function blocks(DOMNode $parent, int $indent): void
    {
        $inline = [];
        $flush  = function () use (&$inline, $indent): void {
            if ($inline !== []) {
                $this->paragraph($inline, 'L', $indent, self::BODY_SIZE, 2.0);
                $inline = [];
            }
        };

        foreach ($parent->childNodes as $node) {
            $tag = $node instanceof DOMElement ? strtolower($node->tagName) : '#text';

            switch ($tag) {
                case 'p':
                case 'div':
                case 'blockquote':
                case 'address':
                    $flush();
                    if ($this->hasBlockChildren($node)) {
                        $this->blocks($node, $indent + ($tag === 'blockquote' ? 8 : 0));
                    } else {
                        $this->paragraph(iterator_to_array($node->childNodes), $this->align($node), $indent + ($tag === 'blockquote' ? 8 : 0), self::BODY_SIZE, 2.0);
                    }
                    break;
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $flush();
                    $sizes = ['h1' => 16, 'h2' => 14.5, 'h3' => 13, 'h4' => 12, 'h5' => 11.5, 'h6' => 11];
                    $this->styleDepth['b']++;
                    $this->paragraph(iterator_to_array($node->childNodes), $this->align($node), $indent, (float) $sizes[$tag], 2.0);
                    $this->styleDepth['b']--;
                    break;
                case 'ul':
                case 'ol':
                    $flush();
                    $this->listBlock($node, $tag === 'ol', $indent);
                    break;
                case 'table':
                    $flush();
                    $this->table($node, $indent);
                    break;
                case 'hr':
                    $flush();
                    $y = $this->GetY() + 1;
                    $this->SetDrawColor(180, 188, 204);
                    $this->Line($this->lMargin + $indent, $y, $this->GetPageWidth() - $this->rMargin, $y);
                    $this->SetY($y + 3);
                    break;
                case 'img':
                case 'script':
                case 'style':
                    break;
                default:
                    $inline[] = $node; // teks, b, i, span, a, br, ...
            }
        }

        $flush();
    }

    private function hasBlockChildren(DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['p', 'div', 'ul', 'ol', 'table', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'hr'], true)) {
                return true;
            }
        }

        return false;
    }

    private function align(DOMNode $node): string
    {
        if (!$node instanceof DOMElement) {
            return 'L';
        }
        $value = strtolower($node->getAttribute('align'));
        if (preg_match('/text-align\s*:\s*([a-z]+)/', strtolower($node->getAttribute('style')), $m)) {
            $value = $m[1];
        }

        return match ($value) { 'center' => 'C', 'right' => 'R', 'justify' => 'J', default => 'L' };
    }

    /**
     * Satu paragraf dengan perataan kiri/tengah/kanan/justify; gaya tebal,
     * miring, garis bawah tetap per kata (lihat flow()).
     *
     * @param array<int,DOMNode> $nodes
     */
    private function paragraph(array $nodes, string $align, int $indent, float $size, float $after): void
    {
        $runs = [];
        foreach ($nodes as $node) {
            $this->collectRuns($node, $runs);
        }
        if (trim(str_replace("\n", '', implode('', array_column($runs, 'text')))) === '') {
            $this->Ln(self::BODY_LINE * 0.6); // paragraf kosong (<p>&nbsp;</p>) = jarak
            return;
        }

        $x = $this->lMargin + $indent;
        $this->flow($runs, $align, $x, $this->GetPageWidth() - $this->rMargin - $x, $size);
        $this->Ln($after);
    }

    /**
     * Susun potongan teks bergaya menjadi baris-baris selebar $width mulai
     * dari $x pada posisi Y sekarang. Kata dipecah per spasi; satu kata boleh
     * terdiri dari beberapa gaya (mis. tebal lalu koma biasa). Justify
     * membagi sisa ruang ke celah antar-kata, kecuali baris terakhir dan
     * baris sebelum <br>.
     *
     * @param array<int,array{text:string,style:array{b:int,i:int,u:int}}> $runs
     */
    private function flow(array $runs, string $align, float $x, float $width, float $size): void
    {
        $saved = $this->styleDepth;
        $lineH = self::BODY_LINE * $size / self::BODY_SIZE;

        // ── Token: kata (beberapa bagian bergaya) dan baris baru ────────────
        $tokens  = [];
        $current = null;
        $space   = false;
        foreach ($runs as $run) {
            if ($run['text'] === "\n") {
                if ($current !== null) {
                    $tokens[] = $current;
                    $current  = null;
                }
                $tokens[] = ['br' => true];
                $space    = false;
                continue;
            }
            foreach (preg_split('/(\s+)/u', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
                if (preg_match('/^\s+$/u', $piece)) {
                    $space = true;
                    continue;
                }
                if ($current === null || $space) {
                    if ($current !== null) {
                        $tokens[] = $current;
                    }
                    $current = ['parts' => [], 'w' => 0.0];
                }
                $this->styleDepth = $run['style'];
                $this->applyStyle($size);
                $w = $this->GetStringWidth($piece);
                $current['parts'][] = ['text' => $piece, 'style' => $run['style'], 'w' => $w];
                $current['w'] += $w;
                $space = false;
            }
        }
        if ($current !== null) {
            $tokens[] = $current;
        }

        $this->styleDepth = ['b' => 0, 'i' => 0, 'u' => 0];
        $this->applyStyle($size);
        $spaceW = $this->GetStringWidth(' ');

        // ── Baris ───────────────────────────────────────────────────────────
        $lines = [];
        $line  = [];
        $used  = 0.0;
        foreach ($tokens as $token) {
            if (isset($token['br'])) {
                $lines[] = ['words' => $line, 'last' => true];
                $line    = [];
                $used    = 0.0;
                continue;
            }
            $need = $line === [] ? $token['w'] : $used + $spaceW + $token['w'];
            if ($line !== [] && $need > $width) {
                $lines[] = ['words' => $line, 'last' => false];
                $line    = [$token];
                $used    = $token['w'];
            } else {
                $line[] = $token;
                $used   = $need;
            }
        }
        if ($line !== []) {
            $lines[] = ['words' => $line, 'last' => true];
        }

        // ── Tulis ───────────────────────────────────────────────────────────
        $oldCMargin    = $this->cMargin;
        $this->cMargin = 0;
        foreach ($lines as $row) {
            if ($this->GetY() + $lineH > $this->GetPageHeight() - $this->bMargin) {
                $this->AddPage();
            }
            $y     = $this->GetY();
            $words = $row['words'];
            if ($words === []) {
                $this->SetY($y + $lineH);
                continue;
            }
            $sum   = array_sum(array_column($words, 'w'));
            $total = $sum + $spaceW * (count($words) - 1);
            $gap   = $spaceW;
            $left  = $x;
            if ($align === 'J' && !$row['last'] && count($words) > 1) {
                $gap = ($width - $sum) / (count($words) - 1);
            } elseif ($align === 'C') {
                $left = $x + max(0, ($width - $total) / 2);
            } elseif ($align === 'R') {
                $left = $x + max(0, $width - $total);
            }

            $cursor = $left;
            foreach ($words as $word) {
                foreach ($word['parts'] as $part) {
                    $this->styleDepth = $part['style'];
                    $this->applyStyle($size);
                    $this->SetXY($cursor, $y);
                    $this->Cell($part['w'], $lineH, $part['text'], 0, 0, 'L');
                    $cursor += $part['w'];
                }
                $cursor += $gap;
            }
            $this->SetY($y + $lineH);
        }
        $this->cMargin    = $oldCMargin;
        $this->styleDepth = $saved;
        $this->applyStyle();
        $this->SetX($this->lMargin);
    }

    /** @param array<int,array{text:string,style:array{b:int,i:int,u:int}}> $runs */
    private function collectRuns(DOMNode $node, array &$runs): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $runs[] = ['text' => str_replace("\u{00A0}", ' ', (string) $node->nodeValue), 'style' => $this->styleDepth];

            return;
        }
        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        if ($tag === 'br') {
            $runs[] = ['text' => "\n", 'style' => $this->styleDepth];

            return;
        }
        if (in_array($tag, ['img', 'script', 'style'], true)) {
            return;
        }

        $css   = strtolower($node->getAttribute('style'));
        $bold  = in_array($tag, ['b', 'strong', 'th'], true) || preg_match('/font-weight\s*:\s*(bold|[6-9]00)/', $css) === 1;
        $ital  = in_array($tag, ['i', 'em'], true) || str_contains($css, 'font-style: italic') || str_contains($css, 'font-style:italic');
        $under = $tag === 'u' || str_contains($css, 'underline');

        $this->styleDepth['b'] += $bold ? 1 : 0;
        $this->styleDepth['i'] += $ital ? 1 : 0;
        $this->styleDepth['u'] += $under ? 1 : 0;
        foreach ($node->childNodes as $child) {
            $this->collectRuns($child, $runs);
        }
        $this->styleDepth['b'] -= $bold ? 1 : 0;
        $this->styleDepth['i'] -= $ital ? 1 : 0;
        $this->styleDepth['u'] -= $under ? 1 : 0;
    }

    private function listBlock(DOMNode $list, bool $ordered, int $indent): void
    {
        $n = (int) ($list instanceof DOMElement && $list->getAttribute('start') !== '' ? $list->getAttribute('start') : 1);

        foreach ($list->childNodes as $item) {
            if (!$item instanceof DOMElement || strtolower($item->tagName) !== 'li') {
                continue;
            }
            $marker = $ordered ? $n++ . '.' : "\u{2022}";
            $this->SetFont('dejavu', '', self::BODY_SIZE - 1);
            $this->SetX($this->lMargin + $indent);
            $this->Cell(7, self::BODY_LINE, $marker, 0, 0, 'R');

            // Isi butir (bisa berisi sub-daftar) ditulis menjorok 9 mm.
            $inline = [];
            $nested = [];
            foreach ($item->childNodes as $child) {
                $tag = $child instanceof DOMElement ? strtolower($child->tagName) : '';
                if (in_array($tag, ['ul', 'ol'], true)) {
                    $nested[] = $child;
                } elseif (in_array($tag, ['p', 'div'], true)) {
                    foreach ($child->childNodes as $grand) {
                        $inline[] = $grand;
                    }
                } else {
                    $inline[] = $child;
                }
            }
            $this->paragraphAt($inline, $indent + 9);
            foreach ($nested as $sub) {
                $this->listBlock($sub, strtolower($sub->tagName) === 'ol', $indent + 9);
            }
        }
        $this->Ln(1.5);
    }

    /** Isi butir daftar: mulai di baris yang sama dengan marker, menjorok $indent mm. */
    private function paragraphAt(array $nodes, int $indent): void
    {
        $runs = [];
        foreach ($nodes as $node) {
            $this->collectRuns($node, $runs);
        }
        $x = $this->lMargin + $indent;
        $this->flow($runs, 'L', $x, $this->GetPageWidth() - $this->rMargin - $x, self::BODY_SIZE);
    }

    /** Tabel sederhana: lebar kolom sama, teks per sel dibungkus, baris pertama <th> ditebalkan. */
    private function table(DOMNode $table, int $indent): void
    {
        $rows = [];
        foreach ((new \DOMXPath($table->ownerDocument))->query('.//tr', $table) as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    $cells[] = ['text' => trim((string) preg_replace('/\s+/u', ' ', $cell->textContent)), 'head' => strtolower($cell->tagName) === 'th'];
                }
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }
        if ($rows === []) {
            return;
        }

        $border = (string) ($table instanceof DOMElement ? $table->getAttribute('border') : '');
        $lines  = $border !== '' && $border !== '0' || str_contains(strtolower($table instanceof DOMElement ? $table->getAttribute('style') : ''), 'border');
        $cols   = max(array_map('count', $rows));
        $width  = ($this->GetPageWidth() - $this->lMargin - $this->rMargin - $indent) / $cols;
        $h      = self::BODY_LINE - 0.4;

        foreach ($rows as $row) {
            $max = 1;
            foreach ($row as $cell) {
                $this->SetFont($this->family, $cell['head'] ? 'B' : '', self::BODY_SIZE - 0.5);
                $max = max($max, $this->wrappedLines($cell['text'], $width - 2));
            }
            if ($this->GetY() + $max * $h > $this->GetPageHeight() - $this->bMargin) {
                $this->AddPage();
            }
            $y = $this->GetY();
            $x = $this->lMargin + $indent;
            foreach ($row as $i => $cell) {
                $this->SetFont($this->family, $cell['head'] ? 'B' : '', self::BODY_SIZE - 0.5);
                $this->SetXY($x + $i * $width, $y);
                $this->MultiCell($width, $h, $cell['text'], 0, 'L');
                if ($lines) {
                    $this->SetDrawColor(180, 188, 204);
                    $this->Rect($x + $i * $width, $y, $width, $max * $h);
                }
            }
            $this->SetY($y + $max * $h);
        }
        $this->Ln(2.5);
    }
}
