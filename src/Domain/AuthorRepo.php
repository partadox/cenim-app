<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Data PAPER beserta first author-nya (satu baris per paper). Daftar lengkap
 * author — termasuk co-author — ada di PaperAuthorRepo; first author di sana
 * selalu disinkronkan dengan kolom first_author_name/email di sini.
 */
final class AuthorRepo
{
    /** Field yang bisa diimpor/diekspor beserta alias header CSV yang dikenali. */
    public const FIELDS = [
        'paper_id'          => ['paper id', 'paperid', 'submission id', 'submissionid', 'id', 'no', 'number'],
        'paper_title'       => ['paper title', 'title', 'judul', 'judul paper'],
        'track'             => ['track', 'topic', 'symposium', 'session', 'bidang'],
        'first_author_name' => ['first author name', 'first author', 'corresponding author', 'contact author', 'author', 'name', 'nama'],
        'email'             => ['first author email', 'contact email', 'email address', 'email', 'e-mail', 'mail'],
        'affiliation'       => ['affiliation', 'institution', 'university', 'organization', 'instansi', 'asal institusi'],
        'country'           => ['country', 'negara'],
        'phone'             => ['phone number', 'phone', 'mobile', 'whatsapp', 'telp', 'no hp', 'hp'],
        'total_pages'       => ['total pages', 'number of pages', 'pages', 'page', 'jumlah halaman'],
        'is_presenter'      => ['is presenter', 'presenter', 'presenting', 'presentasi'],
        'paper_status'      => ['paper status', 'status', 'decision'],
        'note'              => ['note', 'notes', 'remark', 'catatan'],
    ];

    public const REQUIRED_FIELDS = ['paper_id', 'first_author_name', 'email'];

