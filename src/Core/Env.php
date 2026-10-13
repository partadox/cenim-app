<?php
/**
 * Pembaca file .env (format dotenv) tanpa library.
 *
 * Format yang didukung:
 *   KEY=value                 # komentar setelah spasi + '#' diabaikan
 *   export KEY=value          # prefix 'export' boleh (kompatibel dengan shell)
 *   KEY='literal $apa saja'   # kutip tunggal: apa adanya, tanpa escape
 *   KEY="baris1\nbaris2"      # kutip ganda: escape \n \r \t \" \\ diproses
 *
 * Nilai true/false/null/empty (tidak peka huruf, boleh berkutip) diubah ke
 * tipe PHP-nya; selebihnya tetap string — pemanggil env() yang meng-cast,
 * mis. (int) env('SMTP_PORT'). Tidak ada interpolasi ${VAR}, jadi hash
 * bcrypt ($2y$...) aman ditulis tanpa escape.
 *
 * Sengaja di-require langsung oleh bootstrap.php (sebelum autoloader aktif).
 */

declare(strict_types=1);

namespace Cenim\Core;

final class Env
{
    /** @return array<string, mixed> */
    public static function load(string $file): array
    {
        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException("Tidak bisa membaca {$file}");
        }

        return self::parse($raw, basename($file));
    }

    /** @return array<string, mixed> */
    public static function parse(string $raw, string $name = '.env'): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw; // BOM
        $env = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $i => $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                throw new \RuntimeException(sprintf('%s baris %d tidak valid (format: KEY=value)', $name, $i + 1));
            }

            $env[$m[1]] = self::cast(self::value($m[2], $name, $i + 1));
        }

        return $env;
    }

    private static function value(string $value, string $name, int $lineNo): string
    {
        // Kosong, atau kosong yang langsung diikuti komentar (KEY=   # catatan).
        if ($value === '' || $value[0] === '#') {
            return '';
        }

        $quote = $value[0];

        if ($quote === "'" || $quote === '"') {
            $end = $quote === "'"
                ? strpos($value, "'", 1)
                : (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $value, $m) ? strlen($m[0]) - 1 : false);

            if ($end === false) {
                throw new \RuntimeException(sprintf('%s baris %d: tanda kutip tidak ditutup', $name, $lineNo));
            }

            $rest = trim(substr($value, $end + 1));
            if ($rest !== '' && $rest[0] !== '#') {
                throw new \RuntimeException(sprintf('%s baris %d: ada teks setelah tanda kutip penutup', $name, $lineNo));
            }

            $inner = substr($value, 1, $end - 1);

            return $quote === "'"
                ? $inner
                : strtr($inner, ['\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\"' => '"', '\\\\' => '\\']);
        }

        // Tanpa kutip: komentar inline harus diawali spasi, agar nilai seperti
        // URL dengan '#' (https://x.id/#a) tetap utuh.
        $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;

        return trim($value);
    }

    private static function cast(string $value): mixed
    {
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}
