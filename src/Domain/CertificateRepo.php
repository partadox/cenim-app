<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Audit;
use Cenim\Core\Db;
use Cenim\Core\Numbering;

/**
 * Sertifikat: author, presenter, participant, committee, award.
 *
 * Prinsipnya on-demand: baris sertifikat (beserta kode verifikasinya) dibuat
 * saat pertama kali dibutuhkan — baik oleh author yang mengunduh maupun oleh
 * admin yang menerbitkan massal — lalu PDF-nya dibuat dan di-cache saat diunduh.
 */
final class CertificateRepo
{
    public const TYPE_AUTHOR      = 'author';
    public const TYPE_PRESENTER   = 'presenter';
    public const TYPE_PARTICIPANT = 'participant';
    public const TYPE_COMMITTEE   = 'committee';
    public const TYPE_AWARD       = 'award';

    /** @return array<int,string> */
    public static function types(): array
    {
        return array_keys((array) cfg('certificate_types', []));
    }

    public static function label(string $type): string
    {
        return (string) (cfg('certificate_types.' . $type . '.label') ?? ucfirst($type));
    }

    public static function typeCode(string $type): string
    {
        return (string) (cfg('certificate_types.' . $type . '.code') ?? strtoupper(substr($type, 0, 3)));
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM certificates WHERE id = ?', [$id]);
    }

    public static function findByCode(string $code): ?array
    {
        return Db::one(
            'SELECT c.*, e.name AS edition_name, e.year AS edition_year
             FROM certificates c
             LEFT JOIN editions e ON e.id = c.edition_id
             WHERE UPPER(c.code) = UPPER(?)',
            [trim($code)]
        );
    }

    /** Apakah sertifikat sudah boleh dibuka author? */
    public static function isReleased(?array $edition): bool
    {
        $releaseAt = (string) ($edition['certificate_release_at'] ?? '');

        if ($releaseAt === '') {
            return false;
        }

        return strtotime($releaseAt . ' UTC') !== false && strtotime($releaseAt . ' UTC') <= time();
    }

    /**
     * Jenis sertifikat yang menjadi hak SATU ORANG (baris paper_authors) pada
     * satu paper, beserta judul subjeknya. Hanya jenis yang templatenya aktif.
     *
     *   registrasi presenter + lunas  → Author untuk semua author paper,
     *                                   Presenter untuk author bertanda penyaji
     *   registrasi non-presenter + lunas → Participant untuk author bertanda penyaji
     *
     * @return array<string,string> type => subject_title
     */
    public static function eligibleTypesForPerson(int $editionId, array $paper, array $person, ?array $registration, ?array $invoice): array
    {
        if ($registration === null || $invoice === null || (string) $invoice['status'] !== InvoiceRepo::STATUS_PAID) {
            return [];
        }

        $paperTitle  = (string) ($paper['paper_title'] ?? '');
        $isPresenter = (int) ($person['is_presenter'] ?? 0) === 1;

        $types = (string) $registration['participant_type'] === 'non_presenter'
            ? ($isPresenter ? [self::TYPE_PARTICIPANT => ''] : [])
            : [self::TYPE_AUTHOR => $paperTitle] + ($isPresenter ? [self::TYPE_PRESENTER => $paperTitle] : []);

        $eligible = [];
        foreach ($types as $type => $subject) {
            $template = CertificateTemplateRepo::forType($editionId, $type);
            if ($template !== null && (int) $template['is_active'] === 1) {
                $eligible[$type] = $subject;
            }
        }

        return $eligible;
    }

    /** Terbitkan sertifikat yang menjadi hak seseorang (idempoten). */
    public static function ensureForPerson(int $editionId, array $person, array $eligible): void
    {
        foreach ($eligible as $type => $subject) {
            self::ensure([
                'edition_id'            => $editionId,
                'type'                  => $type,
                'recipient_name'        => (string) $person['name'],
                'recipient_email'       => $person['email'] ?? null,
                'recipient_affiliation' => $person['affiliation'] ?? null,
                'recipient_ref_type'    => 'paper_author',
                'recipient_ref_id'      => (int) $person['id'],
                'subject_title'         => $subject,
            ]);
        }
    }

    /** Sertifikat committee untuk baris-baris panitia milik seseorang (idempoten). */
    public static function ensureForCommittee(int $editionId, array $committeeRows): void
    {
        $template = CertificateTemplateRepo::forType($editionId, self::TYPE_COMMITTEE);
        if ($template === null || (int) $template['is_active'] !== 1) {
            return;
        }

        foreach ($committeeRows as $row) {
            self::ensure(self::committeeRecipient($row) + ['edition_id' => $editionId, 'type' => self::TYPE_COMMITTEE]);
        }
    }

