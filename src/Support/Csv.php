<?php

declare(strict_types=1);

namespace Cenim\Support;

use RuntimeException;

/**
 * Baca/tulis CSV untuk import-export data author.
 *
 * Menangani hal-hal yang biasa merusak file ekspor dari Excel/EDAS:
 * BOM UTF-8, pemisah selain koma (; atau tab), dan encoding Windows-1252.
 */
final class Csv
{
    private const DELIMITERS = [',', ';', "\t", '|'];

    /**
     * @return array{headers: array<int,string>, rows: array<int,array<int,string>>, truncated: bool}
     */
    public static function read(string $path, int $maxRows = 20000): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File CSV tidak bisa dibaca.');
        }

        $raw = (string) file_get_contents($path);
        if ($raw === '') {
            throw new RuntimeException('File CSV kosong.');
        }

        // Buang BOM UTF-8 lalu pastikan isinya UTF-8 yang valid.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        // Normalkan akhir baris agar CR-only (Mac lama) tetap terbaca.
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $delimiter = self::detectDelimiter($raw);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Tidak bisa membuka buffer sementara.');
        }
        fwrite($handle, $raw);
        rewind($handle);

        $headers   = [];
        $rows      = [];
        $truncated = false;

        while (($line = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            // Lewati baris yang benar-benar kosong.
            if ($line === [null] || (count($line) === 1 && trim((string) $line[0]) === '')) {
                continue;
            }

            $line = array_map(static fn ($v): string => trim((string) ($v ?? '')), $line);

            if ($headers === []) {
                $headers = $line;
                continue;
            }

            if (count($rows) >= $maxRows) {
                $truncated = true;
                break;
            }

            $rows[] = $line;
        }

        fclose($handle);

        if ($headers === []) {
            throw new RuntimeException('Baris header tidak ditemukan.');
        }

        return ['headers' => $headers, 'rows' => $rows, 'truncated' => $truncated];
    }

    /**
     * Cocokkan header CSV ke field target berdasarkan alias.
     *
     * @param array<int,string>              $headers
     * @param array<string,array<int,string>> $aliases  field => daftar alias
     * @return array<string,int|null>        field => indeks kolom CSV
     */
    public static function guessMapping(array $headers, array $aliases): array
    {
        $normalized = [];
        foreach ($headers as $index => $header) {
            $normalized[$index] = self::normalizeHeader($header);
        }

        $mapping = [];
        $used    = [];

        foreach ($aliases as $field => $candidates) {
            $mapping[$field] = null;

            foreach ($candidates as $candidate) {
                $needle = self::normalizeHeader($candidate);

                foreach ($normalized as $index => $header) {
                    if (in_array($index, $used, true)) {
                        continue;
                    }
                    if ($header === $needle) {
                        $mapping[$field] = $index;
                        $used[]          = $index;
                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Kirim CSV sebagai unduhan. BOM disertakan agar Excel membaca UTF-8 dengan benar.
     *
     * @param array<int,string>              $headers
     * @param iterable<int,array<int,mixed>> $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): never
    {
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
        }

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '\\');
        }

        fclose($out);
        exit;
    }

    public static function detectDelimiter(string $sample): string
    {
        $firstLine = strtok($sample, "\n") ?: '';
        $best      = ',';
        $bestCount = 0;

        foreach (self::DELIMITERS as $delimiter) {
            // Hitung di luar tanda kutip agar judul paper yang memuat koma tidak menipu.
            $count = count(str_getcsv($firstLine, $delimiter, '"', '\\'));
            if ($count > $bestCount) {
                $bestCount = $count;
                $best      = $delimiter;
            }
        }

        return $best;
    }

    private static function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        return (string) preg_replace('/[^a-z0-9]+/', '', $header);
    }
}
