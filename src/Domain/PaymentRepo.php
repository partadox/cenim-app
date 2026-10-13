<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Audit;
use Cenim\Core\Db;

/**
 * Pembayaran (form 2). Satu invoice punya satu baris pembayaran aktif:
 * bila ditolak, author memperbaiki baris yang sama (riwayatnya tetap tercatat
 * di audit_logs), bukan menumpuk baris baru.
 *
 * Alur: submitted ──confirm──► confirmed  (invoice jadi paid + receipt terbit)
 *                    └──reject──► rejected ──author kirim ulang──► submitted
 */
final class PaymentRepo
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED  = 'rejected';

    public const STATUSES = [self::STATUS_SUBMITTED, self::STATUS_CONFIRMED, self::STATUS_REJECTED];

    public const METHODS = ['bank_transfer', 'other'];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM payments WHERE id = ?', [$id]);
    }

    public static function findForInvoice(int $invoiceId): ?array
    {
        return Db::one(
            'SELECT * FROM payments WHERE invoice_id = ? ORDER BY id DESC LIMIT 1',
            [$invoiceId]
        );
    }

    public static function findForAuthor(int $authorId): ?array
    {
        return Db::one(
            'SELECT p.* FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN registrations r ON r.id = i.registration_id
             WHERE r.author_id = ?
             ORDER BY p.id DESC LIMIT 1',
            [$authorId]
        );
    }

    /** Pembayaran + invoice + author, untuk halaman detail admin dan PDF. */
    public static function findWithContext(int $id): ?array
    {
        return Db::one(
            'SELECT p.*,
                    i.number AS invoice_number, i.total AS invoice_total, i.currency AS invoice_currency,
                    i.status AS invoice_status, i.issued_at AS invoice_issued_at, i.due_date,
                    r.id AS registration_id, r.participant_type,
                    a.id AS author_id, a.paper_id, a.paper_title, a.first_author_name, a.email,
                    a.affiliation, a.country, a.phone
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN registrations r ON r.id = i.registration_id
             JOIN authors a ON a.id = r.author_id
             WHERE p.id = ?',
            [$id]
        );
    }

    public static function isEditable(?array $payment): bool
    {
        if ($payment === null) {
            return true; // belum pernah mengirim
        }

        return (string) $payment['status'] === self::STATUS_REJECTED;
    }

    /**
     * Simpan pengajuan pembayaran (baru atau perbaikan setelah ditolak).
     *
     * @param array{
     *   method:string, amount_paid:float, currency:string, paid_at:?string,
     *   bank_name:?string, sender_name:?string, reference:?string, note:?string
     * } $data
     */
    public static function submit(int $editionId, int $invoiceId, array $data): int
    {
        $existing = self::findForInvoice($invoiceId);

        $row = [
            'method'        => in_array($data['method'], self::METHODS, true) ? $data['method'] : 'bank_transfer',
            'amount_paid'   => (float) $data['amount_paid'],
            'currency'      => (string) $data['currency'],
            'paid_at'       => $data['paid_at'],
            'bank_name'     => $data['bank_name'],
            'sender_name'   => $data['sender_name'],
            'reference'     => $data['reference'],
            'note'          => $data['note'],
            'extra_json'    => ($data['extra'] ?? []) === [] ? null : json_encode($data['extra'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status'        => self::STATUS_SUBMITTED,
            'reject_reason' => null,
            'submitted_at'  => now(),
            'updated_at'    => now(),
        ];

        if ($existing !== null && (string) $existing['status'] !== self::STATUS_CONFIRMED) {
            Db::update('payments', $row, 'id = :target_id', ['target_id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $row['edition_id'] = $editionId;
        $row['invoice_id'] = $invoiceId;
        $row['created_at'] = now();

        return Db::insert('payments', $row);
    }

    /** Kaitkan berkas bukti transfer ke baris pembayaran. */
    public static function attachProof(int $paymentId, int $fileId): void
    {
        Db::update('payments', [
            'proof_file_id' => $fileId,
            'updated_at'    => now(),
        ], 'id = :target_id', ['target_id' => $paymentId]);
    }

    /**
     * Konfirmasi pembayaran: invoice menjadi paid dan receipt diterbitkan.
     *
     * @return array{receipt:?array, error:?string}
     */
    public static function confirm(int $id, string $actor = 'admin'): array
    {
        $payment = self::findWithContext($id);

        if ($payment === null) {
            return ['receipt' => null, 'error' => 'Payment not found.'];
        }

        if ((string) $payment['status'] === self::STATUS_CONFIRMED) {
            return ['receipt' => ReceiptRepo::findForPayment($id), 'error' => null];
        }

        $receipt = Db::transaction(static function () use ($payment, $id, $actor): array {
            Db::update('payments', [
                'status'        => self::STATUS_CONFIRMED,
                'confirmed_at'  => now(),
                'confirmed_by'  => $actor,
                'reject_reason' => null,
                'updated_at'    => now(),
            ], 'id = :target_id', ['target_id' => $id]);

            InvoiceRepo::markPaid((int) $payment['invoice_id']);
            // Cap "PAID" harus ikut tercetak, jadi cache PDF invoice dibuang.
            InvoiceRepo::invalidatePdfCache((int) $payment['invoice_id']);

            return ReceiptRepo::issueForPayment($payment);
        });

        Audit::log('payment.confirmed', [
            'actor_type'  => 'admin',
            'actor_id'    => $actor,
            'target_type' => 'payment',
            'target_id'   => $id,
            'meta'        => [
                'invoice'  => $payment['invoice_number'],
                'receipt'  => $receipt['number'] ?? '',
                'amount'   => (float) $payment['amount_paid'],
                'currency' => (string) $payment['currency'],
            ],
        ]);

        return ['receipt' => $receipt, 'error' => null];
    }

    public static function reject(int $id, string $reason, string $actor = 'admin'): void
    {
        $payment = self::find($id);
        if ($payment === null) {
            return;
        }

        Db::update('payments', [
            'status'        => self::STATUS_REJECTED,
            'reject_reason' => $reason,
            'confirmed_at'  => null,
            'confirmed_by'  => $actor,
            'updated_at'    => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        Audit::log('payment.rejected', [
            'actor_type'  => 'admin',
            'actor_id'    => $actor,
            'target_type' => 'payment',
            'target_id'   => $id,
            'meta'        => ['reason' => $reason],
        ]);
    }

    /** @return array<string,int> */
    public static function countsByStatus(int $editionId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);

        foreach (Db::all(
            'SELECT status, COUNT(*) AS total FROM payments WHERE edition_id = ? GROUP BY status',
            [$editionId]
        ) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<string,float> nominal yang sudah dikonfirmasi per mata uang */
    public static function confirmedTotals(int $editionId): array
    {
        $totals = [];

        foreach (Db::all(
            'SELECT currency, SUM(amount_paid) AS amount FROM payments
             WHERE edition_id = ? AND status = ? GROUP BY currency',
            [$editionId, self::STATUS_CONFIRMED]
        ) as $row) {
            $totals[(string) $row['currency']] = (float) $row['amount'];
        }

        return $totals;
    }

    /** Selisih antara nominal yang dibayar dan nominal invoice. */
    public static function difference(array $payment): float
    {
        return round((float) $payment['amount_paid'] - (float) $payment['invoice_total'], 2);
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_CONFIRMED => 'cn-badge-paid',
            self::STATUS_REJECTED  => 'cn-badge-rejected',
            default                => 'cn-badge-submitted',
        };
    }

    public static function methodLabel(string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'Bank transfer',
            default         => 'Other',
        };
    }
}
