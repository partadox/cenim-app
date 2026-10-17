<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Registrasi (form 1). Satu author = satu registrasi (UNIQUE author_id).
 *
 * Alur status:
 *   draft ──submit──► submitted ──verify──► verified
 *                          └────reject────► rejected ──author perbaiki──► submitted
 */
final class RegistrationRepo
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED  = 'verified';
    public const STATUS_REJECTED  = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
    ];

    public const PARTICIPANT_TYPES = ['presenter', 'non_presenter'];

    public const MODES = [
        'offline' => 'Offline — present at the venue',
        'online'  => 'Online — send a pre-recorded video',
    ];

    /** Batas peserta tambahan non-presenter per paper. */
    public const MAX_ATTENDEES = 10;

    public const DEFAULT_SHIRT_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM registrations WHERE id = ?', [$id]);
    }

    public static function findByAuthor(int $authorId): ?array
    {
        return Db::one('SELECT * FROM registrations WHERE author_id = ?', [$authorId]);
    }

    /** Registrasi + data author, untuk halaman detail admin. */
    public static function findWithAuthor(int $id): ?array
    {
        return Db::one(
            'SELECT r.*,
                    a.paper_id, a.paper_title, a.first_author_name, a.email, a.affiliation,
                    a.country AS author_country, a.phone, ' . AuthorRepo::pagesSql() . ' AS total_pages,
                    a.total_pages AS review_pages, a.final_pages, a.track,
                    fc.label AS fee_label, fc.code AS fee_code,
                    pk.name AS package_name
             FROM registrations r
             JOIN authors a ON a.id = r.author_id
             LEFT JOIN fee_categories fc ON fc.id = r.fee_category_id
             LEFT JOIN packages pk ON pk.id = r.package_id
             WHERE r.id = ?',
            [$id]
        );
    }

    /** Buat baris draft bila author belum punya registrasi. */
    public static function ensureDraft(int $editionId, array $author): array
    {
        $existing = self::findByAuthor((int) $author['id']);
        if ($existing !== null) {
            return $existing;
        }

        $id = Db::insert('registrations', [
            'edition_id'       => $editionId,
            'author_id'        => (int) $author['id'],
            'participant_type' => (int) ($author['is_presenter'] ?? 1) === 1 ? 'presenter' : 'non_presenter',
            'currency'         => FeeEngine::currencyForCountry($author['country'] ?? null),
            'status'           => self::STATUS_DRAFT,
            'created_at'       => now(),
        ]);

        return self::find($id) ?? [];
    }

    public static function isEditable(?array $registration): bool
    {
        $status = (string) ($registration['status'] ?? self::STATUS_DRAFT);
        return in_array($status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    /**
     * Simpan pilihan author beserta hasil hitung biaya.
     *
     * @param array{
     *     participant_type:string, is_student:bool, is_ieee_member:bool, ieee_number:?string,
     *     extra_papers:int, package_id:?int, extra:array<string,mixed>,
     *     presenter_id?:?int, presentation_mode?:string, presenter_shirt?:?string, main_author_id?:?int
     * } $input
     * @param array<string,mixed> $quote hasil FeeEngine::quote()
     */
    public static function save(int $id, array $input, array $quote, bool $submit): void
    {
        $data = [
            'participant_type' => $input['participant_type'],
            'is_student'       => $input['is_student'] ? 1 : 0,
            'is_ieee_member'   => $input['is_ieee_member'] ? 1 : 0,
            'ieee_number'      => $input['ieee_number'] !== '' ? $input['ieee_number'] : null,
            'extra_papers'     => (int) $input['extra_papers'],
            'package_id'       => $input['package_id'],
            'fee_category_id'  => $quote['fee_category_id'],
            'extra_pages'      => (int) $quote['extra_pages'],
            'currency'         => (string) $quote['currency'],
            'subtotal'         => (float) $quote['subtotal'],
            'discount'         => (float) $quote['discount'],
            'total'            => (float) $quote['total'],
            'presenter_id'      => $input['presenter_id'] ?? null,
            'presentation_mode' => isset(self::MODES[$input['presentation_mode'] ?? '']) ? $input['presentation_mode'] : 'offline',
            'presenter_shirt'   => ($input['presenter_shirt'] ?? '') !== '' ? $input['presenter_shirt'] : null,
            'main_author_id'    => $input['main_author_id'] ?? null,
            'extra_json'       => $input['extra'] === []
                ? null
                : json_encode($input['extra'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at'       => now(),
        ];

        if ($submit) {
            $data['status']        = self::STATUS_SUBMITTED;
            $data['submitted_at']  = now();
            $data['reject_reason'] = null;
        }

        Db::update('registrations', $data, 'id = :target_id', ['target_id' => $id]);
    }

    // ── Peserta tambahan (non-presenter) ────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    public static function attendees(int $registrationId): array
    {
        return Db::all('SELECT * FROM registration_attendees WHERE registration_id = ? ORDER BY sort, id', [$registrationId]);
    }

    /**
     * Ganti seluruh daftar peserta tambahan.
     *
     * @param array<int,array{paper_author_id:?int,name:string,affiliation:?string,email:?string,shirt_size:?string}> $rows
     */
    public static function saveAttendees(int $editionId, int $registrationId, array $rows): void
    {
        Db::run('DELETE FROM registration_attendees WHERE registration_id = ?', [$registrationId]);

        foreach (array_values($rows) as $i => $row) {
            Db::insert('registration_attendees', [
                'edition_id'      => $editionId,
                'registration_id' => $registrationId,
                'paper_author_id' => $row['paper_author_id'],
                'name'            => mb_substr($row['name'], 0, 190),
                'affiliation'     => $row['affiliation'] !== null ? mb_substr($row['affiliation'], 0, 255) : null,
                'email'           => $row['email'] !== null ? mb_strtolower(mb_substr($row['email'], 0, 190)) : null,
                'shirt_size'      => $row['shirt_size'],
                'sort'            => ($i + 1) * 10,
                'created_at'      => now(),
            ]);
        }
    }

    /**
     * Validasi isian keranjang peserta tambahan dari form.
     *
     * @param array<int,mixed> $input   $_POST['attendees']
     * @param array<int,array<string,mixed>> $paperAuthors
     * @return array{rows:array<int,array<string,mixed>>, errors:array<string,string>}
     */
    public static function validateAttendees(array $input, array $paperAuthors, ?int $presenterId, array $shirtSizes, bool $shirtOn, bool $requireShirt = true): array
    {
        $byId = [];
        foreach ($paperAuthors as $pa) {
            $byId[(int) $pa['id']] = $pa;
        }

        $rows   = [];
        $errors = [];
        $seen   = [];

        foreach (array_values($input) as $i => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $n      = $i + 1;
            $source = (string) ($raw['source'] ?? 'other');
            $shirt  = $shirtOn ? trim((string) ($raw['shirt_size'] ?? '')) : '';
            if ($shirt !== '' && !in_array($shirt, $shirtSizes, true)) {
                $shirt = '';
            }

            if ($source === 'author') {
                $paId = (int) ($raw['paper_author_id'] ?? 0);
                if (!isset($byId[$paId])) {
                    $errors['attendee_' . $n] = "Attendee $n: choose an author from the list.";
                    continue;
                }
                if ($paId === $presenterId) {
                    $errors['attendee_' . $n] = "Attendee $n: " . $byId[$paId]['name'] . ' is already the presenter (included in the registration fee).';
                    continue;
                }
                $pa  = $byId[$paId];
                $row = [
                    'paper_author_id' => $paId,
                    'name'            => (string) $pa['name'],
                    'affiliation'     => $pa['affiliation'] ?? null,
                    'email'           => $pa['email'] ?? null,
                ];
                $key = 'pa' . $paId;
            } else {
                $name  = trim((string) ($raw['name'] ?? ''));
                $aff   = trim((string) ($raw['affiliation'] ?? ''));
                $email = mb_strtolower(trim((string) ($raw['email'] ?? '')));
                if ($name === '' && $aff === '' && $email === '') {
                    continue; // baris kosong
                }
                if ($name === '' || $aff === '' || $email === '') {
                    $errors['attendee_' . $n] = "Attendee $n: name, affiliation and email are required.";
                    continue;
                }
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors['attendee_' . $n] = "Attendee $n: \"$email\" is not a valid email.";
                    continue;
                }
                $row = ['paper_author_id' => null, 'name' => $name, 'affiliation' => $aff, 'email' => $email];
                $key = 'em' . $email;
            }

            if (isset($seen[$key])) {
                $errors['attendee_' . $n] = "Attendee $n: " . $row['name'] . ' is listed twice.';
                continue;
            }
            if ($shirtOn && $requireShirt && $shirt === '') {
                $errors['attendee_' . $n] = "Attendee $n: please choose a shirt size.";
            }

            $seen[$key] = true;
            $rows[]     = $row + ['shirt_size' => $shirt !== '' ? $shirt : null];
        }

        if (count($rows) > self::MAX_ATTENDEES) {
            $errors['attendees'] = 'At most ' . self::MAX_ATTENDEES . ' additional attendees per paper.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    // ── Paper tambahan dari first author yang sama ──────────────────────────

    /**
     * Paper lain (accepted) dengan email first author yang sama, yang boleh
     * dipilih sebagai paper utama. Paper yang sendiri sudah "paper tambahan"
     * tidak bisa menjadi paper utama.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function mainPaperOptions(int $editionId, array $author): array
    {
        $email = mb_strtolower(trim((string) ($author['email'] ?? '')));
        if ($email === '') {
            return [];
        }

        return Db::all(
            "SELECT a.id, a.paper_id, a.paper_title, r.status AS registration_status
             FROM authors a
             LEFT JOIN registrations r ON r.author_id = a.id
             WHERE a.edition_id = ? AND a.id != ? AND LOWER(a.email) = ? AND a.paper_status = 'accepted'
               AND (r.main_author_id IS NULL)
             ORDER BY a.paper_id",
            [$editionId, (int) $author['id'], $email]
        );
    }

    /** Paper ini sudah dipakai sebagai paper utama oleh paper lain? */
    public static function isMainPaperOfOthers(int $authorId): bool
    {
        return Db::value('SELECT 1 FROM registrations WHERE main_author_id = ? LIMIT 1', [$authorId]) !== null;
    }

    // ── Ukuran baju ─────────────────────────────────────────────────────────

    public static function shirtEnabled(int $editionId): bool
    {
        return SettingRepo::getBool($editionId, 'shirt_size_enabled', false);
    }

    /** @return array<int,string> */
    public static function shirtSizes(int $editionId): array
    {
        $sizes = array_values(array_filter(array_map(
            static fn ($s): string => trim((string) $s),
            SettingRepo::getArray($editionId, 'shirt_sizes', self::DEFAULT_SHIRT_SIZES)
        ), static fn (string $s): bool => $s !== ''));

        return $sizes !== [] ? $sizes : self::DEFAULT_SHIRT_SIZES;
    }

    // ── Biaya ───────────────────────────────────────────────────────────────

    /**
     * Masukan FeeEngine::quote() dari registrasi tersimpan — dipakai halaman
     * admin dan penerbitan invoice supaya hitungannya sama dengan form author.
     */
    public static function quoteInput(array $registration, array $author): array
    {
        $main = ($registration['main_author_id'] ?? null) !== null
            ? AuthorRepo::find((int) $registration['main_author_id'])
            : null;

        return [
            'country'          => $author['country'] ?? null,
            'participant_type' => (string) ($registration['participant_type'] ?? 'presenter'),
            'is_student'       => (int) ($registration['is_student'] ?? 0) === 1,
            'is_ieee_member'   => (int) ($registration['is_ieee_member'] ?? 0) === 1,
            'total_pages'      => AuthorRepo::pages($author),
            'extra_papers'     => (int) ($registration['extra_papers'] ?? 0),
            'package_id'       => ($registration['package_id'] ?? null) !== null ? (int) $registration['package_id'] : null,
            'discount'         => (float) ($registration['discount'] ?? 0),
            'main_paper_id'    => $main['paper_id'] ?? null,
            'attendees'        => array_map(static fn (array $a): string => (string) $a['name'], self::attendees((int) $registration['id'])),
        ];
    }

    /**
     * Rekap ukuran baju seluruh registrasi (presenter + peserta tambahan).
     *
     * @return array<string,int> ukuran => jumlah
     */
    public static function shirtSummary(int $editionId, bool $paidOnly = false): array
    {
        $join = $paidOnly
            ? "JOIN invoices i ON i.registration_id = r.id AND i.status = 'paid'"
            : '';
        $rows = Db::all(
            "SELECT size, COUNT(*) AS n FROM (
                 SELECT r.presenter_shirt AS size FROM registrations r $join
                 WHERE r.edition_id = ? AND r.status != 'draft' AND r.presenter_shirt IS NOT NULL
                 UNION ALL
                 SELECT ra.shirt_size FROM registration_attendees ra JOIN registrations r ON r.id = ra.registration_id $join
                 WHERE r.edition_id = ? AND r.status != 'draft' AND ra.shirt_size IS NOT NULL
             ) GROUP BY size",
            [$editionId, $editionId]
        );

        $out = [];
        foreach (self::shirtSizes($editionId) as $size) {
            $out[$size] = 0;
        }
        foreach ($rows as $row) {
            $out[(string) $row['size']] = (int) $row['n'];
        }

        return $out;
    }

    public static function verify(int $id, string $actor = 'admin'): void
    {
        Db::update('registrations', [
            'status'        => self::STATUS_VERIFIED,
            'verified_at'   => now(),
            'verified_by'   => $actor,
            'reject_reason' => null,
            'updated_at'    => now(),
        ], 'id = :target_id', ['target_id' => $id]);
    }

    public static function reject(int $id, string $reason, string $actor = 'admin'): void
    {
        Db::update('registrations', [
            'status'        => self::STATUS_REJECTED,
            'reject_reason' => $reason,
            'verified_at'   => null,
            'verified_by'   => $actor,
            'updated_at'    => now(),
        ], 'id = :target_id', ['target_id' => $id]);
    }

    /** @return array<string,int> jumlah registrasi per status */
    public static function countsByStatus(int $editionId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);

        foreach (Db::all(
            'SELECT status, COUNT(*) AS total FROM registrations WHERE edition_id = ? GROUP BY status',
            [$editionId]
        ) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<string,float> total nominal per mata uang untuk status tertentu */
    public static function totalsByCurrency(int $editionId, string $status): array
    {
        $totals = [];

        foreach (Db::all(
            'SELECT currency, SUM(total) AS amount FROM registrations
             WHERE edition_id = ? AND status = ? GROUP BY currency',
            [$editionId, $status]
        ) as $row) {
            $totals[(string) $row['currency']] = (float) $row['amount'];
        }

        return $totals;
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_VERIFIED  => 'cn-badge-verified',
            self::STATUS_SUBMITTED => 'cn-badge-submitted',
            self::STATUS_REJECTED  => 'cn-badge-rejected',
            default                => 'cn-badge-draft',
        };
    }
}