    /**
     * Buat baris sertifikat bila belum ada (idempoten per penerima + jenis).
     *
     * @param array{
     *   edition_id:int, type:string, recipient_name:string, recipient_email:?string,
     *   recipient_ref_type:string, recipient_ref_id:?int, subject_title:?string
     * } $data
     */
    public static function ensure(array $data): array
    {
        $existing = self::findFor(
            (int) $data['edition_id'],
            (string) $data['type'],
            (string) $data['recipient_ref_type'],
            $data['recipient_ref_id'] !== null ? (int) $data['recipient_ref_id'] : null
        );

        if ($existing !== null) {
            return $existing;
        }

        $editionId = (int) $data['edition_id'];
        $type      = (string) $data['type'];
        $template  = CertificateTemplateRepo::ensure($editionId, $type);
        $year      = (int) (EditionRepo::find($editionId)['year'] ?? date('Y'));

        $id = Db::insert('certificates', [
            'edition_id'         => $editionId,
            'type'               => $type,
            'template_id'        => (int) ($template['id'] ?? 0) ?: null,
            'recipient_name'     => mb_substr(trim((string) $data['recipient_name']), 0, 190),
            'recipient_email'    => $data['recipient_email'] !== null && trim((string) $data['recipient_email']) !== ''
                ? mb_substr(trim((string) $data['recipient_email']), 0, 190)
                : null,
            'recipient_affiliation' => self::nullableText($data['recipient_affiliation'] ?? null, 255),
            'recipient_ref_type' => (string) $data['recipient_ref_type'],
            'recipient_ref_id'   => $data['recipient_ref_id'] !== null ? (int) $data['recipient_ref_id'] : null,
            'subject_title'      => $data['subject_title'] !== null && trim((string) $data['subject_title']) !== ''
                ? mb_substr(trim((string) $data['subject_title']), 0, 500)
                : null,
            'code'               => Numbering::certificate($editionId, $year, self::typeCode($type)),
            'number'             => self::nextNumber($editionId, $template),
            'issued_at'          => now(),
            'created_at'         => now(),
        ]);

        return self::find($id) ?? [];
    }

    /**
     * Nomor sertifikat: nomor induk dari template + "-" + urutan 3 digit
     * (mis. 1234/IT2.VII/HK.01/2026-001). Urutan terpisah per nomor induk, jadi
     * mengganti nomor induk memulai lagi dari -001. null bila nomor induk kosong.
     */
    public static function nextNumber(int $editionId, ?array $template): ?string
    {
        $base = trim((string) ($template['number_base'] ?? ''));
        if ($base === '') {
            return null;
        }

        $key = 'certno-' . (string) $template['type'] . '-' . substr(md5($base), 0, 10);

        return $base . '-' . str_pad((string) Numbering::next($editionId, $key), 3, '0', STR_PAD_LEFT);
    }

    /** Beri nomor ke sertifikat satu template yang belum bernomor (urut terbit). */
    public static function assignMissingNumbers(int $editionId, array $template): int
    {
        if (trim((string) ($template['number_base'] ?? '')) === '') {
            return 0;
        }

        $rows = Db::all(
            'SELECT id FROM certificates WHERE edition_id = ? AND type = ? AND (number IS NULL OR number = \'\') ORDER BY issued_at, id',
            [$editionId, (string) $template['type']]
        );

        foreach ($rows as $row) {
            Db::update('certificates', [
                'number'     => self::nextNumber($editionId, $template),
                'template_id' => (int) $template['id'],
                'updated_at' => now(),
            ], 'id = :target_id', ['target_id' => (int) $row['id']]);
        }

        return count($rows);
    }

    private static function nullableText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    /** Data penerima dari satu baris committee. */
    private static function committeeRecipient(array $row): array
    {
        return [
            'recipient_name'        => (string) $row['name'],
            'recipient_email'       => $row['email'] ?? null,
            'recipient_affiliation' => $row['affiliation'] ?? null,
            'recipient_ref_type'    => 'committee',
            'recipient_ref_id'      => (int) $row['id'],
            'subject_title'         => $row['role'] ?? null,
        ];
    }

