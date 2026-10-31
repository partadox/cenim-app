<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Audit;
use Cenim\Core\Db;
use Cenim\Core\Mailer;
use Cenim\Core\Numbering;

/**
 * Invoice. Diterbitkan setelah registrasi diverifikasi, dan nominalnya
 * di-SNAPSHOT saat penerbitan: perubahan tarif setelahnya tidak mengubah
 * invoice yang sudah keluar.
 */
final class InvoiceRepo
{
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID   = 'paid';
    public const STATUS_VOID   = 'void';

    public const STATUSES = [self::STATUS_UNPAID, self::STATUS_PAID, self::STATUS_VOID];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM invoices WHERE id = ?', [$id]);
    }

    /** Invoice + data registrasi & author, untuk PDF dan halaman detail. */
    public static function findWithContext(int $id): ?array
    {
        return Db::one(
            'SELECT i.*,
                    r.id AS registration_id, r.participant_type, r.is_student, r.is_ieee_member,
                    r.ieee_number, r.extra_pages, r.extra_papers, r.status AS registration_status,
                    r.presentation_mode, (SELECT pp.name FROM paper_authors pp WHERE pp.id = r.presenter_id) AS presenter_name,
                    a.id AS author_id, a.paper_id, a.paper_title, a.first_author_name, a.email,
                    a.affiliation, a.country, a.phone, ' . AuthorRepo::pagesSql() . ' AS total_pages,
                    fc.label AS fee_label, pk.name AS package_name
             FROM invoices i
             JOIN registrations r ON r.id = i.registration_id
             JOIN authors a ON a.id = r.author_id
             LEFT JOIN fee_categories fc ON fc.id = r.fee_category_id
             LEFT JOIN packages pk ON pk.id = r.package_id
             WHERE i.id = ?',
            [$id]
        );
    }

    /** Invoice aktif (belum void) milik satu registrasi. */
    public static function findForRegistration(int $registrationId, bool $includeVoid = false): ?array
    {
        $sql = 'SELECT * FROM invoices WHERE registration_id = ?';
        if (!$includeVoid) {
            $sql .= " AND status != '" . self::STATUS_VOID . "'";
        }

        return Db::one($sql . ' ORDER BY id DESC LIMIT 1', [$registrationId]);
    }

    public static function findForAuthor(int $authorId): ?array
    {
        return Db::one(
            "SELECT i.* FROM invoices i
             JOIN registrations r ON r.id = i.registration_id
             WHERE r.author_id = ? AND i.status != '" . self::STATUS_VOID . "'
             ORDER BY i.id DESC LIMIT 1",
            [$authorId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $invoiceId): array
    {
        return Db::all(
            'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort, id',
            [$invoiceId]
        );
    }

    public static function dueDate(int $editionId, string $issuedAtUtc): ?string
    {
        $days = (int) SettingRepo::get($editionId, 'invoice_due_days', 14);
        if ($days <= 0) {
            return null;
        }

        $issued = strtotime($issuedAtUtc . ' UTC');
        return $issued === false ? null : gmdate('Y-m-d', $issued + $days * 86400);
    }

    /**
     * Terbitkan invoice untuk satu registrasi.
     *
     * @return array{invoice:?array, created:bool, error:?string}
     */
    public static function issueForRegistration(int $registrationId, string $actor = 'admin', bool $notify = true): array
    {
        $registration = RegistrationRepo::findWithAuthor($registrationId);

        if ($registration === null) {
            return ['invoice' => null, 'created' => false, 'error' => 'Registration not found.'];
        }

        if ((string) $registration['status'] !== RegistrationRepo::STATUS_VERIFIED) {
            return [
                'invoice' => null,
                'created' => false,
                'error'   => 'The registration must be verified before an invoice can be issued.',
            ];
        }

        $existing = self::findForRegistration($registrationId);
        if ($existing !== null) {
            return ['invoice' => $existing, 'created' => false, 'error' => null];
        }

        $editionId = (int) $registration['edition_id'];

        $quote = FeeEngine::quote($editionId, RegistrationRepo::quoteInput($registration, [
            'country'     => $registration['author_country'] ?? null,
            'total_pages' => (int) ($registration['total_pages'] ?? 0),
        ]));

        if ($quote['error'] !== null) {
            return ['invoice' => null, 'created' => false, 'error' => (string) $quote['error']];
        }

        if ($quote['items'] === []) {
            return ['invoice' => null, 'created' => false, 'error' => 'The fee breakdown is empty, nothing to invoice.'];
        }

        $year      = (int) (EditionRepo::find($editionId)['year'] ?? date('Y'));
        $issuedAt  = now();

        $invoiceId = Db::transaction(static function () use ($registration, $editionId, $year, $issuedAt, $quote): int {
            $number = Numbering::invoice($editionId, $year);

            $id = Db::insert('invoices', [
                'edition_id'      => $editionId,
                'registration_id' => (int) $registration['id'],
                'number'          => $number,
                'issued_at'       => $issuedAt,
                'due_date'        => self::dueDate($editionId, $issuedAt),
                'currency'        => (string) $quote['currency'],
                'subtotal'        => (float) $quote['subtotal'],
                'discount'        => (float) $quote['discount'],
                'total'           => (float) $quote['total'],
                'status'          => self::STATUS_UNPAID,
                'note'            => SettingRepo::getString($editionId, 'invoice_note'),
                'created_at'      => $issuedAt,
            ]);

            foreach ($quote['items'] as $index => $item) {
                Db::insert('invoice_items', [
                    'invoice_id'  => $id,
                    'description' => (string) $item['description'],
                    'qty'         => (float) $item['qty'],
                    'unit_price'  => (float) $item['unit_price'],
                    'amount'      => (float) $item['amount'],
                    'sort'        => ($index + 1) * 10,
                ]);
            }

            // Simpan juga nominal final ke registrasi agar konsisten dengan invoice.
            Db::update('registrations', [
                'currency'   => (string) $quote['currency'],
                'subtotal'   => (float) $quote['subtotal'],
                'total'      => (float) $quote['total'],
                'updated_at' => now(),
            ], 'id = :target_id', ['target_id' => (int) $registration['id']]);

            return $id;
        });

        $invoice = self::find($invoiceId);

        if ($notify) {
            self::notify($invoice ?? [], $registration);
        }

        Audit::log('invoice.issued', [
            'actor_type'  => $actor === 'system' ? 'system' : 'admin',
            'actor_id'    => $actor,
            'target_type' => 'invoice',
            'target_id'   => $invoiceId,
            'meta'        => [
                'number'   => $invoice['number'] ?? '',
                'paper_id' => $registration['paper_id'],
                'total'    => $quote['total'],
                'currency' => $quote['currency'],
            ],
        ]);

        return ['invoice' => $invoice, 'created' => true, 'error' => null];
    }

    /**
     * Kirim email pemberitahuan invoice ke author. Kegagalan email dicatat di
     * mail_log dan tidak membatalkan penerbitan invoice.
     */
    public static function notify(array $invoice, array $registration): void
    {
        if (($invoice['number'] ?? '') === '' || ($registration['email'] ?? '') === '') {
            return;
        }

        $edition = EditionRepo::find((int) $invoice['edition_id']);

        Mailer::send(
            (string) $registration['email'],
            (string) $registration['first_author_name'],
            'Invoice ' . $invoice['number'] . ' — ' . ($edition['name'] ?? cfg('app_name')),
            'invoice_issued',
            [
                'paperId'  => (string) $registration['paper_id'],
                'number'   => (string) $invoice['number'],
                'amount'   => fmt_money((float) $invoice['total'], (string) $invoice['currency']),
                'dueDate'  => $invoice['due_date'] !== null
                    ? fmt_date((string) $invoice['due_date'] . ' 00:00:00')
                    : 'as soon as possible',
                'loginUrl' => full_url(author_url('login.php')),
            ]
        );
    }

    public static function void(int $id, string $reason, string $actor = 'admin'): void
    {
        $invoice = self::find($id);
        if ($invoice === null) {
            return;
        }

        Db::update('invoices', [
            'status'     => self::STATUS_VOID,
            'note'       => trim(((string) ($invoice['note'] ?? '')) . "\n[VOID] " . $reason),
            'updated_at' => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        Audit::log('invoice.voided', [
            'actor_type'  => 'admin',
            'actor_id'    => $actor,
            'target_type' => 'invoice',
            'target_id'   => $id,
            'meta'        => ['number' => $invoice['number'], 'reason' => $reason],
        ]);
    }

    /**
     * Gratiskan biaya registrasi (mis. paper dari host): registrasi diverifikasi,
     * invoice diterbitkan bila belum ada (tanpa email tagihan), lalu dicatat
     * pembayaran "Fee waived" bernominal 0 yang langsung dikonfirmasi → invoice
     * paid + receipt 0. Tidak dihitung pendapatan (totals() memisahkannya).
     *
     * @return array{invoice:?array, receipt:?array, error:?string}
     */
    public static function waive(int $registrationId, string $note, string $actor = 'admin'): array
    {
        $registration = RegistrationRepo::find($registrationId);
        if ($registration === null) {
            return ['invoice' => null, 'receipt' => null, 'error' => 'Registration not found.'];
        }
        if ((string) $registration['status'] === RegistrationRepo::STATUS_DRAFT) {
            return ['invoice' => null, 'receipt' => null, 'error' => 'The registration has not been submitted yet.'];
        }

        $existing = self::findForRegistration($registrationId);
        if ($existing !== null && (string) $existing['status'] === self::STATUS_PAID) {
            return ['invoice' => $existing, 'receipt' => null, 'error' => 'Invoice ' . $existing['number'] . ' is already paid.'];
        }

        if ((string) $registration['status'] !== RegistrationRepo::STATUS_VERIFIED) {
            RegistrationRepo::verify($registrationId, $actor);
        }

        $issued = self::issueForRegistration($registrationId, $actor, false);
        if ($issued['error'] !== null || $issued['invoice'] === null) {
            return ['invoice' => null, 'receipt' => null, 'error' => (string) ($issued['error'] ?? 'The invoice could not be issued.')];
        }
        $invoice = $issued['invoice'];
        $note    = trim($note) !== '' ? mb_substr(trim($note), 0, 500) : 'Registration fee waived by the committee.';

        $paymentId = PaymentRepo::submit((int) $invoice['edition_id'], (int) $invoice['id'], [
            'method'      => 'waived',
            'amount_paid' => 0.0,
            'currency'    => (string) $invoice['currency'],
            'paid_at'     => gmdate('Y-m-d'),
            'bank_name'   => null,
            'sender_name' => null,
            'reference'   => null,
            'note'        => $note,
        ]);
        Db::update('invoices', ['waived_at' => now(), 'waived_note' => $note, 'updated_at' => now()], 'id = :target_id', ['target_id' => (int) $invoice['id']]);

        $confirmed = PaymentRepo::confirm($paymentId, $actor);

        Audit::log('invoice.waived', [
            'actor_type'  => 'admin',
            'actor_id'    => $actor,
            'target_type' => 'invoice',
            'target_id'   => (int) $invoice['id'],
            'meta'        => ['number' => $invoice['number'], 'total' => (float) $invoice['total'], 'currency' => $invoice['currency'], 'note' => $note],
        ]);

        return ['invoice' => self::find((int) $invoice['id']), 'receipt' => $confirmed['receipt'], 'error' => $confirmed['error']];
    }

    public static function isWaived(?array $invoice): bool
    {
        return ($invoice['waived_at'] ?? null) !== null;
    }

    /** Dipakai Fase 5 saat pembayaran dikonfirmasi. */
    public static function markPaid(int $id): void
    {
        Db::update('invoices', [
            'status'     => self::STATUS_PAID,
            'updated_at' => now(),
        ], 'id = :target_id', ['target_id' => $id]);
    }

    public static function invalidatePdfCache(int $id): void
    {
        Db::update('invoices', ['pdf_cache' => null], 'id = :target_id', ['target_id' => $id]);
    }

    /** @return array<string,int> */
    public static function countsByStatus(int $editionId): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);

        foreach (Db::all(
            'SELECT status, COUNT(*) AS total FROM invoices WHERE edition_id = ? GROUP BY status',
            [$editionId]
        ) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return array<string,array<string,float>> currency => [status => amount] */
    public static function totals(int $editionId): array
    {
        $totals = [];

        foreach (Db::all(
            // Invoice waived dipisah ("waived"): lunas tetapi bukan pendapatan.
            "SELECT currency, CASE WHEN waived_at IS NOT NULL THEN 'waived' ELSE status END AS status, SUM(total) AS amount
             FROM invoices WHERE edition_id = ? GROUP BY currency, CASE WHEN waived_at IS NOT NULL THEN 'waived' ELSE status END",

            [$editionId]
        ) as $row) {
            $totals[(string) $row['currency']][(string) $row['status']] = (float) $row['amount'];
        }

        return $totals;
    }

    /** Registrasi yang sudah terverifikasi tapi belum punya invoice. */
    public static function pendingIssue(int $editionId): array
    {
        return Db::all(
            "SELECT r.id, a.paper_id, a.first_author_name, r.currency, r.total
             FROM registrations r
             JOIN authors a ON a.id = r.author_id
             WHERE r.edition_id = ? AND r.status = ?
               AND NOT EXISTS (
                   SELECT 1 FROM invoices i
                   WHERE i.registration_id = r.id AND i.status != '" . self::STATUS_VOID . "'
               )
             ORDER BY r.verified_at",
            [$editionId, RegistrationRepo::STATUS_VERIFIED]
        );
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'cn-badge-paid',
            self::STATUS_VOID => 'cn-badge-rejected',
            default           => 'cn-badge-unpaid',
        };
    }
}
