<?php

declare(strict_types=1);

namespace Cenim\Pdf;

use Cenim\Core\Db;
use Cenim\Domain\BrandingRepo;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\SettingRepo;

/**
 * PDF invoice. Dibuat dari snapshot invoice + invoice_items, bukan dari
 * hitung ulang — supaya dokumen yang sudah dikirim tidak berubah nominalnya.
 */
final class InvoicePdf extends PdfBase
{
    /** Hasilkan (dan cache) PDF satu invoice; kembalikan path absolutnya. */
    public static function file(int $invoiceId, bool $refresh = false): string
    {
        $invoice = InvoiceRepo::findWithContext($invoiceId);

        if ($invoice === null) {
            throw new \RuntimeException('Invoice tidak ditemukan.');
        }

        $relative = 'generated/invoices/' . self::safeName((string) $invoice['number']) . '.pdf';
        $absolute = storage_path($relative);

        if (!$refresh && ($invoice['pdf_cache'] ?? null) !== null && is_file($absolute)
            && filemtime($absolute) >= self::layoutTime()) {
            return $absolute;
        }

        self::cache($relative, self::render($invoice));

        Db::update('invoices', ['pdf_cache' => $relative], 'id = :target_id', ['target_id' => $invoiceId]);

        return $absolute;
    }

    /** Bangun PDF sebagai string biner. */
    public static function render(array $invoice): string
    {
        $editionId = (int) $invoice['edition_id'];
        $currency  = (string) $invoice['currency'];
        $items     = InvoiceRepo::items((int) $invoice['id']);

        $pdf = new self();
        $pdf->SetTitle('Invoice ' . $invoice['number']);
        $pdf->SetSubject('Registration invoice');
        $pdf->AddPage();

        // ── Judul + metadata ────────────────────────────────────────────────
        $stamp = match ((string) $invoice['status']) {
            InvoiceRepo::STATUS_PAID => ['text' => 'PAID', 'color' => [18, 112, 74]],
            InvoiceRepo::STATUS_VOID => ['text' => 'VOID', 'color' => [163, 35, 31]],
            default                  => null,
        };

        $pdf->documentHeader('INVOICE', [
            'Invoice No.' => (string) $invoice['number'],
            'Issued'      => fmt_date((string) $invoice['issued_at']),
            'Due date'    => $invoice['due_date'] !== null ? fmt_date((string) $invoice['due_date'] . ' 00:00:00') : '-',
            'Currency'    => $currency,
        ], $stamp);

        $pdf->Ln(2);

        // ── Ditagihkan kepada / detail paper ────────────────────────────────
        $participant = $invoice['participant_type'] === 'non_presenter' ? 'Non-presenter' : 'Presenter';

        $category = (string) ($invoice['package_name'] ?? '') !== ''
            ? (string) $invoice['package_name']
            : (string) ($invoice['fee_label'] ?? '-');

        $pdf->twoColumns(
            [
                'title' => 'Billed to',
                'lines' => [
                    (string) $invoice['first_author_name'],
                    (string) ($invoice['affiliation'] ?? ''),
                    (string) ($invoice['country'] ?? ''),
                    (string) $invoice['email'],
                    (string) ($invoice['phone'] ?? ''),
                ],
            ],
            [
                'title' => 'Registration',
                'lines' => [
                    ...(str_starts_with((string) $invoice['paper_id'], \Cenim\Domain\ParticipantRepo::PREFIX)
                        ? ['Participant ' . $invoice['paper_id'], 'Attendance at the venue (non-presenter)']
                        : ['Paper ' . $invoice['paper_id'], (string) ($invoice['paper_title'] ?? '')]),
                    ($invoice['presenter_name'] ?? '') !== '' && (string) $invoice['participant_type'] !== 'non_presenter'
                        ? 'Presenter: ' . $invoice['presenter_name'] . ' (' . ($invoice['presentation_mode'] ?? 'offline') . ')'
                        : $participant,
                    $category,
                    (int) $invoice['is_ieee_member'] === 1 && ($invoice['ieee_number'] ?? '') !== ''
                        ? 'IEEE membership: ' . $invoice['ieee_number']
                        : '',
                ],
            ]
        );

        $pdf->Ln(3);

        // ── Rincian ─────────────────────────────────────────────────────────
        $pdf->itemsTable($items, $currency);
        $pdf->Ln(2);

        $totals = [];
        if ((float) $invoice['discount'] > 0) {
            $totals[] = ['Subtotal', fmt_money((float) $invoice['subtotal'], $currency)];
            $totals[] = ['Discount', '- ' . fmt_money((float) $invoice['discount'], $currency)];
        }
        $totals[] = ['TOTAL DUE', fmt_money((float) $invoice['total'], $currency), true];

        $pdf->totalsBlock($totals);
        $pdf->Ln(4);

        // ── Instruksi pembayaran ────────────────────────────────────────────
        $pdf->noteBlock('Payment instructions', self::bankInstructions($editionId, $currency));

        $note = trim((string) ($invoice['note'] ?? ''));
        if ($note === '') {
            $note = SettingRepo::getString($editionId, 'invoice_note');
        }
        $pdf->noteBlock('Terms', $note);

        $contact = SettingRepo::getString($editionId, 'contact_email');
        $pdf->noteBlock(
            'After paying',
            'Any author of this paper can sign in to the author area with their email (a 6-digit code is sent to it), then submit the proof of '
            . 'payment so the committee can confirm it and issue your official receipt.'
            . ($contact !== '' ? "\nQuestions: " . $contact : '')
        );

        $treasurer = SettingRepo::treasurer($editionId);
        $pdf->signatureBlock($treasurer['name'], $treasurer['title'], BrandingRepo::signaturePath($editionId, 'treasurer'), 'Conference secretariat', self::stampFor($editionId));

        return (string) $pdf->Output('S');
    }

    /** Susun teks rekening dari setting bank_accounts untuk mata uang invoice. */
    private static function bankInstructions(int $editionId, string $currency): string
    {
        $accounts = SettingRepo::getArray($editionId, 'bank_accounts', []);
        $lines    = [];

        foreach ($accounts as $account) {
            if (!is_array($account)) {
                continue;
            }
            if (($account['currency'] ?? '') !== $currency) {
                continue;
            }

            $fields = [
                'Bank'           => (string) ($account['bank_name'] ?? ''),
                'Account name'   => (string) ($account['account_name'] ?? ''),
                'Account number' => (string) ($account['account_no'] ?? ''),
                'SWIFT / BIC'    => (string) ($account['swift'] ?? ''),
                'Branch'         => (string) ($account['branch'] ?? ''),
            ];

            foreach ($fields as $label => $value) {
                if (trim($value) !== '') {
                    $lines[] = $label . ': ' . $value;
                }
            }
        }

        if ($lines === []) {
            return 'Payment details for ' . $currency . ' will be announced by the conference secretariat. '
                . 'Please contact us before transferring.';
        }

        $lines[] = 'Please transfer the full amount in ' . $currency
            . ' and state the invoice number in the transfer description. '
            . 'Any bank charges or transfer fees are borne by the registrant.';

        return implode("\n", $lines);
    }
}
