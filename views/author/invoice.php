<?php
/**
 * @var array|null $registration
 * @var array|null $invoice
 * @var array<int,array<string,mixed>> $items
 * @var array<int,array<string,mixed>> $banks
 * @var string     $contact
 */

use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\RegistrationRepo;

$status = (string) ($registration['status'] ?? RegistrationRepo::STATUS_DRAFT);
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= e(author_url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <?php if ($invoice !== null): ?>
        <span class="cn-badge <?= e(InvoiceRepo::statusBadgeClass((string) $invoice['status'])) ?>">
            <?= e(ucfirst((string) $invoice['status'])) ?>
        </span>
    <?php endif; ?>
</div>

<?php if ($invoice === null): ?>
    <div class="cn-card">
        <div class="cn-card-body">
            <div class="cn-empty">
                <i class="fa-regular fa-file-lines"></i>
                <?php if ($status === RegistrationRepo::STATUS_VERIFIED): ?>
                    Your registration is verified. The committee is preparing your invoice — you will receive an
                    email as soon as it is issued.
                <?php elseif ($status === RegistrationRepo::STATUS_SUBMITTED): ?>
                    Your registration is waiting for verification. The invoice is issued after it is verified.
                <?php else: ?>
                    Please complete and submit the registration form first.
                    <div class="mt-3">
                        <a href="<?= e(author_url('registration.php')) ?>" class="btn btn-sm btn-gold">
                            Open the registration form<i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-invoice text-muted"></i>
                    <h2>Invoice <?= e((string) $invoice['number']) ?></h2>
                    <a class="btn btn-sm btn-navy ms-auto" href="<?= e(author_url('invoice-pdf.php?download=1')) ?>">
                        <i class="fa-solid fa-file-pdf me-1"></i>Download PDF
                    </a>
                </div>
                <div class="cn-card-body">
                    <dl class="row mb-3" style="font-size:13px">
                        <dt class="col-sm-4 text-muted fw-normal">Issued</dt>
                        <dd class="col-sm-8"><?= e(fmt_date((string) $invoice['issued_at'])) ?></dd>
                        <dt class="col-sm-4 text-muted fw-normal">Payment due</dt>
                        <dd class="col-sm-8">
                            <?= $invoice['due_date'] !== null
                                ? e(fmt_date((string) $invoice['due_date'] . ' 00:00:00'))
                                : '<span class="text-muted">as soon as possible</span>' ?>
                        </dd>
                        <dt class="col-sm-4 text-muted fw-normal">Paper</dt>
                        <dd class="col-sm-8 mb-0"><code><?= e((string) $author['paper_id']) ?></code></dd>
                    </dl>

                    <table class="table table-sm mb-0" style="font-size:13px">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-center" style="width:60px">Qty</th>
                                <th class="text-end" style="width:120px">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e((string) $item['description']) ?></td>
                                    <td class="text-center"><?= e((string) (int) $item['qty']) ?></td>
                                    <td class="text-end">
                                        <?= e(fmt_money((float) $item['amount'], (string) $invoice['currency'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <?php if ((float) $invoice['discount'] > 0): ?>
                                <tr>
                                    <td colspan="2" class="text-end text-muted">Discount</td>
                                    <td class="text-end">
                                        - <?= e(fmt_money((float) $invoice['discount'], (string) $invoice['currency'])) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr style="border-top:2px solid var(--cn-border)">
                                <th colspan="2" class="text-end">Total due</th>
                                <th class="text-end" style="color:var(--cn-navy);font-size:15px">
                                    <?= e(fmt_money((float) $invoice['total'], (string) $invoice['currency'])) ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-building-columns text-muted"></i>
                    <h2>How to pay</h2>
                </div>
                <div class="cn-card-body">
                    <?php
                    $matching = array_values(array_filter(
                        $banks,
                        static fn ($bank): bool => is_array($bank)
                            && ($bank['currency'] ?? '') === (string) $invoice['currency']
                            && trim((string) ($bank['account_no'] ?? '')) !== ''
                    ));
                    ?>

                    <?php if ($matching === []): ?>
                        <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:12.5px">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Payment details for <?= e((string) $invoice['currency']) ?> are not published yet. Please
                            contact the secretariat<?= $contact !== '' ? ' at ' . e($contact) : '' ?> before transferring.
                        </div>
                    <?php else: ?>
                        <?php foreach ($matching as $bank): ?>
                            <dl class="row mb-2" style="font-size:13px">
                                <dt class="col-5 text-muted fw-normal">Bank</dt>
                                <dd class="col-7"><?= e((string) ($bank['bank_name'] ?? '—')) ?></dd>
                                <dt class="col-5 text-muted fw-normal">Account name</dt>
                                <dd class="col-7"><?= e((string) ($bank['account_name'] ?? '—')) ?></dd>
                                <dt class="col-5 text-muted fw-normal">Account number</dt>
                                <dd class="col-7"><code><?= e((string) ($bank['account_no'] ?? '—')) ?></code></dd>
                                <?php if (trim((string) ($bank['swift'] ?? '')) !== ''): ?>
                                    <dt class="col-5 text-muted fw-normal">SWIFT / BIC</dt>
                                    <dd class="col-7"><code><?= e((string) $bank['swift']) ?></code></dd>
                                <?php endif; ?>
                            </dl>
                        <?php endforeach; ?>
                        <p class="text-muted mb-0" style="font-size:12px">
                            Transfer the full amount and write the invoice number
                            <b><?= e((string) $invoice['number']) ?></b> in the transfer description. Bank charges are
                            borne by the registrant.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-money-check-dollar text-muted"></i>
                    <h2>Next step</h2>
                </div>
                <div class="cn-card-body text-muted" style="font-size:13px">
                    <?php if ((string) $invoice['status'] === InvoiceRepo::STATUS_PAID): ?>
                        Your payment has been confirmed. Your receipt is available in the author area.
                    <?php else: ?>
                        After transferring, come back and submit your proof of payment so the committee can confirm it
                        and issue your official receipt.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
