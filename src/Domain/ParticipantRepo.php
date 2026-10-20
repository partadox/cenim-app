<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;
use Cenim\Support\Countries;
use Cenim\Support\Text;

/**
 * Peserta non-presenter yang mendaftar sendiri (tidak terkait paper mana pun).
 *
 * Disimpan sebagai baris `authors` dengan paper_status = 'attendee' dan
 * paper_id "NP-0001", ditambah satu baris `paper_authors` (orangnya sendiri).
 * Dengan begitu login OTP, registrasi, invoice, pembayaran, receipt, dan
 * sertifikat Participant memakai alur yang sama dengan paper — tanpa LoA,
 * confirmation form, dan video. Daftar author/paper di admin mengabaikan baris ini.
 */
final class ParticipantRepo
{
    public const STATUS = 'attendee';
    public const PREFIX = 'NP-';

    /** Baris authors ini peserta non-presenter (bukan paper)? */
    public static function isParticipant(?array $paper): bool
    {
        return (string) ($paper['paper_status'] ?? '') === self::STATUS;
    }

    /** Self-registration di halaman login dibuka? (Settings) */
    public static function registrationOpen(int $editionId): bool
    {
        return SettingRepo::getBool($editionId, 'participant_registration_open', true);
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM authors WHERE id = ? AND paper_status = ?', [$id, self::STATUS]);
    }

    /**
     * Email sudah dipakai di edisi ini? 'author' (author/co-author paper),
     * 'participant', atau null.
     */
    public static function emailRole(int $editionId, string $email): ?string
    {
        $row = Db::one(
            'SELECT a.paper_status FROM paper_authors pa JOIN authors a ON a.id = pa.author_id
             WHERE pa.edition_id = ? AND pa.email = ?
             ORDER BY CASE WHEN a.paper_status = ? THEN 1 ELSE 0 END LIMIT 1',
            [$editionId, mb_strtolower(trim($email)), self::STATUS]
        );

        if ($row === null) {
            return null;
        }

        return (string) $row['paper_status'] === self::STATUS ? 'participant' : 'author';
    }

    /**
     * Pilihan Indonesia / International dari form → nama negara. Indonesia
     * ditagih IDR, International USD (FeeEngine::currencyForCountry).
     */
    public static function countryFromInput(string $region, string $country): string
    {
        if ($region === 'indonesia') {
            return 'Indonesia';
        }

        return $region === 'international' && $country !== 'Indonesia' ? $country : '';
    }

    /**
     * @param array<string,mixed> $data name, email, affiliation, country
     * @return array<string,string> field => pesan
     */
    public static function validate(array $data, int $editionId, int $exceptId = 0): array
    {
        $errors = [];
        $name   = trim((string) ($data['name'] ?? ''));
        $email  = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $aff    = trim((string) ($data['affiliation'] ?? ''));

        if ($name === '' || mb_strlen($name) > 190) {
            $errors['name'] = 'Please enter the full name (max 190 characters).';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $existing = Db::one(
                'SELECT a.id, a.paper_status FROM paper_authors pa JOIN authors a ON a.id = pa.author_id
                 WHERE pa.edition_id = ? AND pa.email = ? AND a.id != ? LIMIT 1',
                [$editionId, $email, $exceptId]
            );
            if ($existing !== null) {
                $errors['email'] = (string) $existing['paper_status'] === self::STATUS
                    ? 'This email is already registered as a participant. Sign in to the portal with it.'
                    : 'This email belongs to an author of an accepted paper. Sign in to the portal with it and register through the paper instead.';
            }
        }
        if ($aff === '' || mb_strlen($aff) > 255) {
            $errors['affiliation'] = 'Please enter the affiliation (max 255 characters).';
        }
        if (!in_array((string) ($data['country'] ?? ''), Countries::all(), true)) {
            $errors['country'] = ($data['region'] ?? '') === 'international'
                ? 'Please choose your country.'
                : 'Please choose Indonesia or International.';
        }

        return $errors;
    }

