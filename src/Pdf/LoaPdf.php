<?php

declare(strict_types=1);

namespace Cenim\Pdf;

use Cenim\Domain\BrandingRepo;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\LoaRepo;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\SettingRepo;

/**
 * PDF Letter of Acceptance ber-kop untuk satu paper (semua author tercantum).
 * Tidak di-cache: isinya kecil dan setting/nama author bisa berubah.
 */
final class LoaPdf extends PdfBase
{
    public static function render(array $paper): string
    {
        $paper     = LoaRepo::ensureNumber($paper);
        $editionId = (int) $paper['edition_id'];
        $edition   = EditionRepo::find($editionId) ?? [];
        $authors   = PaperAuthorRepo::forPaper((int) $paper['id']);

        $pdf = new self($edition);
        $pdf->SetTitle('Letter of Acceptance ' . $paper['paper_id']);
        $pdf->SetSubject('Letter of Acceptance');
        $pdf->AddPage();

        // ── Judul + nomor ───────────────────────────────────────────────────
        $pdf->SetFont('dejavu', 'B', 16);
        $pdf->SetTextColor(...self::NAVY);
        $pdf->Cell(0, 9, 'LETTER OF ACCEPTANCE', 0, 1, 'C');
        $pdf->SetFont('dejavu', '', 9.5);
        $pdf->SetTextColor(...self::GREY);
        $pdf->Cell(0, 5, 'No. ' . $paper['loa_number'], 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('dejavu', '', 10);
        $city = SettingRepo::getString($editionId, 'loa_city', LoaRepo::DEFAULT_CITY);
        $pdf->Cell(0, 5, ($city !== '' ? $city . ', ' : '') . fmt_date((string) ($paper['loa_issued_at'] ?? now())), 0, 1, 'R');
        $pdf->Ln(2);

        // ── Penerima: semua author ──────────────────────────────────────────
        $pdf->SetFont('dejavu', '', 10);
        $pdf->Cell(0, 5.5, 'To:', 0, 1);
        foreach ($authors as $author) {
            $line = $author['name'] . (($author['affiliation'] ?? '') !== '' ? ' - ' . $author['affiliation'] : '');
            $pdf->SetX($pdf->GetX() + 4);
            $pdf->MultiCell(0, 5.2, $author['position'] . '. ' . $line, 0, 'L');
        }
        $pdf->Ln(3);

        $pdf->Cell(0, 5.5, 'Dear Author(s),', 0, 1);
        $pdf->Ln(1);

        // ── Isi surat (Settings → LoA text, dengan token) ───────────────────
        $pdf->SetFont('dejavu', '', 10.5);
        $pdf->MultiCell(0, 5.6, LoaRepo::body($paper), 0, 'J');
        $pdf->Ln(4);

        $pdf->detailTable('Paper details', array_filter([
            'Paper ID' => (string) $paper['paper_id'],
            'Title'    => (string) ($paper['paper_title'] ?? ''),
            'Track'    => (string) ($paper['track'] ?? ''),
            'Status'   => 'Accepted',
        ], static fn (string $v): bool => $v !== ''));

        $chair = SettingRepo::chair($editionId);
        $pdf->signatureBlock($chair['name'], $chair['title'], BrandingRepo::signaturePath($editionId, 'chair'), 'Sincerely,', self::stampFor($editionId));

        return (string) $pdf->Output('S');
    }

    public static function filename(array $paper): string
    {
        return 'LoA-' . self::safeName((string) $paper['paper_id']) . '.pdf';
    }
}
