<?php
/**
 * @var array<string,int>                 $counts
 * @var array<string,array<string,float>> $totals
 * @var array<int,array<string,mixed>>    $pending
 * @var array<int,string>                 $statuses
 */

use Cenim\Core\Csrf;
use Cenim\Domain\InvoiceRepo;

$cards = [
    ['label' => 'Unpaid', 'value' => $counts[InvoiceRepo::STATUS_UNPAID], 'icon' => 'fa-file-invoice-dollar', 'filter' => 'unpaid'],
    ['label' => 'Paid',   'value' => $counts[InvoiceRepo::STATUS_PAID],   'icon' => 'fa-circle-check',        'filter' => 'paid'],
    ['label' => 'Void',   'value' => $counts[InvoiceRepo::STATUS_VOID],   'icon' => 'fa-ban',                 'filter' => 'void'],
];
?>

<div class="row g-3 mb-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-4">
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
        <div class="cn-card-body">
            <div class="row g-3">
                <?php foreach ($totals as $currency => $byStatus): ?>
                    <div class="col-md-6">
                        <div class="cn-stat-label mb-1"><?= e((string) $currency) ?> invoiced</div>
                        <div class="d-flex flex-wrap gap-3" style="font-size:13px">
                            <?php foreach ([InvoiceRepo::STATUS_UNPAID => 'Unpaid', InvoiceRepo::STATUS_PAID => 'Paid', 'waived' => 'Waived (not income)'] as $key => $label): ?>
                                <?php if ($key === 'waived' && empty($byStatus['waived'])) { continue; } ?>
                                <span>
                                    <span class="text-muted"><?= e($label) ?>:</span>
                                    <b style="color:var(--cn-navy)">
                                        <?= e(fmt_money((float) ($byStatus[$key] ?? 0), (string) $currency)) ?>
                                    </b>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($pending !== []): ?>
    <div class="cn-card mb-3" style="border-left:3px solid var(--cn-gold)">
        <div class="cn-card-head">
            <i class="fa-solid fa-hourglass-half text-muted"></i>
            <h2>Verified, waiting for an invoice (<?= e((string) count($pending)) ?>)</h2>
            <form method="post" action="<?= e(admin_url('invoice-issue.php')) ?>" class="ms-auto m-0">
                <?= Csrf::field() ?>
                <input type="hidden" name="scope" value="all">
                <button type="submit" class="btn btn-sm btn-navy">
                    <i class="fa-solid fa-bolt me-1"></i>Issue all
                </button>
            </form>
        </div>
        <div class="cn-card-body p-0">
            <table class="table table-sm align-middle mb-0" style="font-size:13px">
                <tbody>
                    <?php foreach ($pending as $row): ?>
                        <tr>
                            <td style="width:150px"><code><?= e((string) $row['paper_id']) ?></code></td>
                            <td><?= e((string) $row['first_author_name']) ?></td>
                            <td class="text-end" style="width:150px">
                                <?= e(fmt_money((float) $row['total'], (string) $row['currency'])) ?>
                            </td>
                            <td class="text-end" style="width:120px">
                                <form method="post" action="<?= e(admin_url('invoice-issue.php')) ?>" class="m-0">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="registration_id" value="<?= e((string) $row['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-file-invoice me-1"></i>Issue
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="cn-card">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-file-invoice text-muted"></i>
        <h2>Invoice list</h2>
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
            <table id="invoicesTable" class="table table-sm table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Paper ID</th>
                        <th>First author</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Modal void -->
<div class="modal fade" id="voidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(admin_url('invoice-void.php')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="voidId">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:15px">Void invoice <span id="voidNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:12.5px">
                    Voiding keeps the record and its number for the audit trail, and lets you issue a corrected
                    invoice for the same registration.
                </p>
                <label class="form-label" for="voidReason" style="font-size:12.5px">Reason</label>
                <input type="text" class="form-control" id="voidReason" name="reason" maxlength="300"
                       placeholder="e.g. wrong fee category — student rate applied by mistake" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-ban me-1"></i>Void invoice
                </button>
            </div>
        </form>
    </div>
</div>
