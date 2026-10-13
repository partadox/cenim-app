<?php
/**
 * @var array      $payment    hasil PaymentRepo::findWithContext()
 * @var array|null $proof
 * @var array|null $receipt
 * @var float      $difference amount_paid - invoice_total
 */

use Cenim\Core\Csrf;
use Cenim\Domain\FileRepo;
use Cenim\Domain\PaymentRepo;
use Cenim\Support\Money;

$status    = (string) $payment['status'];
$confirmed = $status === PaymentRepo::STATUS_CONFIRMED;
$currency  = (string) $payment['currency'];
$isImage   = $proof !== null && str_starts_with((string) $proof['mime'], 'image/');
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(admin_url('payments.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to list
    </a>
    <span class="cn-badge <?= e(PaymentRepo::statusBadgeClass($status)) ?>"><?= e(ucfirst($status)) ?></span>
    <div class="ms-auto d-flex gap-1">
        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
           href="<?= e(admin_url('invoice-pdf.php?id=' . (int) $payment['invoice_id'])) ?>">
            <i class="fa-solid fa-file-invoice me-1"></i>Invoice PDF
        </a>
        <a class="btn btn-sm btn-outline-secondary"
           href="<?= e(admin_url('registration-view.php?id=' . (int) $payment['registration_id'])) ?>">
            <i class="fa-solid fa-clipboard-list me-1"></i>Registration
        </a>
    </div>
</div>

<?php if ($status === PaymentRepo::STATUS_REJECTED && ($payment['reject_reason'] ?? '') !== ''): ?>
    <div class="alert alert-danger" style="font-size:13px">
        <b>Returned to the author</b>
        <div class="mt-1"><?= nl2br(e((string) $payment['reject_reason'])) ?></div>
    </div>
<?php endif; ?>

<?php if (($payment['filled_by_name'] ?? '') !== ''): ?>
    <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:12.5px">
        <i class="fa-solid fa-user-pen me-1" style="color:var(--cn-navy)"></i>
        Last filled in by <b><?= e((string) $payment['filled_by_name']) ?></b>
        <?php if (($payment['updated_at'] ?? '') !== ''): ?>&middot; <?= e(fmt_dt((string) $payment['updated_at'])) ?><?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-money-check-dollar text-muted"></i>
                <h2>What the author reported</h2>
            </div>
            <div class="cn-card-body">
                <div class="p-3 mb-3" style="background:#f7f9fc;border:1px solid var(--cn-border);border-radius:8px">
                    <div class="cn-stat-label">Amount transferred</div>
                    <div style="font-size:22px;font-weight:700;color:var(--cn-navy)">
                        <?= e(fmt_money((float) $payment['amount_paid'], $currency)) ?>
                    </div>
                    <div class="text-muted" style="font-size:12px">
                        <?= e(ucfirst(Money::words((float) $payment['amount_paid'], $currency))) ?>
                    </div>
                </div>

                <?php if (abs($difference) > 0.009): ?>
                    <div class="alert <?= $difference < 0 ? 'alert-danger' : 'alert-warning' ?> py-2 px-3" style="font-size:12.5px">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        <?php if ($difference < 0): ?>
                            <b>Underpaid by <?= e(fmt_money(abs($difference), $currency)) ?></b> compared with the
                            invoice total. Ask the author to transfer the difference, or confirm only if the shortfall
                            is an acceptable bank charge.
                        <?php else: ?>
                            Overpaid by <?= e(fmt_money($difference, $currency)) ?> compared with the invoice total.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-sm-5 text-muted fw-normal">Invoice total</dt>
                    <dd class="col-sm-7">
                        <?= e(fmt_money((float) $payment['invoice_total'], (string) $payment['invoice_currency'])) ?>
                        &middot; <code><?= e((string) $payment['invoice_number']) ?></code>
                    </dd>

                    <dt class="col-sm-5 text-muted fw-normal">Method</dt>
                    <dd class="col-sm-7"><?= e(PaymentRepo::methodLabel((string) $payment['method'])) ?></dd>

                    <dt class="col-sm-5 text-muted fw-normal">Transfer date</dt>
                    <dd class="col-sm-7">
                        <?= ($payment['paid_at'] ?? '') !== ''
                            ? e(fmt_date(substr((string) $payment['paid_at'], 0, 10) . ' 00:00:00'))
                            : '—' ?>
                    </dd>

                    <dt class="col-sm-5 text-muted fw-normal">Sender name</dt>
                    <dd class="col-sm-7"><?= e((string) ($payment['sender_name'] ?? '—')) ?></dd>

                    <dt class="col-sm-5 text-muted fw-normal">Sending bank</dt>
                    <dd class="col-sm-7"><?= e((string) ($payment['bank_name'] ?? '—')) ?></dd>

                    <dt class="col-sm-5 text-muted fw-normal">Reference</dt>
                    <dd class="col-sm-7">
                        <?= ($payment['reference'] ?? '') !== '' ? '<code>' . e((string) $payment['reference']) . '</code>' : '—' ?>
                    </dd>

                    <dt class="col-sm-5 text-muted fw-normal">Submitted</dt>
                    <dd class="col-sm-7"><?= e(fmt_dt((string) $payment['submitted_at'])) ?></dd>

                    <?php if (($payment['note'] ?? '') !== ''): ?>
                        <dt class="col-sm-5 text-muted fw-normal">Author note</dt>
                        <dd class="col-sm-7 mb-0"><?= nl2br(e((string) $payment['note'])) ?></dd>
                    <?php endif; ?>
                    <?php foreach (($extraAnswers ?? []) as $answer): ?>
                        <dt class="col-sm-5 text-muted fw-normal"><?= e($answer['label']) ?></dt>
                        <dd class="col-sm-7 mb-0"><?= e($answer['value']) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-paperclip text-muted"></i>
                <h2>Proof of transfer</h2>
                <?php if ($proof !== null): ?>
                    <a class="btn btn-sm btn-outline-secondary ms-auto" target="_blank" rel="noopener"
                       href="<?= e(app_url('public/file.php?id=' . (int) $proof['id'])) ?>">
                        <i class="fa-solid fa-up-right-from-square me-1"></i>Open original
                    </a>
                <?php endif; ?>
            </div>
            <div class="cn-card-body">
                <?php if ($proof === null): ?>
                    <div class="cn-empty">
                        <i class="fa-regular fa-folder-open"></i>
                        No proof uploaded.
                    </div>
                <?php else: ?>
                    <?php if ($isImage): ?>
                        <a href="<?= e(app_url('public/file.php?id=' . (int) $proof['id'])) ?>" target="_blank" rel="noopener">
                            <img src="<?= e(app_url('public/file.php?id=' . (int) $proof['id'])) ?>"
                                 alt="Proof of transfer"
                                 style="max-width:100%;border:1px solid var(--cn-border);border-radius:8px">
                        </a>
                    <?php else: ?>
                        <object data="<?= e(app_url('public/file.php?id=' . (int) $proof['id'])) ?>"
                                type="<?= e((string) $proof['mime']) ?>"
                                style="width:100%;height:460px;border:1px solid var(--cn-border);border-radius:8px">
                            <p class="text-muted p-3" style="font-size:13px">
                                Preview not available in this browser — use “Open original”.
                            </p>
                        </object>
                    <?php endif; ?>
                    <div class="text-muted mt-2" style="font-size:11.5px">
                        <?= e((string) $proof['original_name']) ?> &middot;
                        <?= e(FileRepo::humanSize((int) $proof['size'])) ?> &middot;
                        <?= e((string) $proof['mime']) ?> &middot;
                        uploaded <?= e(fmt_dt((string) $proof['uploaded_at'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-user text-muted"></i>
                <h2>Author</h2>
            </div>
            <div class="cn-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-5 text-muted fw-normal">Paper</dt>
                    <dd class="col-7"><code><?= e((string) $payment['paper_id']) ?></code></dd>
                    <dt class="col-5 text-muted fw-normal">Name</dt>
                    <dd class="col-7"><?= e((string) $payment['first_author_name']) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Email</dt>
                    <dd class="col-7">
                        <a href="mailto:<?= e((string) $payment['email']) ?>"><?= e((string) $payment['email']) ?></a>
                    </dd>
                    <dt class="col-5 text-muted fw-normal">Affiliation</dt>
                    <dd class="col-7 mb-0"><?= e((string) ($payment['affiliation'] ?? '—')) ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($receipt !== null): ?>
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-receipt text-muted"></i>
                    <h2>Receipt</h2>
                    <span class="cn-badge cn-badge-paid ms-auto">Issued</span>
                </div>
                <div class="cn-card-body">
                    <dl class="row mb-3" style="font-size:13px">
                        <dt class="col-5 text-muted fw-normal">Number</dt>
                        <dd class="col-7"><code><?= e((string) $receipt['number']) ?></code></dd>
                        <dt class="col-5 text-muted fw-normal">Issued</dt>
                        <dd class="col-7"><?= e(fmt_dt((string) $receipt['issued_at'])) ?></dd>
                        <dt class="col-5 text-muted fw-normal">Amount</dt>
                        <dd class="col-7 mb-0">
                            <b><?= e(fmt_money((float) $receipt['amount'], (string) $receipt['currency'])) ?></b>
                        </dd>
                    </dl>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                       href="<?= e(admin_url('receipt-pdf.php?id=' . (int) $receipt['id'])) ?>">
                        <i class="fa-solid fa-file-pdf me-1"></i>Open receipt PDF
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-gavel text-muted"></i>
                <h2>Decision</h2>
            </div>
            <div class="cn-card-body">
                <?php if ($confirmed): ?>
                    <div class="alert alert-success py-2 px-3" style="font-size:12.5px">
                        <i class="fa-solid fa-circle-check me-1"></i>
                        Confirmed on <?= e(fmt_dt($payment['confirmed_at'] ?? null)) ?>. The invoice is marked paid and
                        the receipt has been issued.
                    </div>
                <?php else: ?>
                    <form method="post" class="mb-3">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-navy w-100">
                            <i class="fa-solid fa-check me-1"></i>Confirm payment &amp; issue receipt
                        </button>
                        <p class="text-muted mt-2 mb-0" style="font-size:11.5px">
                            Check the amount against your bank statement first — confirming marks the invoice paid.
                        </p>
                    </form>

                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="reject">
                        <label class="form-label" for="reason" style="font-size:12.5px">
                            Ask for a correction — the note below is emailed to the author
                        </label>
                        <textarea class="form-control mb-2" id="reason" name="reason" rows="3" maxlength="1000"
                                  placeholder="e.g. The transfer proof shows a different amount than the invoice total."></textarea>
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fa-solid fa-rotate-left me-1"></i>Return to the author
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
