<?php
/**
 * Ekspor Excel semua peserta yang sudah mendaftar (registrasi bukan draft):
 * satu baris per orang — presenter, co-author non-presenter, dan tamu dari
 * keranjang Registration form. ?status=paid → hanya yang invoice-nya lunas.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Db;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Support\Xlsx;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$paidOnly  = input('status') === 'paid';
$shirtOn   = RegistrationRepo::shirtEnabled($editionId);

$registrations = Db::all(
    "SELECT r.id, r.participant_type, r.presentation_mode, r.presenter_shirt, r.status, r.submitted_at,
            a.paper_id, a.paper_title,
            pa.name AS presenter_name, pa.email AS presenter_email, pa.affiliation AS presenter_affiliation, pa.country AS presenter_country,
            fc.label AS fee_label,
            i.number AS invoice_number, i.status AS invoice_status
     FROM registrations r
     JOIN authors a ON a.id = r.author_id
     LEFT JOIN paper_authors pa ON pa.id = r.presenter_id
     LEFT JOIN fee_categories fc ON fc.id = r.fee_category_id
     LEFT JOIN invoices i ON i.registration_id = r.id AND i.status != '" . InvoiceRepo::STATUS_VOID . "'
     WHERE r.edition_id = ? AND r.status != '" . RegistrationRepo::STATUS_DRAFT . "'"
        . ($paidOnly ? " AND i.status = '" . InvoiceRepo::STATUS_PAID . "'" : '') . '
     ORDER BY a.paper_id',
    [$editionId]
);

$headers = ['No', 'Paper ID', 'Paper title', 'Role', 'Name', 'Email', 'Affiliation', 'Country', 'Presentation', 'Registration category'];
if ($shirtOn) {
    $headers[] = 'Shirt size';
}
array_push($headers, 'Registration status', 'Invoice No.', 'Payment', 'Submitted at');

$rows = [];
foreach ($registrations as $reg) {
    $mode    = (string) ($reg['presentation_mode'] ?: 'offline');
    $isPres  = (string) $reg['participant_type'] !== 'non_presenter';
    $people  = [[
        $isPres ? 'Presenter' : 'Attendee (non-presenter)',
        (string) ($reg['presenter_name'] ?? ''),
        (string) ($reg['presenter_email'] ?? ''),
        (string) ($reg['presenter_affiliation'] ?? ''),
        (string) ($reg['presenter_country'] ?? ''),
        (string) ($reg['presenter_shirt'] ?? ''),
    ]];

    foreach (Db::all(
        'SELECT ra.*, pa.country FROM registration_attendees ra
         LEFT JOIN paper_authors pa ON pa.id = ra.paper_author_id
         WHERE ra.registration_id = ? ORDER BY ra.sort, ra.id',
        [(int) $reg['id']]
    ) as $guest) {
        $people[] = [
            $guest['paper_author_id'] !== null ? 'Co-author (non-presenter)' : 'Guest (non-presenter)',
            (string) $guest['name'],
            (string) ($guest['email'] ?? ''),
            (string) ($guest['affiliation'] ?? ''),
            (string) ($guest['country'] ?? ''),
            (string) ($guest['shirt_size'] ?? ''),
        ];
    }

    foreach ($people as [$role, $name, $email, $affiliation, $country, $shirt]) {
        $line = [
            count($rows) + 1,
            (string) $reg['paper_id'],
            (string) ($reg['paper_title'] ?? ''),
            $role,
            $name,
            $email,
            $affiliation,
            $country,
            ucfirst($mode),
            (string) ($reg['fee_label'] ?? ''),
        ];
        if ($shirtOn) {
            $line[] = $shirt;
        }
        array_push(
            $line,
            ucfirst((string) $reg['status']),
            (string) ($reg['invoice_number'] ?? ''),
            match ((string) ($reg['invoice_status'] ?? '')) {
                InvoiceRepo::STATUS_PAID   => 'Paid',
                InvoiceRepo::STATUS_UNPAID => 'Unpaid',
                default                    => 'No invoice yet',
            },
            ($reg['submitted_at'] ?? '') !== '' ? fmt_dt((string) $reg['submitted_at']) : ''
        );
        $rows[] = $line;
    }
}

Audit::log('registrations.exported', ['meta' => ['people' => count($rows), 'paid_only' => $paidOnly]]);

Xlsx::download(
    'participants-' . ($edition['slug'] ?? 'cenim') . ($paidOnly ? '-paid' : '') . '-' . gmdate('Ymd') . '.xlsx',
    'Participants',
    $headers,
    $rows
);
