<?php
/**
 * @var array<string,int>   $counts
 * @var array<string,float> $totals
 * @var array<int,string>   $statuses
 */

use Cenim\Domain\PaymentRepo;

$cards = [
    ['label' => 'To verify', 'value' => $counts[PaymentRepo::STATUS_SUBMITTED], 'icon' => 'fa-hourglass-half', 'filter' => 'submitted'],
    ['label' => 'Confirmed', 'value' => $counts[PaymentRepo::STATUS_CONFIRMED], 'icon' => 'fa-circle-check',   'filter' => 'confirmed'],
    ['label' => 'Returned',  'value' => $counts[PaymentRepo::STATUS_REJECTED],  'icon' => 'fa-rotate-left',    'filter' => 'rejected'],
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
