<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;
use Cenim\Core\Numbering;

/**
 * Receipt (kuitansi resmi) — diterbitkan otomatis saat pembayaran dikonfirmasi.
 * Nomornya memakai urutan terpisah dari invoice (CENIM26/RCP/0001).
 */
final class ReceiptRepo
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM receipts WHERE id = ?', [$id]);
    }

    public static function findForPayment(int $paymentId): ?array
    {
        return Db::one('SELECT * FROM receipts WHERE payment_id = ? ORDER BY id DESC LIMIT 1', [$paymentId]);
    }

    public static function findForInvoice(int $invoiceId): ?array
    {
        return Db::one('SELECT * FROM receipts WHERE invoice_id = ? ORDER BY id DESC LIMIT 1', [$invoiceId]);
    }

    public static function findForAuthor(int $authorId): ?array
    {
        return Db::one(
            'SELECT rc.* FROM receipts rc
             JOIN invoices i ON i.id = rc.invoice_id
             JOIN registrations r ON r.id = i.registration_id
             WHERE r.author_id = ?
             ORDER BY rc.id DESC LIMIT 1',
            [$authorId]
        );
    }

    /** Receipt + pembayaran + invoice + author, untuk PDF dan halaman detail. */
    public static function findWithContext(int $id): ?array
    {
        return Db::one(
            'SELECT rc.*,
                    p.method, p.amount_paid, p.paid_at, p.bank_name, p.sender_name, p.reference,
                    p.confirmed_at, p.note AS payment_note,
                    i.number AS invoice_number, i.total AS invoice_total, i.currency AS invoice_currency,
                    r.id AS registration_id, r.participant_type,
                    a.id AS author_id, a.paper_id, a.paper_title, a.first_author_name, a.email,
                    a.affiliation, a.country
             FROM receipts rc
             JOIN payments p ON p.id = rc.payment_id
             JOIN invoices i ON i.id = rc.invoice_id
             JOIN registrations r ON r.id = i.registration_id
             JOIN authors a ON a.id = r.author_id
             WHERE rc.id = ?',
            [$id]
        );
    }

    /**
     * Terbitkan receipt untuk satu pembayaran yang sudah dikonfirmasi.
     * Idempoten: bila sudah ada, baris yang lama dikembalikan.
     *
     * @param array<string,mixed> $payment hasil PaymentRepo::findWithContext()
     */
    public static function issueForPayment(array $payment): array
    {
        $existing = self::findForPayment((int) $payment['id']);
        if ($existing !== null) {
            return $existing;
        }

        $editionId = (int) $payment['edition_id'];
        $year      = (int) (EditionRepo::find($editionId)['year'] ?? date('Y'));

        $id = Db::insert('receipts', [
            'edition_id' => $editionId,
            'invoice_id' => (int) $payment['invoice_id'],
            'payment_id' => (int) $payment['id'],
            'number'     => Numbering::receipt($editionId, $year),
            'issued_at'  => now(),
            'amount'     => (float) $payment['amount_paid'],
            'currency'   => (string) $payment['currency'],
            'created_at' => now(),
        ]);

        return self::find($id) ?? [];
    }

    public static function invalidatePdfCache(int $id): void
    {
        Db::update('receipts', ['pdf_cache' => null], 'id = :target_id', ['target_id' => $id]);
    }

    public static function countByEdition(int $editionId): int
    {
        return Db::count('receipts', 'edition_id = ?', [$editionId]);
    }

    /** @return array<string,float> */
    public static function totals(int $editionId): array
    {
        $totals = [];

        foreach (Db::all(
            'SELECT currency, SUM(amount) AS amount FROM receipts WHERE edition_id = ? GROUP BY currency',
            [$editionId]
        ) as $row) {
            $totals[(string) $row['currency']] = (float) $row['amount'];
        }

        return $totals;
    }
}
