<?php
/**
 * @var array      $workshops    + registered
 * @var array      $registrants
 * @var int|null   $filter       workshop yang difilter
 * @var array|null $editing
 * @var bool       $enabled      workshop dipakai di edisi ini
 * @var bool       $open
 * @var bool       $certOn       sertifikat workshop aktif
 * @var int        $certIssued
 * @var bool       $requiresPay
 */

use Cenim\Core\Csrf;
use Cenim\Domain\ParticipantRepo;

$form = $editing ?? ['id' => null, 'title' => '', 'speaker' => '', 'description' => '', 'schedule' => '', 'quota' => null, 'sort' => (count($workshops) + 1) * 10, 'is_active' => 1];
?>

<div class="row g-3 mb-3">
    <?php foreach ($workshops as $w): ?>
        <div class="col-md-6 col-xl-4">
            <div class="cn-card h-100">
                <div class="cn-card-body">
                    <div class="d-flex align-items-start gap-2">
                        <div class="flex-grow-1">
                            <div style="font-size:14.5px;font-weight:600;color:#101e41"><?= e((string) $w['title']) ?></div>
                            <div class="text-muted" style="font-size:12.5px"><?= e((string) ($w['speaker'] ?? '')) ?></div>
                            <?php if (($w['schedule'] ?? '') !== ''): ?><div style="font-size:12px"><i class="fa-regular fa-clock me-1 text-muted"></i><?= e((string) $w['schedule']) ?></div><?php endif; ?>
                        </div>
                        <span class="cn-badge <?= (int) $w['is_active'] === 1 ? 'cn-badge-verified' : 'cn-badge-draft' ?>"><?= (int) $w['is_active'] === 1 ? 'Active' : 'Hidden' ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <span style="font-size:22px;font-weight:700;color:var(--cn-navy)"><?= (int) $w['registered'] ?></span>
                        <span class="text-muted" style="font-size:12px">registered<?= $w['quota'] !== null ? ' of ' . (int) $w['quota'] . ' seats' : '' ?></span>
                        <span class="ms-auto d-flex gap-1">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('workshops.php?workshop=' . (int) $w['id'])) ?>" title="Show registrants"><i class="fa-solid fa-users"></i></a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('workshops.php?edit=' . (int) $w['id'])) ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <form method="post" class="d-inline" onsubmit="return confirm(<?= e(json_encode('Delete "' . $w['title'] . '" and its ' . (int) $w['registered'] . ' registration(s)?')) ?>);">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-users text-muted"></i>
                <h2>Registrants</h2>
                <div class="ms-auto d-flex flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                        <a class="btn <?= $filter === null ? 'btn-navy' : 'btn-outline-secondary' ?>" href="<?= e(admin_url('workshops.php')) ?>">All</a>
                        <?php foreach ($workshops as $w): ?>
                            <a class="btn <?= $filter === (int) $w['id'] ? 'btn-navy' : 'btn-outline-secondary' ?>" href="<?= e(admin_url('workshops.php?workshop=' . (int) $w['id'])) ?>"><?= e((string) $w['title']) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('workshops.php?export=1' . ($filter !== null ? '&workshop=' . $filter : ''))) ?>">
                        <i class="fa-solid fa-file-excel me-1" style="color:#1d6f42"></i>Export Excel
                    </a>
                </div>
            </div>
            <div class="cn-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="font-size:12.5px">
                        <thead class="table-light">
                            <tr><th>#</th><th>Workshop</th><th>Name</th><th>Affiliation</th><th>From</th><th>Registered</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrants as $i => $r): ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td><?= e((string) $r['workshop_title']) ?></td>
                                    <td><b><?= e((string) $r['name']) ?></b><div class="text-muted"><?= e((string) $r['email']) ?></div></td>
                                    <td><?= e((string) ($r['affiliation'] ?? '')) ?></td>
                                    <td>
                                        <span class="cn-badge <?= ParticipantRepo::isParticipant($r) ? 'cn-badge-draft' : 'cn-badge-submitted' ?>"><?= ParticipantRepo::isParticipant($r) ? 'Participant' : 'Author' ?></span>
                                        <div class="text-muted" style="font-size:11px"><?= e((string) ($r['paper_id'] ?? '')) ?></div>
                                    </td>
                                    <td class="text-muted" style="font-size:11.5px"><?= e(fmt_dt((string) $r['created_at'])) ?></td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this registration?');">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="remove_registration">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Remove"><i class="fa-solid fa-xmark"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($registrants === []): ?>
                                <tr><td colspan="7" class="text-muted p-3">Nobody has registered yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <form method="post" class="cn-card mb-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="settings">
            <div class="cn-card-head">
                <i class="fa-solid fa-toggle-on text-muted"></i>
                <h2>Registration</h2>
            </div>
            <div class="cn-card-body" style="font-size:13px">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="workshop_enabled" name="workshop_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                    <label class="form-check-label" for="workshop_enabled"><b>This edition has workshops</b></label>
                    <div class="form-text" style="font-size:11.5px">Off: the workshop card and form are hidden from the Portal.</div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="workshop_open" name="workshop_open" value="1" <?= $open ? 'checked' : '' ?>>
                    <label class="form-check-label" for="workshop_open">Workshop registration open in the Portal</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="workshop_requires_payment" name="workshop_requires_payment" value="1" <?= $requiresPay ? 'checked' : '' ?>>
                    <label class="form-check-label" for="workshop_requires_payment">Only after the conference registration is paid</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="workshop_certificates" name="workshop_certificates" value="1" <?= $certOn ? 'checked' : '' ?>>
                    <label class="form-check-label" for="workshop_certificates">Workshop certificates</label>
                    <div class="form-text" style="font-size:11.5px">
                        Issued to every registrant after the certificate release date (downloaded from the Portal).
                        Remove registrants who did not attend. <?= (int) $certIssued ?> issued so far.
                        <a href="<?= e(admin_url('certificate-template.php?type=workshop')) ?>">Edit the certificate design</a>.
                    </div>
                </div>
                <button class="btn btn-sm btn-navy"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
                <p class="text-muted mt-2 mb-0" style="font-size:11.5px">Accepted authors and non-presenter participants register in the Portal (card above Step 0).</p>
            </div>
        </form>

        <form method="post" class="cn-card">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e((string) ($form['id'] ?? '')) ?>">
            <div class="cn-card-head">
                <i class="fa-solid <?= $editing !== null ? 'fa-pen' : 'fa-plus' ?> text-muted"></i>
                <h2><?= $editing !== null ? 'Edit workshop' : 'Add a workshop' ?></h2>
            </div>
            <div class="cn-card-body">
                <label class="form-label" for="title">Title</label>
                <input type="text" class="form-control form-control-sm mb-2" id="title" name="title" maxlength="190" value="<?= e((string) $form['title']) ?>" required>
                <label class="form-label" for="speaker">Speaker</label>
                <input type="text" class="form-control form-control-sm mb-2" id="speaker" name="speaker" maxlength="190" value="<?= e((string) ($form['speaker'] ?? '')) ?>">
                <label class="form-label" for="description">Short description</label>
                <textarea class="form-control form-control-sm mb-2" id="description" name="description" rows="3" maxlength="2000"><?= e((string) ($form['description'] ?? '')) ?></textarea>
                <label class="form-label" for="schedule">Schedule <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control form-control-sm mb-2" id="schedule" name="schedule" maxlength="190" placeholder="e.g. 24 Nov 2026, 13:00-16:00, Room A" value="<?= e((string) ($form['schedule'] ?? '')) ?>">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="quota">Seats <span class="text-muted">(empty = no limit)</span></label>
                        <input type="number" class="form-control form-control-sm" id="quota" name="quota" min="0" value="<?= e((string) ($form['quota'] ?? '')) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="sort">Order</label>
                        <input type="number" class="form-control form-control-sm" id="sort" name="sort" value="<?= e((string) $form['sort']) ?>">
                    </div>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $form['is_active'] === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active" style="font-size:13px">Shown in the Portal</label>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-navy"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
                    <?php if ($editing !== null): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('workshops.php')) ?>">Cancel</a><?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
