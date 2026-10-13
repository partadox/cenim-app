<?php
/**
 * @var array<string,array{ok:bool,detail:string,hint?:string,warning?:bool}> $checks
 */

$failed = 0;
$warned = 0;
foreach ($checks as $check) {
    if (!$check['ok']) {
        empty($check['warning']) ? $failed++ : $warned++;
    }
}
?>

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
