<?php
/**
 * Workshop Registration.
 *
 * @var array $person     baris paper_authors orang yang login
 * @var array $workshops  workshop aktif + registered
 * @var array $joined     id workshop yang sudah diikuti
 * @var bool  $editable
 * @var bool  $open
 * @var bool  $needsPay
 */

use Cenim\Core\Csrf;

$disabled = $editable ? '' : 'disabled';
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(author_url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <?php if ($joined !== []): ?>
        <span class="cn-badge cn-badge-verified"><i class="fa-solid fa-check me-1"></i>Registered for <?= count($joined) ?> workshop<?= count($joined) > 1 ? 's' : '' ?></span>
    <?php endif; ?>
    <a href="<?= e(base_url('workshop.php')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="fa-solid fa-circle-info me-1"></i>Workshop details<i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
    </a>
</div>

<?php if ($needsPay): ?>
    <div class="alert alert-warning" style="font-size:13px">
        <i class="fa-solid fa-lock me-1"></i>
        Workshop registration opens once your conference registration payment is confirmed.
        Complete the registration form and the payment first.
    </div>
<?php elseif (!$open): ?>
    <div class="alert alert-warning" style="font-size:13px"><i class="fa-solid fa-lock me-1"></i>Workshop registration is closed.</div>
<?php endif; ?>

<form method="post">
    <?= Csrf::field() ?>
    <div class="cn-card mb-3">
        <div class="cn-card-head">
            <i class="fa-solid fa-chalkboard-user text-muted"></i>
            <h2>Choose your workshops</h2>
        </div>
        <div class="cn-card-body">
            <p class="text-muted" style="font-size:12.5px">
                Registering as <b><?= e((string) $person['name']) ?></b> (<?= e((string) $person['email']) ?>).
                The workshops are held offline at the conference venue. You may join more than one.
            </p>
            <?php foreach ($workshops as $w): ?>
                <?php
                $id     = (int) $w['id'];
                $mine   = in_array($id, $joined, true);
                $full   = $w['quota'] !== null && (int) $w['registered'] >= (int) $w['quota'] && !$mine;
                ?>
                <label class="d-flex gap-3 border rounded p-3 mb-2" style="cursor:<?= $full || !$editable ? 'default' : 'pointer' ?>;<?= $mine ? 'background:#f2fbf6;border-color:#bfe5cf !important' : '' ?>">
                    <input class="form-check-input mt-1" type="checkbox" name="workshops[]" value="<?= $id ?>"
                        <?= $mine ? 'checked' : '' ?> <?= $full ? 'disabled' : $disabled ?>>
                    <span class="flex-grow-1" style="font-size:13px">
                        <b style="font-size:14.5px;color:#101e41"><?= e((string) $w['title']) ?></b>
                        <?php if (($w['speaker'] ?? '') !== ''): ?><span class="d-block">by <b><?= e((string) $w['speaker']) ?></b></span><?php endif; ?>
                        <?php if (($w['description'] ?? '') !== ''): ?><span class="d-block text-muted"><?= e((string) $w['description']) ?></span><?php endif; ?>
                        <?php if (($w['schedule'] ?? '') !== ''): ?><span class="d-block mt-1"><i class="fa-regular fa-clock me-1 text-muted"></i><?= e((string) $w['schedule']) ?></span><?php endif; ?>
                    </span>
                    <span class="text-end" style="font-size:11.5px;white-space:nowrap">
                        <?php if ($w['quota'] !== null): ?>
                            <span class="<?= $full ? 'text-danger' : 'text-muted' ?>"><?= $full ? 'Full' : max(0, (int) $w['quota'] - (int) $w['registered']) . ' seats left' ?></span>
                        <?php endif; ?>
                        <?php if ($mine): ?><span class="d-block cn-badge cn-badge-verified mt-1">Registered</span><?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>
            <?php if ($workshops === []): ?>
                <div class="text-muted" style="font-size:13px">No workshop is open yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($editable && $workshops !== []): ?>
        <button type="submit" class="btn btn-gold px-4"><i class="fa-solid fa-floppy-disk me-1"></i>Save my workshop registration</button>
        <span class="text-muted ms-2" style="font-size:12px">Untick a workshop and save to cancel it.</span>
    <?php endif; ?>
</form>
