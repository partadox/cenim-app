<?php

declare(strict_types=1);

namespace Cenim\Support;

use RuntimeException;

/**
 * Penulis berkas Excel (.xlsx) satu sheet — PHP murni di atas ZipWriter, tanpa
 * library dan tanpa ekstensi zip. Baris judul tebal, dibekukan, dan diberi
 * filter; lebar kolom menyesuaikan isi.
 */
final class Xlsx
{
    /**
     * @param list<string>             $headers
     * @param list<list<string|int|float|null>> $rows
     */
    public static function write(string $path, string $sheetName, array $headers, array $rows): void
    {
        $sheetName = mb_substr(preg_replace('#[\[\]:*?/\\\\]#', ' ', $sheetName) ?? 'Sheet1', 0, 31) ?: 'Sheet1';
        $cols      = count($headers);

        if ($cols === 0) {
            throw new RuntimeException('Excel export needs at least one column.');
        }

        // Lebar kolom: panjang isi terpanjang (dibatasi), dalam satuan karakter.
        $widths = array_map(static fn ($h): int => mb_strlen((string) $h), $headers);
        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $value) {
                if ($i < $cols) {
                    $widths[$i] = max($widths[$i], mb_strlen((string) $value));
                }
            }
        }

        $colXml = '';
        foreach ($widths as $i => $w) {
            $colXml .= sprintf('<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>', $i + 1, max(8, min(60, $w + 2)));
        }

        $sheetRows = self::row(1, $headers, 1);
        foreach (array_values($rows) as $r => $row) {
            $sheetRows .= self::row($r + 2, array_slice(array_values($row), 0, $cols), 0);
        }

        $lastCell = self::column($cols) . (count($rows) + 1);

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $colXml . '</cols>'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastCell . '"/>'
            . '</worksheet>';

        $zip = new ZipWriter($path);
        $zip->addString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . self::xml(str_replace("'", "''", $sheetName)) . '\'!$A$1:$' . self::column($cols) . '$' . (count($rows) + 1) . '</definedName></definedNames>'
            . '</workbook>');
        $zip->addString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF003880"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');
        $zip->addString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
    }

    /** Kirim berkas .xlsx ke browser lalu hentikan request. */
    public static function download(string $filename, string $sheetName, array $headers, array $rows): never
    {
        $tmp = storage_path('tmp/export-' . random_token(8) . '.xlsx');
        if (!ensure_dir(dirname($tmp))) {
            throw new RuntimeException('Folder tmp tidak writable.');
        }

        try {
            self::write($tmp, $sheetName, $headers, $rows);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
            header('Content-Length: ' . (string) filesize($tmp));
            header('Cache-Control: no-store');
            readfile($tmp);
        } finally {
            @unlink($tmp);
        }

        exit;
    }

    /** @param list<string|int|float|null> $values */
    private static function row(int $index, array $values, int $style): string
    {
        $cells = '';
        foreach ($values as $i => $value) {
            $ref = self::column($i + 1) . $index;
            $s   = $style > 0 ? ' s="' . $style . '"' : '';

            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="' . $ref . '"' . $s . '><v>' . $value . '</v></c>';
            } elseif ($value !== null && $value !== '') {
                $cells .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . self::xml((string) $value) . '</t></is></c>';
            }
        }

        return '<row r="' . $index . '">' . $cells . '</row>';
    }

    /** 1 → A, 27 → AA. */
    private static function column(int $n): string
    {
        $name = '';
        while ($n > 0) {
            $n--;
            $name = chr(65 + $n % 26) . $name;
            $n    = intdiv($n, 26);
        }

        return $name;
    }

    private static function xml(string $text): string
    {
        // Karakter kontrol tidak sah di XML (kecuali tab/baris baru).
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
