<?php
/**
 * @var array<string,int>   $counts
 * @var array<string,float> $totals
 * @var array<int,string>   $statuses
 * @var array<int,array<string,mixed>> $unpaid  invoice belum lunas tanpa bukti bayar aktif
 */

use Cenim\Domain\PaymentRepo;

$cards = [
    ['label' => 'Not paid yet', 'value' => count($unpaid), 'icon' => 'fa-file-invoice-dollar', 'filter' => 'unpaid'],
    ['label' => 'To verify', 'value' => $counts[PaymentRepo::STATUS_SUBMITTED], 'icon' => 'fa-hourglass-half', 'filter' => 'submitted'],
    ['label' => 'Confirmed', 'value' => $counts[PaymentRepo::STATUS_CONFIRMED], 'icon' => 'fa-circle-check',   'filter' => 'confirmed'],
    ['label' => 'Returned',  'value' => $counts[PaymentRepo::STATUS_REJECTED],  'icon' => 'fa-rotate-left',    'filter' => 'rejected'],
];
?>

<div class="row g-3 mb-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-6 col-lg-3">
            <a class="text-decoration-none" href="#" data-status-filter="<?= e($card['filter']) ?>">
                <div class="cn-card h-100">
                    <div class="cn-card-body cn-stat">
                        <span class="cn-stat-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></span>
                        <span>
                            <span class="cn-stat-value d-block"><?= e((string) $card['value']) ?></span>
                            <span class="cn-stat-label"><?= e($card['label']) ?></span>
                        </span>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($totals !== []): ?>
    <div class="cn-card mb-3">
        <div class="cn-card-body d-flex flex-wrap align-items-center gap-4">
            <span class="cn-stat-label">Confirmed received</span>
            <?php foreach ($totals as $currency => $amount): ?>
                <span style="font-size:15px;font-weight:600;color:#12704a">
                    <?= e(fmt_money($amount, (string) $currency)) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="cn-card">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-money-check-dollar text-muted"></i>
        <h2>Payment list</h2>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <select id="fStatus" class="form-select form-select-sm" style="width:auto">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>"><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="fCurrency" class="form-select form-select-sm" style="width:auto">
                <option value="">All currencies</option>
                <option value="IDR">IDR</option>
                <option value="USD">USD</option>
            </select>
        </div>
    </div>
    <div class="cn-card-body">
        <div class="table-responsive">
            <table id="paymentsTable" class="table table-sm table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Paper ID</th>
                        <th>First author</th>
                        <th>Transfer date</th>
                        <th class="text-end">Amount paid</th>
                        <th class="text-end">Difference</th>
                        <th>Proof</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="cn-card mt-3" id="unpaidList">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-file-invoice-dollar text-muted"></i>
        <h2>Not paid yet (<?= count($unpaid) ?>)</h2>
        <span class="text-muted" style="font-size:12px">Unpaid invoices without an uploaded proof. If the author paid but did not upload, use <b>Mark as paid</b>.</span>
    </div>
    <div class="cn-card-body">
        <table id="unpaidTable" class="table table-sm align-middle mb-0" style="width:100%;font-size:12.5px">
            <thead class="table-light">
                <tr><th class="text-center">No</th><th>Invoice</th><th>Paper</th><th>Author</th><th class="text-end">Total</th><th>Issued</th><th>Due</th><th>Payment</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($unpaid as $i => $row): ?>
                    <tr>
                        <td class="text-center text-muted"><?= $i + 1 ?></td>
                        <td><code><?= e((string) $row['number']) ?></code></td>
                        <td><code><?= e((string) $row['paper_id']) ?></code></td>
                        <td><?= e((string) $row['first_author_name']) ?>
                            <div class="text-muted" style="font-size:11.5px"><?= e((string) $row['email']) ?></div></td>
                        <td class="text-end" data-order="<?= (float) $row['total'] ?>"><b><?= e(fmt_money((float) $row['total'], (string) $row['currency'])) ?></b></td>
                        <td data-order="<?= e((string) $row['issued_at']) ?>"><?= e(fmt_date((string) $row['issued_at'])) ?></td>
                        <td data-order="<?= e((string) ($row['due_date'] ?? '')) ?>">
                            <?php if (($row['due_date'] ?? null) !== null): ?>
                                <span class="<?= (string) $row['due_date'] < gmdate('Y-m-d') ? 'text-danger fw-semibold' : '' ?>"><?= e(fmt_date($row['due_date'] . ' 00:00:00')) ?></span>
                            <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if (($row['payment_status'] ?? null) === 'rejected'): ?>
                                <a href="<?= e(admin_url('payment-view.php?id=' . (int) $row['payment_id'])) ?>" class="cn-badge cn-badge-rejected">returned</a>
                            <?php else: ?><span class="text-muted">none</span><?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" title="Registration" href="<?= e(admin_url('registration-view.php?id=' . (int) $row['registration_id'])) ?>"><i class="fa-solid fa-clipboard-list"></i></a>
                            <a class="btn btn-sm btn-outline-success" href="<?= e(admin_url('payment-record.php?invoice=' . (int) $row['id'])) ?>">
                                <i class="fa-solid fa-check me-1"></i>Mark as paid
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
