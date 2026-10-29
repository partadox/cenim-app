<?php
/**
 * Form 0 — Confirmation form.
 *
 * @var array      $author        paper terpilih
 * @var array|null $confirmation
 * @var array|null $registration
 * @var array      $paperAuthors
 * @var bool       $editable
 * @var bool       $isOpen
 * @var string     $deadline      YYYY-MM-DD atau ''
 * @var array      $fee           ConfirmationRepo::pageFee()
 * @var float|null $attendeeFee   biaya non-presenter per orang
 * @var int        $maxAttendees
 * @var array<int,string> $shirtSizes  [] = ukuran baju tidak ditanyakan
 * @var array      $values
 * @var array<string,string> $errors
 * @var bool       $adminMode     dibuka admin (4dm1n/confirmation-edit.php)
 * @var string     $backUrl
 */

use Cenim\Core\Csrf;
use Cenim\Domain\RegistrationRepo;

$disabled = $editable ? '' : 'disabled';
$currency = (string) $fee['currency'];
$err = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>'
    : '';
$adminMode    = $adminMode ?? false;
$submittedReg = $registration !== null && (string) $registration['status'] !== RegistrationRepo::STATUS_DRAFT;
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e($backUrl ?? author_url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <?php if ($confirmation !== null): ?>
        <span class="cn-badge cn-badge-verified"><i class="fa-solid fa-check me-1"></i>Confirmed</span>
    <?php endif; ?>
    <?php if ($deadline !== ''): ?>
        <span class="text-muted ms-auto" style="font-size:12.5px"><i class="fa-regular fa-clock me-1"></i>Deadline <?= e(fmt_date($deadline . ' 00:00:00')) ?></span>
    <?php endif; ?>
</div>

<?php if ($adminMode): ?>
    <div class="alert alert-warning" style="font-size:13px">
        <i class="fa-solid fa-user-shield me-1"></i>
        You are editing this confirmation <b>as the secretariat</b>. The deadline and the registration status are not checked.
        <?php if ($submittedReg): ?>
            The registration has already been submitted: changes here do <b>not</b> change the registration, invoice or fee.
        <?php endif; ?>
    </div>
<?php elseif ($submittedReg): ?>
    <div class="alert alert-info" style="font-size:13px">
        <i class="fa-solid fa-circle-info me-1"></i>
        The registration form has been submitted, so this confirmation is read-only. Contact the secretariat if something has changed.
    </div>
<?php elseif (!$isOpen): ?>
    <div class="alert alert-warning" style="font-size:13px">
        <i class="fa-solid fa-lock me-1"></i>
        The confirmation form is closed. Please contact the secretariat.
    </div>
<?php endif; ?>

<?php if (($confirmation['filled_by_name'] ?? '') !== ''): ?>
    <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:12.5px">
        <i class="fa-solid fa-user-pen me-1" style="color:var(--cn-navy)"></i>
        Last filled in by <b><?= e((string) $confirmation['filled_by_name']) ?></b>
        &middot; <?= e(fmt_dt((string) ($confirmation['updated_at'] ?? $confirmation['submitted_at']))) ?>
    </div>
<?php endif; ?>

<form method="post" novalidate id="confirmForm">
    <?= Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <p class="text-muted" style="font-size:12.5px">
                Please confirm how this paper will be presented. This helps the committee plan the sessions and the venue.
                The registration form opens right after you submit this form.
            </p>

            <!-- ── 1. Presentasi ── -->
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-person-chalkboard text-muted"></i>
                    <h2>1. Presentation</h2>
                </div>
                <div class="cn-card-body">
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
                    <?= $err('presentation_mode') ?>

                    <div id="offlineBox" class="mt-3"<?= $values['presentation_mode'] === 'offline' ? '' : ' hidden' ?>>
                        <label class="form-label" for="attendees">How many people will come to the venue? <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <input type="number" class="form-control<?= isset($errors['attendees']) ? ' is-invalid' : '' ?>" id="attendees" name="attendees"
                                   value="<?= e((string) $values['attendees']) ?>" min="1" max="<?= $maxAttendees ?>" style="width:110px" <?= $disabled ?>>
                            <span id="attendeeText" class="text-muted" style="font-size:13px"></span>
                        </div>
                        <?= $err('attendees') ?>
                        <div class="form-text" style="font-size:11.5px">
                            Including the presenter. One person presents the paper; everyone else attends as a non-presenter
                            <?php if ($attendeeFee !== null): ?>
                                (non-presenter fee <?= e(fmt_money((float) $attendeeFee, $currency)) ?> per person)
                            <?php endif; ?>.
                            This is an estimate; you choose the people in the registration form.
                        </div>

                        <?php if ($shirtSizes !== []): ?>
                            <div class="mt-3">
                                <label class="form-label mb-1"><i class="fa-solid fa-shirt me-1 text-muted"></i>Shirt size of each person <span class="text-danger">*</span></label>
                                <div class="form-text mt-0 mb-2" style="font-size:11.5px">We order the conference shirts early, so please give one size per person who will come.</div>
                                <div class="row g-2" id="shirtList">
                                    <?php for ($i = 0; $i < $maxAttendees; $i++): ?>
                                        <?php $current = (string) ($values['shirts'][$i] ?? ''); ?>
                                        <div class="col-6 col-md-4 cn-shirt-row" data-index="<?= $i ?>">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text" style="min-width:104px;font-size:12px"><?= $i === 0 ? 'Presenter' : 'Non-presenter ' . $i ?></span>
                                                <select class="form-select" name="shirts[<?= $i ?>]" <?= $disabled !== '' ? 'disabled data-locked' : '' ?>>
                                                    <option value="">Size…</option>
                                                    <?php foreach ($shirtSizes as $size): ?>
                                                        <option value="<?= e($size) ?>" <?= $size === $current ? 'selected' : '' ?>><?= e($size) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                                <?= $err('shirts') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="onlineBox" class="form-text mt-3" style="font-size:12px"<?= $values['presentation_mode'] === 'online' ? '' : ' hidden' ?>>
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Online presenters send a pre-recorded video through the Video Presentation form after the payment is confirmed.
                    </div>
                </div>
            </div>

            <!-- ── 2. Halaman ── -->
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-lines text-muted"></i>
                    <h2>2. Number of pages</h2>
                </div>
                <div class="cn-card-body" style="font-size:13px">
                    <dl class="row mb-2">
                        <dt class="col-sm-5 text-muted fw-normal">Pages of the reviewed manuscript</dt>
                        <dd class="col-sm-7"><b><?= (int) $fee['pages'] ?></b> <span class="text-muted">(<?= (int) $fee['included'] ?> included in the registration fee)</span></dd>

                        <dt class="col-sm-5 text-muted fw-normal">Extra pages</dt>
                        <dd class="col-sm-7"><?= (int) $fee['extra'] ?></dd>

                        <dt class="col-sm-5 text-muted fw-normal">Extra page fee</dt>
                        <dd class="col-sm-7 mb-0">
                            <?php if ((int) $fee['extra'] > 0): ?>
                                <b style="color:var(--cn-navy)"><?= e(fmt_money((float) $fee['total'], $currency)) ?></b>
                                <span class="text-muted">(<?= (int) $fee['extra'] ?> &times; <?= e(fmt_money((float) $fee['unit'], $currency)) ?>)</span>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                    <div class="alert alert-light border py-2 px-3 mb-0" style="font-size:12.5px">
                        <i class="fa-solid fa-circle-info me-1" style="color:var(--cn-navy)"></i>
                        The limit is <?= (int) $fee['included'] ?> pages. The registration fee uses the number of pages of your
                        <b>final manuscript</b>: if you shorten the paper to <?= (int) $fee['included'] ?> pages, no extra page fee applies.
                        The number shown in the registration form is updated after the committee checks the final manuscript.
                    </div>
                </div>
            </div>

            <!-- ── 3. Catatan ── -->
            <div class="cn-card mb-3">
                <div class="cn-card-body">
                    <label class="form-label" for="note">Other notes for the committee <span class="text-muted">(optional)</span></label>
                    <textarea class="form-control<?= isset($errors['note']) ? ' is-invalid' : '' ?>" id="note" name="note" rows="3" maxlength="2000"
                              placeholder="e.g. preferred session day, accessibility needs, questions about the fee" <?= $disabled ?>><?= e((string) $values['note']) ?></textarea>
                    <?= $err('note') ?>
                </div>
            </div>

            <?php if ($editable): ?>
                <button type="submit" class="btn btn-gold px-4">
                    <i class="fa-solid fa-paper-plane me-1"></i><?= $confirmation === null ? 'Submit confirmation' : 'Update confirmation' ?>
                </button>
                <?php if ($confirmation !== null && !$adminMode): ?>
                    <span class="text-muted ms-2" style="font-size:12px">You can change it until the deadline, as long as the registration has not been submitted.</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-regular fa-file-lines text-muted"></i>
                    <h2>Paper <?= e((string) $author['paper_id']) ?></h2>
                </div>
                <div class="cn-card-body" style="font-size:13px">
                    <p class="mb-2"><b><?= e((string) ($author['paper_title'] ?? '')) ?></b></p>
                    <?php foreach ($paperAuthors as $pa): ?>
                        <div style="font-size:12.5px"><?= (int) $pa['position'] ?>. <?= e((string) $pa['name']) ?>
                            <span class="text-muted"><?= e((string) ($pa['affiliation'] ?? '')) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('confirmForm');
    var offline = document.getElementById('offlineBox');
    var online = document.getElementById('onlineBox');
    var input = document.getElementById('attendees');
    var text = document.getElementById('attendeeText');

    function update() {
        var mode = (form.querySelector('[name="presentation_mode"]:checked') || {}).value || '';
        offline.hidden = mode !== 'offline';
        online.hidden = mode !== 'online';
        var n = parseInt(input.value, 10) || 0;
        text.textContent = n < 1 ? '' : (n === 1 ? '1 presenter' : '1 presenter + ' + (n - 1) + ' non-presenter' + (n > 2 ? 's' : ''));
        // Satu pilihan ukuran baju per orang; sisanya disembunyikan dan tidak ikut dikirim.
        form.querySelectorAll('.cn-shirt-row').forEach(function (row) {
            var show = parseInt(row.dataset.index, 10) < n;
            row.hidden = !show;
            var select = row.querySelector('select');
            if (select && !select.hasAttribute('data-locked')) { select.disabled = !show; }
        });
    }

    form.addEventListener('change', update);
    input.addEventListener('input', update);
    update();
})();
</script>
