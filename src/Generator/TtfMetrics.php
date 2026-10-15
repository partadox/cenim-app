<?php

declare(strict_types=1);

namespace Cenim\Generator;

use RuntimeException;

/**
 * Kotak batas teks dari berkas TrueType — pengganti imagettfbbox() untuk
 * server tanpa ekstensi gd.
 *
 * Membaca tabel head, hhea, maxp, hmtx, cmap (format 4/12), loca, dan glyf
 * langsung dari .ttf. Hasilnya berformat sama dengan imagettfbbox (8 angka,
 * piksel pada 96 dpi, sumbu y ke bawah, titik asal di baseline kiri) supaya
 * perhitungan tata letak logo dan kop tetap sama. Kerning diabaikan, seperti
 * GD pada font yang kerningnya di tabel GPOS.
 */
final class TtfMetrics
{
    /** @var array<string,self> */
    private static array $cache = [];

    private int $unitsPerEm;

    /** @var array<int,int> codepoint => glyph */
    private array $cmap = [];

    /** @var list<int> */
    private array $advance = [];

    /** @var array<int,array{0:int,1:int,2:int,3:int}|null> glyph => xMin,yMin,xMax,yMax */
    private array $boxes = [];

    private string $data;

    /** @var array<string,int> */
    private array $tables = [];

    /** @var list<int> */
    private array $loca = [];

    /**
     * imagettfbbox bila gd ada (hasil persis), selain itu hitungan sendiri.
     *
     * @return array{0:int,1:int,2:int,3:int,4:int,5:int,6:int,7:int}
     */
    public static function bbox(float $size, string $font, string $text): array
    {
        if (ext_available('gd') && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $font, $text);
            if ($box !== false) {
                return $box;
            }
        }