    public static function findFor(int $editionId, string $type, string $refType, ?int $refId): ?array
    {
        if ($refId === null) {
            return null;
        }

        return Db::one(
            'SELECT * FROM certificates
             WHERE edition_id = ? AND type = ? AND recipient_ref_type = ? AND recipient_ref_id = ?
             ORDER BY id DESC LIMIT 1',
            [$editionId, $type, $refType, $refId]
        );
    }

    /**
     * Terbitkan massal untuk satu jenis.
     *
     * @return array{issued:int, existing:int, skipped:int}
     */
    public static function issueBulk(int $editionId, string $type, string $actor = 'admin'): array
    {
        $issued   = 0;
        $existing = 0;

        foreach (self::recipientsFor($editionId, $type) as $recipient) {
            $before = self::findFor($editionId, $type, $recipient['recipient_ref_type'], $recipient['recipient_ref_id']);
            self::ensure($recipient + ['edition_id' => $editionId, 'type' => $type]);
            $before === null ? $issued++ : $existing++;
        }

        if ($issued > 0) {
            Audit::log('certificate.issued_bulk', [
                'actor_type' => 'admin',
                'actor_id'   => $actor,
                'meta'       => ['type' => $type, 'issued' => $issued, 'existing' => $existing],
            ]);
        }

        return ['issued' => $issued, 'existing' => $existing, 'skipped' => 0];
    }

    /**
     * Calon penerima untuk satu jenis sertifikat.
     *
     * @return array<int,array{recipient_name:string,recipient_email:?string,recipient_ref_type:string,recipient_ref_id:?int,subject_title:?string}>
     */
    public static function recipientsFor(int $editionId, string $type): array
    {
        if ($type === self::TYPE_COMMITTEE) {
            return array_map(static fn (array $row): array => self::committeeRecipient($row), CommitteeRepo::all($editionId));
        }

        if ($type === self::TYPE_AWARD) {
            return []; // penghargaan hanya diterbitkan manual satu per satu
        }

        $condition = match ($type) {
            self::TYPE_PARTICIPANT => "r.participant_type = 'non_presenter' AND pa.is_presenter = 1",
            self::TYPE_PRESENTER   => "r.participant_type = 'presenter' AND pa.is_presenter = 1",
            default                => "r.participant_type = 'presenter'",
        };

        $rows = Db::all(
            "SELECT pa.id, pa.name, pa.email, pa.affiliation, a.paper_title
             FROM registrations r
             JOIN authors a        ON a.id = r.author_id
             JOIN paper_authors pa ON pa.author_id = a.id
             JOIN invoices i       ON i.registration_id = r.id AND i.status = '" . InvoiceRepo::STATUS_PAID . "'
             WHERE r.edition_id = ? AND {$condition}
             ORDER BY a.paper_id, pa.position",
            [$editionId]
        );

        $recipients = array_map(
            static fn (array $row): array => [
                'recipient_name'        => (string) $row['name'],
                'recipient_email'       => $row['email'] ?? null,
                'recipient_affiliation' => $row['affiliation'] ?? null,
                'recipient_ref_type'    => 'paper_author',
                'recipient_ref_id'      => (int) $row['id'],
                'subject_title'         => $type === self::TYPE_PARTICIPANT ? null : ($row['paper_title'] ?? null),
            ],
            $rows
        );

        // Tamu non-presenter dari keranjang Registration form (bukan author, jadi
        // tidak bisa login — sertifikatnya diterbitkan & dikirim panitia).
        if ($type === self::TYPE_PARTICIPANT) {
            foreach (Db::all(
                "SELECT ra.id, ra.name, ra.email, ra.affiliation
                 FROM registration_attendees ra
                 JOIN registrations r ON r.id = ra.registration_id
                 JOIN invoices i ON i.registration_id = r.id AND i.status = '" . InvoiceRepo::STATUS_PAID . "'
                 WHERE r.edition_id = ? AND ra.paper_author_id IS NULL
                 ORDER BY ra.name",
                [$editionId]
            ) as $row) {
                $recipients[] = [
                    'recipient_name'        => (string) $row['name'],
                    'recipient_email'       => $row['email'] ?? null,
                    'recipient_affiliation' => $row['affiliation'] ?? null,
                    'recipient_ref_type'    => 'registration_attendee',
                    'recipient_ref_id'      => (int) $row['id'],
                    'subject_title'         => null,
                ];
            }
        }

        return $recipients;
    }

