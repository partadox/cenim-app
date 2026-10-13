<?php
/**
 * Admin: pratinjau kop PDF (LoA, invoice, receipt) dengan logo & teks saat ini.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Pdf\PdfBase;

$pdf = new PdfBase(current_edition());
$pdf->SetTitle('Letterhead preview');
$pdf->AddPage();

$pdf->SetFont('dejavu', 'B', 16);
$pdf->SetTextColor(...PdfBase::NAVY);
$pdf->Cell(0, 9, 'DOCUMENT TITLE', 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('dejavu', '', 10);
$pdf->Ln(4);
$pdf->MultiCell(0, 5.6, 'This page previews the letterhead used on the Letter of Acceptance, invoices, and receipts. '
    . 'Change the logos (order, side, height) and the texts on the Letterhead page, then reload this preview.', 0, 'J');

Response::bytes((string) $pdf->Output('S'), 'letterhead-preview.pdf');
