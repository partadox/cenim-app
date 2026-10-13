<?php
/**
 * @var int   $inserted
 * @var int   $updated
 * @var int   $skipped
 * @var int   $warnings
 * @var array<int,array{line:int,paper_id:string,level:string,message:string}> $issues
 * @var bool  $truncated
 * @var string $batch
 */

$cards = [
    ['label' => 'Inserted', 'value' => $inserted, 'icon' => 'fa-circle-plus',          'color' => '#12704a', 'bg' => 'rgba(26,156,98,.1)'],
    ['label' => 'Updated',  'value' => $updated,  'icon' => 'fa-arrows-rotate',        'color' => '#14509b', 'bg' => 'rgba(0,57,129,.08)'],
    ['label' => 'Skipped',  'value' => $skipped,  'icon' => 'fa-circle-minus',         'color' => '#a3231f', 'bg' => 'rgba(163,35,31,.1)'],
    ['label' => 'Warnings', 'value' => $warnings, 'icon' => 'fa-triangle-exclamation', 'color' => '#8a5a06', 'bg' => 'rgba(254,189,6,.15)'],
];
?>

<div class="row g-3 mb-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-6 col-lg-3">
            <div class="cn-card">
                <div class="cn-card-body cn-stat">
                    <span class="cn-stat-icon" style="background:<?= e($card['bg']) ?>;color:<?= e($card['color']) ?>">
                        <i class="fa-solid <?= e($card['icon']) ?>"></i>
                    </span>
                    <span>
                        <span class="cn-stat-value d-block"><?= e((string) $card['value']) ?></span>
                        <span class="cn-stat-label"><?= e($card['label']) ?></span>
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-clipboard-check text-muted"></i>
        <h2>Import report</h2>
        <span class="text-muted ms-auto" style="font-size:12px">batch <code><?= e($batch) ?></code></span>
    </div>
    <div class="cn-card-body">
        <?php if ($truncated): ?>
            <div class="alert alert-warning py-2 px-3" style="font-size:13px">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                The file exceeded the row limit, so only the first batch of rows was processed. Split the file and import the rest.
            </div>
        <?php endif; ?>

        <?php if ($issues === []): ?>
            <div class="alert alert-success py-2 px-3 mb-3" style="font-size:13px">
                <i class="fa-solid fa-circle-check me-1"></i>
                Every row was imported without problems.
            </div>
        <?php else: ?>
            <p class="mb-2" style="font-size:13px">
                <?= e((string) count($issues)) ?> row(s) need attention.
                <span class="cn-badge cn-badge-rejected">Skipped</span> rows were <b>not</b> imported — fix them in the file
                and import again (existing records are simply updated).
                <span class="cn-badge cn-badge-unpaid">Warning</span> rows <b>were</b> imported, but one field fell back to
                its default value.
            </p>
            <div class="table-responsive mb-3" style="max-height:420px;overflow-y:auto">
                <table class="table table-sm table-bordered mb-0" style="font-size:12.5px">
                    <thead>
                        <tr>
                            <th style="width:96px">Result</th>
                            <th style="width:80px">CSV line</th>
                            <th style="width:160px">Paper ID</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issues as $issue): ?>
                            <?php $isError = ($issue['level'] ?? 'error') === 'error'; ?>
                            <tr>
                                <td>
                                    <span class="cn-badge <?= $isError ? 'cn-badge-rejected' : 'cn-badge-unpaid' ?>">
                                        <?= $isError ? 'Skipped' : 'Warning' ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= e((string) $issue['line']) ?></td>
                                <td><code><?= e($issue['paper_id'] === '' ? '(empty)' : $issue['paper_id']) ?></code></td>
                                <td><?= e($issue['message']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-2">
            <a href="<?= e(admin_url('authors.php')) ?>" class="btn btn-navy">
                <i class="fa-solid fa-users me-1"></i>View authors
            </a>
            <a href="<?= e(admin_url('authors-import.php')) ?>" class="btn btn-outline-secondary">
                <i class="fa-solid fa-file-import me-1"></i>Import another file
            </a>
        </div>
    </div>
</div>
