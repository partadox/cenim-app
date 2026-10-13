<?php
/**
 * Form 5 — Video Presentation.
 *
 * @var array      $author       paper terpilih
 * @var array|null $attendance
 * @var array      $registration
 * @var array|null $presenter
 * @var array      $attendees    peserta tambahan dari registrasi
 * @var bool       $isOnline
 * @var bool       $isOpen
 * @var string     $deadline     YYYY-MM-DD atau ''
 * @var array      $values
 * @var array<string,string> $errors
 * @var array      $extraFields
 */

use Cenim\Core\Csrf;

$disabled = $isOpen ? '' : 'disabled';
$err = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>'
    : '';
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(author_url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <?php if (($attendance['video_url'] ?? '') !== ''): ?>
        <span class="cn-badge cn-badge-verified"><i class="fa-solid fa-check me-1"></i>Video link sent</span>
    <?php endif; ?>
    <?php if ($deadline !== ''): ?>
        <span class="text-muted ms-auto" style="font-size:12.5px"><i class="fa-regular fa-clock me-1"></i>Open until <?= e(fmt_date($deadline . ' 00:00:00')) ?></span>
    <?php endif; ?>
</div>

<?php if (!$isOpen): ?>
    <div class="alert alert-warning" style="font-size:13px">
        <i class="fa-solid fa-lock me-1"></i>The video presentation form is closed. Please contact the secretariat if something has changed.
    </div>
<?php endif; ?>

<?php if (($attendance['filled_by_name'] ?? '') !== ''): ?>
    <div class="alert alert-light border py-2 px-3 mb-3" style="font-size:12.5px">
        <i class="fa-solid fa-user-pen me-1" style="color:var(--cn-navy)"></i>
        Last filled in by <b><?= e((string) $attendance['filled_by_name']) ?></b>
        &middot; <?= e(fmt_dt((string) ($attendance['updated_at'] ?? $attendance['submitted_at']))) ?>
    </div>
<?php endif; ?>

<form method="post" novalidate>
    <?= Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-brands fa-youtube text-muted"></i>
                    <h2>Presentation video</h2>
                    <span class="cn-badge <?= $isOnline ? 'cn-badge-submitted' : 'cn-badge-draft' ?> ms-auto">
                        <?= $isOnline ? 'required — online presentation' : 'optional — offline presentation' ?>
                    </span>
                </div>
                <div class="cn-card-body">
                    <?php if (!$isOnline): ?>
                        <p class="text-muted" style="font-size:12.5px">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            This paper is presented <b>offline</b>, so no video is needed. You may still send one as a backup
                            in case the presenter cannot travel.
                        </p>
                    <?php endif; ?>
                    <label class="form-label" for="video_url">
                        Link to the video (Google Drive or YouTube)<?= $isOnline ? ' <span class="text-danger">*</span>' : '' ?>
                    </label>
                    <input type="url" class="form-control<?= isset($errors['video_url']) ? ' is-invalid' : '' ?>" id="video_url" name="video_url"
                           value="<?= e((string) $values['video_url']) ?>" placeholder="https://drive.google.com/file/d/… or https://youtu.be/…" <?= $disabled ?>>
                    <?= $err('video_url') ?>
                    <ul class="text-muted mt-2 mb-0 ps-3" style="font-size:12px">
                        <li><b>Google Drive</b>: share the file as "Anyone with the link — Viewer" so the committee can download it.</li>
                        <li><b>YouTube</b>: upload as <b>Unlisted</b> (not Private).</li>
                        <li>MP4, 1080p or 720p, within the presentation time given by the committee; the presenter's face/voice should be in the video.</li>
                    </ul>
                </div>
            </div>

            <?php if ($extraFields !== []): ?>
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
                <div class="cn-card-body">
                    <label class="form-label" for="note">Note for the committee <span class="text-muted">(optional)</span></label>
                    <textarea class="form-control" id="note" name="note" rows="2" maxlength="1000"
                              placeholder="e.g. the password of the video, a second link" <?= $disabled ?>><?= e((string) $values['note']) ?></textarea>
                </div>
            </div>

            <?php if ($isOpen): ?>
                <button type="submit" class="btn btn-gold px-4">
                    <i class="fa-solid fa-paper-plane me-1"></i><?= $attendance === null ? 'Send' : 'Update' ?>
                </button>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-regular fa-file-lines text-muted"></i>
                    <h2>Paper <?= e((string) $author['paper_id']) ?></h2>
                </div>
                <div class="cn-card-body" style="font-size:13px">
                    <p class="mb-2"><?= e((string) ($author['paper_title'] ?? '')) ?></p>
                    <dl class="mb-2" style="font-size:12.5px">
                        <dt class="text-muted fw-normal">Presenter</dt>
                        <dd><?= e((string) ($presenter['name'] ?? '—')) ?> &middot; <?= $isOnline ? 'online' : 'offline' ?></dd>
                        <dt class="text-muted fw-normal">Also attending</dt>
                        <dd class="mb-0"><?= $attendees !== [] ? e(implode(', ', array_column($attendees, 'name'))) : '—' ?></dd>
                    </dl>
                    <p class="text-muted mb-0" style="font-size:11.5px">
                        The presenter, the presentation mode and the attendees come from the registration form.
                        Contact the secretariat to change them.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>
