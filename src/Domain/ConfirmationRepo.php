<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Form 0 — Confirmation form (satu per paper, tabel `paper_confirmations`).
 *
 * Diisi setelah paper diterima, sebelum final manuscript: rencana presentasi
 * offline/online, perkiraan jumlah orang yang datang (offline: 1 presenter +
 * sisanya non-presenter), jumlah halaman manuskrip review beserta perkiraan
 * biaya halaman tambahan, ukuran baju tiap orang yang datang (opsional, bila
 * Settings → shirt_size_enabled), dan catatan untuk panitia — dipakai untuk
 * memesan kaos dan memesan hotel lebih awal.
 *
 * Registration form baru terbuka setelah form ini dikirim (Settings →
 * confirmation_required). Form ini bisa diubah selama dibuka (confirmation_open
 * + confirmation_deadline) dan registrasinya belum dikirim.
 */
final class ConfirmationRepo
{
    public const MAX_NOTE = 2000;

    public static function findForPaper(int $authorId): ?array
    {
        return Db::one('SELECT * FROM paper_confirmations WHERE author_id = ?', [$authorId]);
    }

    /** Dibuka admin (Settings) dan belum melewati batas waktu. */
    public static function isOpen(int $editionId): bool
    {
        if (!SettingRepo::getBool($editionId, 'confirmation_open', true)) {
            return false;
        }

        $deadline = self::deadline($editionId);

        return $deadline === '' || strtotime($deadline . ' 23:59:59 ' . (string) env('APP_TZ', 'Asia/Jakarta')) >= time();
    }

    /** YYYY-MM-DD atau ''. */
    public static function deadline(int $editionId): string
    {
        return SettingRepo::getString($editionId, 'confirmation_deadline');
    }

    /** Registration form menunggu confirmation form? (Settings → confirmation_required) */
    public static function blocksRegistration(int $editionId, array $paper, ?array $registration): bool
    {
        if (!SettingRepo::getBool($editionId, 'confirmation_required', true)
            || (string) ($paper['paper_status'] ?? '') !== 'accepted') {
            return false; // hanya paper accepted (bukan peserta non-presenter)
        }

        // Registrasi yang sudah pernah dikirim (sebelum form ini ada) tidak ditahan.
        $status = (string) ($registration['status'] ?? RegistrationRepo::STATUS_DRAFT);
        if ($status !== RegistrationRepo::STATUS_DRAFT) {
            return false;
        }

        return self::findForPaper((int) $paper['id']) === null;
    }

    /** Masih bisa diubah author: form dibuka dan registrasi belum dikirim. */
    public static function isEditable(int $editionId, ?array $registration): bool
    {
        $status = (string) ($registration['status'] ?? RegistrationRepo::STATUS_DRAFT);

        return self::isOpen($editionId) && $status === RegistrationRepo::STATUS_DRAFT;
    }

    /**
     * Jumlah halaman & perkiraan biaya halaman tambahan dari manuskrip review.
     *
     * @return array{pages:int, included:int, extra:int, unit:float, total:float, currency:string}
     */
    public static function pageFee(int $editionId, array $paper): array
    {
        $pages    = (int) ($paper['total_pages'] ?? 0);
        $currency = FeeEngine::currencyForCountry($paper['country'] ?? null);
        $extra    = FeeEngine::extraPages($editionId, $pages);
        $unit     = FeeEngine::extraPageFee($editionId, $currency);

        return [
            'pages'    => $pages,
            'included' => FeeEngine::includedPages($editionId),
            'extra'    => $extra,
            'unit'     => $unit,
            'total'    => $extra * $unit,
            'currency' => $currency,
        ];
    }

    /** Batas orang yang datang offline: presenter + peserta tambahan registrasi. */
    public static function maxAttendees(): int
    {
        return 1 + RegistrationRepo::MAX_ATTENDEES;
    }

    /** Ukuran baju per orang (urutan pertama = presenter); [] bila tidak ditanyakan. */
    public static function shirts(?array $confirmation): array
    {
        $list = json_decode((string) ($confirmation['shirt_sizes_json'] ?? ''), true);

        return is_array($list) ? array_values(array_map('strval', $list)) : [];
    }

