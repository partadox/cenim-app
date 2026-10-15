<?php

declare(strict_types=1);

namespace Cenim\Support;

use Cenim\Core\Db;
use Cenim\Domain\EditionRepo;
use InvalidArgumentException;

/**
 * Salin PENGATURAN satu edisi ke edisi lain (mis. CENIM 2026 → CENIM 2027).
 *
 * Hanya konfigurasi; data peserta (paper, author, registrasi, invoice,
 * pembayaran, sertifikat terbit, log) tidak pernah ikut. Bagian yang dipilih
 * di edisi tujuan DIGANTI isinya dengan salinan dari edisi sumber.
 *
 * Opsi "years": teks "CENIM 2026" → "CENIM 2027" dan angka tahun 2026 yang
 * berdiri sendiri → 2027 di settings, konten landing, timeline, dan template
 * sertifikat; tanggal (YYYY-MM-DD) digeser sejumlah selisih tahun.
 */
final class EditionCloner
{
    /** Bagian yang bisa disalin → [label, keterangan, bawaan dicentang]. */
    public const GROUPS = [
        'settings'     => ['Settings', 'Registration & payment switches, bank accounts, contacts, signatory, document notes, email, letterhead texts', true],
        'fees'         => ['Fees & packages', 'Fee categories, packages, extra page/paper rules', true],
        'forms'        => ['Form builder', 'Extra questions on the registration, payment & video presentation forms', true],
        'content'      => ['Landing content', 'All edited texts of the public pages (CMS blocks)', true],
        'timeline'     => ['Timeline', 'Important dates (text only; previous extensions are cleared)', true],
        'certificates' => ['Certificate templates', 'Template images, signature, layout of every type (number bases are cleared)', true],
        'kop'          => ['Letterhead logos', 'Logos of the PDF letterhead (order, side, height)', true],
        'sponsors'     => ['Sponsors', 'Sponsor & supporting institution logos on the landing page', true],
        'gallery'      => ['Gallery photos', 'Photos in the landing gallery (the CFP poster is not copied)', true],
        'speakers'     => ['Keynote speakers', 'Usually different every year', false],
        'committee'    => ['Committee & reviewers', 'Usually partly different every year', false],
        'logo'         => ['Edition logo', 'Usually a new logo with the new year — leave off and set it on the Logo page', false],
    ];

    /** Setting yang tidak disalin (khas per tahun / dikelola bagian lain). */
    private const SKIP_SETTINGS = ['logo_file_id', 'kop_logos_customized', 'loa_number_base', 'cfp_poster_file_id'];

    /** Setting yang disalin apa adanya, tanpa penggantian tahun (rekening, kontak, nama orang). */
    private const VERBATIM_SETTINGS = [
        'bank_accounts', 'contact_email', 'contact_whatsapp', 'signatory_name',
        'mail_reply_to', 'mail_signature_name', 'mail_signature_email', 'mail_signature_web', 'loa_city', 'treasurer_name',
    ];

    private array $replace = [];
    private int $shiftYears = 0;

    /**
     * @param array<int,string> $groups kunci dari GROUPS
     * @return array<string,int> jumlah baris yang disalin per bagian
     */
    public static function copy(int $fromId, int $toId, array $groups, bool $updateYears = true): array
    {
        $from = EditionRepo::find($fromId);
        $to   = EditionRepo::find($toId);

        if ($from === null || $to === null || $fromId === $toId) {
            throw new InvalidArgumentException('Choose two different editions.');
        }

        $self = new self();
        $self->shiftYears = (int) $to['year'] - (int) $from['year'];
        if ($updateYears && $self->shiftYears !== 0) {
            $self->replace = [
                (string) $from['name'] => (string) $to['name'],
                'CENIM' . $from['year'] => 'CENIM' . $to['year'],
                'CENIM' . substr((string) $from['year'], 2) => 'CENIM' . substr((string) $to['year'], 2),
            ];
        }
        $years = $updateYears ? [(int) $from['year'], (int) $to['year']] : null;

        $groups = array_values(array_intersect(array_keys(self::GROUPS), $groups));
        $report = [];

        // Bagian yang dirujuk data peserta tidak boleh diganti di edisi yang sudah berjalan.
        $blocked = array_filter([
            'fees'         => in_array('fees', $groups, true) && Db::count('registrations', 'edition_id = ?', [$toId]) > 0
                ? 'fees (the target edition already has registrations)' : null,
            'certificates' => in_array('certificates', $groups, true) && Db::count('certificates', 'edition_id = ?', [$toId]) > 0
                ? 'certificate templates (the target edition already issued certificates)' : null,
            'committee'    => in_array('committee', $groups, true)
                && Db::value("SELECT 1 FROM certificates WHERE edition_id = ? AND recipient_ref_type = 'committee' LIMIT 1", [$toId]) !== null
                ? 'committee (committee certificates were already issued in the target edition)' : null,
        ]);
        if ($blocked !== []) {
            throw new InvalidArgumentException('Cannot replace: ' . implode('; ', $blocked) . '. Untick those parts and try again.');
        }

        Db::transaction(static function () use ($self, $fromId, $toId, $groups, $years, &$report): void {
            foreach ($groups as $group) {
                $report[$group] = match ($group) {
                    'settings'     => $self->settings($fromId, $toId, $years),
                    'fees'         => $self->rows('fee_categories', $fromId, $toId, $years, ['label'])
                        + $self->rows('packages', $fromId, $toId, $years, ['name', 'description']),
                    'forms'        => $self->rows('form_fields', $fromId, $toId, $years, ['label', 'placeholder', 'help_text', 'options_json']),
                    'content'      => $self->rows('content_blocks', $fromId, $toId, $years, ['data_json']),
                    'timeline'     => $self->rows('timeline_items', $fromId, $toId, $years, ['title', 'current_date_text', 'note'], ['history_json' => '[]']),
                    'certificates' => $self->rows('certificate_templates', $fromId, $toId, $years, ['name', 'fields_json'], ['number_base' => null]),
                    'kop'          => $self->rows('kop_logos', $fromId, $toId, null, [])
                        + $self->copySetting($fromId, $toId, 'kop_logos_customized'),
                    'speakers'     => $self->rows('speakers', $fromId, $toId, null, []),
                    'sponsors'     => $self->rows('sponsors', $fromId, $toId, null, []),
                    'gallery'      => $self->rows('gallery_photos', $fromId, $toId, null, []),
                    'committee'    => $self->rows('committee', $fromId, $toId, null, []),
                    'logo'         => $self->copySetting($fromId, $toId, 'logo_file_id'),
                };
            }

            // Tanggal konferensi & rilis sertifikat digeser bila edisi tujuan belum punya.
            $self->editionDates($fromId, $toId);
        });

        return $report;
    }