    /** Sertifikat milik satu orang (baris paper_authors) yang sudah diterbitkan. */
    public static function forPerson(int $editionId, int $paperAuthorId): array
    {
        return Db::all(
            "SELECT * FROM certificates
             WHERE edition_id = ? AND recipient_ref_type = 'paper_author' AND recipient_ref_id = ?
             ORDER BY type",
            [$editionId, $paperAuthorId]
        );
    }

    /**
     * Sertifikat committee untuk baris-baris panitia tertentu.
     *
     * @param array<int,int> $committeeIds
     */
    public static function forCommittee(int $editionId, array $committeeIds): array
    {
        $committeeIds = array_values(array_filter(array_map('intval', $committeeIds)));
        if ($committeeIds === []) {
            return [];
        }

        return Db::all(
            "SELECT * FROM certificates
             WHERE edition_id = ? AND recipient_ref_type = 'committee'
               AND recipient_ref_id IN (" . implode(',', array_fill(0, count($committeeIds), '?')) . ')
             ORDER BY type, id',
            array_merge([$editionId], $committeeIds)
        );
    }

    /**
     * Boleh dilihat orang yang login? Miliknya sendiri (sebagai author di paper
     * mana pun, atau sebagai panitia).
     *
     * @param array<int,int> $paperAuthorIds
     * @param array<int,int> $committeeIds
     */
    public static function belongsTo(array $certificate, array $paperAuthorIds, array $committeeIds): bool
    {
        $refId = (int) $certificate['recipient_ref_id'];

        return match ((string) $certificate['recipient_ref_type']) {
            'paper_author' => in_array($refId, array_map('intval', $paperAuthorIds), true),
            'committee'    => in_array($refId, array_map('intval', $committeeIds), true),
            default        => false,
        };
    }

    /** @return array<int,array<string,mixed>> */
    public static function byType(int $editionId, string $type): array
    {
        return Db::all(
            'SELECT * FROM certificates WHERE edition_id = ? AND type = ? ORDER BY recipient_name',
            [$editionId, $type]
        );
    }

    /** @return array<string,int> */
    public static function countsByType(int $editionId): array
    {
        $counts = array_fill_keys(self::types(), 0);

        foreach (Db::all(
            'SELECT type, COUNT(*) AS total FROM certificates
             WHERE edition_id = ? AND revoked_at IS NULL GROUP BY type',
            [$editionId]
        ) as $row) {
            $counts[(string) $row['type']] = (int) $row['total'];
        }

        return $counts;
    }

    /** Jumlah calon penerima yang belum punya sertifikat, per jenis. */
    public static function pendingCounts(int $editionId): array
    {
        $pending = [];

        foreach (self::types() as $type) {
            if ($type === self::TYPE_AWARD) {
                $pending[$type] = 0;
                continue;
            }

            $count = 0;
            foreach (self::recipientsFor($editionId, $type) as $recipient) {
                if (self::findFor($editionId, $type, $recipient['recipient_ref_type'], $recipient['recipient_ref_id']) === null) {
                    $count++;
                }
            }
            $pending[$type] = $count;
        }

        return $pending;
    }

    public static function revoke(int $id, string $reason, string $actor = 'admin'): void
    {
        Db::update('certificates', [
            'revoked_at'    => now(),
            'revoke_reason' => mb_substr($reason, 0, 300),
            'pdf_cache'     => null,
            'updated_at'    => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        Audit::log('certificate.revoked', [
            'actor_type'  => 'admin',
            'actor_id'    => $actor,
            'target_type' => 'certificate',
            'target_id'   => $id,
            'meta'        => ['reason' => $reason],
        ]);
    }

    public static function restore(int $id, string $actor = 'admin'): void
    {
        Db::update('certificates', [
            'revoked_at'    => null,
            'revoke_reason' => null,
            'updated_at'    => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        Audit::log('certificate.restored', [
            'actor_type'  => 'admin',
            'actor_id'    => $actor,
            'target_type' => 'certificate',
            'target_id'   => $id,
        ]);
    }

    public static function markDownloaded(int $id): void
    {
        Db::run(
            'UPDATE certificates SET download_count = download_count + 1, last_downloaded_at = :now WHERE id = :id',
            ['now' => now(), 'id' => $id]
        );
    }

    public static function verifyUrl(string $code): string
    {
        return abs_url('verify.php?code=' . urlencode($code));
    }

    public static function isRevoked(array $certificate): bool
    {
        return ($certificate['revoked_at'] ?? null) !== null;
    }
}
