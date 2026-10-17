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
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-1">
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" id="confirmation_open" name="confirmation_open" value="1"
                                <?= $values['confirmation_open'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="confirmation_open">Confirmation form open (step 0)</label>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label for="confirmation_deadline" class="text-muted" style="font-size:12.5px">until</label>
                            <input type="date" class="form-control form-control-sm" id="confirmation_deadline" name="confirmation_deadline"
                                   value="<?= e((string) $values['confirmation_deadline']) ?>" style="width:160px">
                        </div>
                    </div>
                    <div class="form-check mb-3 ms-4">
                        <input class="form-check-input" type="checkbox" id="confirmation_required" name="confirmation_required" value="1"
                            <?= $values['confirmation_required'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="confirmation_required" style="font-size:13px">
                            Registration form opens only after the confirmation form is submitted
                        </label>
                        <div class="form-text" style="font-size:11.5px">Turn off to let papers that missed the deadline register without a confirmation.</div>
                    </div>
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
                                <i class="fa-solid fa-shirt me-1 text-muted"></i>Ask the <b>shirt size</b> of everyone attending (confirmation and registration form)
                            </label>
                        </div>
                        <label class="form-label mb-1" for="shirt_sizes" style="font-size:12px">Sizes offered, comma separated</label>
                        <input type="text" class="form-control form-control-sm" id="shirt_sizes" name="shirt_sizes" maxlength="200"
                               value="<?= e((string) $values['shirt_sizes']) ?>" placeholder="XS, S, M, L, XL, XXL, 3XL">
                        <div class="form-text" style="font-size:11.5px">Asked per person in the confirmation form (early recap in Confirmations, for ordering) and again in the registration form (recap in Presentations).</div>
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
                        The registration dates are informational; what actually opens or closes the forms are the switches above (and the confirmation / video deadlines).
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
                    <h2>Contact</h2>
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
                    </div>
                </div>
            </div>

            <div class="cn-card mt-3" id="chair">
                <div class="cn-card-head">
                    <i class="fa-solid fa-user-tie text-muted"></i>
                    <h2>Conference chair</h2>
                </div>
                <div class="cn-card-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="signatory_name" style="font-size:12.5px">Name</label>
                            <input type="text" class="form-control form-control-sm" id="signatory_name" name="signatory_name"
                                   value="<?= e((string) $values['signatory_name']) ?>" placeholder="e.g. Dr. Ahmad Zaini" maxlength="190">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="signatory_title" style="font-size:12.5px">Position</label>
                            <input type="text" class="form-control form-control-sm" id="signatory_title" name="signatory_title"
                                   value="<?= e((string) $values['signatory_title']) ?>" placeholder="General Chair {edition_name}" maxlength="190">
                        </div>
                    </div>
                    <div class="form-text mb-3" style="font-size:11.5px">
                        One place for the chair: signs the LoA, fills the signatory on certificates, and closes every email.
                        Token: <code>{edition_name}</code>.
                    </div>

                    <?php
                    $role  = 'chair';
                    $file  = $signatures['chair'];
                    $label = 'Signature';
                    $help  = 'The chair\'s signature only, about 1000 × 500 px — shown above the name on the LoA. The stamp is uploaded separately in <a href="#stamp">Stamp</a>.';
                    require __DIR__ . '/_signature_upload.php';
                    ?>
                </div>
            </div>

            <div class="cn-card mt-3" id="treasurer">
                <div class="cn-card-head">
                    <i class="fa-solid fa-coins text-muted"></i>
                    <h2>Treasurer</h2>
                </div>
                <div class="cn-card-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label" for="treasurer_name" style="font-size:12.5px">Name</label>
                            <input type="text" class="form-control form-control-sm" id="treasurer_name" name="treasurer_name"
                                   value="<?= e((string) $values['treasurer_name']) ?>" maxlength="190">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="treasurer_title" style="font-size:12.5px">Position</label>
                            <input type="text" class="form-control form-control-sm" id="treasurer_title" name="treasurer_title"
                                   value="<?= e((string) $values['treasurer_title']) ?>" placeholder="Treasurer {edition_name}" maxlength="190">
                        </div>
                    </div>
                    <div class="form-text mb-3" style="font-size:11.5px">Signs invoices and receipts. Token: <code>{edition_name}</code>.</div>

                    <?php
                    $role  = 'treasurer';
                    $file  = $signatures['treasurer'];
                    $label = 'Signature';
                    $help  = 'The treasurer\'s signature only, about 1000 × 500 px — shown above the name on invoices and receipts. The stamp is uploaded separately in <a href="#stamp">Stamp</a>.';
                    require __DIR__ . '/_signature_upload.php';
                    ?>
                </div>
            </div>

            <div class="cn-card mt-3" id="stamp">
                <div class="cn-card-head">
                    <i class="fa-solid fa-stamp text-muted"></i>
                    <h2>Stamp</h2>
                </div>
                <div class="cn-card-body">
                    <?php
                    $role  = 'stamp';
                    $file  = $signatures['stamp'];
                    $label = 'Conference stamp';
                    $help  = 'Placed on top of the signature on the LoA, invoices, and receipts. A square image, about 600 × 600 px.';
                    require __DIR__ . '/_signature_upload.php';
                    ?>

                    <div class="row g-2 mt-2">
                        <div class="col-4">
                            <label class="form-label" for="stamp_size" style="font-size:12px">Size (mm)</label>
                            <input type="number" class="form-control form-control-sm" id="stamp_size" name="stamp_size" min="10" max="50" step="1"
                                   value="<?= e((string) $stamp['size']) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="stamp_dx" style="font-size:12px">Shift left/right (mm)</label>
                            <input type="number" class="form-control form-control-sm" id="stamp_dx" name="stamp_dx" min="-40" max="40" step="1"
                                   value="<?= e((string) $stamp['dx']) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="stamp_dy" style="font-size:12px">Shift up/down (mm)</label>
                            <input type="number" class="form-control form-control-sm" id="stamp_dy" name="stamp_dy" min="-25" max="25" step="1"
                                   value="<?= e((string) $stamp['dy']) ?>">
                        </div>
                    </div>
                    <div class="form-text" style="font-size:11.5px">
                        Measured from the middle of the signature: negative = left / up, positive = right / down.
                        The preview follows your changes; click <b>Save settings</b> to apply them to the PDFs.
                    </div>

                    <?php
                    $previewSig = $signatures['chair'] ?? $signatures['treasurer'];
                    $fileUrl    = static fn (?array $f): string => $f !== null ? app_url('public/file.php?id=' . (int) $f['id']) : '';
                    ?>
                    <div class="mt-2 d-flex justify-content-center">
                        <div id="stampPreview" class="border rounded position-relative" title="Preview at 4 px per mm"
                             data-sig="<?= e($fileUrl($previewSig)) ?>" data-stamp="<?= e($fileUrl($signatures['stamp'])) ?>"
                             style="width:280px;height:170px;background:#fff;overflow:hidden">
                            <div class="position-absolute text-muted" style="top:6px;left:0;right:0;text-align:center;font-size:11px">Preview (signature block, 70 mm wide)</div>
                            <img id="stampPreviewSig" alt="" class="position-absolute" style="display:none">
                            <img id="stampPreviewStamp" alt="" class="position-absolute" style="display:none;opacity:.9">
                            <div id="stampPreviewLine" class="position-absolute" style="left:32px;right:32px;border-top:1px solid #8a94a6"></div>
                            <div id="stampPreviewName" class="position-absolute text-center" style="left:0;right:0;font-size:11px;font-weight:700"><?= e((string) $values['signatory_name'] ?: 'Name') ?></div>
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
                    <div class="form-text mt-0 mb-2" style="font-size:11.5px">Position and name come from <a href="#chair">Conference chair</a>.</div>
                    <div class="row g-2">
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

                    <label class="form-label" for="loa_city" style="font-size:12.5px">Issuing city</label>
                    <input type="text" class="form-control form-control-sm" id="loa_city" name="loa_city" maxlength="120"
                           value="<?= e((string) $values['loa_city']) ?>" placeholder="<?= e(Cenim\Domain\LoaRepo::DEFAULT_CITY) ?>">
                    <div class="form-text mb-3" style="font-size:11.5px">
                        Printed before the date at the top right, e.g. <i><?= e(((string) $values['loa_city'] ?: Cenim\Domain\LoaRepo::DEFAULT_CITY) . ', ' . fmt_date(now())) ?></i>.
                    </div>

                    <label class="form-label" for="loa_body" style="font-size:12.5px">Letter text</label>
                    <textarea class="form-control form-control-sm" id="loa_body" name="loa_body" rows="9"><?= e((string) $values['loa_body']) ?></textarea>
                    <div class="form-text" style="font-size:11.5px">
                        Edit freely, including the conference location. Clear it to go back to the default text. Tokens:
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

<?php foreach (['chair' => 'the chair signature', 'treasurer' => 'the treasurer signature', 'stamp' => 'the stamp'] as $signRole => $signWhat): ?>
    <form method="post" enctype="multipart/form-data" id="signature-form-<?= e($signRole) ?>" class="d-none">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="signature_upload">
        <input type="hidden" name="role" value="<?= e($signRole) ?>">
    </form>
    <form method="post" id="signature-remove-<?= e($signRole) ?>" class="d-none"
          onsubmit="return confirm(<?= e(json_encode('Remove ' . $signWhat . ' from the documents?')) ?>)">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="signature_remove">
        <input type="hidden" name="role" value="<?= e($signRole) ?>">
    </form>
<?php endforeach; ?>

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

<script>
// Pratinjau stempel: skala 4 px/mm, kotak tanda tangan maks. 56×30 mm (sama dengan PDF).
(function () {
    var box = document.getElementById('stampPreview');
    if (!box) { return; }
    var MM = 4, BLOCK_W = 70, TOP = 22;
    var sig = document.getElementById('stampPreviewSig');
    var stamp = document.getElementById('stampPreviewStamp');
    var line = document.getElementById('stampPreviewLine');
    var name = document.getElementById('stampPreviewName');
    var inputs = ['stamp_size', 'stamp_dx', 'stamp_dy'].map(function (id) { return document.getElementById(id); });

    function draw() {
        var w = 56, h = 14;
        if (sig.dataset.ok === '1') {
            h = w * sig.naturalHeight / sig.naturalWidth;
            if (h > 30) { h = 30; w = h * sig.naturalWidth / sig.naturalHeight; }
            sig.style.cssText = 'display:block;position:absolute;left:' + ((BLOCK_W - w) / 2 * MM) + 'px;top:' + TOP + 'px;width:' + (w * MM) + 'px;height:' + (h * MM) + 'px';
        }
        if (stamp.dataset.ok === '1') {
            var size = parseFloat(inputs[0].value) || 28, dx = parseFloat(inputs[1].value) || 0, dy = parseFloat(inputs[2].value) || 0;
            var sh = size * stamp.naturalHeight / stamp.naturalWidth;
            stamp.style.cssText = 'display:block;position:absolute;opacity:.9;width:' + (size * MM) + 'px;height:' + (sh * MM) + 'px;left:'
                + ((BLOCK_W / 2 + dx - size / 2) * MM) + 'px;top:' + (TOP + (h / 2 + dy - sh / 2) * MM) + 'px';
        }
        line.style.top = (TOP + (h + 1) * MM) + 'px';
        name.style.top = (TOP + (h + 2) * MM) + 'px';
    }

    [[sig, box.dataset.sig], [stamp, box.dataset.stamp]].forEach(function (pair) {
        if (!pair[1]) { return; }
        pair[0].onload = function () { pair[0].dataset.ok = '1'; draw(); };
        pair[0].src = pair[1];
    });
    inputs.forEach(function (input) { input.addEventListener('input', draw); });
    draw();
})();
</script>
