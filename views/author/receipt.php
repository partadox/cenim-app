<?php
/**
 * @var array|null $receipt
 * @var array|null $invoice
 * @var array|null $payment
 */

use Cenim\Domain\PaymentRepo;
use Cenim\Support\Money;
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= e(author_url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <?php if ($receipt !== null): ?>
        <span class="cn-badge cn-badge-paid">Paid</span>
    <?php endif; ?>
</div>

<?php if ($receipt === null): ?>
    <div class="cn-card">
        <div class="cn-card-body">
            <div class="cn-empty">
                <i class="fa-regular fa-file-lines"></i>
                <?php if ($payment !== null && (string) $payment['status'] === PaymentRepo::STATUS_SUBMITTED): ?>
                    Your payment is being verified. The receipt is issued right after it is confirmed.
                <?php elseif ($payment !== null && (string) $payment['status'] === PaymentRepo::STATUS_REJECTED): ?>
                    The committee asked for a correction to your payment.
                    <div class="mt-3">
                        <a href="<?= e(author_url('payment.php')) ?>" class="btn btn-sm btn-gold">
                            Correct my payment<i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php elseif ($invoice !== null): ?>
                    Submit your proof of payment first — the receipt is issued after the committee confirms it.
                    <div class="mt-3">
                        <a href="<?= e(author_url('payment.php')) ?>" class="btn btn-sm btn-gold">
                            Open the payment form<i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php else: ?>
                    Your receipt will be available after your registration and payment are complete.
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-receipt text-muted"></i>
                    <h2>Receipt <?= e((string) $receipt['number']) ?></h2>
                    <a class="btn btn-sm btn-navy ms-auto" href="<?= e(author_url('receipt-pdf.php?download=1')) ?>">
                        <i class="fa-solid fa-file-pdf me-1"></i>Download PDF
                    </a>
                </div>
                <div class="cn-card-body">
                    <div class="p-3 mb-3" style="background:#e6f6ed;border:1px solid #cbeada;border-radius:8px">
                        <div class="cn-stat-label" style="color:#12704a">Amount received</div>
                        <div style="font-size:22px;font-weight:700;color:#12704a">
                            <?= e(fmt_money((float) $receipt['amount'], (string) $receipt['currency'])) ?>
                        </div>
                        <div class="text-muted" style="font-size:12px">
                            <?= e(ucfirst(Money::words((float) $receipt['amount'], (string) $receipt['currency']))) ?>
                        </div>
                    </div>

                    <dl class="row mb-0" style="font-size:13px">
                        <dt class="col-sm-5 text-muted fw-normal">Issued</dt>
                        <dd class="col-sm-7"><?= e(fmt_dt((string) $receipt['issued_at'])) ?></dd>
                        <dt class="col-sm-5 text-muted fw-normal">Against invoice</dt>
                        <dd class="col-sm-7"><code><?= e((string) ($invoice['number'] ?? '—')) ?></code></dd>
                        <dt class="col-sm-5 text-muted fw-normal">Transfer date</dt>
                        <dd class="col-sm-7">
                            <?= $payment !== null && ($payment['paid_at'] ?? '') !== ''
                                ? e(fmt_date(substr((string) $payment['paid_at'], 0, 10) . ' 00:00:00'))
                                : '—' ?>
                        </dd>
                        <dt class="col-sm-5 text-muted fw-normal">Sender</dt>
                        <dd class="col-sm-7 mb-0"><?= e((string) ($payment['sender_name'] ?? '—')) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-certificate text-muted"></i>
                    <h2>What is next</h2>
                </div>
                <div class="cn-card-body text-muted" style="font-size:13px">
                    Your registration is complete. Certificates become available in the author area after the
                    conference — you will be notified by email.
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
