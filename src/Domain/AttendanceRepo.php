<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Form 5 — Video Presentation (satu per paper, tabel `attendances`).
 *
 * Siapa yang presentasi, mode offline/online, dan siapa saja yang datang
 * ditentukan di Registration form (registrations + registration_attendees).
 * Form ini hanya menerima link video presentasi (Google Drive / YouTube):
 * wajib untuk presentasi online, opsional (cadangan) untuk offline.
 *
 * Tersedia setelah pembayaran lunas dan selama dibuka (Settings →
 * attendance_open + batas waktu attendance_deadline). Kolom mode/presenter/
 * attendee_ids_json disalin dari registrasi saat disimpan, sebagai arsip.
 */
final class AttendanceRepo
{
    /** Host yang diterima untuk link video → label. */
    public const VIDEO_HOSTS = ['drive' => 'Google Drive', 'youtube' => 'YouTube'];

    public static function findForPaper(int $authorId): ?array
    {
        return Db::one('SELECT * FROM attendances WHERE author_id = ?', [$authorId]);
    }

    /** Form muncul setelah invoice paper ini lunas. */
    public static function isAvailable(?array $invoice): bool
    {
        return $invoice !== null && (string) $invoice['status'] === InvoiceRepo::STATUS_PAID;
    }

    /** Dibuka admin (Settings) dan belum melewati batas waktu. */
    public static function isOpen(int $editionId): bool
    {
        if (!SettingRepo::getBool($editionId, 'attendance_open', true)) {
            return false;
        }

        $deadline = SettingRepo::getString($editionId, 'attendance_deadline');

        return $deadline === '' || strtotime($deadline . ' 23:59:59 ' . (string) env('APP_TZ', 'Asia/Jakarta')) >= time();
    }

    /** Sertifikat presenter paper ini sudah terbit → presenter tidak diubah lagi. */
    public static function presenterLocked(int $authorId): bool
    {
        return Db::value(
            "SELECT 1 FROM certificates c
             JOIN paper_authors pa ON pa.id = c.recipient_ref_id
             WHERE c.recipient_ref_type = 'paper_author' AND c.type = 'presenter' AND pa.author_id = ? AND c.revoked_at IS NULL
             LIMIT 1",
            [$authorId]
        ) !== null;
    }

    /** "drive" / "youtube" untuk URL video yang valid, atau null. */
    public static function videoHost(string $url): ?string
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !preg_match('#^https://#i', $url)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            in_array($host, ['drive.google.com', 'docs.google.com'], true)                           => 'drive',
            in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true) => 'youtube',
            default                                                                                => null,
        };
    }

    /**
     * Validasi link video.
     *
     * @return array{values:array<string,mixed>, errors:array<string,string>}
     */
    public static function validate(array $input, bool $videoRequired): array
    {
        $errors = [];
        $video  = trim((string) ($input['video_url'] ?? ''));
        $host   = null;

        if ($video === '') {
            if ($videoRequired) {
                $errors['video_url'] = 'Please paste the link to the presentation video.';
            }
        } elseif (($host = self::videoHost($video)) === null) {
            $errors['video_url'] = 'Use a Google Drive or YouTube link starting with https://.';
        }

        return [
            'values' => [
                'video_url'  => $video !== '' ? mb_substr($video, 0, 500) : null,
                'video_host' => $host,
                'note'       => mb_substr(trim((string) ($input['note'] ?? '')), 0, 1000) ?: null,
            ],
            'errors' => $errors,
        ];
    }

    /** Simpan (buat/ubah) video presentasi paper ini; mode & presenter ikut registrasi. */
    public static function save(array $paper, array $registration, array $values, array $extra, ?array $person): int
    {
        $authorId = (int) $paper['id'];
        $existing = self::findForPaper($authorId);

        $attending = array_values(array_filter(array_merge(
            [(int) ($registration['presenter_id'] ?? 0)],
            array_map(static fn (array $a): int => (int) ($a['paper_author_id'] ?? 0), RegistrationRepo::attendees((int) $registration['id']))
        )));

        $row = [
            'mode'              => (string) ($registration['presentation_mode'] ?? 'offline'),
            'presenter_id'      => ($registration['presenter_id'] ?? null) !== null ? (int) $registration['presenter_id'] : null,
            'attendee_ids_json' => json_encode($attending),
            'video_url'         => $values['video_url'],
            'video_host'        => $values['video_host'],
            'note'              => $values['note'],
            'extra_json'        => $extra === [] ? null : json_encode($extra, JSON_UNESCAPED_UNICODE),
            'filled_by_id'      => $person !== null ? (int) $person['id'] : null,
            'filled_by_name'    => $person !== null ? mb_substr($person['name'] . ' (' . ($person['email'] ?? '-') . ')', 0, 255) : null,
            'submitted_at'      => now(),
            'updated_at'        => now(),
        ];

        if ($existing !== null) {
            Db::update('attendances', $row, 'id = :target_id', ['target_id' => (int) $existing['id']]);

            return (int) $existing['id'];
        }

        return Db::insert('attendances', $row + [
            'edition_id' => (int) $paper['edition_id'],
            'author_id'  => $authorId,
            'created_at' => now(),
        ]);
    }

    /**
     * Daftar untuk admin: semua paper yang sudah lunas — presenter, mode,
     * siapa saja yang datang (dengan ukuran baju), dan video presentasinya.
     *
     * @param string $filter '' | offline | online | missing (online tanpa video)
     * @return array<int,array<string,mixed>>
     */
    public static function overview(int $editionId, string $filter = ''): array
    {
        $rows = Db::all(
            "SELECT a.id AS paper_row, a.paper_id, a.paper_title,
                    r.id AS registration_id, r.participant_type, r.presentation_mode AS mode, r.presenter_shirt,
                    pa.name AS presenter_name, pa.email AS presenter_email, pa.affiliation AS presenter_affiliation,
                    t.id AS attendance_id, t.video_url, t.video_host, t.note, t.extra_json, t.filled_by_name, t.updated_at
             FROM authors a
             JOIN registrations r ON r.author_id = a.id
             JOIN invoices i ON i.registration_id = r.id AND i.status = '" . InvoiceRepo::STATUS_PAID . "'
             LEFT JOIN paper_authors pa ON pa.id = r.presenter_id
             LEFT JOIN attendances t ON t.author_id = a.id
             WHERE a.edition_id = ?
             ORDER BY a.paper_id",
            [$editionId]
        );

        $out = [];
        foreach ($rows as $row) {
            $row['mode']      = (string) ($row['mode'] ?: 'offline');
            $row['attendees'] = RegistrationRepo::attendees((int) $row['registration_id']);
            $row['missing']   = $row['mode'] === 'online' && ($row['video_url'] ?? '') === '';

            $keep = match ($filter) {
                'offline', 'online' => $row['mode'] === $filter,
                'missing'           => $row['missing'],
                default             => true,
            };
            if ($keep) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
