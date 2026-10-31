<?php
/**
 * @var array $registration  hasil RegistrationRepo::findWithAuthor()
 * @var array|null $confirmation  confirmation form (form 0)
 * @var array $recalculated  hasil FeeEngine::quote() atas data terkini
 * @var array<int,array<string,mixed>> $files
 * @var array<int,array{label:string,value:string}> $extraAnswers
 * @var array|null $presenter        baris paper_authors
 * @var array      $attendees        peserta tambahan non-presenter
 * @var array|null $mainPaper        paper utama (bila paper ini paper tambahan)
 * @var array|null $mainRegistration
 * @var array      $additionalPapers paper lain yang memakai paper ini sebagai paper utama
 * @var bool       $shirtOn
 * @var array<int,array<string,mixed>> $categories kategori presenter (mata uang registrasi ini)
 * @var int        $included   halaman yang termasuk biaya registrasi
 */

use Cenim\Core\Csrf;
use Cenim\Domain\FileRepo;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\RegistrationRepo;

$status    = (string) $registration['status'];
$reviewed  = in_array($status, [RegistrationRepo::STATUS_SUBMITTED, RegistrationRepo::STATUS_REJECTED, RegistrationRepo::STATUS_VERIFIED], true);
$stored    = (float) $registration['total'];
$expected  = (float) $recalculated['total'];
$mismatch  = $reviewed && abs($stored - $expected) > 0.009;

$purposeLabels = [
    'student_proof' => 'Student proof',
    'ieee_proof'    => 'IEEE membership proof',
];

