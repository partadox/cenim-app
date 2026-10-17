<?php

declare(strict_types=1);

namespace Cenim\Support;

use Cenim\Domain\AuthorRepo;
use Cenim\Domain\PaperAuthorRepo;

/**
 * Import ekspor "papers" dari EDAS (Conference → Papers → Download → CSV).
 *
 * Satu baris = satu paper, dengan kolom "#" (paper ID), "Title", "Status",
 * dan per author: "Author N title/given/surname/affiliation/country/email"
 * (N = 1..6, atau lebih bila EDAS menambah kolom). Sekali import mengisi
 * paper (tabel authors, first author = Author 1) beserta semua author-nya
 * (paper_authors, urutan = N).
 *
 * Catatan format EDAS:
 *   - Header "Author N affiliation"/"Author N email" muncul dua kali: blok
 *     ringkas di depan (afiliasi + ", Negara") dan blok rinci per author.
 *     Yang dipakai blok rinci (kolom terakhir dengan nama itu).
 *   - Afiliasi rinci kadang "none"/kosong; isinya lalu diambil dari kolom
 *     "Authors with affiliation and country" (mis. "A, B and C (ITS, Indonesia); D (PT X, Indonesia)").
 *   - Negara berupa kode ISO ("ID") → diubah ke nama ("Indonesia").
 *   - Nama di kolom gabungan itu biasanya nama lengkap ("Faris Muhamad
 *     Syariati"), sedangkan given+surname bisa terpotong ("Faris Syariati");
 *     nama lengkap dipakai bila jelas milik author yang sama.
 *   - Jumlah halaman manuskrip review (opsional) dari kolom "review manuscript: pages" / "Pages".
 */
final class EdasImport
{
    /** Status EDAS → status paper di sistem. */
    public static function status(string $edas): string
    {
        $s = mb_strtolower(trim($edas));

        return match (true) {
            str_contains($s, 'accept') || str_contains($s, 'publish') => 'accepted',
            str_contains($s, 'reject')                                => 'rejected',
            str_contains($s, 'withdr')                                => 'withdrawn',
            default                                                   => 'pending',
        };
    }

    /** File ini ekspor paper EDAS? */
    public static function detect(array $headers): bool
    {
        $norm = array_map(static fn (string $h): string => mb_strtolower(trim($h)), $headers);

        return in_array('#', $norm, true)
            && in_array('title', $norm, true)
            && in_array('author 1 given', $norm, true)
            && in_array('author 1 email', $norm, true);
    }