    /** Buat peserta baru. $source: 'self' (halaman register) atau 'admin'. Kembalikan id authors. */
    public static function create(int $editionId, array $data, string $source = 'self'): int
    {
        $name    = Text::titleCase((string) $data['name']);
        $email   = mb_strtolower(trim((string) $data['email']));
        $aff     = Text::titleCase((string) $data['affiliation']);
        $country = (string) $data['country'];

        return Db::transaction(static function () use ($editionId, $name, $email, $aff, $country, $source): int {
            $id = Db::insert('authors', [
                'edition_id'        => $editionId,
                'paper_id'          => self::nextPaperId($editionId),
                'paper_title'       => null,
                'first_author_name' => $name,
                'email'             => $email,
                'affiliation'       => $aff,
                'country'           => $country,
                'total_pages'       => 0,
                'is_presenter'      => 1,
                'paper_status'      => self::STATUS,
                'imported_batch'    => 'participant-' . $source,
                'created_at'        => now(),
            ]);

            Db::insert('paper_authors', [
                'edition_id'   => $editionId,
                'author_id'    => $id,
                'position'     => 1,
                'name'         => $name,
                'email'        => $email,
                'affiliation'  => $aff,
                'country'      => $country,
                'is_presenter' => 1,
                'created_at'   => now(),
            ]);

            return $id;
        });
    }

    public static function update(int $id, array $data): void
    {
        $row = [
            'first_author_name' => Text::titleCase((string) $data['name']),
            'email'             => mb_strtolower(trim((string) $data['email'])),
            'affiliation'       => Text::titleCase((string) $data['affiliation']),
            'country'           => (string) $data['country'],
            'updated_at'        => now(),
        ];

        Db::transaction(static function () use ($id, $row): void {
            Db::update('authors', $row, 'id = :target_id', ['target_id' => $id]);
            Db::update('paper_authors', [
                'name'        => $row['first_author_name'],
                'email'       => $row['email'],
                'affiliation' => $row['affiliation'],
                'country'     => $row['country'],
                'updated_at'  => $row['updated_at'],
            ], 'author_id = :target_id', ['target_id' => $id]);
        });
    }

    /** Hapus peserta beserta registrasi, invoice, pembayaran, receipt, dan sertifikatnya. */
    public static function delete(int $id): void
    {
        if (self::find($id) !== null) {
            AuthorRepo::purge($id);
        }
    }

    /** "NP-0001", "NP-0002", … per edisi. */
    public static function nextPaperId(int $editionId): string
    {
        $max = 0;
        foreach (Db::all('SELECT paper_id FROM authors WHERE edition_id = ? AND paper_id LIKE ?', [$editionId, self::PREFIX . '%']) as $row) {
            $max = max($max, (int) substr((string) $row['paper_id'], strlen(self::PREFIX)));
        }

        return self::PREFIX . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Semua peserta non-presenter edisi ini beserta status registrasi & pembayarannya.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(int $editionId): array
    {
        return Db::all(
            "SELECT a.id, a.paper_id, a.first_author_name AS name, a.email, a.affiliation, a.country,
                    a.imported_batch, a.created_at,
                    r.id AS registration_id, r.status AS registration_status, r.total, r.currency,
                    (SELECT COUNT(*) FROM registration_attendees ra WHERE ra.registration_id = r.id) AS guests,
                    i.number AS invoice_number, i.status AS invoice_status
             FROM authors a
             LEFT JOIN registrations r ON r.author_id = a.id
             LEFT JOIN invoices i ON i.registration_id = r.id AND i.status != '" . InvoiceRepo::STATUS_VOID . "'
             WHERE a.edition_id = ? AND a.paper_status = ?
             ORDER BY a.paper_id",
            [$editionId, self::STATUS]
        );
    }
}
