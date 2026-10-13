<?php
/**
 * @var array $edition
 * @var array $period
 * @var array<string,array<string,mixed>> $banks
 * @var array<string,mixed> $values
 */

use Cenim\Core\Csrf;
?>

<form method="post">
    <?= Csrf::field() ?>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-toggle-on text-muted"></i>
                    <h2>Open periods</h2>
                </div>
                <div class="cn-card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="registration_open" name="registration_open" value="1"
                            <?= $values['registration_open'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="registration_open">
                            Registration form open for authors
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="payment_open" name="payment_open" value="1"
                            <?= $values['payment_open'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="payment_open">
                            Payment submission open for authors
                        </label>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" id="attendance_open" name="attendance_open" value="1"
                                <?= $values['attendance_open'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="attendance_open">Video presentation form open (after payment)</label>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label for="attendance_deadline" class="text-muted" style="font-size:12.5px">until</label>
                            <input type="date" class="form-control form-control-sm" id="attendance_deadline" name="attendance_deadline"
                                   value="<?= e((string) $values['attendance_deadline']) ?>" style="width:160px">
                        </div>
                    </div>

                    <div class="border rounded p-2 px-3 mb-3" style="background:var(--cn-bg, #f7f9fc)">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="shirt_size_enabled" name="shirt_size_enabled" value="1"
                                <?= $values['shirt_size_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="shirt_size_enabled">
                                <i class="fa-solid fa-shirt me-1 text-muted"></i>Ask the <b>shirt size</b> of everyone attending (registration form)
                            </label>
                        </div>
                        <label class="form-label mb-1" for="shirt_sizes" style="font-size:12px">Sizes offered, comma separated</label>
                        <input type="text" class="form-control form-control-sm" id="shirt_sizes" name="shirt_sizes" maxlength="200"
                               value="<?= e((string) $values['shirt_sizes']) ?>" placeholder="XS, S, M, L, XL, XXL, 3XL">
                        <div class="form-text" style="font-size:11.5px">Asked for the presenter and each additional attendee. Recap in Presentations.</div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="period_start" style="font-size:12.5px">Registration period start</label>
                            <input type="date" class="form-control form-control-sm" id="period_start" name="period_start"
                                   value="<?= e((string) ($period['start'] ?? '')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="period_end" style="font-size:12.5px">Registration period end</label>
                            <input type="date" class="form-control form-control-sm" id="period_end" name="period_end"
                                   value="<?= e((string) ($period['end'] ?? '')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="invoice_due_days" style="font-size:12.5px">Invoice due (days)</label>
                            <input type="number" class="form-control form-control-sm" id="invoice_due_days"
                                   name="invoice_due_days" value="<?= e((string) $values['invoice_due_days']) ?>" min="0" max="180">
                        </div>
                    </div>
                    <div class="form-text mt-2" style="font-size:11.5px">
                        The dates are informational; what actually opens or closes the forms are the two switches above.
                    </div>
                </div>
            </div>

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-regular fa-calendar text-muted"></i>
                    <h2>Conference &amp; certificates</h2>
                </div>
                <div class="cn-card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="conference_start" style="font-size:12.5px">Conference start</label>
                            <input type="date" class="form-control form-control-sm" id="conference_start" name="conference_start"
                                   value="<?= e(substr((string) ($edition['conference_start'] ?? ''), 0, 10)) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="conference_end" style="font-size:12.5px">Conference end</label>
                            <input type="date" class="form-control form-control-sm" id="conference_end" name="conference_end"
                                   value="<?= e(substr((string) ($edition['conference_end'] ?? ''), 0, 10)) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="certificate_release_at" style="font-size:12.5px">
                                Certificates available to authors from (UTC)
                            </label>
                            <input type="datetime-local" class="form-control form-control-sm" id="certificate_release_at"
                                   name="certificate_release_at"
                                   value="<?= e(str_replace(' ', 'T', substr((string) ($edition['certificate_release_at'] ?? ''), 0, 16))) ?>">
                            <div class="form-text" style="font-size:11.5px">
                                Before this moment authors only see a notice; you can still issue and download certificates.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-address-book text-muted"></i>
                    <h2>Contact &amp; signatory</h2>
                </div>
                <div class="cn-card-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="contact_email" style="font-size:12.5px">Secretariat email</label>
                            <input type="email" class="form-control form-control-sm" id="contact_email" name="contact_email"
                                   value="<?= e((string) $values['contact_email']) ?>" maxlength="190">
                            <div class="form-text" style="font-size:11px">Shown on the sign-in page and documents; also the email Reply-To and the closing email unless set separately.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact_whatsapp" style="font-size:12.5px">WhatsApp</label>
                            <input type="text" class="form-control form-control-sm" id="contact_whatsapp" name="contact_whatsapp"
                                   value="<?= e((string) $values['contact_whatsapp']) ?>" maxlength="40">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="signatory_name" style="font-size:12.5px">Signatory name</label>
                            <input type="text" class="form-control form-control-sm" id="signatory_name" name="signatory_name"
                                   value="<?= e((string) $values['signatory_name']) ?>" maxlength="190">
                            <div class="form-text" style="font-size:11.5px">Printed on receipts and certificates.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="signatory_title" style="font-size:12.5px">Signatory title</label>
                            <input type="text" class="form-control form-control-sm" id="signatory_title" name="signatory_title"
                                   value="<?= e((string) $values['signatory_title']) ?>" maxlength="190">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-building-columns text-muted"></i>
                    <h2>Bank accounts</h2>
                </div>
                <div class="cn-card-body">
                    <p class="text-muted" style="font-size:12px">
                        Printed on invoices and shown on the payment page for the matching currency.
                    </p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="bank_public" name="bank_public" value="1"
                            <?= $values['bank_public'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="bank_public" style="font-size:12.5px">
                            Also show these accounts on the <b>public registration page</b>
                            <span class="text-muted d-block" style="font-size:11.5px">Leave off until the official accounts are filled in.</span>
                        </label>
                    </div>
                    <?php foreach (['IDR', 'USD'] as $currency): ?>
                        <?php $bank = $banks[$currency] ?? []; ?>
                        <div class="mb-3 pb-3" style="border-bottom:1px solid var(--cn-border)">
                            <div class="cn-stat-label mb-2"><?= e($currency) ?></div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" class="form-control form-control-sm" name="bank_<?= e($currency) ?>_name"
                                           value="<?= e((string) ($bank['bank_name'] ?? '')) ?>" placeholder="Bank name">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control form-control-sm" name="bank_<?= e($currency) ?>_holder"
                                           value="<?= e((string) ($bank['account_name'] ?? '')) ?>" placeholder="Account holder">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control form-control-sm" name="bank_<?= e($currency) ?>_number"
                                           value="<?= e((string) ($bank['account_no'] ?? '')) ?>" placeholder="Account number">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control form-control-sm" name="bank_<?= e($currency) ?>_swift"
                                           value="<?= e((string) ($bank['swift'] ?? '')) ?>" placeholder="SWIFT">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control form-control-sm" name="bank_<?= e($currency) ?>_branch"
                                           value="<?= e((string) ($bank['branch'] ?? '')) ?>" placeholder="Branch">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-regular fa-file-lines text-muted"></i>
                    <h2>Document notes</h2>
                </div>
                <div class="cn-card-body">
                    <label class="form-label" for="invoice_note" style="font-size:12.5px">Invoice terms</label>
                    <textarea class="form-control form-control-sm mb-3" id="invoice_note" name="invoice_note" rows="3"><?= e((string) $values['invoice_note']) ?></textarea>

                    <label class="form-label" for="receipt_note" style="font-size:12.5px">Receipt note</label>
                    <textarea class="form-control form-control-sm" id="receipt_note" name="receipt_note" rows="2"><?= e((string) $values['receipt_note']) ?></textarea>
                </div>
            </div>

            <div class="cn-card mt-3">
                <div class="cn-card-head">
                    <i class="fa-regular fa-envelope text-muted"></i>
                    <h2>Email</h2>
                </div>
                <div class="cn-card-body">
                    <?php $sender = Cenim\Core\Mailer::from(); ?>
                    <div class="form-text mb-2" style="font-size:11.5px">
                        Sent from <code><?= e($sender['email'] !== '' ? $sender['email'] : '(SMTP_SENDER not set)') ?></code>
                        (fixed in <code>.env</code>, must be a domain verified in ZeptoMail).
                        Tokens: <code>{edition_name}</code> <code>{edition_year}</code>.
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="mail_sender_name" style="font-size:12.5px">Sender name (From)</label>
                            <input type="text" class="form-control form-control-sm" id="mail_sender_name" name="mail_sender_name"
                                   value="<?= e((string) $values['mail_sender_name']) ?>" placeholder="{edition_name}" maxlength="190">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mail_reply_to" style="font-size:12.5px">Reply-To address</label>
                            <input type="email" class="form-control form-control-sm" id="mail_reply_to" name="mail_reply_to"
                                   value="<?= e((string) $values['mail_reply_to']) ?>" placeholder="same as Secretariat email" maxlength="190">
                        </div>
                    </div>

                    <div class="mt-3 mb-1" style="font-size:12.5px;font-weight:600">Closing (Regards) of every email</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="mail_signature_role" style="font-size:12px">Position</label>
                            <input type="text" class="form-control form-control-sm" id="mail_signature_role" name="mail_signature_role"
                                   value="<?= e((string) $values['mail_signature_role']) ?>" placeholder="General Chair {edition_name}" maxlength="190">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mail_signature_name" style="font-size:12px">Name</label>
                            <input type="text" class="form-control form-control-sm" id="mail_signature_name" name="mail_signature_name"
                                   value="<?= e((string) $values['mail_signature_name']) ?>" maxlength="190">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mail_signature_email" style="font-size:12px">Email</label>
                            <input type="email" class="form-control form-control-sm" id="mail_signature_email" name="mail_signature_email"
                                   value="<?= e((string) $values['mail_signature_email']) ?>" placeholder="same as Secretariat email" maxlength="190">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mail_signature_web" style="font-size:12px">Website</label>
                            <input type="text" class="form-control form-control-sm" id="mail_signature_web" name="mail_signature_web"
                                   value="<?= e((string) $values['mail_signature_web']) ?>" placeholder="<?= e(rtrim(abs_url(''), '/') . '/') ?>" maxlength="190">
                        </div>
                    </div>
                    <div class="mt-2 p-2 border rounded bg-light" style="font-size:12px;white-space:pre-line"><?= e(implode("\n", Cenim\Core\Mailer::signatureLines())) ?></div>
                    <div class="form-text" style="font-size:11px">Preview of the saved closing (with tokens filled in).</div>
                </div>
            </div>

            <div class="cn-card mt-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-envelope-open-text text-muted"></i>
                    <h2>Letter of Acceptance</h2>
                </div>
                <div class="cn-card-body">
                    <label class="form-label" for="loa_number_base" style="font-size:12.5px">LoA number base</label>
                    <input type="text" class="form-control form-control-sm" id="loa_number_base" name="loa_number_base" maxlength="120"
                           value="<?= e((string) $values['loa_number_base']) ?>" placeholder="e.g. 1234/IT2.VII/HK.01.00/2026">
                    <div class="form-text mb-3" style="font-size:11.5px">
                        Numbers become <code>base-001</code>, <code>base-002</code>, … in the order papers first open their LoA.
                        Empty = <code>CENIM26/LOA/0001</code>. A number never changes once given.
                    </div>

                    <label class="form-label" for="loa_body" style="font-size:12.5px">Letter text</label>
                    <textarea class="form-control form-control-sm" id="loa_body" name="loa_body" rows="7"
                              placeholder="<?= e(Cenim\Domain\LoaRepo::DEFAULT_BODY) ?>"><?= e((string) $values['loa_body']) ?></textarea>
                    <div class="form-text" style="font-size:11.5px">
                        Empty = the default text shown as placeholder. Tokens:
                        <code>{paper_id}</code> <code>{paper_title}</code> <code>{authors}</code> <code>{track}</code>
                        <code>{edition_name}</code> <code>{conference_dates}</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 my-4">
        <button type="submit" class="btn btn-navy">
            <i class="fa-solid fa-floppy-disk me-1"></i>Save settings
        </button>
        <a href="<?= e(admin_url('health.php')) ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-heart-pulse me-1"></i>Health check
        </a>
    </div>
</form>

<div class="cn-card mt-3" id="user-guide">
    <div class="cn-card-head">
        <i class="fa-regular fa-file-pdf text-muted"></i>
        <h2>User guide (PDF)</h2>
    </div>
    <div class="cn-card-body">
        <p class="text-muted" style="font-size:12.5px">
            Linked under "Cannot sign in?" on the author &amp; committee sign-in page, so authors can read how to use the portal.
            <?= e(upload_hint('PDF only')) ?>.
        </p>
        <?php if (!empty($guide) && ($guide['deleted_at'] ?? null) === null): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3" style="font-size:13px">
                <i class="fa-regular fa-file-pdf" style="color:#c0392b"></i>
                <a href="<?= e(public_file_url($guide)) ?>" target="_blank" rel="noopener"><?= e((string) $guide['original_name']) ?></a>
                <span class="text-muted">(<?= e(Cenim\Domain\FileRepo::humanSize((int) $guide['size'])) ?>, <?= e(fmt_dt((string) $guide['uploaded_at'])) ?>)</span>
                <form method="post" class="ms-auto m-0" onsubmit="return confirm('Remove the user guide link from the sign-in page?')">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="guide_remove">
                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Remove</button>
                </form>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="guide_upload">
            <input type="file" class="form-control form-control-sm" name="guide" accept="application/pdf" required style="max-width:420px">
            <button class="btn btn-sm btn-navy"><i class="fa-solid fa-upload me-1"></i><?= !empty($guide) ? 'Replace' : 'Upload' ?></button>
        </form>
    </div>
</div>

