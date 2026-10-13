<?php
/**
 * @var string                                                        $tab
 * @var array<string,int>                                             $counts
 * @var list<string>                                                  $actions
 * @var list<string>                                                  $actors
 * @var list<string>                                                  $templates
 * @var list<array{name:string,size:int,time:int}>                    $appLogs
 * @var int                                                           $mailFiles
 * @var list<array{name:string,path:string,size:int,time:int,kind:string}> $backups
 */
?>

<ul class="nav nav-tabs mb-3" style="font-size:13.5px">
    <?php foreach ([
        'audit' => ['Audit trail', 'fa-list-check', $counts['audit']],
        'mail'  => ['Email log', 'fa-envelope', $counts['mail']],
        'files' => ['Files & backup', 'fa-folder-open', $counts['files']],
    ] as $key => [$label, $icon, $count]): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
               href="<?= e(admin_url('logs.php?tab=' . $key)) ?>">
                <i class="fa-solid <?= e($icon) ?> me-1"></i><?= e($label) ?>
                <span class="text-muted">(<?= e(number_format($count, 0, ',', '.')) ?>)</span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'audit'): ?>
    <div class="cn-card">
        <div class="cn-card-head">
            <i class="fa-solid fa-list-check text-muted"></i>
            <h2>Audit trail</h2>
            <span class="text-muted ms-auto" style="font-size:12px">
                Read-only — entries are never edited or deleted from the panel.
            </span>
        </div>
        <div class="cn-card-body">
            <div class="row g-2 mb-3">
                <div class="col-sm-4">
                    <select id="fAction" class="form-select form-select-sm">
                        <option value="">All actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= e($action) ?>"><?= e($action) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <select id="fActor" class="form-select form-select-sm">
                        <option value="">All actors</option>
                        <?php foreach ($actors as $actor): ?>
                            <option value="<?= e($actor) ?>"><?= e($actor) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table id="auditTable" class="table table-sm table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>When (UTC+7)</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>IP</th>
                            <th>Meta</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'mail'): ?>
    <div class="cn-card">
        <div class="cn-card-head">
            <i class="fa-solid fa-envelope text-muted"></i>
            <h2>Email log</h2>
            <span class="text-muted ms-auto" style="font-size:12px">
                <code>skipped</code> = MAIL_ENABLED off, email disimpan sebagai berkas HTML.
            </span>
        </div>
        <div class="cn-card-body">
            <div class="row g-2 mb-3">
                <div class="col-sm-3">
                    <select id="fStatus" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option value="sent">Sent</option>
                        <option value="skipped">Skipped</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="col-sm-4">
                    <select id="fTemplate" class="form-select form-select-sm">
                        <option value="">All templates</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= e($template) ?>"><?= e($template) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table id="mailTable" class="table table-sm table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>When (UTC+7)</th>
                            <th>To</th>
                            <th>Subject</th>
                            <th>Template</th>
                            <th>Status</th>
                            <th class="text-end">Open</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="cn-card h-100">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-lines text-muted"></i>
                    <h2>Application log</h2>
                    <span class="text-muted ms-auto" style="font-size:12px">
                        <?= e((string) count($appLogs)) ?> file, dibersihkan otomatis &gt; 90 hari
                    </span>
                </div>
                <div class="cn-card-body">
                    <?php if ($appLogs === []): ?>
                        <p class="text-muted mb-0" style="font-size:13px">
                            Belum ada berkas log — artinya belum ada warning/error yang tercatat.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>File</th>
                                        <th>Size</th>
                                        <th>Modified (UTC+7)</th>
                                        <th class="text-end">Open</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appLogs as $log): ?>
                                        <tr>
                                            <td><code><?= e($log['name']) ?></code></td>
                                            <td><?= e(fmt_bytes($log['size'])) ?></td>
                                            <td style="font-size:12px"><?= e(fmt_dt(gmdate('Y-m-d H:i:s', $log['time']))) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                                                   title="Lihat 300 baris terakhir"
                                                   href="<?= e(admin_url('log-file.php?kind=app&name=' . urlencode($log['name']))) ?>">
                                                    <i class="fa-solid fa-up-right-from-square"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted mt-3 mb-0" style="font-size:12px">
                        <i class="fa-solid fa-envelope-open-text me-1"></i>
                        <?= e((string) $mailFiles) ?> berkas email tersimpan di <code>storage/logs/mail/</code>
                        — dibuka dari tab <a href="<?= e(admin_url('logs.php?tab=mail')) ?>">Email log</a>.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="cn-card h-100">
                <div class="cn-card-head">
                    <i class="fa-solid fa-database text-muted"></i>
                    <h2>Backup</h2>
                </div>
                <div class="cn-card-body">
                    <?php if ($backups === []): ?>
                        <div class="alert alert-warning mb-0" style="font-size:13px">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Belum ada arsip backup. Pasang cron harian:
                            <code>php cenim-app/bin/cron.php --quiet</code>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Archive</th>
                                        <th>Size</th>
                                        <th>Created (UTC+7)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($backups, 0, 10) as $file): ?>
                                        <tr>
                                            <td style="font-size:12px">
                                                <i class="fa-solid <?= $file['kind'] === 'db' ? 'fa-database' : 'fa-file-zipper' ?> text-muted me-1"></i>
                                                <?= e($file['name']) ?>
                                            </td>
                                            <td><?= e(fmt_bytes($file['size'])) ?></td>
                                            <td style="font-size:12px"><?= e(fmt_dt(gmdate('Y-m-d H:i:s', $file['time']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted mt-3 mb-0" style="font-size:12px">
                            Arsip disimpan di <code>storage/backup/</code> (di luar web root, tidak bisa diunduh
                            lewat browser). Pulihkan dengan langkah di <code>docs/runbook-deploy.md</code>.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
