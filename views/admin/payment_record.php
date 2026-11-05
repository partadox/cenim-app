<?php
/**
 * @var array      $invoice   InvoiceRepo::findWithContext()
 * @var array|null $existing  pembayaran yang dikembalikan ke author (bila ada)
 * @var array      $values
 * @var array<string,string> $errors
 */

use Cenim\Core\Csrf;

$err = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>' : '';
$cls = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$currency = (string) $invoice['currency'];
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(admin_url('payments.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to payments
    </a>
    <a class="btn btn-sm btn-outline-secondary ms-auto" target="_blank" rel="noopener"
       href="<?= e(admin_url('invoice-pdf.php?id=' . (int) $invoice['id'])) ?>">
        <i class="fa-solid fa-file-invoice me-1"></i>Invoice PDF
    </a>
</div>

<div class="alert alert-warning" style="font-size:13px">
    <i class="fa-solid fa-user-shield me-1"></i>
    Use this when the author <b>has paid but did not upload the proof</b>. Check the transfer in the bank statement first:
    saving marks invoice <b><?= e((string) $invoice['number']) ?></b> as paid and issues the receipt right away.
    <?php if ($existing !== null): ?>
        <div class="mt-1">The payment the author uploaded earlier was returned; it is replaced by this record.</div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" enctype="multipart/form-data" class="cn-card" novalidate
              onsubmit="return confirm('Record this payment, mark the invoice as paid and issue the receipt?');">
            <?= Csrf::field() ?>
            <div class="cn-card-head">
                <i class="fa-solid fa-money-check-dollar text-muted"></i>
                <h2>Payment received</h2>
            </div>
            <div class="cn-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="amount">Amount received <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><?= e($currency) ?></span>
                            <input type="text" inputmode="decimal" class="form-control<?= $cls('amount') ?>" id="amount" name="amount" value="<?= e($values['amount']) ?>">
                        </div>
                        <?= $err('amount') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="paid_at">Transfer date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control<?= $cls('paid_at') ?>" id="paid_at" name="paid_at" value="<?= e($values['paid_at']) ?>">
                        <?= $err('paid_at') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sender_name">Sender name</label>
                        <input class="form-control" id="sender_name" name="sender_name" maxlength="190" value="<?= e($values['sender_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="bank_name">Sending bank</label>
                        <input class="form-control" id="bank_name" name="bank_name" maxlength="120" value="<?= e($values['bank_name']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="reference">Reference / transaction number</label>
                        <input class="form-control" id="reference" name="reference" maxlength="120" value="<?= e($values['reference']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="note">Note <span class="text-danger">*</span></label>
                        <textarea class="form-control<?= $cls('note') ?>" id="note" name="note" rows="3" maxlength="900"
                                  placeholder="e.g. Transfer found in the bank statement on 3 Nov 2026; the author did not upload the proof."><?= e($values['note']) ?></textarea>
                        <?= $err('note') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="payment_proof">Proof of transfer <span class="text-muted">(optional, e.g. a screenshot of the bank statement)</span></label>
                        <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text" style="font-size:11.5px"><?= e(upload_hint()) ?></div>
                    </div>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="notify" name="notify" value="1" <?= $values['notify'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify" style="font-size:13px">Email the payment confirmation to the author</label>
                </div>
                <button type="submit" class="btn btn-navy mt-3"><i class="fa-solid fa-check me-1"></i>Mark as paid &amp; issue receipt</button>
            </div>
        </form>
    </div>

    <div class="col-xl-5">
        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-file-invoice text-muted"></i>
                <h2>Invoice</h2>
            </div>
            <div class="cn-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-5 text-muted fw-normal">Number</dt>
                    <dd class="col-7"><code><?= e((string) $invoice['number']) ?></code></dd>
                    <dt class="col-5 text-muted fw-normal">Total</dt>
                    <dd class="col-7"><b><?= e(fmt_money((float) $invoice['total'], $currency)) ?></b></dd>
                    <dt class="col-5 text-muted fw-normal">Issued</dt>
                    <dd class="col-7"><?= e(fmt_date((string) $invoice['issued_at'])) ?></dd>
                    <dt class="col-5 text-muted fw-normal">Paper</dt>
                    <dd class="col-7"><code><?= e((string) $invoice['paper_id']) ?></code></dd>
                    <dt class="col-5 text-muted fw-normal">Author</dt>
                    <dd class="col-7"><?= e((string) $invoice['first_author_name']) ?>
                        <div class="text-muted" style="font-size:12px"><?= e((string) $invoice['email']) ?></div></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
