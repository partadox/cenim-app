<?php
/**
 * @var array<string,float> $totals
 */
?>

<?php if ($totals !== []): ?>
    <div class="cn-card mb-3">
        <div class="cn-card-body d-flex flex-wrap align-items-center gap-4">
            <span class="cn-stat-label">Total receipted</span>
            <?php foreach ($totals as $currency => $amount): ?>
                <span style="font-size:15px;font-weight:600;color:#12704a">
                    <?= e(fmt_money($amount, (string) $currency)) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-receipt text-muted"></i>
        <h2>Receipt list</h2>
    </div>
    <div class="cn-card-body">
        <div class="table-responsive">
            <table id="receiptsTable" class="table table-sm table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Invoice No.</th>
                        <th>Paper ID</th>
                        <th>First author</th>
                        <th>Issued</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
