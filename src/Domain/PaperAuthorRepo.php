<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Semua author dari setiap paper (first author + co-author).
 *
 * Tabel `authors` tetap satu baris per paper dan menyimpan first author;
 * baris position = 1 di sini selalu disinkronkan dengan kolom first_author_name,
 * email, affiliation, country di tabel itu (dua arah), sehingga modul lama yang
 * membaca `authors` tidak perlu berubah.
 *
 * Email adalah identitas login (OTP): satu orang bisa menjadi author di beberapa
 * paper, dan sekaligus panitia/reviewer.
 */
final class PaperAuthorRepo
{
    /** Field import "daftar author" beserta alias header CSV yang dikenali. */
    public const FIELDS = [
        'paper_id'     => ['paper id', 'paperid', 'paper #', 'paper', 'submission id', 'submission', 'id', 'no'],
        'name'         => ['author name', 'name', 'full name', 'nama', 'author', 'nama author'],
        'email'        => ['email', 'e-mail', 'email address', 'author email', 'mail'],
        'affiliation'  => ['affiliation', 'institution', 'organization', 'organisation', 'university', 'instansi', 'institusi'],
        'country'      => ['country', 'negara'],
        'position'     => ['position', 'order', 'author order', 'author #', 'urutan', 'seq'],
        'is_presenter' => ['presenter', 'is presenter', 'presenting', 'penyaji'],
        'paper_title'  => ['paper title', 'title', 'judul', 'judul paper'],
    ];

    public const REQUIRED_FIELDS = ['paper_id', 'name'];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM paper_authors WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> author satu paper, urut posisi */
    public static function forPaper(int $authorId): array
    {
        return Db::all('SELECT * FROM paper_authors WHERE author_id = ? ORDER BY position, id', [$authorId]);
    }

    public static function findInPaper(int $authorId, string $email): ?array
    {
        $email = self::email($email);

        return $email === null ? null : Db::one(
            'SELECT * FROM paper_authors WHERE author_id = ? AND email = ? ORDER BY position LIMIT 1',
            [$authorId, $email]
        );
    }

    /**
     * Paper (baris authors) tempat email ini tercatat sebagai author, beserta
     * baris paper_authors miliknya (kolom me_id, me_position, me_is_presenter).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function papersForEmail(int $editionId, string $email): array
    {
        $email = self::email($email);
        if ($email === null) {
            return [];
        }

        return Db::all(
            'SELECT a.*, pa.id AS me_id, pa.position AS me_position, pa.is_presenter AS me_is_presenter,
                    pa.name AS me_name
             FROM paper_authors pa
             JOIN authors a ON a.id = pa.author_id
             WHERE pa.edition_id = ? AND pa.email = ?
             GROUP BY a.id
             ORDER BY a.paper_id',
            [$editionId, $email]
        );
    }

    /**
     * Seperti papersForEmail(), tetapi dari SEMUA edisi — supaya author edisi
     * lama tetap bisa membuka LoA, invoice, receipt, dan sertifikatnya. Paper
     * edisi aktif di urutan pertama, lalu edisi terbaru ke terlama.
     *
     * @return array<int,array<string,mixed>> + edition_year, edition_name, edition_active
     */
    public static function papersForEmailAllEditions(string $email): array
    {
        $email = self::email($email);
        if ($email === null) {
            return [];
        }

        return Db::all(
            'SELECT a.*, pa.id AS me_id, pa.position AS me_position, pa.is_presenter AS me_is_presenter,
                    pa.name AS me_name, e.year AS edition_year, e.name AS edition_name, e.is_active AS edition_active
             FROM paper_authors pa
             JOIN authors a ON a.id = pa.author_id
             JOIN editions e ON e.id = a.edition_id
             WHERE pa.email = ?
             GROUP BY a.id
             ORDER BY e.is_active DESC, e.year DESC, a.paper_id',
            [$email]
        );
    }

    public static function countForPaper(int $authorId): int
    {
        return Db::count('paper_authors', 'author_id = ?', [$authorId]);
    }

    // ── Tulis ───────────────────────────────────────────────────────────────

    /** Tambah author ke paper; posisi bawaan = paling akhir. */
    public static function create(int $authorId, array $data): int
    {
        $paper = AuthorRepo::find($authorId);
        if ($paper === null) {
            throw new \InvalidArgumentException('Paper not found.');
        }

        $row = self::normalize($data);
        $row['position'] ??= (int) Db::value('SELECT COALESCE(MAX(position), 0) + 1 FROM paper_authors WHERE author_id = ?', [$authorId]);
        $row['edition_id'] = (int) $paper['edition_id'];
        $row['author_id']  = $authorId;
        $row['created_at'] = now();

        $id = Db::insert('paper_authors', $row);
        self::resequence($authorId, $id);

        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $current = self::find($id);
        if ($current === null) {
            return;
        }

        $row = self::normalize($data);
        if ($row['position'] === null) {
            unset($row['position']);
        }
        $row['updated_at'] = now();

        Db::update('paper_authors', $row, 'id = :target_id', ['target_id' => $id]);
        self::resequence((int) $current['author_id'], $id);
    }

    /**
     * Hapus satu author. Author terakhir sebuah paper tidak boleh dihapus, dan
     * author yang sudah punya sertifikat juga tidak (sertifikatnya harus dicabut dulu).
     *
     * @return string|null pesan error, null bila berhasil
     */
    public static function delete(int $id): ?string
    {
        $row = self::find($id);
        if ($row === null) {
            return null;
        }

        if (self::countForPaper((int) $row['author_id']) <= 1) {
            return 'A paper must keep at least one author.';
        }

        $hasCertificate = Db::value(
            "SELECT 1 FROM certificates WHERE recipient_ref_type = 'paper_author' AND recipient_ref_id = ? LIMIT 1",
            [$id]
        ) !== null;
        if ($hasCertificate) {
            return 'This author already has a certificate. Revoke it before removing the author.';
        }

        Db::delete('paper_authors', 'id = ?', [$id]);
        self::resequence((int) $row['author_id']);

        return null;
    }

