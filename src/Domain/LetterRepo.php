<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Letter Generate (Admin → Documents → Letters): surat bebas per edisi,
 * opsional untuk satu paper. Isi HTML dari editor boleh memuat variabel
 * (VARIABLES) yang diganti dengan data paper saat PDF dibuat (LetterPdf).
 */
final class LetterRepo
{
    public const SIGNERS = [
        'chair'     => 'Conference chair',
        'treasurer' => 'Treasurer',
        'none'      => 'No signature',
    ];

    /** Variabel yang bisa disisipkan di isi surat => keterangan. */
    public const VARIABLES = [
        'paper_id'      => 'Paper ID',
        'paper_title'   => 'Paper title',
        'first_author'  => 'First author',
        'authors'       => 'All authors (comma separated)',
        'affiliation'   => 'Affiliation of the first author',
        'country'       => 'Country of the first author',
        'letter_number' => 'Letter number',
        'letter_date'   => 'Letter date',
        'conference'    => 'Conference name',
    ];

    /** @return array<int,array<string,mixed>> + paper_id, paper_title, first_author_name */
    public static function all(int $editionId): array
    {
        return Db::all(
            'SELECT l.*, a.paper_id, a.paper_title, a.first_author_name
             FROM letters l LEFT JOIN authors a ON a.id = l.author_id
             WHERE l.edition_id = ? ORDER BY l.id DESC',
            [$editionId]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT l.*, a.paper_id, a.paper_title, a.first_author_name
             FROM letters l LEFT JOIN authors a ON a.id = l.author_id WHERE l.id = ?',
            [$id]
        );
    }

    /**
     * @param array<string,mixed> $data title, number, letter_date, author_id, body, signer, show_title
     * @return array<string,string> field => pesan
     */
    public static function validate(array $data, int $editionId): array
    {
        $errors = [];
        if (trim((string) ($data['title'] ?? '')) === '' || mb_strlen((string) $data['title']) > 190) {
            $errors['title'] = 'Please enter the letter name (max 190 characters).';
        }
        if (mb_strlen((string) ($data['number'] ?? '')) > 190) {
            $errors['number'] = 'The letter number is too long.';
        }
        $date = (string) ($data['letter_date'] ?? '');
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['letter_date'] = 'Please choose a valid date.';
        }
        $authorId = (int) ($data['author_id'] ?? 0);
        if ($authorId > 0 && Db::value('SELECT 1 FROM authors WHERE id = ? AND edition_id = ?', [$authorId, $editionId]) === null) {
            $errors['author_id'] = 'Paper not found in this edition.';
        }
        if (trim(strip_tags((string) ($data['body'] ?? ''))) === '') {
            $errors['body'] = 'Please write the content of the letter.';
        }
        if (!isset(self::SIGNERS[(string) ($data['signer'] ?? '')])) {
            $errors['signer'] = 'Please choose who signs the letter.';
        }

        return $errors;
    }

    public static function save(int $editionId, ?int $id, array $data): int
    {
        $row = [
            'title'       => trim((string) $data['title']),
            'number'      => ($n = trim((string) ($data['number'] ?? ''))) !== '' ? $n : null,
            'letter_date' => ($d = (string) ($data['letter_date'] ?? '')) !== '' ? $d : null,
            'author_id'   => (int) ($data['author_id'] ?? 0) > 0 ? (int) $data['author_id'] : null,
            'body'        => (string) $data['body'],
            'signer'      => (string) $data['signer'],
            'show_title'  => !empty($data['show_title']) ? 1 : 0,
        ];

        if ($id !== null) {
            Db::update('letters', $row + ['updated_at' => now()], 'id = :target_id', ['target_id' => $id]);

            return $id;
        }

        return Db::insert('letters', $row + ['edition_id' => $editionId, 'created_at' => now()]);
    }

    public static function delete(int $id): void
    {
        Db::delete('letters', 'id = ?', [$id]);
    }

    /**
     * Nilai variabel untuk satu surat (paper kosong → variabel paper kosong).
     *
     * @return array<string,string>
     */
    public static function values(array $letter): array
    {
        $paper   = ($letter['author_id'] ?? null) !== null ? AuthorRepo::find((int) $letter['author_id']) : null;
        $authors = $paper !== null ? PaperAuthorRepo::forPaper((int) $paper['id']) : [];
        $edition = EditionRepo::find((int) $letter['edition_id']) ?? [];

        return [
            'paper_id'      => (string) ($paper['paper_id'] ?? ''),
            'paper_title'   => (string) ($paper['paper_title'] ?? ''),
            'first_author'  => (string) ($paper['first_author_name'] ?? ''),
            'authors'       => implode(', ', array_map(static fn (array $a): string => (string) $a['name'], $authors)),
            'affiliation'   => (string) ($paper['affiliation'] ?? ''),
            'country'       => (string) ($paper['country'] ?? ''),
            'letter_number' => (string) ($letter['number'] ?? ''),
            'letter_date'   => ($letter['letter_date'] ?? null) !== null ? fmt_date($letter['letter_date'] . ' 00:00:00') : '',
            'conference'    => (string) ($edition['name'] ?? cfg('app_name')),
        ];
    }

    /** Ganti {variabel} di HTML isi surat (nilai di-escape). */
    public static function render(array $letter): string
    {
        $values = self::values($letter);

        return (string) preg_replace_callback('/\{([a-z_]+)\}/', static function (array $m) use ($values): string {
            return array_key_exists($m[1], $values) ? htmlspecialchars($values[$m[1]], ENT_QUOTES, 'UTF-8') : $m[0];
        }, (string) $letter['body']);
    }
}