    /**
     * Ubah baris CSV menjadi daftar paper siap simpan.
     *
     * @param array<int,string>            $headers
     * @param array<int,array<int,string>> $rows
     * @return array<int,array{line:int, paper_id:string, title:string, edas_status:string, status:string,
     *                         pages:int, authors:array<int,array<string,string>>, warnings:array<int,string>}>
     */
    public static function parse(array $headers, array $rows): array
    {
        // Indeks kolom; untuk header ganda yang terakhir menang (blok rinci).
        $col = [];
        foreach ($headers as $i => $h) {
            $col[mb_strtolower(trim($h))] = $i;
        }
        $cell = static fn (array $row, string $name): string => isset($col[$name]) ? trim((string) ($row[$col[$name]] ?? '')) : '';
        $pagesCol = null;
        // Halaman manuskrip review; halaman final diimpor terpisah (Confirmations → Import final pages).
        foreach (['review manuscript: pages', 'pages', 'total pages'] as $name) {
            if (isset($col[$name])) {
                $pagesCol = $name;
                break;
            }
        }

        $maxAuthors = 0;
        while (isset($col['author ' . ($maxAuthors + 1) . ' given'])) {
            $maxAuthors++;
        }

        $papers = [];
        foreach ($rows as $i => $row) {
            $paperId = $cell($row, '#');
            $title   = $cell($row, 'title');
            if ($paperId === '' && $title === '') {
                continue;
            }

            $warnings = [];
            $groups   = self::parseAuthorList($cell($row, 'authors with affiliation and country'));
            $authors  = [];

            // Kolom gabungan hanya dipakai bila jumlah namanya cocok dengan jumlah author.
            $slots = 0;
            for ($n = 1; $n <= $maxAuthors; $n++) {
                if ($cell($row, "author $n given") . $cell($row, "author $n surname") !== '') {
                    $slots++;
                }
            }
            if (count($groups) !== $slots) {
                $groups = [];
            }

            for ($n = 1; $n <= $maxAuthors; $n++) {
                $given   = $cell($row, "author $n given");
                $surname = $cell($row, "author $n surname");
                $email   = $cell($row, "author $n email");
                if ($given === '' && $surname === '' && $email === '') {
                    continue;
                }

                $name  = trim(preg_replace('/\s+/', ' ', $given . ' ' . $surname) ?? '');
                $group = $groups[count($authors)] ?? null;
                if ($group !== null && self::sameName($name, $group['name'])) {
                    $name = $group['name'];
                }

                $affiliation = $cell($row, "author $n affiliation");
                if (self::isBlank($affiliation)) {
                    $affiliation = $group['affiliation'] ?? '';
                }

                $country = self::country($cell($row, "author $n country"));
                if ($country === '' && $n === 1) {
                    $country = self::country($cell($row, 'first author country'));
                }

                if ($name === '') {
                    $warnings[] = "Author $n has no name — skipped.";
                    continue;
                }
                if ($email === '') {
                    $warnings[] = "$name has no email and cannot sign in.";
                } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $warnings[] = "Email \"$email\" of $name is not valid — imported without email.";
                    $email = '';
                }

                $authors[] = [
                    'position'    => (string) (count($authors) + 1),
                    'name'        => $name,
                    'email'       => mb_strtolower($email),
                    'affiliation' => self::isBlank($affiliation) ? '' : $affiliation,
                    'country'     => $country,
                ];
            }

            $papers[] = [
                'line'        => $i + 2,
                'paper_id'    => $paperId,
                'title'       => $title,
                'edas_status' => $cell($row, 'status'),
                'status'      => self::status($cell($row, 'status')),
                'pages'       => $pagesCol !== null ? (int) preg_replace('/\D+/', '', $cell($row, $pagesCol)) : 0,
                'authors'     => $authors,
                'warnings'    => $warnings,
            ];
        }

