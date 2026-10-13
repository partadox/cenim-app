<?php

declare(strict_types=1);

namespace Cenim\Pdf;

use Cenim\Core\Db;
use Cenim\Domain\ReceiptRepo;
use Cenim\Domain\SettingRepo;
use Cenim\Storage\StorageManager;

/**
 * PDF receipt (kuitansi resmi) untuk pembayaran yang sudah dikonfirmasi.
 */
final class ReceiptPdf extends PdfBase
{
    public static function file(int $receiptId, bool $refresh = false): string
    {
        $receipt = ReceiptRepo::findWithContext($receiptId);

        if ($receipt === null) {
            throw new \RuntimeException('Receipt tidak ditemukan.');
        }

        $relative = 'generated/receipts/' . self::safeName((string) $receipt['number']) . '.pdf';
        $absolute = storage_path($relative);

        if (!$refresh && ($receipt['pdf_cache'] ?? null) !== null && is_file($absolute)) {
            return $absolute;
        }

        self::cache($relative, self::render($receipt));

        Db::update('receipts', ['pdf_cache' => $relative], 'id = :target_id', ['target_id' => $receiptId]);

        return $absolute;
    }

    public static function render(array $receipt): string
    {
        $editionId = (int) $receipt['edition_id'];
        $currency  = (string) $receipt['currency'];

        $pdf = new self();
        $pdf->SetTitle('Receipt ' . $receipt['number']);
        $pdf->SetSubject('Payment receipt');
        $pdf->AddPage();

        $pdf->documentHeader('RECEIPT', [
            'Receipt No.' => (string) $receipt['number'],
            'Issued'      => fmt_date((string) $receipt['issued_at']),
            'Invoice No.' => (string) $receipt['invoice_number'],
            'Currency'    => $currency,
        ], ['text' => 'PAID', 'color' => [18, 112, 74]]);

        $pdf->Ln(2);

        $pdf->twoColumns(
            [
                'title' => 'Received from',
                'lines' => [
                    (string) $receipt['first_author_name'],
                    (string) ($receipt['affiliation'] ?? ''),
                    (string) ($receipt['country'] ?? ''),
                    (string) $receipt['email'],
                ],
            ],
            [
                'title' => 'For',
                'lines' => [
                    'Paper ' . $receipt['paper_id'],
                    (string) ($receipt['paper_title'] ?? ''),
                    'Registration fee — ' . ($receipt['participant_type'] === 'non_presenter' ? 'Non-presenter' : 'Presenter'),
                ],
            ]
        );

        $pdf->Ln(3);

        // ── Nominal diterima ────────────────────────────────────────────────
        $pdf->amountReceived((float) $receipt['amount'], $currency);

        $pdf->Ln(3);

        // ── Rincian pembayaran ──────────────────────────────────────────────
        $paidAt = $receipt['paid_at'] !== null && (string) $receipt['paid_at'] !== ''
            ? fmt_date((string) $receipt['paid_at'] . (strlen((string) $receipt['paid_at']) <= 10 ? ' 00:00:00' : ''))
            : '—';

        $rows = [
            'Payment method'     => \Cenim\Domain\PaymentRepo::methodLabel((string) $receipt['method']),
            'Transfer date'      => $paidAt,
            'Sender name'        => (string) ($receipt['sender_name'] ?? '—'),
            'Sender bank'        => (string) ($receipt['bank_name'] ?? '—'),
            'Reference'          => (string) ($receipt['reference'] ?? '—'),
            'Confirmed by'       => 'Conference secretariat, ' . fmt_dt((string) $receipt['confirmed_at'], 'd M Y'),
            'Invoice amount'     => fmt_money((float) $receipt['invoice_total'], (string) $receipt['invoice_currency']),
        ];

        $pdf->detailTable('Payment details', $rows);

        $pdf->Ln(2);
        $pdf->noteBlock('Notes', SettingRepo::getString($editionId, 'receipt_note'));

        // ── Tanda tangan ────────────────────────────────────────────────────
        $pdf->signatureBlock(
            SettingRepo::getString($editionId, 'signatory_name'),
            SettingRepo::getString($editionId, 'signatory_title'),
            self::signaturePath($editionId)
        );

        return (string) $pdf->Output('S');
    }

    /** Gambar tanda tangan dari tabel media (kind = signature), bila ada. */
    public static function signaturePath(int $editionId): ?string
    {
        if (!Db::tableExists('media')) {
            return null;
        }

        $row = Db::one(
            "SELECT f.* FROM media m
             JOIN files f ON f.id = m.file_id
             WHERE m.edition_id = ? AND m.kind = 'signature' AND f.deleted_at IS NULL
             ORDER BY m.id DESC LIMIT 1",
            [$editionId]
        );

        if ($row === null) {
            return null;
        }

        $path = StorageManager::forFile($row)->localPath($row);

        return $path !== null && is_file($path) ? $path : null;
    }
}
