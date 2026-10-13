<?php
/**
 * @var array  $author
 * @var array  $registration
 * @var bool   $editable
 * @var bool   $isOpen
 * @var array  $values
 * @var array<string,string> $errors
 * @var array  $quote
 * @var array  $packages
 * @var array  $extraFields
 * @var array  $paperAuthors
 * @var array  $mainOptions     paper lain dengan first author sama
 * @var bool   $isMainOfOthers
 * @var bool   $shirtOn
 * @var array  $shirtSizes
 * @var array  $feeData         tarif untuk hitung ulang di browser
 * @var array|null $studentProof
 * @var array|null $ieeeProof
 * @var int    $includedPages
 */

use Cenim\Core\Csrf;
use Cenim\Domain\FileRepo;
use Cenim\Domain\RegistrationRepo;

$status   = (string) $registration['status'];
$disabled = $editable && $isOpen ? '' : 'disabled';
$percent  = rtrim(rtrim(number_format((float) $feeData['percent'], 2, '.', ''), '0'), '.');
$currency = (string) $feeData['currency'];

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>'
        : '';
};

/** Kartu ringkas untuk satu berkas yang sudah diunggah. */
$proofBox = static function (?array $file): string {
    if ($file === null) {
        return '<div class="text-muted" style="font-size:12px"><i class="fa-regular fa-circle-xmark me-1"></i>Not uploaded yet.</div>';
    }

    return '<div class="d-flex align-items-center gap-2 mt-1" style="font-size:12.5px">'
        . '<i class="fa-solid fa-paperclip text-muted"></i>'
        . '<a href="' . e(app_url('public/file.php?id=' . (int) $file['id'])) . '" target="_blank" rel="noopener">'
        . e((string) $file['original_name']) . '</a>'
        . '<span class="text-muted">' . e(FileRepo::humanSize((int) $file['size'])) . ' &middot; '
        . e(fmt_dt((string) $file['uploaded_at'])) . '</span>'
        . '</div>';
};

/** <select> ukuran baju. */
$shirtSelect = static function (string $name, string $value, string $id = '') use ($shirtSizes, $disabled): string {
    $html = '<select class="form-select form-select-sm cn-shirt" name="' . e($name) . '"' . ($id !== '' ? ' id="' . e($id) . '"' : '') . ' ' . $disabled . '>'
        . '<option value="">Size…</option>';
    foreach ($shirtSizes as $size) {
        $html .= '<option value="' . e($size) . '"' . ($size === $value ? ' selected' : '') . '>' . e($size) . '</option>';
    }

    return $html . '</select>';
};

