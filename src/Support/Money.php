<?php

declare(strict_types=1);

namespace Cenim\Support;

/**
 * Nominal dalam kata — dipakai pada kuitansi (receipt), kebiasaan umum
 * dokumen pembayaran resmi. Ditulis dalam bahasa Inggris karena seluruh
 * dokumen konferensi berbahasa Inggris.
 */
final class Money
{
    private const UNITS = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen',
    ];

    private const TENS = [
        2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety',
    ];

    private const SCALES = [
        1_000_000_000_000 => 'trillion',
        1_000_000_000     => 'billion',
        1_000_000         => 'million',
        1_000             => 'thousand',
    ];

    /**
     * Contoh:
     *   words(4500000, 'IDR') → "four million five hundred thousand rupiah"
     *   words(350.5, 'USD')   → "three hundred fifty US dollars and fifty cents"
     */
    public static function words(float $amount, string $currency): string
    {
        $amount = round(abs($amount), 2);
        $whole  = (int) floor($amount);
        $cents  = (int) round(($amount - $whole) * 100);

        $currencyName = match (strtoupper($currency)) {
            'IDR'   => 'rupiah',
            'USD'   => 'US dollar',
            default => strtoupper($currency),
        };

        $text = self::number($whole);

        // Rupiah tidak memakai bentuk jamak; mata uang lain memakai "s".
        if ($currencyName !== 'rupiah' && $whole !== 1) {
            $currencyName .= 's';
        }

        $result = $text . ' ' . $currencyName;

        // Sen tidak relevan untuk rupiah pada praktik konferensi ini.
        if ($cents > 0 && strtoupper($currency) !== 'IDR') {
            $result .= ' and ' . self::number($cents) . ' cent' . ($cents === 1 ? '' : 's');
        }

        return $result;
    }

    /** Angka bulat menjadi kata. */
    public static function number(int $number): string
    {
        if ($number < 0) {
            return 'minus ' . self::number(-$number);
        }

        if ($number < 20) {
            return self::UNITS[$number];
        }

        if ($number < 100) {
            $tens = intdiv($number, 10);
            $rest = $number % 10;
            return self::TENS[$tens] . ($rest > 0 ? '-' . self::UNITS[$rest] : '');
        }

        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $rest     = $number % 100;
            return self::UNITS[$hundreds] . ' hundred' . ($rest > 0 ? ' ' . self::number($rest) : '');
        }

        foreach (self::SCALES as $value => $name) {
            if ($number >= $value) {
                $count = intdiv($number, $value);
                $rest  = $number % $value;
                return self::number($count) . ' ' . $name . ($rest > 0 ? ' ' . self::number($rest) : '');
            }
        }

        return (string) $number; // tidak akan tercapai untuk nominal realistis
    }
}
