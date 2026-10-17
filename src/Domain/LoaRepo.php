<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;
use Cenim\Core\Numbering;

/**
 * Letter of Acceptance per paper. Tersedia begitu status paper "accepted";
 * nomor diberikan sekali saat LoA pertama kali dibuka/diunduh, lalu tetap.
 *
 * Nomor: setting loa_number_base + "-001" (urut terbit). Bila nomor induk
 * kosong dipakai pola bawaan CENIM26/LOA/0001.
 */
final class LoaRepo
{
    /** Kota terbit surat bila Settings → Letter of Acceptance → Issuing city belum diisi. */
    public const DEFAULT_CITY = 'Surabaya';

    public const DEFAULT_BODY = "We are pleased to inform you that your paper entitled \"{paper_title}\" (Paper ID {paper_id}), "
        . "authored by {authors}, has been ACCEPTED for presentation at {edition_name}, held on {conference_dates} "
        . "in Surabaya, Indonesia.\n\n"
        . "Please complete the registration and payment through the Author Area before the deadline so that your paper "
        . "can be scheduled for presentation and submitted for inclusion in the proceedings.\n\n"
        . "Congratulations, and we look forward to your participation.";

    public static function isAvailable(array $paper): bool
    {
        return (string) ($paper['paper_status'] ?? '') === 'accepted';
    }

    /** Pastikan paper punya nomor LoA; kembalikan baris authors terbaru. */
    public static function ensureNumber(array $paper): array
    {
        if (trim((string) ($paper['loa_number'] ?? '')) !== '') {
            return $paper;
        }

        $editionId = (int) $paper['edition_id'];
        $base      = SettingRepo::getString($editionId, 'loa_number_base');
        $year      = (int) (EditionRepo::find($editionId)['year'] ?? date('Y'));

        $number = $base !== ''
            ? $base . '-' . str_pad((string) Numbering::next($editionId, 'loa-' . substr(md5($base), 0, 10)), 3, '0', STR_PAD_LEFT)
            : Numbering::format('CENIM{yy}/LOA/{seq}', $year, Numbering::next($editionId, 'loa'));

        Db::update('authors', [
            'loa_number'    => $number,
            'loa_issued_at' => now(),
        ], 'id = :target_id AND (loa_number IS NULL OR loa_number = \'\')', ['target_id' => (int) $paper['id']]);

        return AuthorRepo::find((int) $paper['id']) ?? $paper;
    }

    /** Isi surat dengan token diganti nilai paper ini. */
    public static function body(array $paper): string
    {
        $editionId = (int) $paper['edition_id'];
        $edition   = EditionRepo::find($editionId) ?? [];
        $template  = SettingRepo::getString($editionId, 'loa_body');

        $names = array_column(PaperAuthorRepo::forPaper((int) $paper['id']), 'name');
        $authors = count($names) > 1
            ? implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names)
            : (string) ($names[0] ?? $paper['first_author_name']);

        $start = (string) ($edition['conference_start'] ?? '');
        $end   = (string) ($edition['conference_end'] ?? '');
        $dates = $start === '' ? 'the scheduled conference dates'
            : ($end !== '' && $end !== $start
                ? fmt_date($start . ' 00:00:00') . ' - ' . fmt_date($end . ' 00:00:00')
                : fmt_date($start . ' 00:00:00'));

        return strtr($template !== '' ? $template : self::DEFAULT_BODY, [
            '{paper_id}'         => (string) $paper['paper_id'],
            '{paper_title}'      => (string) ($paper['paper_title'] ?? ''),
            '{authors}'          => $authors,
            '{track}'            => (string) ($paper['track'] ?? ''),
            '{edition_name}'     => (string) ($edition['name'] ?? cfg('app_name')),
            '{conference_dates}' => $dates,
        ]);
    }
}
