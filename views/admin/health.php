<?php
/**
 * @var array<string,array{ok:bool,detail:string,hint?:string,warning?:bool}> $checks
 * @var array<int,string> $pending  migrasi yang belum diterapkan
 * @var bool              $canRun   admin login (bukan jalur darurat ?key=)
 */

use Cenim\Core\Csrf;

$failed = 0;
$warned = 0;
foreach ($checks as $check) {
    if (!$check['ok']) {
        empty($check['warning']) ? $failed++ : $warned++;
    }
}
?>

<?php if ($pending !== []): ?>
    <div class="cn-card mb-3" style="border-left:4px solid var(--cn-gold)">
        <div class="cn-card-body d-flex flex-wrap align-items-center gap-3">
            <i class="fa-solid fa-database" style="font-size:22px;color:#8a5a06"></i>
            <div style="font-size:13px">
                <b><?= count($pending) ?> database migration(s) pending</b> — usually after a Git pull with new features.
                <div class="text-muted" style="font-size:12px"><?= e(implode(', ', $pending)) ?></div>
                <div class="text-muted" style="font-size:11.5px">A snapshot of the database is saved in storage/backup first.</div>
            </div>
            <?php if ($canRun): ?>
                <form method="post" class="ms-auto m-0" onsubmit="return confirm('Run <?= count($pending) ?> pending migration(s) now? A database snapshot is taken first.')">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="migrate">
                    <button class="btn btn-navy"><i class="fa-solid fa-play me-1"></i>Run pending migrations</button>
                </form>
            <?php else: ?>
                <span class="text-muted ms-auto" style="font-size:12px">Sign in to the admin panel to run them.</span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="cn-card">
            <div class="cn-card-body cn-stat">
                <span class="cn-stat-icon" style="background:rgba(26,156,98,.1);color:#12704a"><i class="fa-solid fa-circle-check"></i></span>
                <span>
                    <span class="cn-stat-value d-block"><?= e((string) (count($checks) - $failed - $warned)) ?></span>
                    <span class="cn-stat-label">Passed</span>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="cn-card">
            <div class="cn-card-body cn-stat">
                <span class="cn-stat-icon" style="background:rgba(254,189,6,.15);color:#8a5a06"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <span>
                    <span class="cn-stat-value d-block"><?= e((string) $warned) ?></span>
                    <span class="cn-stat-label">Warning</span>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="cn-card">
            <div class="cn-card-body cn-stat">
                <span class="cn-stat-icon" style="background:rgba(163,35,31,.1);color:#a3231f"><i class="fa-solid fa-circle-xmark"></i></span>
                <span>
                    <span class="cn-stat-value d-block"><?= e((string) $failed) ?></span>
                    <span class="cn-stat-label">Failed</span>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-heart-pulse text-muted"></i>
        <h2>Environment checks</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:44px"></th>
                    <th>Check</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $name => $check): ?>
                    <tr>
                        <td class="text-center">
                            <?php if ($check['ok']): ?>
                                <i class="fa-solid fa-circle-check" style="color:#1a9c62"></i>
                            <?php elseif (!empty($check['warning'])): ?>
                                <i class="fa-solid fa-triangle-exclamation" style="color:#d69200"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-circle-xmark" style="color:#a3231f"></i>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap"><?= e($name) ?></td>
                        <td>
                            <span style="font-size:12.5px"><code><?= e($check['detail']) ?></code></span>
                            <?php if (!$check['ok'] && !empty($check['hint'])): ?>
                                <div class="text-muted" style="font-size:12px">
                                    <i class="fa-solid fa-arrow-turn-up fa-rotate-90 me-1"></i><?= e($check['hint']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