        return $papers;
    }

    /**
     * Simpan hasil parse. Field yang tidak ada di EDAS (telepon, presenter,
     * catatan, track; halaman bila kolomnya tidak ada) pada paper yang sudah ada tidak diubah.
     * Author yang sudah tidak ada di EDAS tidak dihapus — dilaporkan saja.
     *
     * @return array{inserted:int, updated:int, skipped:int, warnings:int, authors:int,
     *               issues:array<int,array{line:int,paper_id:string,level:string,message:string}>}
     */
    public static function import(int $editionId, array $papers, string $batch, bool $acceptedOnly, bool $updateExisting): array
    {
        $out = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'warnings' => 0, 'authors' => 0, 'issues' => []];
        $note = static function (array $p, string $level, string $message) use (&$out): void {
            if (count($out['issues']) < 300) {
                $out['issues'][] = ['line' => $p['line'], 'paper_id' => $p['paper_id'], 'level' => $level, 'message' => $message];
            }
        };

        foreach ($papers as $p) {
            if ($acceptedOnly && $p['status'] !== 'accepted') {
                $out['skipped']++;
                $note($p, 'error', 'Status "' . $p['edas_status'] . '" — only accepted papers are imported.');
                continue;
            }
            if ($p['paper_id'] === '') {
                $out['skipped']++;
                $note($p, 'error', 'Paper ID (#) is empty.');
                continue;
            }

            $first = $p['authors'][0] ?? null;
            if ($first === null) {
                $out['skipped']++;
                $note($p, 'error', 'No authors in this row.');
                continue;
            }

            $existing = AuthorRepo::findByPaperId($editionId, $p['paper_id']);
            if ($existing !== null && !$updateExisting) {
                $out['skipped']++;
                $note($p, 'warning', 'Already exists — left unchanged (mode: do not update).');
                continue;
            }

            $data = [
                'paper_id'          => $p['paper_id'],
                'paper_title'       => $p['title'],
                'first_author_name' => $first['name'],
                'email'             => $first['email'],
                'affiliation'       => $first['affiliation'],
                'country'           => $first['country'],
                'paper_status'      => $p['status'],
            ];
            if (($p['pages'] ?? 0) > 0) {
                $data['total_pages'] = (string) $p['pages'];
            }
            if ($existing !== null) {
                // Pertahankan data yang tidak dibawa EDAS.
                $data += array_intersect_key($existing, array_flip(['track', 'phone', 'total_pages', 'is_presenter', 'note']));
            }

            $check = AuthorRepo::validateForImport($data, $editionId, (int) ($existing['id'] ?? 0));
            // Email first author boleh kosong di EDAS → paper tetap masuk, author lain tetap bisa login.
            unset($check['errors']['email']);
            if ($check['errors'] !== []) {
                $out['skipped']++;
                $note($p, 'error', implode(' ', $check['errors']));
                continue;
            }

            $result = AuthorRepo::upsert($editionId, $data, $batch);
            $result['action'] === 'inserted' ? $out['inserted']++ : $out['updated']++;

            $keep = [];
            foreach ($p['authors'] as $a) {
                $saved  = PaperAuthorRepo::upsertForPaper($result['id'], $a);
                $keep[] = $saved['id'];
                $out['authors']++;
            }

            foreach (PaperAuthorRepo::forPaper($result['id']) as $row) {
                if (!in_array((int) $row['id'], $keep, true)) {
                    $p['warnings'][] = $row['name'] . ' is no longer listed in EDAS — kept; remove manually if needed.';
                }
            }

            if ($p['warnings'] !== []) {
                $out['warnings']++;
                $note($p, 'warning', implode(' ', $p['warnings']));
            }
        }

        return $out;
    }

    /**
     * "A, B and C (Aff, Country); D (Aff2, Country & Aff3, Country)" →
     * [['name' => 'A', 'affiliation' => 'Aff'], …] dalam urutan author.
     *
     * @return array<int,array{name:string, affiliation:string}>
     */
    public static function parseAuthorList(string $text): array
    {
        $out   = [];
        $depth = 0;
        $names = '';
        $aff   = '';

        $flush = static function () use (&$out, &$names, &$aff): void {
            $list = preg_split('/\s*,\s*|\s+and\s+/u', trim($names)) ?: [];
            // Ambil afiliasi pertama, tanpa negara di belakang koma terakhir.
            $first = trim(explode(' & ', $aff)[0]);
            if (str_contains($first, ',')) {
                $first = trim(substr($first, 0, (int) strrpos($first, ',')));
            }
            foreach ($list as $name) {
                if (trim($name) !== '') {
                    $out[] = ['name' => trim($name), 'affiliation' => $first];
                }
            }
            $names = '';
            $aff   = '';
        };

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if ($ch === '(') {
                if ($depth++ > 0) {
                    $aff .= $ch;
                }
            } elseif ($ch === ')') {
                if (--$depth > 0) {
                    $aff .= $ch;
                } elseif ($depth === 0) {
                    $flush();
                }
                $depth = max(0, $depth);
            } elseif ($depth > 0) {
                $aff .= $ch;
            } elseif ($ch !== ';') {
                $names .= $ch;
            }
        }
        if (trim($names) !== '') {
            $flush();
        }

        return $out;
    }

    /** Kode ISO 2 huruf → nama negara (bahasa Inggris); teks lain dibiarkan. */
    public static function country(string $value): string
    {
        $value = trim($value);

        return Countries::name($value) ?? $value;
    }

    private static function isBlank(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['', 'none', '-', 'n/a', 'na', 'null'], true);
    }

    /** "Faris Syariati" vs "Faris Muhamad Syariati": kata pertama & terakhir sama. */
    private static function sameName(string $short, string $full): bool
    {
        $a = preg_split('/\s+/u', mb_strtolower(trim($short))) ?: [];
        $b = preg_split('/\s+/u', mb_strtolower(trim($full))) ?: [];
        if ($a === [] || $b === [] || $a[0] === '') {
            return false;
        }

        return $a[0] === $b[0] && end($a) === end($b);
    }
}