/** Satu baris keranjang peserta tambahan. $i = indeks, $row = nilai (atau kosong untuk template). */
$attendeeRow = static function (string $i, array $row) use ($paperAuthors, $disabled, $shirtOn, $shirtSelect): string {
    $source = (string) ($row['source'] ?? 'author');
    $paId   = (int) ($row['paper_author_id'] ?? 0);
    $other  = $source === 'other';

    $options = '<option value="">Choose a co-author…</option>';
    foreach ($paperAuthors as $pa) {
        $options .= '<option value="' . (int) $pa['id'] . '"' . ((int) $pa['id'] === $paId ? ' selected' : '') . '>'
            . e((string) $pa['name']) . '</option>';
    }

    $field = static fn (string $key, string $placeholder, string $type, int $max, string $col = 'col') => '<div class="' . $col . ' cn-att-other"' . ($other ? '' : ' hidden') . '>'
        . '<input type="' . $type . '" class="form-control form-control-sm cn-att-' . $key . '" name="attendees[' . $i . '][' . $key . ']"'
        . ' placeholder="' . $placeholder . '" maxlength="' . $max . '" value="' . e($other ? (string) ($row[$key] ?? '') : '') . '" ' . $disabled . '></div>';

    return '<div class="cn-attendee border rounded p-2 mb-2" data-source="' . e($source) . '">'
        . '<input type="hidden" name="attendees[' . $i . '][source]" value="' . e($source) . '">'
        . '<div class="row g-2 align-items-center">'
        . '<div class="col-auto"><span class="cn-badge ' . ($other ? 'cn-badge-draft' : 'cn-badge-submitted') . '" style="min-width:80px;text-align:center">'
        . ($other ? 'Guest' : 'Co-author') . '</span></div>'
        . '<div class="col cn-att-author"' . ($other ? ' hidden' : '') . '>'
        . '<select class="form-select form-select-sm cn-att-pa" name="attendees[' . $i . '][paper_author_id]" ' . $disabled . '>' . $options . '</select></div>'
        . $field('name', 'Full name', 'text', 190)
        . ($shirtOn ? '<div class="col-auto" style="width:110px">' . $shirtSelect('attendees[' . $i . '][shirt_size]', (string) ($row['shirt_size'] ?? '')) . '</div>' : '')
        . ($disabled === '' ? '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger cn-att-remove" title="Remove"><i class="fa-solid fa-xmark"></i></button></div>' : '')
        // Tamu: afiliasi & email di baris kedua supaya tidak sempit.
        . '<div class="w-100 cn-att-other"' . ($other ? '' : ' hidden') . '></div>'
        . $field('affiliation', 'Affiliation', 'text', 255, 'col-md-6')
        . $field('email', 'Email', 'email', 190, 'col-md-6')
        . '</div></div>';
};
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= e(author_url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <span class="cn-badge <?= e(RegistrationRepo::statusBadgeClass($status)) ?>">
        <?= e(ucfirst(str_replace('_', ' ', $status))) ?>
    </span>
</div>

<?php if ($status === RegistrationRepo::STATUS_REJECTED && ($registration['reject_reason'] ?? '') !== ''): ?>
    <div class="alert alert-danger" style="font-size:13px">
        <b><i class="fa-solid fa-circle-exclamation me-1"></i>The committee asked for a correction</b>
        <div class="mt-1"><?= nl2br(e((string) $registration['reject_reason'])) ?></div>
        <div class="mt-1 text-muted">Please update the form below and submit it again.</div>
    </div>
<?php endif; ?>

<?php if (!$isOpen): ?>
    <div class="alert alert-warning" style="font-size:13px">
        <i class="fa-solid fa-lock me-1"></i>
        Registration is currently closed. Please contact the secretariat if you believe this is a mistake.
    </div>
<?php elseif (!$editable): ?>
    <div class="alert alert-info" style="font-size:13px">
        <i class="fa-solid fa-circle-info me-1"></i>
        Your registration has been submitted, so the form is read-only. Contact the secretariat if something needs changing.
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate id="regForm">
    <?= Csrf::field() ?>

<?php if (($registration['filled_by_name'] ?? '') !== ''): ?>
    <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:12.5px">
        <i class="fa-solid fa-user-pen me-1" style="color:var(--cn-navy)"></i>
        Last filled in by <b><?= e((string) $registration['filled_by_name']) ?></b>
        <?php if (($registration['updated_at'] ?? '') !== ''): ?>&middot; <?= e(fmt_dt((string) $registration['updated_at'])) ?><?php endif; ?>
    </div>
<?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <!-- ── 1. Data paper (dari EDAS, tidak bisa diubah author) ── -->
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-lines text-muted"></i>
                    <h2>1. Confirm the paper</h2>
                </div>
                <div class="cn-card-body">
                    <dl class="row mb-0" style="font-size:13px">
                        <dt class="col-sm-4 text-muted fw-normal">Paper ID</dt>
                        <dd class="col-sm-8"><code><?= e((string) $author['paper_id']) ?></code></dd>

                        <dt class="col-sm-4 text-muted fw-normal">Paper title</dt>
                        <dd class="col-sm-8"><?= e((string) ($author['paper_title'] ?? '—')) ?></dd>

                        <dt class="col-sm-4 text-muted fw-normal">Authors</dt>
                        <dd class="col-sm-8">
                            <?php foreach ($paperAuthors as $pa): ?>
                                <div><?= e((string) $pa['position']) ?>. <?= e((string) $pa['name']) ?>
                                    <span class="text-muted" style="font-size:12px"><?= e((string) ($pa['affiliation'] ?? '')) ?></span></div>
                            <?php endforeach; ?>
                        </dd>

                        <dt class="col-sm-4 text-muted fw-normal">Country (first author)</dt>
                        <dd class="col-sm-8">
                            <?= e((string) ($author['country'] ?? '—')) ?>
                            <span class="cn-badge cn-badge-submitted ms-1">bills in <?= e($currency) ?></span>
                        </dd>

                        <dt class="col-sm-4 text-muted fw-normal">Total pages</dt>
                        <dd class="col-sm-8 mb-0">
                            <?= e((string) (int) ($author['total_pages'] ?? 0)) ?>
                            <span class="text-muted">(<?= e((string) $includedPages) ?> included)</span>
                        </dd>
                    </dl>
                    <div class="form-text mt-2" style="font-size:11.5px">
                        These come from your accepted submission. If anything is wrong, contact the secretariat before submitting.
                    </div>
                </div>
            </div>

            <!-- ── 2. Presenter ── -->
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-person-chalkboard text-muted"></i>
                    <h2>2. Presenter &amp; registration category</h2>
                </div>
                <div class="cn-card-body">
                    <p class="text-muted mb-3" style="font-size:12.5px">
                        The registration fee covers <b>one presenter</b> of this paper. Other people who come without presenting
                        are added in step 4.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-<?= $shirtOn ? '8' : '12' ?>">
                            <label class="form-label" for="presenter_id">Presenter <span class="text-danger">*</span></label>
                            <select class="form-select<?= isset($errors['presenter_id']) ? ' is-invalid' : '' ?>" id="presenter_id" name="presenter_id" <?= $disabled ?>>
                                <?php foreach ($paperAuthors as $pa): ?>
                                    <option value="<?= (int) $pa['id'] ?>" <?= (int) $pa['id'] === (int) $values['presenter_id'] ? 'selected' : '' ?>>
                                        <?= e((string) $pa['name']) ?><?= ($pa['affiliation'] ?? '') !== '' ? ' — ' . e((string) $pa['affiliation']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('presenter_id') ?>
                            <div class="form-text" style="font-size:11.5px">The presenter certificate is issued to this person.</div>
                        </div>
                        <?php if ($shirtOn): ?>
                            <div class="col-md-4">
                                <label class="form-label" for="presenter_shirt">Presenter shirt size <span class="text-danger">*</span></label>
                                <?= $shirtSelect('presenter_shirt', (string) $values['presenter_shirt'], 'presenter_shirt') ?>
                                <?= $err('presenter_shirt') ?>
                            </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Presentation</label>
                            <div class="row g-2">
                                <?php foreach (RegistrationRepo::MODES as $mode => $label): ?>
                                    <div class="col-md-6">
                                        <label class="border rounded p-2 px-3 d-flex gap-2 h-100" style="cursor:pointer;font-size:13px">
                                            <input class="form-check-input mt-1" type="radio" name="presentation_mode" value="<?= e($mode) ?>"
                                                <?= $values['presentation_mode'] === $mode ? 'checked' : '' ?> <?= $disabled ?>>
                                            <span>
                                                <b><i class="fa-solid <?= $mode === 'offline' ? 'fa-people-group' : 'fa-video' ?> me-1" style="color:var(--cn-navy)"></i><?= e(ucfirst($mode)) ?></b>
                                                <span class="d-block text-muted" style="font-size:12px"><?= e(explode(' — ', $label)[1] ?? $label) ?></span>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text" style="font-size:11.5px">
                                Online presenters send the video link in the <b>Video Presentation form</b> after the payment is confirmed.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input cn-fee" type="checkbox" name="is_student" id="is_student" value="1"
                                    <?= $values['is_student'] ? 'checked' : '' ?> <?= $disabled ?>>
                                <label class="form-check-label" for="is_student">The presenter is a student</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input cn-fee" type="checkbox" name="is_ieee_member" id="is_ieee_member" value="1"
                                    <?= $values['is_ieee_member'] ? 'checked' : '' ?> <?= $disabled ?>>
                                <label class="form-check-label" for="is_ieee_member">The presenter is an IEEE member</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ieee_number">IEEE membership number</label>
                            <input type="text" class="form-control<?= isset($errors['ieee_number']) ? ' is-invalid' : '' ?>"
                                   id="ieee_number" name="ieee_number" value="<?= e($values['ieee_number']) ?>"
                                   maxlength="40" <?= $disabled ?>>
                            <?= $err('ieee_number') ?>
                        </div>

                        <?php if ($packages !== []): ?>
                            <div class="col-md-6">
                                <label class="form-label" for="package_id">Package (optional)</label>
                                <select class="form-select cn-fee" id="package_id" name="package_id" <?= $disabled ?>>
                                    <option value="0">— no package —</option>
                                    <?php foreach ($packages as $package): ?>
                                        <option value="<?= e((string) $package['id']) ?>"
                                            <?= $values['package_id'] === (int) $package['id'] ? 'selected' : '' ?>>
                                            <?= e((string) $package['name']) ?>
                                            (<?= e(fmt_money((float) $package['amount'], (string) $package['currency'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" style="font-size:11.5px">A package replaces the category fee.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── 3. Paper tambahan ── -->
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-copy text-muted"></i>
                    <h2>3. Additional paper</h2>
                </div>
                <div class="cn-card-body" style="font-size:13px">
                    <p class="mb-2">
                        The registration fee applies to the first author of the accepted paper. An additional paper by the
                        same first author can be included for <b><?= e($percent) ?>%</b> of the registration fee.
                        Each paper keeps its own invoice, LoA and receipt.
                    </p>
                    <?php if ($isMainOfOthers): ?>
                        <div class="alert alert-light border py-2 px-3 mb-0" style="font-size:12.5px">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Another paper of this first author is registered as an additional paper of <b>this</b> paper,
                            so this one is the main paper and pays the full fee.
                        </div>
                    <?php elseif ($mainOptions === []): ?>
                        <div class="text-muted" style="font-size:12.5px">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            The first author has no other accepted paper in this conference, so the full fee applies.
                        </div>
                    <?php else: ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input cn-fee" type="checkbox" id="is_additional" name="is_additional" value="1"
                                <?= $values['main_author_id'] !== null ? 'checked' : '' ?> <?= $disabled ?>>
                            <label class="form-check-label" for="is_additional">
                                This is an <b>additional paper</b> of the first author (<?= e((string) $author['first_author_name']) ?>)
                            </label>
                        </div>
                        <div id="mainPaperBox"<?= $values['main_author_id'] === null ? ' hidden' : '' ?>>
                            <label class="form-label" for="main_author_id">Main paper (pays the full fee)</label>
                            <select class="form-select<?= isset($errors['main_author_id']) ? ' is-invalid' : '' ?>" id="main_author_id" name="main_author_id" <?= $disabled ?>>
                                <option value="0">Choose the main paper…</option>
                                <?php foreach ($mainOptions as $option): ?>
                                    <option value="<?= (int) $option['id'] ?>" data-paper="<?= e((string) $option['paper_id']) ?>"
                                        <?= (int) $option['id'] === (int) $values['main_author_id'] ? 'selected' : '' ?>>
                                        <?= e((string) $option['paper_id']) ?> — <?= e(mb_strimwidth((string) ($option['paper_title'] ?? ''), 0, 70, '…')) ?>
                                        (<?= e($option['registration_status'] !== null ? (string) $option['registration_status'] : 'not registered yet') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('main_author_id') ?>
                            <div class="form-text" style="font-size:11.5px">
                                The main paper must be registered at the full fee; the committee checks it when verifying.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── 4. Keranjang peserta tambahan ── -->
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-cart-plus text-muted"></i>
                    <h2>4. Additional attendees (non-presenter)</h2>
                </div>
                <div class="cn-card-body">
                    <p class="text-muted mb-2" style="font-size:12.5px">
                        Co-authors or colleagues who come without presenting.
                        <?php if ($feeData['attendeeFee'] !== null): ?>
                            Each pays the non-presenter fee of <b><?= e(fmt_money((float) $feeData['attendeeFee'], $currency)) ?></b>,
                            added to this paper's invoice.
                        <?php else: ?>
                            <span class="text-danger">The non-presenter fee has not been set yet — contact the secretariat.</span>
                        <?php endif; ?>
                    </p>
                    <?= $err('attendees') ?>
                    <?php foreach ($errors as $key => $message): ?>
                        <?php if (str_starts_with($key, 'attendee_')): ?>
                            <div class="invalid-feedback d-block" style="font-size:12px"><?= e($message) ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div id="attendeeList" class="mt-2">
                        <?php foreach ($values['attendees'] as $i => $row): ?>
                            <?= $attendeeRow((string) $i, $row) ?>
                        <?php endforeach; ?>
                    </div>
                    <div id="attendeeEmpty" class="text-muted" style="font-size:12.5px"<?= $values['attendees'] !== [] ? ' hidden' : '' ?>>
                        <i class="fa-regular fa-circle me-1"></i>Nobody added — only the presenter attends.
                    </div>

                    <?php if ($disabled === ''): ?>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-add="author" <?= count($paperAuthors) < 2 ? 'disabled title="This paper has no co-authors"' : '' ?>>
                                <i class="fa-solid fa-user-plus me-1"></i>Add a co-author
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-add="other">
                                <i class="fa-solid fa-user-plus me-1"></i>Add someone else
                            </button>
                            <span class="text-muted align-self-center" style="font-size:11.5px">max <?= RegistrationRepo::MAX_ATTENDEES ?></span>
                        </div>
                        <template id="attendeeTemplate"><?= $attendeeRow('__i__', ['source' => 'author']) ?></template>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── 5. Bukti ── -->
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-arrow-up text-muted"></i>
                    <h2>5. Supporting documents</h2>
                </div>
                <div class="cn-card-body">
                    <div class="mb-3">
                        <label class="form-label" for="student_proof">
                            Student proof
                            <span class="text-muted" style="font-weight:400">— required if the presenter registers as a student</span>
                        </label>
                        <input type="file" class="form-control<?= isset($errors['student_proof']) ? ' is-invalid' : '' ?>"
                               id="student_proof" name="student_proof" accept=".pdf,.jpg,.jpeg,.png" <?= $disabled ?>>
                        <?= $err('student_proof') ?>
                        <?= $proofBox($studentProof) ?>
                    </div>

                    <div>
                        <label class="form-label" for="ieee_proof">
                            IEEE membership proof
                            <span class="text-muted" style="font-weight:400">— required for the IEEE rate</span>
                        </label>
                        <input type="file" class="form-control<?= isset($errors['ieee_proof']) ? ' is-invalid' : '' ?>"
                               id="ieee_proof" name="ieee_proof" accept=".pdf,.jpg,.jpeg,.png" <?= $disabled ?>>
                        <?= $err('ieee_proof') ?>
                        <?= $proofBox($ieeeProof) ?>
                    </div>

                    <div class="form-text mt-2" style="font-size:11.5px">
                        <?= e(upload_hint('PDF, JPG or PNG')) ?>.
                        Uploading again replaces the previous file.
                    </div>
                </div>
            </div>

            <?php if ($extraFields !== []): ?>
                <div class="cn-card mb-3">
                    <div class="cn-card-head">
                        <i class="fa-solid fa-square-check text-muted"></i>
                        <h2>6. Additional questions</h2>
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
        </div>

        <!-- ── Rincian biaya ── -->
        <div class="col-lg-5">
            <div class="cn-card mb-3" style="position:sticky;top:16px">
                <div class="cn-card-head">
                    <i class="fa-solid fa-receipt text-muted"></i>
                    <h2>Fee summary</h2>
                </div>
                <div class="cn-card-body">
                    <?php if (isset($errors['fee'])): ?>
                        <div class="alert alert-danger py-2 px-3" style="font-size:12.5px"><?= e($errors['fee']) ?></div>
                    <?php elseif ($quote['error'] !== null): ?>
                        <div class="alert alert-warning py-2 px-3" style="font-size:12.5px"><?= e((string) $quote['error']) ?></div>
                    <?php endif; ?>

                    <table class="table table-sm mb-2" style="font-size:13px">
                        <tbody id="feeItems">
                            <?php foreach ($quote['items'] as $item): ?>
                                <tr>
                                    <td>
                                        <?= e((string) $item['description']) ?>
                                        <?php if ((float) $item['qty'] > 1): ?>
                                            <span class="text-muted">× <?= e((string) (int) $item['qty']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" style="white-space:nowrap">
                                        <?= e(fmt_money((float) $item['amount'], (string) $quote['currency'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($quote['items'] === []): ?>
                                <tr><td colspan="2" class="text-muted">Choose your category to see the fee.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr style="border-top:2px solid var(--cn-border)">
                                <th>Total</th>
                                <th class="text-end" id="feeTotal" style="white-space:nowrap;color:var(--cn-navy);font-size:15px">
                                    <?= e(fmt_money((float) $quote['total'], (string) $quote['currency'])) ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>

                    <p class="text-muted" style="font-size:11.5px">
                        One invoice for this paper: the presenter's registration fee plus every additional attendee.
                        The committee verifies the documents before issuing it, so the final invoice may differ if the
                        category does not match the proof.
                    </p>

                    <?php if ($editable && $isOpen): ?>
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="submit" class="btn btn-navy">
                                <i class="fa-solid fa-paper-plane me-1"></i>Submit registration
                            </button>
                            <button type="submit" name="action" value="save" class="btn btn-outline-secondary">
                                <i class="fa-regular fa-floppy-disk me-1"></i>Save draft
                            </button>
                        </div>
                        <p class="text-muted mt-2 mb-0" style="font-size:11.5px">
                            After submitting you cannot edit the form unless the committee asks for a correction.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var fee      = <?= json_encode($feeData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var packages = <?= json_encode((object) array_column(array_map(static fn (array $p): array => ['id' => (int) $p['id'], 'name' => (string) $p['name'], 'amount' => (float) $p['amount']], $packages), null, 'id'), JSON_HEX_TAG) ?>;
    var form     = document.getElementById('regForm');
    var list     = document.getElementById('attendeeList');
    var empty    = document.getElementById('attendeeEmpty');
    var tpl      = document.getElementById('attendeeTemplate');
    var counter  = list.children.length;
    var max      = <?= RegistrationRepo::MAX_ATTENDEES ?>;
    var noCoauthors = <?= count($paperAuthors) < 2 ? 'true' : 'false' ?>;

    function money(n) {
        if (fee.currency === 'IDR') { return 'Rp ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
        return '$ ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function attendeeName(row) {
        if (row.dataset.source === 'author') {
            var sel = row.querySelector('.cn-att-pa');
            return sel && sel.value ? sel.options[sel.selectedIndex].text.trim() : '';
        }
        return (row.querySelector('.cn-att-name').value || '').trim();
    }

    function recalc() {
        var items = [];
        var pkg   = form.querySelector('[name="package_id"]');
        var pid   = pkg ? parseInt(pkg.value, 10) : 0;
        if (pid > 0 && packages[pid]) {
            items.push(['Package: ' + packages[pid].name, packages[pid].amount]);
        } else {
            var key  = (form.is_student.checked ? 's' : 'n') + (form.is_ieee_member.checked ? 'i' : 'n');
            var rate = fee.rates[key];
            if (!rate) {
                document.getElementById('feeItems').innerHTML = '<tr><td colspan="2" class="text-warning">No fee configured for this combination — contact the secretariat.</td></tr>';
                document.getElementById('feeTotal').textContent = '—';
                return;
            }
            var add  = document.getElementById('is_additional');
            var main = document.getElementById('main_author_id');
            if (add && add.checked && main && main.value !== '0') {
                var unit = Math.round(rate.amount * fee.percent) / 100;
                items.push([rate.label + ' — additional paper of the same first author (' + fee.percent + '% of the fee; main paper '
                    + main.options[main.selectedIndex].dataset.paper + ')', unit]);
            } else {
                items.push([rate.label, rate.amount]);
            }
            if (fee.extraPages > 0 && fee.extraPageFee > 0) {
                items.push(['Extra page (' + fee.extraPages + ' beyond ' + fee.includedPages + ' included)', fee.extraPages * fee.extraPageFee]);
            }
        }
        list.querySelectorAll('.cn-attendee').forEach(function (row) {
            var name = attendeeName(row);
            items.push(['Additional attendee (non-presenter): ' + (name || '(not filled in yet)'), fee.attendeeFee || 0]);
        });

        var total = 0, html = '';
        items.forEach(function (it) {
            total += it[1];
            html += '<tr><td>' + esc(it[0]) + '</td><td class="text-end" style="white-space:nowrap">' + money(it[1]) + '</td></tr>';
        });
        document.getElementById('feeItems').innerHTML = html;
        document.getElementById('feeTotal').textContent = money(total);
    }

    function sync() {
        var rows = list.querySelectorAll('.cn-attendee');
        empty.hidden = rows.length > 0;
        form.querySelectorAll('[data-add]').forEach(function (b) {
            b.disabled = rows.length >= max || (b.dataset.add === 'author' && noCoauthors);
        });
        // Presenter tidak bisa dipilih lagi sebagai peserta tambahan.
        var presenter = form.presenter_id ? form.presenter_id.value : '';
        list.querySelectorAll('.cn-att-pa option').forEach(function (o) {
            o.disabled = o.value !== '' && o.value === presenter;
        });
        recalc();
    }

    if (tpl) {
        form.querySelectorAll('[data-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                var wrap = document.createElement('div');
                wrap.innerHTML = tpl.innerHTML.split('__i__').join('n' + (counter++)).trim();
                var row = wrap.firstElementChild;
                var isAuthor = button.dataset.add === 'author';
                row.dataset.source = button.dataset.add;
                row.querySelector('input[type="hidden"]').value = button.dataset.add;
                row.querySelector('.cn-att-author').hidden = !isAuthor;
                row.querySelectorAll('.cn-att-other').forEach(function (c) { c.hidden = isAuthor; });
                var badge = row.querySelector('.cn-badge');
                badge.textContent = isAuthor ? 'Co-author' : 'Guest';
                badge.className = 'cn-badge ' + (isAuthor ? 'cn-badge-submitted' : 'cn-badge-draft');
                list.appendChild(row);
                sync();
                var first = row.querySelector(isAuthor ? '.cn-att-pa' : '.cn-att-name');
                if (first) { first.focus(); }
            });
        });
    }

    list.addEventListener('click', function (e) {
        var rm = e.target.closest('.cn-att-remove');
        if (rm) { rm.closest('.cn-attendee').remove(); sync(); }
    });
    list.addEventListener('input', recalc);
    list.addEventListener('change', recalc);
    form.querySelectorAll('.cn-fee, #main_author_id, #presenter_id').forEach(function (el) { el.addEventListener('change', sync); });

    var add = document.getElementById('is_additional');
    if (add) {
        add.addEventListener('change', function () { document.getElementById('mainPaperBox').hidden = !add.checked; });
    }
    sync();
})();
</script>