    /**
     * Rekap ukuran baju dari semua konfirmasi offline.
     *
     * @param array<int,array<string,mixed>> $rows overview()
     * @param array<int,string>              $sizes
     * @return array<string,int>
     */
    public static function shirtSummary(array $rows, array $sizes): array
    {
        $out = array_fill_keys($sizes, 0);
        foreach ($rows as $row) {
            if (($row['confirmation_id'] ?? null) === null || (string) $row['presentation_mode'] !== 'offline') {
                continue;
            }
            foreach (self::shirts($row) as $size) {
                if ($size !== '') {
                    $out[$size] = ($out[$size] ?? 0) + 1;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $input $_POST
     * @param array<int,string>   $shirtSizes ukuran yang ditawarkan; [] = tidak menanyakan ukuran baju
     * @return array{values:array{presentation_mode:string, attendees:int, note:string, shirts:array<int,string>}, errors:array<string,string>}
     */
    public static function validate(array $input, array $shirtSizes = []): array
    {
        $errors = [];
        $mode   = (string) ($input['presentation_mode'] ?? '');
        if (!isset(RegistrationRepo::MODES[$mode])) {
            $errors['presentation_mode'] = 'Please choose offline or online.';
            $mode = '';
        }

        $attendees = 0;
        if ($mode === 'offline') {
            $attendees = (int) ($input['attendees'] ?? 0);
            if ($attendees < 1 || $attendees > self::maxAttendees()) {
                $errors['attendees'] = 'Enter how many people will come (1 to ' . self::maxAttendees() . ', including the presenter).';
            }
        }

        // Ukuran baju: satu per orang yang datang (pertama = presenter).
        $shirts = [];
        if ($shirtSizes !== [] && $mode === 'offline' && !isset($errors['attendees'])) {
            $raw = is_array($input['shirts'] ?? null) ? array_values($input['shirts']) : [];
            for ($i = 0; $i < $attendees; $i++) {
                $size = trim((string) ($raw[$i] ?? ''));
                $shirts[] = in_array($size, $shirtSizes, true) ? $size : '';
            }
            if (in_array('', $shirts, true)) {
                $errors['shirts'] = 'Please choose a shirt size for every person who will come.';
            }
        }

        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > self::MAX_NOTE) {
            $errors['note'] = 'The note must not exceed ' . self::MAX_NOTE . ' characters.';
        }

        return [
            'values' => ['presentation_mode' => $mode, 'attendees' => $attendees, 'note' => $note, 'shirts' => $shirts],
            'errors' => $errors,
        ];
    }

    /** Simpan (buat atau ubah) konfirmasi paper. Kembalikan id baris. */
    public static function save(int $editionId, array $paper, array $values, ?array $person): int
    {
        $fee  = self::pageFee($editionId, $paper);
        $data = [
            'presentation_mode' => $values['presentation_mode'],
            'attendees'         => $values['presentation_mode'] === 'offline' ? (int) $values['attendees'] : 0,
            'pages'             => $fee['pages'],
            'extra_pages'       => $fee['extra'],
            'extra_page_fee'    => $fee['total'],
            'currency'          => $fee['currency'],
            'note'              => $values['note'] !== '' ? $values['note'] : null,
            'shirt_sizes_json'  => ($values['shirts'] ?? []) !== [] && $values['presentation_mode'] === 'offline'
                ? json_encode(array_values($values['shirts']), JSON_UNESCAPED_UNICODE)
                : null,
            'filled_by_id'      => $person !== null ? (int) $person['id'] : null,
            'filled_by_name'    => $person !== null ? mb_substr($person['name'] . ' (' . ($person['email'] ?? '-') . ')', 0, 255) : null,
        ];

        $existing = self::findForPaper((int) $paper['id']);
        if ($existing !== null) {
            Db::update('paper_confirmations', $data + ['updated_at' => now()], 'id = :target_id', ['target_id' => (int) $existing['id']]);

            return (int) $existing['id'];
        }

        return Db::insert('paper_confirmations', $data + [
            'edition_id'   => $editionId,
            'author_id'    => (int) $paper['id'],
            'submitted_at' => now(),
            'created_at'   => now(),
        ]);
    }

    /** "Offline · 2 people (presenter + 1 non-presenter)" */
    public static function summary(array $confirmation): string
    {
        if ((string) $confirmation['presentation_mode'] === 'online') {
            return 'Online presentation';
        }

        $n = (int) $confirmation['attendees'];

        return 'Offline · ' . $n . ' ' . ($n === 1 ? 'person' : 'people')
            . ' (presenter' . ($n > 1 ? ' + ' . ($n - 1) . ' non-presenter' : '') . ')';
    }

    /**
     * Semua paper accepted beserta konfirmasinya (null bila belum), untuk admin.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function overview(int $editionId): array
    {
        return Db::all(
            "SELECT a.id AS author_id, a.paper_id, a.paper_title, a.first_author_name, a.email, a.country,
                    a.total_pages, a.final_pages,
                    c.id AS confirmation_id, c.presentation_mode, c.attendees, c.pages, c.extra_pages,
                    c.extra_page_fee, c.currency, c.note, c.shirt_sizes_json, c.filled_by_name, c.submitted_at, c.updated_at,
                    r.status AS registration_status
             FROM authors a
             LEFT JOIN paper_confirmations c ON c.author_id = a.id
             LEFT JOIN registrations r ON r.author_id = a.id
             WHERE a.edition_id = ? AND a.paper_status = 'accepted'
             ORDER BY a.paper_id",
            [$editionId]
        );
    }
}
