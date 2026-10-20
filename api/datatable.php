<?php
/**
 * Endpoint DataTables server-side untuk panel admin.
 *
 * POST /cenim-app/api/datatable.php  { table: 'authors', ...params DataTables }
 *
 * Setiap tabel punya spec sendiri di bawah — daftar kolom yang boleh diurutkan
 * dan dicari bersifat whitelist, tidak diambil dari request.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AdminAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Domain\EditionRepo;
use Cenim\Support\DataTable;

panel_boot('admin');
AdminAuth::requireLogin();
AdminAuth::applyEditionView();

if (!is_post()) {
    Response::json(['error' => 'POST required.'], 405);
}

Csrf::verify();

$editionId = EditionRepo::contextId();
$table     = (string) input('table', '');

switch ($table) {
    case 'authors':
        // Peserta non-presenter (paper_status 'attendee') punya halaman sendiri (Participants).
        $where  = "a.edition_id = ? AND a.paper_status != 'attendee'";
        $params = [$editionId];

        // Filter tambahan dari toolbar.
        // Presenter dipilih author di Registration form (bukan diset admin saat import).
        $presenter = (string) input('f_presenter', '');
        if ($presenter === 'yes' || $presenter === 'no') {
            $where .= ($presenter === 'yes' ? ' AND EXISTS' : ' AND NOT EXISTS')
                . " (SELECT 1 FROM registrations rp WHERE rp.author_id = a.id AND rp.presenter_id IS NOT NULL AND rp.status != 'draft')";
        }

        $country = (string) input('f_country', '');
        if ($country !== '') {
            $where   .= ' AND a.country = ?';
            $params[] = $country;
        }

        $status = (string) input('f_status', '');
        if ($status !== '') {
            $where   .= ' AND a.paper_status = ?';
            $params[] = $status;
        }

        $batch = (string) input('f_batch', '');
        if ($batch !== '') {
            $where   .= ' AND a.imported_batch = ?';
            $params[] = $batch;
        }

        DataTable::respond([
            'table'   => 'authors a',
            'columns' => [
                // Tombol expand; isinya ikut dicari supaya co-author juga ketemu lewat kotak pencarian.
                'expand'            => ['select' => "(SELECT GROUP_CONCAT(pa.name || ' ' || IFNULL(pa.email, '') || ' ' || IFNULL(pa.affiliation, ''), ' ')
                                                     FROM paper_authors pa WHERE pa.author_id = a.id)", 'orderable' => false],
                'paper_id'          => ['select' => 'a.paper_id'],
                'first_author_name' => ['select' => 'a.first_author_name'],
                'email'             => ['select' => 'a.email'],
                'paper_title'       => ['select' => 'a.paper_title'],
                'affiliation'       => ['select' => 'a.affiliation'],
                'country'           => ['select' => 'a.country'],
                'total_pages'       => ['select' => 'a.total_pages', 'searchable' => false],
                'is_presenter'      => ['select' => "(SELECT pa.name FROM registrations rp JOIN paper_authors pa ON pa.id = rp.presenter_id
                                                         WHERE rp.author_id = a.id AND rp.status != 'draft' LIMIT 1)", 'searchable' => false],
                'paper_status'      => ['select' => 'a.paper_status', 'searchable' => false],
                'created_at'        => ['select' => 'a.created_at', 'searchable' => false],
                'actions'           => ['select' => 'a.id', 'searchable' => false, 'orderable' => false],
            ],
            'extra_select'  => ['id' => 'a.id', 'track' => 'a.track', 'imported_batch' => 'a.imported_batch', 'final_pages' => 'a.final_pages'],
            'where'         => $where,
            'params'        => $params,
            'default_order' => ['paper_id', 'asc'],
            'row'           => static function (array $row): array {
                $id = (int) $row['id'];

                $title = (string) ($row['paper_title'] ?? '');
                $titleCell = $title === ''
                    ? '<span class="text-muted">&mdash;</span>'
                    : '<span title="' . e($title) . '">' . e(mb_strimwidth($title, 0, 60, '…')) . '</span>';

                $presenter = (string) ($row['is_presenter'] ?? '') !== ''
                    ? '<span class="cn-badge cn-badge-verified" title="Chosen in the Registration form">' . e((string) $row['is_presenter']) . '</span>'
                    : '<span class="text-muted" style="font-size:12px">Not chosen yet</span>';

                $statusClass = match ((string) $row['paper_status']) {
                    'accepted' => 'cn-badge-verified',
                    'pending'  => 'cn-badge-submitted',
                    default    => 'cn-badge-rejected',
                };

                $actions = '<div class="d-flex gap-1 justify-content-end">'
                    . '<a class="btn btn-sm btn-outline-secondary" title="Edit" href="'
                    . e(admin_url('author-edit.php?id=' . $id)) . '"><i class="fa-solid fa-pen"></i></a>'
                    . '<button type="button" class="btn btn-sm btn-outline-danger cn-delete" title="Delete"'
                    . ' data-id="' . $id . '" data-name="' . e($row['first_author_name']) . '">'
                    . '<i class="fa-solid fa-trash"></i></button>'
                    . '</div>';

                $authors = Cenim\Domain\PaperAuthorRepo::forPaper($id);
                $list    = '';
                foreach ($authors as $pa) {
                    $list .= '<tr>'
                        . '<td class="text-muted">' . (int) $pa['position'] . '</td>'
                        . '<td><b>' . e((string) $pa['name']) . '</b>'
                        . ((int) $pa['is_presenter'] === 1 ? ' <span class="cn-badge cn-badge-verified">Presenter</span>' : '') . '</td>'
                        . '<td>' . (($pa['email'] ?? '') !== '' ? '<a href="mailto:' . e((string) $pa['email']) . '">' . e((string) $pa['email']) . '</a>'
                            : '<span class="text-danger">no email &mdash; cannot sign in</span>') . '</td>'
                        . '<td>' . (e((string) ($pa['affiliation'] ?? '')) ?: '<span class="text-muted">&mdash;</span>') . '</td>'
                        . '<td>' . (e((string) ($pa['country'] ?? '')) ?: '<span class="text-muted">&mdash;</span>') . '</td>'
                        . '</tr>';
                }
                $detail = '<div class="cn-coauthors">'
                    . '<div class="d-flex align-items-center gap-2 mb-1"><b style="font-size:12.5px">' . e((string) ($row['paper_title'] ?? '')) . '</b>'
                    . '<a class="ms-auto btn btn-sm btn-outline-secondary" href="' . e(admin_url('author-edit.php?id=' . $id)) . '#authors">'
                    . '<i class="fa-solid fa-users-gear me-1"></i>Manage authors</a></div>'
                    . '<table class="table table-sm mb-0" style="font-size:12.5px"><thead><tr>'
                    . '<th style="width:36px">#</th><th>Name</th><th>Email</th><th>Affiliation</th><th>Country</th>'
                    . '</tr></thead><tbody>' . $list . '</tbody></table></div>';

                return [
                    'expand'            => '<button type="button" class="btn btn-sm btn-outline-secondary cn-expand text-nowrap" title="Show all authors">'
                        . '<i class="fa-solid fa-chevron-right me-1"></i>' . count($authors) . '</button>',
                    'detail'            => $detail,
                    'paper_id'          => '<code>' . e($row['paper_id']) . '</code>',
                    'first_author_name' => e($row['first_author_name']),
                    'email'             => '<a href="mailto:' . e($row['email']) . '">' . e($row['email']) . '</a>',
                    'paper_title'       => $titleCell,
                    'affiliation'       => e((string) ($row['affiliation'] ?? '')) ?: '<span class="text-muted">&mdash;</span>',
                    'country'           => e((string) ($row['country'] ?? '')) ?: '<span class="text-muted">&mdash;</span>',
                    'total_pages'       => (int) ($row['final_pages'] ?? 0) > 0
                        ? ((int) $row['final_pages'] !== (int) $row['total_pages']
                            ? '<span class="text-muted text-decoration-line-through" title="Reviewed manuscript">' . (int) $row['total_pages'] . '</span> '
                            : '') . '<b title="Final manuscript">' . (int) $row['final_pages'] . '</b>'
                        : ((int) $row['total_pages'] > 0 ? (string) (int) $row['total_pages'] : '<span class="text-muted">&mdash;</span>'),
                    'is_presenter'      => $presenter,
                    'paper_status'      => '<span class="cn-badge ' . $statusClass . '">' . e(ucfirst((string) $row['paper_status'])) . '</span>',
                    'created_at'        => '<span class="text-muted" style="font-size:12px">' . e(fmt_date((string) $row['created_at'])) . '</span>',
                    'actions'           => $actions,
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    case 'registrations':
        $where  = 'r.edition_id = ?';
        $params = [$editionId];

        $status = (string) input('f_status', '');
        if (in_array($status, Cenim\Domain\RegistrationRepo::STATUSES, true)) {
            $where   .= ' AND r.status = ?';
            $params[] = $status;
        }

        $type = (string) input('f_type', '');
        if (in_array($type, Cenim\Domain\RegistrationRepo::PARTICIPANT_TYPES, true)) {
            $where   .= ' AND r.participant_type = ?';
            $params[] = $type;
        }

        $currency = (string) input('f_currency', '');
        if ($currency === 'IDR' || $currency === 'USD') {
            $where   .= ' AND r.currency = ?';
            $params[] = $currency;
        }

        DataTable::respond([
            'table' => 'registrations r',
            'joins' => 'JOIN authors a ON a.id = r.author_id
                        LEFT JOIN fee_categories fc ON fc.id = r.fee_category_id
                        LEFT JOIN packages pk ON pk.id = r.package_id',
            'columns' => [
                'paper_id'          => ['select' => 'a.paper_id'],
                'first_author_name' => ['select' => 'a.first_author_name'],
                'email'             => ['select' => 'a.email'],
                'category'          => ['select' => "COALESCE(pk.name, fc.label, '')"],
                'participant_type'  => ['select' => 'r.participant_type', 'searchable' => false],
                'total'             => ['select' => 'r.total', 'searchable' => false],
                'status'            => ['select' => 'r.status', 'searchable' => false],
                'submitted_at'      => ['select' => 'r.submitted_at', 'searchable' => false],
                'docs'              => ['select' => 'r.id', 'searchable' => false, 'orderable' => false],
                'actions'           => ['select' => 'r.id', 'searchable' => false, 'orderable' => false],
            ],
            'extra_select' => [
                'id'             => 'r.id',
                'currency'       => 'r.currency',
                'is_student'     => 'r.is_student',
                'is_ieee_member' => 'r.is_ieee_member',
                'doc_count'      => "(SELECT COUNT(*) FROM files f
                                       WHERE f.owner_type = 'registration' AND f.owner_id = r.id
                                         AND f.deleted_at IS NULL)",
            ],
            'where'         => $where,
            'params'        => $params,
            'default_order' => ['submitted_at', 'desc'],
            'row'           => static function (array $row): array {
                $id = (int) $row['id'];

                $badges = [];
                if ((int) $row['is_student'] === 1) {
                    $badges[] = '<span class="cn-badge cn-badge-draft">Student</span>';
                }
                if ((int) $row['is_ieee_member'] === 1) {
                    $badges[] = '<span class="cn-badge cn-badge-submitted">IEEE</span>';
                }

                $docCount = (int) $row['doc_count'];
                $docs = $docCount > 0
                    ? '<span class="cn-badge cn-badge-verified" title="' . $docCount . ' file(s) uploaded">'
                        . '<i class="fa-solid fa-paperclip me-1"></i>' . $docCount . '</span>'
                    : '<span class="text-muted">&mdash;</span>';

                return [
                    'paper_id'          => '<code>' . e($row['paper_id']) . '</code>',
                    'first_author_name' => e($row['first_author_name'])
                        . '<div class="text-muted" style="font-size:11.5px">' . e($row['email']) . '</div>',
                    'email'             => e($row['email']),
                    'category'          => ($row['category'] !== '' ? e((string) $row['category']) : '<span class="text-muted">&mdash;</span>')
                        . ($badges === [] ? '' : '<div class="mt-1 d-flex gap-1">' . implode('', $badges) . '</div>'),
                    'participant_type'  => $row['participant_type'] === 'non_presenter'
                        ? '<span class="cn-badge cn-badge-draft">Non-presenter</span>'
                        : '<span class="cn-badge cn-badge-verified">Presenter</span>',
                    'docs'              => $docs,
                    'total'             => (float) $row['total'] > 0
                        ? '<b>' . e(fmt_money((float) $row['total'], (string) $row['currency'])) . '</b>'
                        : '<span class="text-muted">&mdash;</span>',
                    'status'            => '<span class="cn-badge '
                        . e(Cenim\Domain\RegistrationRepo::statusBadgeClass((string) $row['status'])) . '">'
                        . e(ucfirst((string) $row['status'])) . '</span>',
                    'submitted_at'      => $row['submitted_at'] === null
                        ? '<span class="text-muted">&mdash;</span>'
                        : '<span class="text-muted" style="font-size:12px">' . e(fmt_dt((string) $row['submitted_at'])) . '</span>',
                    'actions'           => '<a class="btn btn-sm btn-outline-secondary" href="'
                        . e(admin_url('registration-view.php?id=' . $id)) . '">'
                        . '<i class="fa-solid fa-eye me-1"></i>Review</a>',
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    case 'invoices':
        $where  = 'i.edition_id = ?';
        $params = [$editionId];

        $status = (string) input('f_status', '');
        if (in_array($status, Cenim\Domain\InvoiceRepo::STATUSES, true)) {
            $where   .= ' AND i.status = ?';
            $params[] = $status;
        }

        $currency = (string) input('f_currency', '');
        if ($currency === 'IDR' || $currency === 'USD') {
            $where   .= ' AND i.currency = ?';
            $params[] = $currency;
        }

        DataTable::respond([
            'table' => 'invoices i',
            'joins' => 'JOIN registrations r ON r.id = i.registration_id
                        JOIN authors a ON a.id = r.author_id',
            'columns' => [
                'number'            => ['select' => 'i.number'],
                'paper_id'          => ['select' => 'a.paper_id'],
                'first_author_name' => ['select' => 'a.first_author_name'],
                'email'             => ['select' => 'a.email'],
                'issued_at'         => ['select' => 'i.issued_at', 'searchable' => false],
                'due_date'          => ['select' => 'i.due_date', 'searchable' => false],
                'total'             => ['select' => 'i.total', 'searchable' => false],
                'status'            => ['select' => 'i.status', 'searchable' => false],
                'actions'           => ['select' => 'i.id', 'searchable' => false, 'orderable' => false],
            ],
            'extra_select' => ['id' => 'i.id', 'currency' => 'i.currency'],
            'where'        => $where,
            'params'       => $params,
            'default_order' => ['number', 'desc'],
            'row'          => static function (array $row): array {
                $id     = (int) $row['id'];
                $status = (string) $row['status'];

                $actions = '<div class="d-flex gap-1 justify-content-end">'
                    . '<a class="btn btn-sm btn-outline-secondary" title="Open PDF" target="_blank" rel="noopener" href="'
                    . e(admin_url('invoice-pdf.php?id=' . $id)) . '"><i class="fa-solid fa-file-pdf"></i></a>';

                if ($status !== Cenim\Domain\InvoiceRepo::STATUS_PAID && $status !== Cenim\Domain\InvoiceRepo::STATUS_VOID) {
                    $actions .= '<button type="button" class="btn btn-sm btn-outline-danger cn-void" title="Void"'
                        . ' data-id="' . $id . '" data-number="' . e((string) $row['number']) . '">'
                        . '<i class="fa-solid fa-ban"></i></button>';
                }

                $actions .= '</div>';

                return [
                    'number'            => '<code>' . e((string) $row['number']) . '</code>',
                    'paper_id'          => '<code>' . e((string) $row['paper_id']) . '</code>',
                    'first_author_name' => e((string) $row['first_author_name'])
                        . '<div class="text-muted" style="font-size:11.5px">' . e((string) $row['email']) . '</div>',
                    'email'             => e((string) $row['email']),
                    'issued_at'         => '<span style="font-size:12px">' . e(fmt_date((string) $row['issued_at'])) . '</span>',
                    'due_date'          => $row['due_date'] === null
                        ? '<span class="text-muted">&mdash;</span>'
                        : '<span style="font-size:12px">' . e(fmt_date((string) $row['due_date'] . ' 00:00:00')) . '</span>',
                    'total'             => '<b>' . e(fmt_money((float) $row['total'], (string) $row['currency'])) . '</b>',
                    'status'            => '<span class="cn-badge '
                        . e(Cenim\Domain\InvoiceRepo::statusBadgeClass($status)) . '">'
                        . e(ucfirst($status)) . '</span>',
                    'actions'           => $actions,
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    case 'payments':
        $where  = 'p.edition_id = ?';
        $params = [$editionId];

        $status = (string) input('f_status', '');
        if (in_array($status, Cenim\Domain\PaymentRepo::STATUSES, true)) {
            $where   .= ' AND p.status = ?';
            $params[] = $status;
        }

        $currency = (string) input('f_currency', '');
        if ($currency === 'IDR' || $currency === 'USD') {
            $where   .= ' AND p.currency = ?';
            $params[] = $currency;
        }

        DataTable::respond([
            'table' => 'payments p',
            'joins' => 'JOIN invoices i ON i.id = p.invoice_id
                        JOIN registrations r ON r.id = i.registration_id
                        JOIN authors a ON a.id = r.author_id',
            'columns' => [
                'invoice_number'    => ['select' => 'i.number'],
                'paper_id'          => ['select' => 'a.paper_id'],
                'first_author_name' => ['select' => 'a.first_author_name'],
                'sender_name'       => ['select' => 'p.sender_name'],
                'reference'         => ['select' => 'p.reference'],
                'paid_at'           => ['select' => 'p.paid_at', 'searchable' => false],
                'amount_paid'       => ['select' => 'p.amount_paid', 'searchable' => false],
                'difference'        => ['select' => '(p.amount_paid - i.total)', 'searchable' => false],
                'status'            => ['select' => 'p.status', 'searchable' => false],
                'proof'             => ['select' => 'p.proof_file_id', 'searchable' => false, 'orderable' => false],
                'actions'           => ['select' => 'p.id', 'searchable' => false, 'orderable' => false],
            ],
            'extra_select'  => ['id' => 'p.id', 'currency' => 'p.currency', 'invoice_total' => 'i.total'],
            'where'         => $where,
            'params'        => $params,
            'default_order' => ['paid_at', 'desc'],
            'row'           => static function (array $row): array {
                $id         = (int) $row['id'];
                $currency   = (string) $row['currency'];
                $difference = (float) $row['difference'];

                $diffCell = abs($difference) < 0.009
                    ? '<span class="text-muted">&mdash;</span>'
                    : '<span class="cn-badge ' . ($difference < 0 ? 'cn-badge-rejected' : 'cn-badge-unpaid') . '">'
                        . ($difference > 0 ? '+' : '-') . e(fmt_money(abs($difference), $currency)) . '</span>';

                return [
                    'invoice_number'    => '<code>' . e((string) $row['invoice_number']) . '</code>',
                    'paper_id'          => '<code>' . e((string) $row['paper_id']) . '</code>',
                    'first_author_name' => e((string) $row['first_author_name'])
                        . '<div class="text-muted" style="font-size:11.5px">'
                        . e((string) ($row['sender_name'] ?? '')) . '</div>',
                    'sender_name'       => e((string) ($row['sender_name'] ?? '')),
                    'reference'         => e((string) ($row['reference'] ?? '')),
                    'paid_at'           => ($row['paid_at'] ?? '') === ''
                        ? '<span class="text-muted">&mdash;</span>'
                        : '<span style="font-size:12px">' . e(fmt_date(substr((string) $row['paid_at'], 0, 10) . ' 00:00:00')) . '</span>',
                    'amount_paid'       => '<b>' . e(fmt_money((float) $row['amount_paid'], $currency)) . '</b>',
                    'difference'        => $diffCell,
                    'proof'             => $row['proof'] !== null
                        ? '<i class="fa-solid fa-paperclip" style="color:#12704a" title="Proof attached"></i>'
                        : '<i class="fa-solid fa-triangle-exclamation text-warning" title="No proof"></i>',
                    'status'            => '<span class="cn-badge '
                        . e(Cenim\Domain\PaymentRepo::statusBadgeClass((string) $row['status'])) . '">'
                        . e(ucfirst((string) $row['status'])) . '</span>',
                    'actions'           => '<a class="btn btn-sm btn-outline-secondary" href="'
                        . e(admin_url('payment-view.php?id=' . $id)) . '">'
                        . '<i class="fa-solid fa-eye me-1"></i>Review</a>',
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    case 'receipts':
        DataTable::respond([
            'table' => 'receipts rc',
            'joins' => 'JOIN invoices i ON i.id = rc.invoice_id
                        JOIN registrations r ON r.id = i.registration_id
                        JOIN authors a ON a.id = r.author_id',
            'columns' => [
                'number'            => ['select' => 'rc.number'],
                'invoice_number'    => ['select' => 'i.number'],
                'paper_id'          => ['select' => 'a.paper_id'],
                'first_author_name' => ['select' => 'a.first_author_name'],
                'issued_at'         => ['select' => 'rc.issued_at', 'searchable' => false],
                'amount'            => ['select' => 'rc.amount', 'searchable' => false],
                'actions'           => ['select' => 'rc.id', 'searchable' => false, 'orderable' => false],
            ],
            'extra_select'  => ['id' => 'rc.id', 'currency' => 'rc.currency'],
            'where'         => 'rc.edition_id = ?',
            'params'        => [$editionId],
            'default_order' => ['number', 'desc'],
            'row'           => static function (array $row): array {
                $id = (int) $row['id'];

                return [
                    'number'            => '<code>' . e((string) $row['number']) . '</code>',
                    'invoice_number'    => '<code>' . e((string) $row['invoice_number']) . '</code>',
                    'paper_id'          => '<code>' . e((string) $row['paper_id']) . '</code>',
                    'first_author_name' => e((string) $row['first_author_name']),
                    'issued_at'         => '<span style="font-size:12px">' . e(fmt_date((string) $row['issued_at'])) . '</span>',
                    'amount'            => '<b>' . e(fmt_money((float) $row['amount'], (string) $row['currency'])) . '</b>',
                    'actions'           => '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="'
                        . e(admin_url('receipt-pdf.php?id=' . $id)) . '" title="Open PDF">'
                        . '<i class="fa-solid fa-file-pdf"></i></a>',
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    case 'certificates':
        $where  = 'c.edition_id = ?';
        $params = [$editionId];

        $type = (string) input('f_type', '');
        if (in_array($type, Cenim\Domain\CertificateRepo::types(), true)) {
            $where   .= ' AND c.type = ?';
            $params[] = $type;
        }

        $state = (string) input('f_state', '');
        if ($state === 'valid') {
            $where .= ' AND c.revoked_at IS NULL';
        } elseif ($state === 'revoked') {
            $where .= ' AND c.revoked_at IS NOT NULL';
        }

        DataTable::respond([
            'table'   => 'certificates c',
            'columns' => [
                'code'           => ['select' => 'c.code'],
                'type'           => ['select' => 'c.type', 'searchable' => false],
                'recipient_name' => ['select' => 'c.recipient_name'],
                'subject_title'  => ['select' => 'c.subject_title'],
                'issued_at'      => ['select' => 'c.issued_at', 'searchable' => false],
                'download_count' => ['select' => 'c.download_count', 'searchable' => false],
                'state'          => ['select' => 'c.revoked_at', 'searchable' => false],
                'actions'        => ['select' => 'c.id', 'searchable' => false, 'orderable' => false],
            ],
            'extra_select'  => ['id' => 'c.id', 'revoke_reason' => 'c.revoke_reason', 'email' => 'c.recipient_email', 'number' => 'c.number'],
            'where'         => $where,
            'params'        => $params,
            'default_order' => ['issued_at', 'desc'],
            'row'           => static function (array $row): array {
                $id      = (int) $row['id'];
                $revoked = ($row['state'] ?? null) !== null;

                $actions = '<div class="d-flex gap-1 justify-content-end">'
                    . '<a class="btn btn-sm btn-outline-secondary" title="View / download PDF" target="_blank" rel="noopener" href="'
                    . e(admin_url('certificate-view.php?id=' . $id)) . '"><i class="fa-regular fa-eye"></i></a>'
                    . '<a class="btn btn-sm btn-outline-secondary" title="Verification page" target="_blank" rel="noopener" href="'
                    . e(Cenim\Domain\CertificateRepo::verifyUrl((string) $row['code'])) . '"><i class="fa-solid fa-qrcode"></i></a>';

                if ($revoked) {
                    $actions .= '<form method="post" action="' . e(admin_url('certificate-revoke.php')) . '" class="m-0">'
                        . Cenim\Core\Csrf::field()
                        . '<input type="hidden" name="id" value="' . $id . '">'
                        . '<input type="hidden" name="action" value="restore">'
                        . '<button type="submit" class="btn btn-sm btn-outline-secondary" title="Restore">'
                        . '<i class="fa-solid fa-rotate-left"></i></button></form>';
                } else {
                    $actions .= '<button type="button" class="btn btn-sm btn-outline-danger cn-revoke" title="Revoke"'
                        . ' data-id="' . $id . '" data-code="' . e((string) $row['code']) . '">'
                        . '<i class="fa-solid fa-ban"></i></button>';
                }

                $actions .= '</div>';

                $subject = (string) ($row['subject_title'] ?? '');

                return [
                    'code'           => '<code>' . e((string) $row['code']) . '</code>'
                        . (($row['number'] ?? '') !== ''
                            ? '<div class="text-muted" style="font-size:11.5px">No. ' . e((string) $row['number']) . '</div>'
                            : ''),
                    'type'           => '<span class="cn-badge cn-badge-submitted">'
                        . e(Cenim\Domain\CertificateRepo::label((string) $row['type'])) . '</span>',
                    'recipient_name' => e((string) $row['recipient_name'])
                        . (($row['email'] ?? '') !== ''
                            ? '<div class="text-muted" style="font-size:11.5px">' . e((string) $row['email']) . '</div>'
                            : ''),
                    'subject_title'  => $subject === ''
                        ? '<span class="text-muted">&mdash;</span>'
                        : '<span title="' . e($subject) . '">' . e(mb_strimwidth($subject, 0, 48, '…')) . '</span>',
                    'issued_at'      => '<span style="font-size:12px">' . e(fmt_date((string) $row['issued_at'])) . '</span>',
                    'download_count' => (string) (int) $row['download_count'],
                    'state'          => $revoked
                        ? '<span class="cn-badge cn-badge-rejected" title="' . e((string) ($row['revoke_reason'] ?? '')) . '">Revoked</span>'
                        : '<span class="cn-badge cn-badge-verified">Valid</span>',
                    'actions'        => $actions,
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    case 'audit_logs':
        // Baris dengan edition_id NULL berasal dari CLI (cron/backup) — tetap ditampilkan.
        $where  = '(a.edition_id = ? OR a.edition_id IS NULL)';
        $params = [$editionId];

        $action = (string) input('f_action', '');
        if ($action !== '') {
            $where   .= ' AND a.action = ?';
            $params[] = $action;
        }

        $actor = (string) input('f_actor', '');
        if ($actor !== '') {
            $where   .= ' AND a.actor_type = ?';
            $params[] = $actor;
        }

        DataTable::respond([
            'table'   => 'audit_logs a',
            'columns' => [
                'created_at' => ['select' => 'a.created_at', 'searchable' => false],
                'actor'      => ['select' => 'a.actor_type'],
                'action'     => ['select' => 'a.action'],
                'target'     => ['select' => 'a.target_type', 'searchable' => false],
                'ip'         => ['select' => 'a.ip'],
                'meta'       => ['select' => 'a.meta_json', 'orderable' => false],
            ],
            'extra_select'  => ['actor_id' => 'a.actor_id', 'target_id' => 'a.target_id', 'ua' => 'a.ua'],
            'where'         => $where,
            'params'        => $params,
            'default_order' => ['created_at', 'desc'],
            'row'           => static function (array $row): array {
                $meta   = (string) ($row['meta'] ?? '');
                $target = (string) ($row['target'] ?? '');
                $actor  = (string) ($row['actor'] ?? '');

                if ($target !== '' && ($row['target_id'] ?? null) !== null) {
                    $target .= ' #' . (int) $row['target_id'];
                }

                return [
                    'created_at' => '<span style="font-size:12px">' . e(fmt_dt((string) $row['created_at'])) . '</span>',
                    'actor'      => '<span class="cn-badge cn-badge-submitted">' . e($actor) . '</span>'
                        . (($row['actor_id'] ?? '') !== ''
                            ? '<div class="text-muted" style="font-size:11.5px">' . e((string) $row['actor_id']) . '</div>'
                            : ''),
                    'action'     => '<code>' . e((string) $row['action']) . '</code>',
                    'target'     => $target === ''
                        ? '<span class="text-muted">&mdash;</span>'
                        : e($target),
                    'ip'         => '<span style="font-size:12px" title="' . e((string) ($row['ua'] ?? '')) . '">'
                        . e((string) ($row['ip'] ?? '')) . '</span>',
                    'meta'       => $meta === ''
                        ? '<span class="text-muted">&mdash;</span>'
                        : '<span style="font-size:11.5px" title="' . e($meta) . '">'
                            . e(mb_strimwidth($meta, 0, 70, '…')) . '</span>',
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    case 'mail_log':
        $where  = 'm.edition_id = ?';
        $params = [$editionId];

        $status = (string) input('f_status', '');
        if (in_array($status, ['sent', 'skipped', 'failed'], true)) {
            $where   .= ' AND m.status = ?';
            $params[] = $status;
        }

        $template = (string) input('f_template', '');
        if ($template !== '') {
            $where   .= ' AND m.template = ?';
            $params[] = $template;
        }

        DataTable::respond([
            'table'   => 'mail_log m',
            'columns' => [
                'created_at' => ['select' => 'm.created_at', 'searchable' => false],
                'to_email'   => ['select' => 'm.to_email'],
                'subject'    => ['select' => 'm.subject'],
                'template'   => ['select' => 'm.template'],
                'status'     => ['select' => 'm.status', 'searchable' => false],
                'actions'    => ['select' => 'm.id', 'searchable' => false, 'orderable' => false],
            ],
            'extra_select'  => ['error' => 'm.error', 'file_path' => 'm.file_path'],
            'where'         => $where,
            'params'        => $params,
            'default_order' => ['created_at', 'desc'],
            'row'           => static function (array $row): array {
                $status = (string) $row['status'];
                $badge  = match ($status) {
                    'sent'    => 'cn-badge-verified',
                    'failed'  => 'cn-badge-rejected',
                    default   => 'cn-badge-draft',
                };

                $file    = (string) ($row['file_path'] ?? '');
                $actions = '<span class="text-muted">&mdash;</span>';

                if ($file !== '') {
                    $actions = '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"'
                        . ' title="Open saved email" href="'
                        . e(admin_url('log-file.php?kind=mail&name=' . urlencode(basename($file))))
                        . '"><i class="fa-solid fa-envelope-open-text"></i></a>';
                }

                $subject = (string) $row['subject'];

                return [
                    'created_at' => '<span style="font-size:12px">' . e(fmt_dt((string) $row['created_at'])) . '</span>',
                    'to_email'   => e((string) $row['to_email']),
                    'subject'    => '<span title="' . e($subject) . '">' . e(mb_strimwidth($subject, 0, 46, '…')) . '</span>',
                    'template'   => '<code>' . e((string) $row['template']) . '</code>',
                    'status'     => '<span class="cn-badge ' . $badge . '"'
                        . (($row['error'] ?? '') !== '' ? ' title="' . e((string) $row['error']) . '"' : '')
                        . '>' . e(ucfirst($status)) . '</span>',
                    'actions'    => '<div class="text-end">' . $actions . '</div>',
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    case 'committee':
        $where  = 'cm.edition_id = ?';
        $params = [$editionId];

        $division = (string) input('f_division', '');
        if ($division !== '') {
            $where   .= ' AND cm.division = ?';
            $params[] = $division;
        }

        DataTable::respond([
            'table'   => 'committee cm',
            'columns' => [
                'sort'            => ['select' => 'cm.sort', 'searchable' => false],
                'name'            => ['select' => 'cm.name'],
                'role'            => ['select' => 'cm.role'],
                'division'        => ['select' => 'cm.division'],
                'email'           => ['select' => 'cm.email'],
                'show_on_landing' => ['select' => 'cm.show_on_landing', 'searchable' => false],
                'certificate'     => ['select' => 'cm.id', 'searchable' => false, 'orderable' => false],
                'actions'         => ['select' => 'cm.id', 'searchable' => false, 'orderable' => false],
            ],
            'extra_select'  => [
                'id'        => 'cm.id',
                'cert_code' => "(SELECT code FROM certificates ce
                                  WHERE ce.recipient_ref_type = 'committee' AND ce.recipient_ref_id = cm.id
                                    AND ce.revoked_at IS NULL LIMIT 1)",
            ],
            'where'         => $where,
            'params'        => $params,
            'default_order' => ['sort', 'asc'],
            'row'           => static function (array $row): array {
                $id = (int) $row['id'];

                return [
                    'sort'            => (string) (int) $row['sort'],
                    'name'            => e((string) $row['name']),
                    'role'            => e((string) ($row['role'] ?? '')) ?: '<span class="text-muted">&mdash;</span>',
                    'division'        => e((string) ($row['division'] ?? '')) ?: '<span class="text-muted">&mdash;</span>',
                    'email'           => ($row['email'] ?? '') !== ''
                        ? '<a href="mailto:' . e((string) $row['email']) . '">' . e((string) $row['email']) . '</a>'
                        : '<span class="text-muted">&mdash;</span>',
                    'show_on_landing' => (int) $row['show_on_landing'] === 1
                        ? '<i class="fa-solid fa-check" style="color:#1a9c62"></i>'
                        : '<i class="fa-solid fa-minus text-muted"></i>',
                    'certificate'     => ($row['cert_code'] ?? null) !== null
                        ? '<code>' . e((string) $row['cert_code']) . '</code>'
                        : '<span class="text-muted">not issued</span>',
                    'actions'         => '<div class="d-flex gap-1 justify-content-end">'
                        . '<a class="btn btn-sm btn-outline-secondary" title="Edit" href="'
                        . e(admin_url('committee-edit.php?id=' . $id)) . '"><i class="fa-solid fa-pen"></i></a>'
                        . '<button type="button" class="btn btn-sm btn-outline-danger cn-delete" title="Delete"'
                        . ' data-id="' . $id . '" data-name="' . e((string) $row['name']) . '">'
                        . '<i class="fa-solid fa-trash"></i></button></div>',
                ];
            },
        ]);

        // DataTable::respond() selalu exit.

    default:
        Response::json(['error' => 'Unknown table.'], 400);
}