    public const PAPER_STATUSES = ['accepted', 'pending', 'rejected', 'withdrawn'];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM authors WHERE id = ?', [$id]);
    }

    public static function findByPaperId(int $editionId, string $paperId): ?array
    {
        return Db::one(
            'SELECT * FROM authors WHERE edition_id = ? AND LOWER(paper_id) = ? LIMIT 1',
            [$editionId, mb_strtolower(trim($paperId))]
        );
    }

    /** Cek duplikat email dalam satu edisi (peringatan, bukan larangan). */
    public static function countByEmail(int $editionId, string $email, int $exceptId = 0): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM authors WHERE edition_id = ? AND LOWER(email) = ? AND id != ?',
            [$editionId, mb_strtolower(trim($email)), $exceptId]
        );
    }

    public static function create(int $editionId, array $data, ?string $batch = null): int
    {
        $row = self::normalize($data);
        $row['edition_id']     = $editionId;
        $row['imported_batch'] = $batch;
        $row['created_at']     = now();

        $id = Db::insert('authors', $row);
        PaperAuthorRepo::syncFromPaper($id);

        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $row = self::normalize($data);
        $row['updated_at'] = now();

        Db::update('authors', $row, 'id = :target_id', ['target_id' => $id]);
        PaperAuthorRepo::syncFromPaper($id);
    }

    /**
     * Simpan berdasarkan paper_id: baris yang sudah ada diperbarui, yang baru dibuat.
     *
     * @return array{action:'inserted'|'updated', id:int}
     */
    public static function upsert(int $editionId, array $data, ?string $batch = null): array
    {
        $existing = self::findByPaperId($editionId, (string) ($data['paper_id'] ?? ''));

        if ($existing !== null) {
            $row = self::normalize($data);
            $row['updated_at']     = now();
            $row['imported_batch'] = $batch;

            Db::update('authors', $row, 'id = :target_id', ['target_id' => (int) $existing['id']]);
            PaperAuthorRepo::syncFromPaper((int) $existing['id']);

            return ['action' => 'updated', 'id' => (int) $existing['id']];
        }

        return ['action' => 'inserted', 'id' => self::create($editionId, $data, $batch)];
    }

    /** Author tidak boleh dihapus bila sudah punya registrasi/pembayaran. */
    public static function hasRegistration(int $id): bool
    {
        return Db::value('SELECT 1 FROM registrations WHERE author_id = ? LIMIT 1', [$id]) !== null;
    }

    public static function delete(int $id): void
    {
        Db::delete('authors', 'id = ?', [$id]);
    }

    public static function countByEdition(int $editionId): int
    {
        return Db::count('authors', 'edition_id = ?', [$editionId]);
    }

    /** @return array<int,string> */
    public static function countries(int $editionId): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['country'],
            Db::all(
                "SELECT DISTINCT country FROM authors
                 WHERE edition_id = ? AND country IS NOT NULL AND country != ''
                 ORDER BY country",
                [$editionId]
            )
        );
    }

    /** @return array<int,string> */
    public static function batches(int $editionId): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['imported_batch'],
            Db::all(
                "SELECT DISTINCT imported_batch FROM authors
                 WHERE edition_id = ? AND imported_batch IS NOT NULL AND imported_batch != ''
                 ORDER BY imported_batch DESC",
                [$editionId]
            )
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function allForExport(int $editionId): array
    {
        return Db::all(
            'SELECT * FROM authors WHERE edition_id = ? ORDER BY paper_id',
            [$editionId]
        );
    }

    /**
     * Validasi satu baris (dipakai form manual maupun import).
     *
     * @return array<string,string> field => pesan error
     */
    public static function validateRow(array $data, int $editionId, int $exceptId = 0): array
    {
        $errors = [];

        $paperId = trim((string) ($data['paper_id'] ?? ''));
        if ($paperId === '') {
            $errors['paper_id'] = 'Paper ID is required.';
        } elseif (mb_strlen($paperId) > 40) {
            $errors['paper_id'] = 'Paper ID must not exceed 40 characters.';
        } else {
            $duplicate = self::findByPaperId($editionId, $paperId);
            if ($duplicate !== null && (int) $duplicate['id'] !== $exceptId) {
                $errors['paper_id'] = 'Paper ID already exists in this edition.';
            }
        }

        $name = trim((string) ($data['first_author_name'] ?? ''));
        if ($name === '') {
            $errors['first_author_name'] = 'First author name is required.';
        } elseif (mb_strlen($name) > 190) {
            $errors['first_author_name'] = 'Name must not exceed 190 characters.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email is not a valid address.';
        } elseif (mb_strlen($email) > 190) {
            $errors['email'] = 'Email must not exceed 190 characters.';
        }

        $pages = (string) ($data['total_pages'] ?? '');
        if ($pages !== '' && (preg_match('/^\d+$/', $pages) !== 1 || (int) $pages > 200)) {
            $errors['total_pages'] = 'Total pages must be a number between 0 and 200.';
        }

        $status = trim((string) ($data['paper_status'] ?? ''));
        if ($status !== '' && !in_array(mb_strtolower($status), self::PAPER_STATUSES, true)) {
            $errors['paper_status'] = 'Paper status must be one of: ' . implode(', ', self::PAPER_STATUSES) . '.';
        }

        return $errors;
    }

    /**
     * Validasi untuk import CSV — lebih longgar dari form manual.
     *
     * Field yang tidak menentukan identitas peserta (jumlah halaman, status paper)
     * tidak boleh membatalkan satu baris; nilainya dinormalkan ke default dan
     * dilaporkan sebagai peringatan. Yang membatalkan baris hanya paper ID,
     * nama, dan email — karena paper ID + email adalah kredensial login author.
     *
     * @return array{errors: array<string,string>, warnings: array<string,string>}
     */
    public static function validateForImport(array $data, int $editionId, int $exceptId = 0): array
    {
        $soft     = ['total_pages', 'paper_status'];
        $errors   = [];
        $warnings = [];

        foreach (self::validateRow($data, $editionId, $exceptId) as $field => $message) {
            if (in_array($field, $soft, true)) {
                $warnings[$field] = $message . ' Imported with the default value.';
            } else {
                $errors[$field] = $message;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** Ubah nilai mentah (form/CSV) menjadi baris database yang bersih. */
    public static function normalize(array $data): array
    {
        return [
            'paper_id'          => trim((string) ($data['paper_id'] ?? '')),
            'paper_title'       => self::nullable($data['paper_title'] ?? null, 500),
            'track'             => self::nullable($data['track'] ?? null, 190),
            'first_author_name' => trim((string) ($data['first_author_name'] ?? '')),
            'email'             => mb_strtolower(trim((string) ($data['email'] ?? ''))),
            'affiliation'       => self::nullable($data['affiliation'] ?? null, 255),
            'country'           => self::nullable($data['country'] ?? null, 100),
            'phone'             => self::nullable($data['phone'] ?? null, 50),
            'total_pages'       => (int) preg_replace('/\D+/', '', (string) ($data['total_pages'] ?? '0')),
            'is_presenter'      => self::toBool($data['is_presenter'] ?? 1) ? 1 : 0,
            'paper_status'      => self::status($data['paper_status'] ?? null),
            'note'              => self::nullable($data['note'] ?? null, 1000),
        ];
    }

    /** Terima berbagai penulisan boolean dari CSV: yes/no, y/n, true/false, 1/0. */
    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $text = mb_strtolower(trim((string) $value));

        if ($text === '') {
            return true; // kolom tidak diisi → anggap presenter (kasus paling umum)
        }

        return in_array($text, ['1', 'y', 'yes', 'true', 'ya', 'presenter', 'oral', 'attend'], true);
    }

    private static function status(mixed $value): string
    {
        $status = mb_strtolower(trim((string) $value));
        return in_array($status, self::PAPER_STATUSES, true) ? $status : 'accepted';
    }

    private static function nullable(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
