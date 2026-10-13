<?php
/**
 * @var array  $rows
 * @var array  $counts  offline|online|missing|people
 * @var int    $total
 * @var string $filter
 * @var array  $extra   field Form Builder (attend_step3)
 * @var bool   $shirtOn
 * @var array<string,int> $shirts
 */

use Cenim\Domain\AttendanceRepo;
use Cenim\Domain\FormRepo;

$tabs = [
    ''        => ['All paid papers', $total],
    'offline' => ['Offline', $counts['offline']],
    'online'  => ['Online', $counts['online']],
    'missing' => ['Online without video', $counts['missing']],
];
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm flex-wrap">
        <?php foreach ($tabs as $key => [$label, $n]): ?>
            <a class="btn <?= $filter === $key ? 'btn-navy' : 'btn-outline-secondary' ?>"
               href="<?= e(admin_url('attendance.php' . ($key !== '' ? '?mode=' . $key : ''))) ?>"><?= e($label) ?> <span class="badge text-bg-light ms-1"><?= (int) $n ?></span></a>
        <?php endforeach; ?>
    </div>
    <span class="text-muted" style="font-size:12.5px"><i class="fa-solid fa-users me-1"></i><?= (int) $counts['people'] ?> people attending</span>
    <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e(admin_url('attendance.php?export=1' . ($filter !== '' ? '&mode=' . $filter : ''))) ?>">
        <i class="fa-solid fa-file-export me-1"></i>Export CSV
    </a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('forms.php?form=' . FormRepo::FORM_ATTENDANCE)) ?>">
        <i class="fa-solid fa-square-check me-1"></i>Form questions
    </a>
</div>

<?php if ($shirtOn): ?>
    <div class="cn-card mb-3">
        <div class="cn-card-body d-flex flex-wrap align-items-center gap-3" style="font-size:13px">
            <span><i class="fa-solid fa-shirt me-1 text-muted"></i><b>Shirt sizes</b> <span class="text-muted">(paid papers)</span></span>
            <?php foreach ($shirts as $size => $n): ?>
                <span class="border rounded px-2 py-1"><?= e($size) ?> <b><?= (int) $n ?></b></span>
            <?php endforeach; ?>
            <span class="text-muted">total <b><?= array_sum($shirts) ?></b></span>
        </div>
    </div>
<?php endif; ?>

<div class="cn-card">
    <div class="cn-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:12.5px">
                <thead class="table-light">
                    <tr><th>Paper</th><th>Mode</th><th>Presenter</th><th>Also attending (non-presenter)</th><th>Video</th><th>Answers &amp; note</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $answers = FormRepo::decode($row['extra_json'] ?? null); ?>
                        <tr>
                            <td style="max-width:260px">
                                <a href="<?= e(admin_url('registration-view.php?id=' . (int) $row['registration_id'])) ?>"><b><?= e((string) $row['paper_id']) ?></b></a>
                                <div class="text-muted text-truncate" title="<?= e((string) $row['paper_title']) ?>"><?= e((string) $row['paper_title']) ?></div>
                            </td>
                            <td>
                                <span class="cn-badge <?= $row['mode'] === 'online' ? 'cn-badge-submitted' : 'cn-badge-verified' ?>">
                                    <i class="fa-solid <?= $row['mode'] === 'online' ? 'fa-video' : 'fa-people-group' ?> me-1"></i><?= e((string) $row['mode']) ?>
                                </span>
                            </td>
                            <td>
                                <?= e((string) ($row['presenter_name'] ?? '—')) ?>
                                <?php if ($shirtOn && ($row['presenter_shirt'] ?? '') !== ''): ?>
                                    <span class="cn-badge cn-badge-draft ms-1" title="Shirt size"><?= e((string) $row['presenter_shirt']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px">
                                <?php foreach ($row['attendees'] as $a): ?>
                                    <div>
                                        <?= e((string) $a['name']) ?>
                                        <span class="text-muted"><?= $a['paper_author_id'] !== null ? 'co-author' : e('guest · ' . ($a['affiliation'] ?? '')) ?></span>
                                        <?php if ($shirtOn && ($a['shirt_size'] ?? '') !== ''): ?><span class="cn-badge cn-badge-draft"><?= e((string) $a['shirt_size']) ?></span><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($row['attendees'] === []): ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if (($row['video_url'] ?? '') !== ''): ?>
                                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="<?= e((string) $row['video_url']) ?>">
                                        <i class="fa-brands <?= $row['video_host'] === 'youtube' ? 'fa-youtube' : 'fa-google-drive' ?> me-1"></i><?= e(AttendanceRepo::VIDEO_HOSTS[$row['video_host']] ?? 'Open') ?>
                                    </a>
                                <?php elseif ($row['missing']): ?>
                                    <span class="text-danger" style="font-size:11.5px">missing</span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td style="font-size:11.5px;max-width:240px">
                                <?php foreach ($extra as $field): ?>
                                    <?php $v = $answers[(string) $field['field_key']] ?? null; ?>
                                    <?php if ($v !== null && $v !== '' && $v !== false): ?>
                                        <div><span class="text-muted"><?= e((string) $field['label']) ?>:</span> <?= e(is_bool($v) ? 'Yes' : (string) $v) ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (($row['note'] ?? '') !== ''): ?><div><i class="fa-regular fa-note-sticky me-1"></i><?= e((string) $row['note']) ?></div><?php endif; ?>
                                <?php if (($row['filled_by_name'] ?? '') !== ''): ?><div class="text-muted">by <?= e((string) $row['filled_by_name']) ?></div><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="6" class="text-muted p-3">No paper here yet — a paper appears once its payment is confirmed.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