        return self::load($font)->measure($size, $text);
    }

    public static function load(string $font): self
    {
        return self::$cache[$font] ??= new self($font);
    }

    private function __construct(string $font)
    {
        $data = @file_get_contents($font);

        if ($data === false || strlen($data) < 12) {
            throw new RuntimeException('Font tidak bisa dibaca: ' . basename($font));
        }

        $this->data = $data;

        $count = $this->u16(4);
        for ($i = 0; $i < $count; $i++) {
            $at = 12 + 16 * $i;
            $this->tables[substr($data, $at, 4)] = $this->u32($at + 8);
        }

        foreach (['head', 'hhea', 'maxp', 'hmtx', 'cmap', 'loca', 'glyf'] as $tag) {
            if (!isset($this->tables[$tag])) {
                throw new RuntimeException('Font tidak didukung (tabel ' . $tag . ' tidak ada): ' . basename($font));
            }
        }

        $head             = $this->tables['head'];
        $this->unitsPerEm = $this->u16($head + 18);
        $longLoca         = $this->s16($head + 50) === 1;
        $numGlyphs        = $this->u16($this->tables['maxp'] + 4);
        $numHMetrics      = $this->u16($this->tables['hhea'] + 34);

        $last = 0;
        for ($g = 0; $g < $numGlyphs; $g++) {
            if ($g < $numHMetrics) {
                $last = $this->u16($this->tables['hmtx'] + 4 * $g);
            }
            $this->advance[] = $last;
        }

        $loca = $this->tables['loca'];
        for ($g = 0; $g <= $numGlyphs; $g++) {
            $this->loca[] = $longLoca ? $this->u32($loca + 4 * $g) : 2 * $this->u16($loca + 2 * $g);
        }

        $this->readCmap();
    }

    /** @return array{0:int,1:int,2:int,3:int,4:int,5:int,6:int,7:int} */
    public function measure(float $size, string $text): array
    {
        $scale = $size * 96 / 72 / $this->unitsPerEm;
        $pen   = 0;
        $xMin  = $yMin = PHP_INT_MAX;
        $xMax  = $yMax = PHP_INT_MIN;

        foreach (mb_str_split($text) as $char) {
            $glyph = $this->cmap[mb_ord($char)] ?? 0;
            $box   = $this->glyphBox($glyph);

            if ($box !== null) {
                $xMin = min($xMin, $pen + $box[0]);
                $yMin = min($yMin, $box[1]);
                $xMax = max($xMax, $pen + $box[2]);
                $yMax = max($yMax, $box[3]);
            }

            $pen += $this->advance[$glyph] ?? 0;
        }

        if ($xMin === PHP_INT_MAX) {
            $xMin = $yMin = $yMax = 0;
            $xMax = $pen;
        }

        $l = (int) round($xMin * $scale);
        $r = (int) round($xMax * $scale);
        $b = (int) round(-$yMin * $scale);
        $t = (int) round(-$yMax * $scale);

        return [$l, $b, $r, $b, $r, $t, $l, $t];
    }

    /** @return array{0:int,1:int,2:int,3:int}|null */
    private function glyphBox(int $glyph): ?array
    {
        if (array_key_exists($glyph, $this->boxes)) {
            return $this->boxes[$glyph];
        }

        $start = $this->loca[$glyph] ?? 0;
        $end   = $this->loca[$glyph + 1] ?? $start;

        if ($end <= $start) {
            return $this->boxes[$glyph] = null; // glyph kosong (spasi)
        }

        $at = $this->tables['glyf'] + $start;

        return $this->boxes[$glyph] = [$this->s16($at + 2), $this->s16($at + 4), $this->s16($at + 6), $this->s16($at + 8)];
    }

    private function readCmap(): void
    {
        $cmap     = $this->tables['cmap'];
        $count    = $this->u16($cmap + 2);
        $format4  = null;
        $format12 = null;

        for ($i = 0; $i < $count; $i++) {
            $platform = $this->u16($cmap + 4 + 8 * $i);
            $encoding = $this->u16($cmap + 6 + 8 * $i);
            $offset   = $cmap + $this->u32($cmap + 8 + 8 * $i);
            $format   = $this->u16($offset);

            if ($format === 12 && ($platform === 3 || $platform === 0)) {
                $format12 = $offset;
            } elseif ($format === 4 && ($format4 === null || ($platform === 3 && $encoding === 1))) {
                $format4 = $offset; // utamakan Windows Unicode BMP (3,1)
            }
        }

        if ($format12 !== null) {
            $groups = $this->u32($format12 + 12);
            for ($i = 0; $i < $groups; $i++) {
                $at    = $format12 + 16 + 12 * $i;
                $first = $this->u32($at);
                $last  = min($this->u32($at + 4), $first + 0xFFFF);
                $glyph = $this->u32($at + 8);
                for ($c = $first; $c <= $last; $c++) {
                    $this->cmap[$c] = $glyph + ($c - $first);
                }
            }
            return;
        }

        if ($format4 === null) {
            throw new RuntimeException('Font tidak didukung (cmap Unicode tidak ada).');
        }

        $segX2     = $this->u16($format4 + 6);
        $ends      = $format4 + 14;
        $starts    = $ends + $segX2 + 2;
        $deltas    = $starts + $segX2;
        $rangeOffs = $deltas + $segX2;

        for ($s = 0; $s < $segX2 / 2; $s++) {
            $end   = $this->u16($ends + 2 * $s);
            $start = $this->u16($starts + 2 * $s);
            $delta = $this->s16($deltas + 2 * $s);
            $range = $this->u16($rangeOffs + 2 * $s);

            for ($c = $start; $c <= $end && $c !== 0xFFFF; $c++) {
                if ($range === 0) {
                    $glyph = ($c + $delta) & 0xFFFF;
                } else {
                    $glyph = $this->u16($rangeOffs + 2 * $s + $range + 2 * ($c - $start));
                    $glyph = $glyph === 0 ? 0 : ($glyph + $delta) & 0xFFFF;
                }
                $this->cmap[$c] = $glyph;
            }
        }
    }

    private function u16(int $at): int
    {
        return unpack('n', $this->data, $at)[1];
    }

    private function s16(int $at): int
    {
        $v = $this->u16($at);

        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private function u32(int $at): int
    {
        return unpack('N', $this->data, $at)[1];
    }
}
