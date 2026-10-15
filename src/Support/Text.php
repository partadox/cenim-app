<?php

declare(strict_types=1);

namespace Cenim\Support;

/**
 * Perapian teks nama orang dan instansi.
 */
final class Text
{
    /** Kata sambung yang tetap huruf kecil bila bukan kata pertama. */
    private const MINOR_WORDS = [
        'of', 'and', 'the', 'for', 'in', 'on', 'at', 'to', 'de', 'del', 'la', 'le', 'du', 'des',
        'da', 'do', 'dos', 'van', 'von', 'der', 'den', 'y', 'e', 'di', 'dan', 'bin', 'binti', 'al',
    ];

    /**
     * Huruf kapital di awal setiap kata saja: "INSTITUT TEKNOLOGI" dan
     * "institut teknologi" → "Institut Teknologi".
     *
     * Yang sengaja dipertahankan: singkatan huruf besar (ITS, IEEE, PENS, PT,
     * UIN), kata ber-huruf-besar-di-tengah yang biasanya merek (NetApp, McDonald),
     * dan kata sambung (of, and, de, …) yang dibuat kecil. Pada teks yang
     * seluruhnya huruf besar, singkatan tidak bisa dibedakan dari kata biasa,
     * jadi hanya kata 1–3 huruf yang dibiarkan kapital.
     */
    public static function titleCase(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return '';
        }

        $letters  = preg_replace('/[^\p{L}]/u', '', $text) ?? '';
        $shouting = $letters !== '' && mb_strtoupper($letters) === $letters && mb_strlen($letters) > 4;

        $words = explode(' ', $text);
        foreach ($words as $i => $word) {
            $words[$i] = self::word($word, $i === 0, $shouting);
        }

        return implode(' ', $words);
    }

    private static function word(string $word, bool $first, bool $shouting): string
    {
        // Bagian yang dipisah tanda hubung / garis miring dirapikan masing-masing.
        if (preg_match('#[-/]#u', $word) === 1) {
            $parts = preg_split('#([-/])#u', $word, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$word];

            // Tiap bagian diperlakukan sebagai awal kata: "Al-Farisi", bukan "al-Farisi".
            return implode('', array_map(
                static fn (string $part): string => in_array($part, ['-', '/'], true) ? $part : self::word($part, true, $shouting),
                $parts
            ));
        }

        $core  = preg_replace('/[^\p{L}]/u', '', $word) ?? '';
        $lower = mb_strtolower($word);

        if ($core === '') {
            return $word; // angka, tanda baca
        }

        if (!$first && in_array(mb_strtolower($core), self::MINOR_WORDS, true)) {
            return $lower;
        }

        $isUpper = mb_strtoupper($core) === $core;

        if ($isUpper) {
            // Singkatan: ITS, IEEE, (PENS); pada teks berhuruf besar semua hanya ≤ 3 huruf.
            if (mb_strlen($core) >= 2 && (!$shouting ? mb_strlen($core) <= 6 : mb_strlen($core) <= 3)) {
                return $word;
            }
        } elseif (preg_match('/^\p{L}\p{Ll}*\p{Lu}/u', $core) === 1) {
            return $word; // NetApp, McDonald, CoreHW
        }

        // Huruf pertama (setelah tanda baca pembuka seperti "(") jadi kapital.
        return preg_replace_callback('/\p{L}/u', static fn (array $m): string => mb_strtoupper($m[0]), $lower, 1) ?? $lower;
    }
}
