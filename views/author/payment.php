<?php
/**
 * @var array      $invoice
 * @var array|null $payment
 * @var bool       $editable
 * @var bool       $isOpen
 * @var bool       $isPaid
 * @var array      $values
 * @var array<string,string> $errors
 * @var array|null $proof
 * @var array<int,array<string,mixed>> $banks
 */

use Cenim\Core\Csrf;
use Cenim\Domain\FileRepo;
use Cenim\Domain\PaymentRepo;

$status   = (string) ($payment['status'] ?? '');
$disabled = $editable && $isOpen ? '' : 'disabled';

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>'
        : '';
};
$cls = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(author_url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <?php if ($status !== ''): ?>
        <span class="cn-badge <?= e(PaymentRepo::statusBadgeClass($status)) ?>">
            Payment <?= e($status) ?>
        </span>
    <?php endif; ?>
    <a href="<?= e(author_url('invoice.php')) ?>" class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="fa-solid fa-file-invoice me-1"></i>View invoice
    </a>
</div>

<?php if ($isPaid): ?>
    <div class="alert alert-success" style="font-size:13px">
        <i class="fa-solid fa-circle-check me-1"></i>
        Your payment has been confirmed. Your receipt is ready in the
        <a href="<?= e(author_url('receipt.php')) ?>">receipt page</a>.
    </div>
<?php elseif ($status === PaymentRepo::STATUS_REJECTED && ($payment['reject_reason'] ?? '') !== ''): ?>
    <div class="alert alert-danger" style="font-size:13px">
        <b><i class="fa-solid fa-circle-exclamation me-1"></i>The committee could not confirm this payment</b>
        <div class="mt-1"><?= nl2br(e((string) $payment['reject_reason'])) ?></div>
        <div class="mt-1 text-muted">Please correct the details below and submit again.</div>
    </div>
<?php elseif ($status === PaymentRepo::STATUS_SUBMITTED): ?>
    <div class="alert alert-info" style="font-size:13px">
        <i class="fa-solid fa-hourglass-half me-1"></i>
        Your proof of payment is being verified against our bank statement. You will receive an email with the result.
    </div>
<?php endif; ?>

<?php if (!$isOpen): ?>
    <div class="alert alert-warning" style="font-size:13px">
        <i class="fa-solid fa-lock me-1"></i>Payment submission is currently closed.
    </div>
<?php endif; ?>

<?php if (($payment['filled_by_name'] ?? '') !== ''): ?>
    <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:12.5px">
        <i class="fa-solid fa-user-pen me-1" style="color:var(--cn-navy)"></i>
        Last filled in by <b><?= e((string) $payment['filled_by_name']) ?></b>
        <?php if (($payment['updated_at'] ?? '') !== ''): ?>&middot; <?= e(fmt_dt((string) $payment['updated_at'])) ?><?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
    <?= Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-money-check-dollar text-muted"></i>
                    <h2>Transfer details</h2>
                </div>
                <div class="cn-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="amount_paid">
                                Amount transferred (<?= e((string) $invoice['currency']) ?>) <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control<?= $cls('amount_paid') ?>" id="amount_paid"
                                   name="amount_paid" value="<?= e((string) $values['amount_paid']) ?>"
                                   inputmode="decimal" <?= $disabled ?>>
                            <?= $err('amount_paid') ?>
                            <div class="form-text" style="font-size:11.5px">
                                Invoice total: <b><?= e(fmt_money((float) $invoice['total'], (string) $invoice['currency'])) ?></b>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="paid_at">Transfer date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control<?= $cls('paid_at') ?>" id="paid_at" name="paid_at"
                                   value="<?= e(substr((string) $values['paid_at'], 0, 10)) ?>" <?= $disabled ?>>
                            <?= $err('paid_at') ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="sender_name">Name on the sending account <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $cls('sender_name') ?>" id="sender_name"
                                   name="sender_name" value="<?= e((string) $values['sender_name']) ?>" maxlength="190" <?= $disabled ?>>
                            <?= $err('sender_name') ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="bank_name">Sending bank</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name"
                                   value="<?= e((string) $values['bank_name']) ?>" maxlength="120" <?= $disabled ?>>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="method">Payment method</label>
                            <select class="form-select" id="method" name="method" <?= $disabled ?>>
                                <?php foreach (PaymentRepo::METHODS as $method): ?>
                                    <option value="<?= e($method) ?>" <?= $values['method'] === $method ? 'selected' : '' ?>>
                                        <?= e(PaymentRepo::methodLabel($method)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="reference">Transfer reference / trace number</label>
                            <input type="text" class="form-control" id="reference" name="reference"
                                   value="<?= e((string) $values['reference']) ?>" maxlength="120" <?= $disabled ?>>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="note">Note for the committee</label>
                            <textarea class="form-control" id="note" name="note" rows="2" maxlength="500" <?= $disabled ?>><?= e((string) $values['note']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (($extraFields ?? []) !== []): ?>
                <div class="cn-card mb-3">
                    <div class="cn-card-head">
                        <i class="fa-solid fa-square-check text-muted"></i>
                        <h2>Additional questions</h2>
                    </div>
                    <div class="cn-card-body">
                        <?= Cenim\Core\View::partial('author/_extra_fields', [
                            'extraFields' => $extraFields,
                            'extraValues' => $values['extra'] ?? [],
                            'errors'      => $errors,
                            'disabled'    => $disabled,
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-arrow-up text-muted"></i>
                    <h2>Proof of transfer</h2>
                </div>
                <div class="cn-card-body">
                    <label class="form-label" for="payment_proof">
                        Bank receipt or screenshot <span class="text-danger">*</span>
                    </label>
                    <input type="file" class="form-control<?= $cls('payment_proof') ?>" id="payment_proof"
                           name="payment_proof" accept=".pdf,.jpg,.jpeg,.png" <?= $disabled ?>>
                    <?= $err('payment_proof') ?>

                    <?php if ($proof !== null): ?>
                        <div class="d-flex align-items-center gap-2 mt-2" style="font-size:12.5px">
                            <i class="fa-solid fa-paperclip text-muted"></i>
                            <a href="<?= e(app_url('public/file.php?id=' . (int) $proof['id'])) ?>" target="_blank" rel="noopener">
                                <?= e((string) $proof['original_name']) ?>
                            </a>
                            <span class="text-muted">
                                <?= e(FileRepo::humanSize((int) $proof['size'])) ?> &middot;
                                <?= e(fmt_dt((string) $proof['uploaded_at'])) ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="text-muted mt-2" style="font-size:12px">
                            <i class="fa-regular fa-circle-xmark me-1"></i>Not uploaded yet.
                        </div>
                    <?php endif; ?>

                    <div class="form-text mt-2" style="font-size:11.5px">
                        <?= e(upload_hint('PDF, JPG or PNG')) ?>.
                        Make sure the amount, date, and destination account are readable.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="cn-card mb-3" style="position:sticky;top:16px">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-invoice text-muted"></i>
                    <h2>Invoice <?= e((string) $invoice['number']) ?></h2>
                </div>
                <div class="cn-card-body">
                    <dl class="row mb-3" style="font-size:13px">
                        <dt class="col-6 text-muted fw-normal">Amount due</dt>
                        <dd class="col-6 text-end">
                            <b style="color:var(--cn-navy)">
                                <?= e(fmt_money((float) $invoice['total'], (string) $invoice['currency'])) ?>
                            </b>
                        </dd>
                        <dt class="col-6 text-muted fw-normal">Payment due date</dt>
                        <dd class="col-6 text-end mb-0">
                            <?= $invoice['due_date'] !== null
                                ? e(fmt_date((string) $invoice['due_date'] . ' 00:00:00'))
                                : '—' ?>
                        </dd>
                    </dl>

                    <?php
                    $matching = array_values(array_filter(
                        $banks,
                        static fn ($bank): bool => is_array($bank)
                            && ($bank['currency'] ?? '') === (string) $invoice['currency']
                            && trim((string) ($bank['account_no'] ?? '')) !== ''
                    ));
                    ?>
                    <?php if ($matching !== []): ?>
                        <div class="cn-stat-label mb-1">Destination account</div>
                        <?php foreach ($matching as $bank): ?>
                            <div style="font-size:12.5px" class="mb-2">
                                <?= e((string) ($bank['bank_name'] ?? '')) ?><br>
                                <code><?= e((string) ($bank['account_no'] ?? '')) ?></code><br>
                                <span class="text-muted"><?= e((string) ($bank['account_name'] ?? '')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($editable && $isOpen): ?>
                        <button type="submit" class="btn btn-navy w-100">
                            <i class="fa-solid fa-paper-plane me-1"></i>Submit proof of payment
                        </button>
                        <p class="text-muted mt-2 mb-0" style="font-size:11.5px">
                            After submitting you cannot edit it unless the committee asks for a correction.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>