    public static function setPresenter(int $id, bool $isPresenter): void
    {
        Db::update('paper_authors', ['is_presenter' => $isPresenter ? 1 : 0, 'updated_at' => now()], 'id = :target_id', ['target_id' => $id]);
    }

    /**
     * Upsert dari import: dicocokkan per email (atau nama bila email kosong)
     * di dalam paper yang sama.
     *
     * @return array{action:'inserted'|'updated', id:int}
     */
    public static function upsertForPaper(int $authorId, array $data): array
    {
        $email    = self::email((string) ($data['email'] ?? ''));
        $existing = $email !== null
            ? self::findInPaper($authorId, $email)
            : Db::one(
                'SELECT * FROM paper_authors WHERE author_id = ? AND LOWER(name) = ? LIMIT 1',
                [$authorId, mb_strtolower(trim((string) ($data['name'] ?? '')))]
            );

        if ($existing !== null) {
            self::update((int) $existing['id'], $data + ['is_presenter' => $existing['is_presenter']]);
            return ['action' => 'updated', 'id' => (int) $existing['id']];
        }

        return ['action' => 'inserted', 'id' => self::create($authorId, $data)];
    }

    // ── Sinkronisasi dengan tabel authors (first author) ────────────────────

    /** Setelah baris paper dibuat/diubah lewat modul lama: pastikan first author di sini sama. */
    public static function syncFromPaper(int $authorId): void
    {
        $paper = AuthorRepo::find($authorId);
        if ($paper === null) {
            return;
        }

        $data = [
            'name'        => (string) $paper['first_author_name'],
            'email'       => (string) $paper['email'],
            'affiliation' => $paper['affiliation'],
            'country'     => $paper['country'],
        ];

        $first = Db::one('SELECT * FROM paper_authors WHERE author_id = ? ORDER BY position, id LIMIT 1', [$authorId]);

        if ($first === null) {
            // Presenter belum ada: dipilih author sendiri di Registration form.
            Db::insert('paper_authors', self::normalize($data + ['position' => 1, 'is_presenter' => 0]) + [
                'edition_id' => (int) $paper['edition_id'],
                'author_id'  => $authorId,
                'created_at' => now(),
            ]);
            return;
        }

        $row = self::normalize($data);
        unset($row['position'], $row['is_presenter']);
        $row['updated_at'] = now();
        Db::update('paper_authors', $row, 'id = :target_id', ['target_id' => (int) $first['id']]);
    }

    /** Kebalikannya: first author berubah di sini → salin ke tabel authors. */
    private static function syncPaperFromFirst(int $authorId): void
    {
        $first = Db::one('SELECT * FROM paper_authors WHERE author_id = ? ORDER BY position, id LIMIT 1', [$authorId]);
        if ($first === null) {
            return;
        }

        Db::update('authors', [
            'first_author_name' => (string) $first['name'],
            'email'             => (string) ($first['email'] ?? ''),
            'affiliation'       => $first['affiliation'],
            'country'           => $first['country'],
            'updated_at'        => now(),
        ], 'id = :target_id', ['target_id' => $authorId]);
    }

    /**
     * Rapikan posisi menjadi 1..n. Baris $movedId dipertahankan di posisi yang
     * diminta (author lain bergeser), lalu first author disalin ke tabel authors.
     */
    private static function resequence(int $authorId, ?int $movedId = null): void
    {
        $rows  = self::forPaper($authorId);
        $moved = null;

        if ($movedId !== null) {
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $movedId) {
                    $moved = $row;
                    unset($rows[$i]);
                    break;
                }
            }
        }

        $rows = array_values($rows);
        if ($moved !== null) {
            $target = max(1, min(count($rows) + 1, (int) $moved['position']));
            array_splice($rows, $target - 1, 0, [$moved]);
        }

        foreach ($rows as $i => $row) {
            if ((int) $row['position'] !== $i + 1) {
                Db::update('paper_authors', ['position' => $i + 1], 'id = :target_id', ['target_id' => (int) $row['id']]);
            }
        }

        self::syncPaperFromFirst($authorId);
    }

    // ── Validasi & normalisasi ──────────────────────────────────────────────

    /** @return array<int,string> pesan error */
    public static function validate(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors[] = 'Name is required.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email "' . $email . '" is not valid.';
        }

        return $errors;
    }

    public static function normalize(array $data): array
    {
        $position = trim((string) ($data['position'] ?? ''));

        return [
            'name'         => mb_substr(trim((string) ($data['name'] ?? '')), 0, 190),
            'email'        => self::email((string) ($data['email'] ?? '')),
            'affiliation'  => self::nullable($data['affiliation'] ?? null, 255),
            'country'      => self::nullable($data['country'] ?? null, 100),
            'position'     => ctype_digit($position) && (int) $position > 0 ? (int) $position : null,
            'is_presenter' => !empty($data['is_presenter']) && AuthorRepo::toBool($data['is_presenter']) ? 1 : 0,
        ];
    }

    public static function email(string $email): ?string
    {
        $email = mb_strtolower(trim($email));

        return $email === '' ? null : mb_substr($email, 0, 190);
    }

    private static function nullable(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