    // ── Bagian ──────────────────────────────────────────────────────────────

    private function settings(int $fromId, int $toId, ?array $years): int
    {
        $rows = Db::all('SELECT "key", value_json FROM settings WHERE edition_id = ?', [$fromId]);
        $n    = 0;

        foreach ($rows as $row) {
            if (in_array($row['key'], self::SKIP_SETTINGS, true)) {
                continue;
            }

            $value = json_decode((string) $row['value_json'], true);
            if (!in_array($row['key'], self::VERBATIM_SETTINGS, true)) {
                $value = $this->transform($value, $years);
            }

            Db::run('DELETE FROM settings WHERE edition_id = ? AND "key" = ?', [$toId, $row['key']]);
            Db::insert('settings', [
                'edition_id' => $toId,
                'key'        => $row['key'],
                'value_json' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * Ganti seluruh baris $table milik edisi tujuan dengan salinan dari edisi
     * sumber. Kolom di $textColumns ikut diperbarui tahunnya.
     */
    private function rows(string $table, int $fromId, int $toId, ?array $years, array $textColumns, array $overrides = []): int
    {
        Db::delete($table, 'edition_id = ?', [$toId]);

        $rows = Db::all('SELECT * FROM ' . $table . ' WHERE edition_id = ? ORDER BY id', [$fromId]);
        foreach ($rows as $row) {
            unset($row['id']);
            $row['edition_id'] = $toId;

            foreach ($textColumns as $column) {
                if (isset($row[$column]) && is_string($row[$column])) {
                    $row[$column] = $this->transformText($row[$column], $years);
                }
            }

            foreach (['created_at', 'updated_at'] as $stamp) {
                if (array_key_exists($stamp, $row)) {
                    $row[$stamp] = now();
                }
            }

            Db::insert($table, $overrides + $row);
        }

        return count($rows);
    }

    private function copySetting(int $fromId, int $toId, string $key): int
    {
        $value = Db::value('SELECT value_json FROM settings WHERE edition_id = ? AND "key" = ?', [$fromId, $key]);
        if ($value === null) {
            return 0;
        }

        Db::run('DELETE FROM settings WHERE edition_id = ? AND "key" = ?', [$toId, $key]);
        Db::insert('settings', ['edition_id' => $toId, 'key' => $key, 'value_json' => $value, 'updated_at' => now()]);

        return 1;
    }

    private function editionDates(int $fromId, int $toId): void
    {
        $from = EditionRepo::find($fromId) ?? [];
        $to   = EditionRepo::find($toId) ?? [];
        $data = [];

        foreach (['conference_start', 'conference_end', 'certificate_release_at'] as $column) {
            if (($to[$column] ?? '') === '' && ($from[$column] ?? '') !== '') {
                $data[$column] = $this->shiftDate((string) $from[$column]);
            }
        }

        if ($data !== []) {
            Db::update('editions', $data + ['updated_at' => now()], 'id = :target_id', ['target_id' => $toId]);
        }
    }

    // ── Transformasi teks & tanggal ─────────────────────────────────────────

    private function transform(mixed $value, ?array $years): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->transform($item, $years), $value);
        }

        return is_string($value) ? $this->transformText($value, $years) : $value;
    }

    private function transformText(string $text, ?array $years): string
    {
        if ($years === null) {
            return $text;
        }

        // Tanggal ISO lebih dulu (digeser utuh), lalu nama edisi, lalu tahun lepas.
        $text = (string) preg_replace_callback(
            '/\b(\d{4})-(\d{2})-(\d{2})\b/',
            fn (array $m): string => $this->shiftDate($m[0]),
            $text
        );
        $text = strtr($text, $this->replace);

        return (string) preg_replace('/(?<![\d\/.-])' . $years[0] . '(?![\d])/', (string) $years[1], $text);
    }

    private function shiftDate(string $value): string
    {
        if ($this->shiftYears === 0 || preg_match('/^(\d{4})(-\d{2}-\d{2})(.*)$/', $value, $m) !== 1) {
            return $value;
        }

        $year = (int) $m[1] + $this->shiftYears;
        $date = $year . $m[2];

        // 29 Februari di tahun bukan kabisat → 28 Februari.
        if (!checkdate((int) substr($m[2], 1, 2), (int) substr($m[2], 4, 2), $year)) {
            $date = $year . '-02-28';
        }

        return $date . $m[3];
    }
}