$isParticipantReg = str_starts_with((string) $registration['paper_id'], Cenim\Domain\ParticipantRepo::PREFIX);
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(admin_url('registrations.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to list
    </a>
    <span class="cn-badge <?= e(RegistrationRepo::statusBadgeClass($status)) ?>">
        <?= e(ucfirst($status)) ?>
    </span>
    <a href="<?= e(admin_url('author-edit.php?id=' . (int) $registration['author_id'])) ?>"
       class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="fa-solid fa-pen me-1"></i>Edit author data
    </a>
</div>

<?php if (($registration['admin_note'] ?? '') !== ''): ?>
    <div class="alert alert-light border py-2 px-3" style="font-size:12.5px">
        <i class="fa-regular fa-note-sticky me-1" style="color:var(--cn-navy)"></i>
        <b>Admin note</b> <span class="text-muted">(not shown to the author, edit it in the Registrations list)</span>
        <div class="mt-1" style="white-space:pre-line"><?= e((string) $registration['admin_note']) ?></div>
    </div>
<?php endif; ?>

<?php if ($status === RegistrationRepo::STATUS_DRAFT): ?>
    <div class="alert alert-secondary" style="font-size:13px">
        <i class="fa-solid fa-circle-info me-1"></i>
        The author has started the form but has not submitted it yet, so there is nothing to review.
    </div>
<?php endif; ?>

<?php if ($status === RegistrationRepo::STATUS_REJECTED && ($registration['reject_reason'] ?? '') !== ''): ?>
    <div class="alert alert-danger" style="font-size:13px">
        <b>Returned to the author</b>
        <div class="mt-1"><?= nl2br(e((string) $registration['reject_reason'])) ?></div>
    </div>
<?php endif; ?>

<?php if (($registration['filled_by_name'] ?? '') !== ''): ?>
    <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:12.5px">
        <i class="fa-solid fa-user-pen me-1" style="color:var(--cn-navy)"></i>
        Last filled in by <b><?= e((string) $registration['filled_by_name']) ?></b>
        <?php if (($registration['updated_at'] ?? '') !== ''): ?>&middot; <?= e(fmt_dt((string) $registration['updated_at'])) ?><?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-user text-muted"></i>
                <h2><?= $isParticipantReg ? 'Participant (non-presenter, no paper)' : 'Author &amp; paper' ?></h2>
            </div>
            <div class="cn-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-sm-4 text-muted fw-normal">Paper ID</dt>
                    <dd class="col-sm-8"><code><?= e((string) $registration['paper_id']) ?></code></dd>

                    <?php if (!$isParticipantReg): ?>
                    <dt class="col-sm-4 text-muted fw-normal">Title</dt>
                    <dd class="col-sm-8"><?= e((string) ($registration['paper_title'] ?? '—')) ?></dd>
                    <?php endif; ?>

                    <dt class="col-sm-4 text-muted fw-normal">First author</dt>
                    <dd class="col-sm-8"><?= e((string) $registration['first_author_name']) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Email</dt>
                    <dd class="col-sm-8">
                        <a href="mailto:<?= e((string) $registration['email']) ?>"><?= e((string) $registration['email']) ?></a>
                    </dd>

                    <dt class="col-sm-4 text-muted fw-normal">Affiliation</dt>
                    <dd class="col-sm-8"><?= e((string) ($registration['affiliation'] ?? '—')) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Country</dt>
                    <dd class="col-sm-8"><?= e((string) ($registration['author_country'] ?? '—')) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Phone</dt>
                    <dd class="col-sm-8"><?= e((string) ($registration['phone'] ?? '—')) ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">Total pages</dt>
                    <dd class="col-sm-8">
                        <?= e((string) (int) ($registration['total_pages'] ?? 0)) ?>
                        <?php if ((int) ($registration['final_pages'] ?? 0) > 0): ?>
                            <span class="cn-badge cn-badge-verified ms-1">final</span>
                            <span class="text-muted" style="font-size:12px">&middot; review <?= (int) ($registration['review_pages'] ?? 0) ?></span>
                        <?php else: ?>
                            <span class="cn-badge cn-badge-draft ms-1">review</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted fw-normal">Confirmation</dt>
                    <dd class="col-sm-8 mb-0">
                        <?php if ($confirmation !== null): ?>
                            <?= e(Cenim\Domain\ConfirmationRepo::summary($confirmation)) ?>
                            <?php if (($confirmation['note'] ?? '') !== ''): ?>
                                <div class="text-muted" style="font-size:12px"><i class="fa-regular fa-note-sticky me-1"></i><?= nl2br(e((string) $confirmation['note'])) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Not submitted</span>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-tags text-muted"></i>
                <h2>Declared by the author</h2>
            </div>
            <div class="cn-card-body">
                <dl class="row mb-0" style="font-size:13px">
                    <dt class="col-sm-4 text-muted fw-normal">Presenter</dt>
                    <dd class="col-sm-8">
                        <?php if ($isParticipantReg): ?>
                            <b><?= e((string) ($presenter['name'] ?? '—')) ?></b> <span class="text-muted">attends as a non-presenter (offline)</span>
                            <?php if (($registration['presenter_shirt'] ?? '') !== ''): ?>
                                <span class="cn-badge cn-badge-draft ms-1" title="Shirt size"><i class="fa-solid fa-shirt me-1"></i><?= e((string) $registration['presenter_shirt']) ?></span>
                            <?php endif; ?>
                        <?php elseif ($registration['participant_type'] === 'non_presenter'): ?>
                            Non-presenter registration (old form)
                        <?php else: ?>
                            <b><?= e((string) ($presenter['name'] ?? '—')) ?></b>
                            <?php if (($presenter['email'] ?? '') !== ''): ?><span class="text-muted">&middot; <?= e((string) $presenter['email']) ?></span><?php endif; ?>
                            <?php if ($shirtOn || ($registration['presenter_shirt'] ?? '') !== ''): ?>
                                <span class="cn-badge cn-badge-draft ms-1" title="Shirt size"><i class="fa-solid fa-shirt me-1"></i><?= e((string) ($registration['presenter_shirt'] ?? '—')) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted fw-normal">Presentation</dt>
                    <dd class="col-sm-8">
                        <span class="cn-badge <?= ($registration['presentation_mode'] ?? '') === 'online' ? 'cn-badge-submitted' : 'cn-badge-verified' ?>">
                            <?= e(ucfirst((string) ($registration['presentation_mode'] ?? 'offline'))) ?>
                        </span>
                    </dd>

                    <dt class="col-sm-4 text-muted fw-normal">Additional paper</dt>
                    <dd class="col-sm-8">
                        <?php if ($mainPaper !== null): ?>
                            <span class="cn-badge cn-badge-unpaid">Yes — reduced fee</span>
                            main paper <code><?= e((string) $mainPaper['paper_id']) ?></code>
                            <?php if ($mainRegistration !== null): ?>
                                <a href="<?= e(admin_url('registration-view.php?id=' . (int) $mainRegistration['id'])) ?>">(registration: <?= e((string) $mainRegistration['status']) ?>)</a>
                            <?php else: ?>
                                <span class="text-danger">(main paper not registered yet — check before verifying)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            No — full fee
                        <?php endif; ?>
                        <?php foreach ($additionalPapers as $extraPaper): ?>
                            <div style="font-size:12px" class="text-muted">
                                Main paper of <a href="<?= e(admin_url('registration-view.php?id=' . (int) $extraPaper['registration_id'])) ?>"><code><?= e((string) $extraPaper['paper_id']) ?></code></a>
                                (<?= e((string) $extraPaper['status']) ?>)
                            </div>
                        <?php endforeach; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted fw-normal">Additional attendees</dt>
                    <dd class="col-sm-8">
                        <?php if ($attendees === []): ?>
                            —
                        <?php else: ?>
                            <?php foreach ($attendees as $a): ?>
                                <div>
                                    <?= e((string) $a['name']) ?>
                                    <span class="text-muted" style="font-size:12px">
                                        <?= $a['paper_author_id'] !== null ? 'co-author' : 'guest' ?>
                                        <?= ($a['affiliation'] ?? '') !== '' ? '&middot; ' . e((string) $a['affiliation']) : '' ?>
                                        <?= ($a['email'] ?? '') !== '' ? '&middot; ' . e((string) $a['email']) : '' ?>
                                    </span>
                                    <?php if (($a['shirt_size'] ?? '') !== ''): ?><span class="cn-badge cn-badge-draft"><?= e((string) $a['shirt_size']) ?></span><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted fw-normal">Student</dt>
                    <dd class="col-sm-8"><?= (int) $registration['is_student'] === 1 ? 'Yes' : 'No' ?></dd>

                    <dt class="col-sm-4 text-muted fw-normal">IEEE member</dt>
                    <dd class="col-sm-8">
                        <?= (int) $registration['is_ieee_member'] === 1 ? 'Yes' : 'No' ?>
                        <?php if (($registration['ieee_number'] ?? '') !== ''): ?>
                            &middot; <code><?= e((string) $registration['ieee_number']) ?></code>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted fw-normal">Extra pages</dt>
                    <dd class="col-sm-8"><?= e((string) (int) $registration['extra_pages']) ?></dd>

                    <?php if ((int) $registration['extra_papers'] > 0): ?>
                        <dt class="col-sm-4 text-muted fw-normal">Additional papers (old form)</dt>
                        <dd class="col-sm-8"><?= e((string) (int) $registration['extra_papers']) ?></dd>
                    <?php endif; ?>

                    <dt class="col-sm-4 text-muted fw-normal">Submitted</dt>
                    <dd class="col-sm-8 mb-0"><?= e(fmt_dt($registration['submitted_at'] ?? null)) ?></dd>
                </dl>

                <?php if ($extraAnswers !== []): ?>
                    <hr class="my-3">
                    <dl class="row mb-0" style="font-size:13px">
                        <?php foreach ($extraAnswers as $answer): ?>
                            <dt class="col-sm-4 text-muted fw-normal"><?= e($answer['label']) ?></dt>
                            <dd class="col-sm-8"><?= e($answer['value']) ?></dd>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
        </div>

        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-paperclip text-muted"></i>
                <h2>Supporting documents</h2>
            </div>
            <div class="cn-card-body">
                <?php if ($files === []): ?>
                    <div class="cn-empty">
                        <i class="fa-regular fa-folder-open"></i>
                        No document uploaded.
                    </div>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0" style="font-size:13px">
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td style="width:180px">
                                        <?= e($purposeLabels[(string) $file['purpose']] ?? ucfirst(str_replace('_', ' ', (string) $file['purpose']))) ?>
                                    </td>
                                    <td>
                                        <a href="<?= e(app_url('public/file.php?id=' . (int) $file['id'])) ?>" target="_blank" rel="noopener">
                                            <i class="fa-solid fa-up-right-from-square me-1"></i><?= e((string) $file['original_name']) ?>
                                        </a>
                                        <div class="text-muted" style="font-size:11.5px">
                                            <?= e(FileRepo::humanSize((int) $file['size'])) ?> &middot;
                                            <?= e((string) $file['mime']) ?> &middot;
                                            uploaded <?= e(fmt_dt((string) $file['uploaded_at'])) ?>
                                            &middot; <?= e((string) $file['driver']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-coins text-muted"></i>
                <h2>Fee</h2>
            </div>
            <div class="cn-card-body">
                <table class="table table-sm mb-2" style="font-size:13px">
                    <tbody>
                        <?php
                        // Tombol hapus peserta tambahan: hanya selama invoice belum lunas.
                        $canRemoveGuest = $reviewed && !(($invoice ?? null) !== null && (string) $invoice['status'] === InvoiceRepo::STATUS_PAID);
                        ?>
                        <?php foreach ($recalculated['items'] as $item): ?>
                            <?php
                            $guest = isset($item['attendee']) ? ($attendees[(int) $item['attendee']] ?? null) : null;
                            if ($guest !== null && !str_ends_with((string) $item['description'], trim((string) $guest['name']))) {
                                $guest = null; // urutan tidak cocok: jangan tawarkan hapus
                            }
                            ?>
                            <tr>
                                <td><?= e((string) $item['description']) ?></td>
                                <td class="text-end" style="white-space:nowrap">
                                    <?= e(fmt_money((float) $item['amount'], (string) $recalculated['currency'])) ?>
                                    <?php if ($guest !== null && $canRemoveGuest): ?>
                                        <form method="post" class="d-inline ms-1"
                                              onsubmit="return confirm(<?= e(json_encode('Remove ' . $guest['name'] . ' from this registration? The fee is recalculated' . (($invoice ?? null) !== null ? ', and the unpaid invoice is voided and issued again (emailed to the author).' : '.'))) ?>);">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="remove_attendee">
                                            <input type="hidden" name="attendee_id" value="<?= (int) $guest['id'] ?>">
                                            <input type="hidden" name="reissue" value="1">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1" title="Remove this attendee"><i class="fa-solid fa-minus"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid var(--cn-border)">
                            <th>Recalculated total</th>
                            <th class="text-end" style="white-space:nowrap;color:var(--cn-navy)">
                                <?= e(fmt_money($expected, (string) $recalculated['currency'])) ?>
                            </th>
                        </tr>
                        <tr>
                            <td class="text-muted">Stored on the registration</td>
                            <td class="text-end text-muted" style="white-space:nowrap">
                                <?= e(fmt_money($stored, (string) $registration['currency'])) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <?php if ($mismatch): ?>
                    <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:12.5px">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        The stored amount differs from the recalculated one — the fee table or the author data changed
                        after submission. The invoice will use the amount at the time it is issued.
                    </div>
                <?php endif; ?>

                <?php
                $invoicePaid  = ($invoice ?? null) !== null && (string) $invoice['status'] === InvoiceRepo::STATUS_PAID;
                $showCategory = (string) $registration['participant_type'] !== 'non_presenter' && ($registration['package_id'] ?? null) === null && $categories !== [];
                ?>
                <?php if ($reviewed && !$invoicePaid && (!$isParticipantReg || $showCategory)): ?>
                    <details class="mt-3" id="adjustBox"<?= input('adjust') !== null ? ' open' : '' ?>>
                        <summary class="fw-semibold" style="font-size:13px;cursor:pointer;color:var(--cn-navy)">
                            <i class="fa-solid fa-sliders me-1"></i>Adjust pages and category
                        </summary>
                        <form method="post" class="mt-2 border rounded p-3" style="font-size:13px"
                              onsubmit="return confirm('Recalculate and save the fee of this registration?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="adjust">

                            <?php if (!$isParticipantReg): ?>
                                <label class="form-label mb-1" for="adjPages">Pages (final manuscript)</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="number" class="form-control form-control-sm" id="adjPages" name="pages" min="1" max="200" required
                                           value="<?= (int) $registration['total_pages'] ?>" style="width:100px">
                                    <span class="text-muted" style="font-size:12px">
                                        review <?= (int) ($registration['review_pages'] ?? 0) ?><?= ($registration['final_pages'] ?? null) !== null ? ' · final ' . (int) $registration['final_pages'] : '' ?>
                                        · <?= (int) $included ?> included, extra pages are charged
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($showCategory): ?>
                                <label class="form-label mb-1 mt-2" for="adjCategory">Fee category</label>
                                <select class="form-select form-select-sm" id="adjCategory" name="fee_category_id" required>
                                    <?php foreach ($categories as $category): ?>
                                        <?php $current = (int) $category['is_student'] === (int) $registration['is_student'] && (int) $category['is_ieee'] === (int) $registration['is_ieee_member']; ?>
                                        <option value="<?= (int) $category['id'] ?>" <?= $current ? 'selected' : '' ?>>
                                            <?= e((string) $category['label']) ?> &mdash; <?= e(fmt_money((float) $category['amount'], (string) $category['currency'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" style="font-size:11.5px">Sets the Student and IEEE member answers of this registration.</div>
                            <?php endif; ?>

                            <?php if (($invoice ?? null) !== null): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="adjReissue" name="reissue" value="1" checked>
                                    <label class="form-check-label" for="adjReissue" style="font-size:12.5px">
                                        If the amount changes, void invoice <?= e((string) $invoice['number']) ?> and issue a new one (emailed to the author)
                                    </label>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-sm btn-navy mt-3">
                                <i class="fa-solid fa-calculator me-1"></i>Recalculate &amp; save
                            </button>
                        </form>
                    </details>
                <?php elseif ($reviewed && $invoicePaid): ?>
                    <p class="text-muted mt-2 mb-0" style="font-size:12px"><i class="fa-solid fa-lock me-1"></i>The invoice is paid, so the fee can no longer be adjusted.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($invoice ?? null) !== null): ?>
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-invoice text-muted"></i>
                    <h2>Invoice</h2>
                    <span class="cn-badge <?= e(InvoiceRepo::statusBadgeClass((string) $invoice['status'])) ?> ms-auto">
                        <?= e(ucfirst((string) $invoice['status'])) ?><?= InvoiceRepo::isWaived($invoice) ? ' &middot; waived' : '' ?>
                    </span>
                </div>
                <div class="cn-card-body">
                    <dl class="row mb-3" style="font-size:13px">
                        <dt class="col-5 text-muted fw-normal">Number</dt>
                        <dd class="col-7"><code><?= e((string) $invoice['number']) ?></code></dd>
                        <dt class="col-5 text-muted fw-normal">Issued</dt>
                        <dd class="col-7"><?= e(fmt_dt((string) $invoice['issued_at'])) ?></dd>
                        <dt class="col-5 text-muted fw-normal">Total</dt>
                        <dd class="col-7 mb-0">
                            <b><?= e(fmt_money((float) $invoice['total'], (string) $invoice['currency'])) ?></b>
                        </dd>
                    </dl>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                       href="<?= e(admin_url('invoice-pdf.php?id=' . (int) $invoice['id'])) ?>">
                        <i class="fa-solid fa-file-pdf me-1"></i>Open invoice PDF
                    </a>
                </div>
            </div>
        <?php elseif ($status === RegistrationRepo::STATUS_VERIFIED): ?>
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-invoice text-muted"></i>
                    <h2>Invoice</h2>
                </div>
                <div class="cn-card-body">
                    <p class="text-muted" style="font-size:13px">
                        This registration is verified but has no invoice yet.
                    </p>
                    <form method="post" action="<?= e(admin_url('invoice-issue.php')) ?>" class="m-0">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="registration_id" value="<?= e((string) $registration['id']) ?>">
                        <input type="hidden" name="back" value="<?= e(admin_url('registration-view.php?id=' . (int) $registration['id'])) ?>">
                        <button type="submit" class="btn btn-sm btn-navy">
                            <i class="fa-solid fa-file-invoice me-1"></i>Issue invoice now
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($reviewed): ?>
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-gavel text-muted"></i>
                    <h2>Decision</h2>
                </div>
                <div class="cn-card-body">
                    <?php if ($status === RegistrationRepo::STATUS_VERIFIED): ?>
                        <div class="alert alert-success py-2 px-3" style="font-size:12.5px">
                            <i class="fa-solid fa-circle-check me-1"></i>
                            Verified on <?= e(fmt_dt($registration['verified_at'] ?? null)) ?>.
                            The invoice is issued in the next step (Phase 4).
                        </div>
                    <?php endif; ?>

                    <form method="post" class="mb-3" id="verifyForm">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="verify">
                        <button type="submit" class="btn btn-navy w-100"
                            <?= $status === RegistrationRepo::STATUS_VERIFIED ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-check me-1"></i>Verify &amp; notify the author
                        </button>
                    </form>

                    <form method="post" id="rejectForm">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="reject">
                        <label class="form-label" for="reason" style="font-size:12.5px">
                            Ask for a correction — the note below is emailed to the author
                        </label>
                        <textarea class="form-control mb-2" id="reason" name="reason" rows="3"
                                  maxlength="1000"
                                  placeholder="e.g. The uploaded student card has expired. Please upload a valid one."></textarea>
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fa-solid fa-rotate-left me-1"></i>Return to the author
                        </button>
                    </form>

                    <?php if (!$invoicePaid): ?>
                        <hr>
                        <form method="post" id="waiveForm"
                              onsubmit="return confirm('Waive the registration fee of this paper? The invoice is marked as paid (not counted as income) and a receipt of 0 is issued.');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="waive">
                            <label class="form-label" for="waiveNote" style="font-size:12.5px">
                                Waive the fee (e.g. paper from the host) — marked as paid, counted as 0 income
                            </label>
                            <input type="text" class="form-control form-control-sm mb-2" id="waiveNote" name="note" maxlength="500"
                                   placeholder="Note on the receipt, e.g. Host paper - registration fee waived">
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="fa-solid fa-hand-holding-heart me-1"></i>Waive fee
                            </button>
                            <div class="form-text" style="font-size:11.5px">Verifies the registration if needed, issues the invoice (no payment email) and a receipt. Both stay downloadable.</div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
