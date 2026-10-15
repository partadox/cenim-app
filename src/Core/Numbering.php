<?php

declare(strict_types=1);

namespace Cenim\Core;

/**
 * Penomoran dokumen (invoice, receipt, sertifikat) yang tidak boleh duplikat.
 *
 * Nomor urut disimpan per (edition_id, key) di tabel number_sequences dan
 * dinaikkan di dalam transaksi BEGIN IMMEDIATE, sehingga dua permintaan yang
 * datang bersamaan tetap mendapat nomor berbeda.
 *
 * Format diambil dari config/app.php:
 *   invoice     → CENIM{yy}/INV/{seq}       → CENIM26/INV/0001
 *   receipt     → CENIM{yy}/RCP/{seq}       → CENIM26/RCP/0001
 *   certificate → CENIM{year}-{type}-{seq}  → CENIM2026-AUT-0001
 */
final class Numbering
{
    /** Ambil nomor urut berikutnya (nilai mentah, belum diformat). */
    public static function next(int $editionId, string $key): int
    {
        return (int) Db::immediateTransaction(static function () use ($editionId, $key): int {
            // Tanpa upsert (ON CONFLICT butuh SQLite 3.24; server bisa lebih lama).
            Db::run(
                'INSERT OR IGNORE INTO number_sequences (edition_id, "key", current, updated_at)
                 VALUES (:edition_id, :key, 0, :updated_at)',
                ['edition_id' => $editionId, 'key' => $key, 'updated_at' => now()]
            );
            Db::run(
                'UPDATE number_sequences SET current = current + 1, updated_at = :updated_at
                 WHERE edition_id = :edition_id AND "key" = :key',
                ['edition_id' => $editionId, 'key' => $key, 'updated_at' => now()]
            );

            return (int) Db::value(
                'SELECT current FROM number_sequences WHERE edition_id = ? AND "key" = ?',
                [$editionId, $key]
            );
        });
    }

    /** Nomor terakhir yang sudah terpakai, tanpa menaikkannya. */
    public static function peek(int $editionId, string $key): int
    {
        return (int) (Db::value(
            'SELECT current FROM number_sequences WHERE edition_id = ? AND "key" = ?',
            [$editionId, $key]
        ) ?? 0);
    }

    public static function invoice(int $editionId, int $year): string
    {
        return self::format((string) cfg('numbering.invoice', 'CENIM{yy}/INV/{seq}'), $year, self::next($editionId, 'invoice'));
    }

    public static function receipt(int $editionId, int $year): string
    {
        return self::format((string) cfg('numbering.receipt', 'CENIM{yy}/RCP/{seq}'), $year, self::next($editionId, 'receipt'));
    }

    public static function certificate(int $editionId, int $year, string $typeCode): string
    {
        return self::format(
            (string) cfg('numbering.certificate', 'CENIM{year}-{type}-{seq}'),
            $year,
            self::next($editionId, 'certificate_' . $typeCode),
            ['type' => $typeCode]
        );
    }

    /** @param array<string,string> $extra placeholder tambahan, mis. ['type' => 'AUT'] */
    public static function format(string $template, int $year, int $sequence, array $extra = []): string
    {
        $pad = max(1, (int) cfg('numbering.seq_pad', 4));

        $replacements = [
            '{year}' => (string) $year,
            '{yy}'   => substr((string) $year, -2),
            '{seq}'  => str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT),
        ];

        foreach ($extra as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }

        return strtr($template, $replacements);
    }
}
