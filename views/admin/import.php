<?php
/**
 * Import CSV generik (lihat 4dm1n/import.php).
 *
 * @var string $step   upload | map | result
 * @var string $kind
 * @var array  $def    definisi jenis import (title, back, fields, required, keyLabel, help, options)
 */

use Cenim\Core\Csrf;

$selfUrl = admin_url('import.php?kind=' . $kind);
?>

<?php if ($step === 'upload'): ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-csv text-muted"></i>
                    <h2><?= e($def['title']) ?></h2>
                    <span class="cn-badge cn-badge-submitted ms-auto">Step 1 of 3</span>
                </div>
                <div class="cn-card-body">
                    <form method="post" enctype="multipart/form-data" action="<?= e($selfUrl) ?>">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="upload">
                        <label class="form-label" for="csv" style="font-size:12.5px">CSV file (max <?= e((string) $maxMb) ?>)</label>
                        <input type="file" class="form-control mb-3" id="csv" name="csv" accept=".csv,.txt,text/csv" required>
                        <p class="text-muted" style="font-size:12.5px"><?= $def['help'] ?></p>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-navy"><i class="fa-solid fa-upload me-1"></i>Upload &amp; map columns</button>
                            <a href="<?= e($def['back']) ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-table-columns text-muted"></i>
                    <h2>Recognised columns</h2>
                </div>
                <div class="cn-card-body" style="font-size:12.5px">
                    <p class="text-muted">Header names do not have to match exactly; delimiter (comma or semicolon) is detected automatically.</p>
                    <table class="table table-sm mb-0">
                        <?php foreach ($def['fields'] as $field => $aliases): ?>
                            <tr>
                                <td style="width:38%"><code><?= e($field) ?></code><?= in_array($field, $def['required'], true) ? ' <span class="text-danger">*</span>' : '' ?></td>
                                <td class="text-muted"><?= e(implode(', ', array_slice($aliases, 0, 5))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($step === 'map'): ?>
    <div class="cn-card mb-3">
        <div class="cn-card-head">
            <i class="fa-solid fa-diagram-project text-muted"></i>
            <h2>Map columns</h2>
            <span class="cn-badge cn-badge-submitted ms-auto">Step 2 of 3</span>
        </div>
        <div class="cn-card-body">
            <p class="mb-3" style="font-size:13px">
                <i class="fa-regular fa-file-lines me-1 text-muted"></i>
                <b><?= e($fileName) ?></b> &middot; <?= (int) $rowCount ?> data row(s), <?= count($headers) ?> column(s).
                The mapping below was detected automatically — please verify it.
                <?php if ($truncated): ?>
                    <span class="text-warning d-block mt-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>
                        The file is very large; only the first <?= (int) $rowCount ?> rows will be imported.</span>
                <?php endif; ?>
            </p>

            <form method="post" action="<?= e($selfUrl) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="confirm">

                <div class="row g-3">
                    <?php foreach (array_keys($def['fields']) as $field): ?>
                        <?php $isRequired = in_array($field, $def['required'], true); ?>
                        <div class="col-md-6 col-xl-3">
                            <label class="form-label" for="map_<?= e($field) ?>" style="font-size:12.5px">
                                <code><?= e($field) ?></code><?= $isRequired ? ' <span class="text-danger">*</span>' : '' ?>
                            </label>
                            <select class="form-select form-select-sm" id="map_<?= e($field) ?>" name="map[<?= e($field) ?>]" <?= $isRequired ? 'required' : '' ?>>
                                <option value="-1">&mdash; not mapped &mdash;</option>
                                <?php foreach ($headers as $index => $header): ?>
                                    <option value="<?= (int) $index ?>" <?= ($mapping[$field] ?? null) === $index ? 'selected' : '' ?>>
                                        <?= e($header === '' ? '(column ' . ($index + 1) . ')' : $header) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($def['defaults'])): ?>
                    <div class="row g-2 mt-2">
                        <?php foreach ($def['defaults'] as $field => $default): ?>
                            <div class="col-md-6 col-xl-4">
                                <label class="form-label" for="default_<?= e($field) ?>" style="font-size:12.5px"><?= e($default['label']) ?></label>
                                <input type="text" class="form-control form-control-sm" id="default_<?= e($field) ?>" name="default_<?= e($field) ?>"
                                       value="<?= e((string) $default['value']) ?>" maxlength="190" list="default_<?= e($field) ?>_list">
                                <?php if ($default['list'] !== []): ?>
                                    <datalist id="default_<?= e($field) ?>_list">
                                        <?php foreach ($default['list'] as $item): ?><option value="<?= e((string) $item) ?>"><?php endforeach; ?>
                                    </datalist>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($def['options'] as $name => $label): ?>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="opt_<?= e($name) ?>" name="<?= e($name) ?>" value="1">
                        <label class="form-check-label" for="opt_<?= e($name) ?>" style="font-size:13px"><?= e($label) ?></label>
                    </div>
                <?php endforeach; ?>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-navy"><i class="fa-solid fa-play me-1"></i>Run import</button>
                    <button type="submit" class="btn btn-outline-secondary" name="action" value="cancel" formnovalidate>Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="cn-card">
        <div class="cn-card-head">
            <i class="fa-solid fa-eye text-muted"></i>
            <h2>File preview</h2>
            <span class="text-muted ms-auto" style="font-size:12px">first <?= count($preview) ?> row(s)</span>
        </div>
        <div class="cn-card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" style="font-size:12px">
                    <thead><tr>
                        <th style="width:44px">#</th>
                        <?php foreach ($headers as $index => $header): ?>
                            <th><span class="text-muted"><?= (int) $index + 1 ?>.</span> <?= e($header === '' ? '(no header)' : $header) ?></th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($preview as $i => $row): ?>
                            <tr>
                                <td class="text-muted"><?= (int) $i + 2 ?></td>
                                <?php foreach ($headers as $index => $header): ?>
                                    <td><?= e(mb_strimwidth((string) ($row[$index] ?? ''), 0, 40, '…')) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="cn-card mb-3">
        <div class="cn-card-head">
            <i class="fa-solid fa-clipboard-check text-muted"></i>
            <h2>Import finished</h2>
            <span class="cn-badge cn-badge-verified ms-auto">Step 3 of 3</span>
        </div>
        <div class="cn-card-body d-flex flex-wrap gap-4">
            <?php
            $tiles = ['Added' => $stats['inserted'], 'Updated' => $stats['updated'], 'Skipped' => $stats['skipped'], 'Warnings' => $stats['warnings']];
            if ($kind === 'paper_authors') {
                $tiles['Papers created'] = $stats['papers_created'];
            }
            ?>
            <?php foreach ($tiles as $label => $value): ?>
                <div><div class="cn-stat-value"><?= (int) $value ?></div><div class="cn-stat-label"><?= e($label) ?></div></div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($issues !== []): ?>
        <div class="cn-card mb-3">
            <div class="cn-card-head"><i class="fa-solid fa-list-check text-muted"></i><h2>Row report</h2></div>
            <div class="cn-card-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:12.5px">
                        <thead><tr><th style="width:70px">Line</th><th style="width:200px"><?= e($def['keyLabel']) ?></th><th style="width:90px">Level</th><th>Message</th></tr></thead>
                        <tbody>
                            <?php foreach ($issues as $issue): ?>
                                <tr>
                                    <td><?= (int) $issue['line'] ?></td>
                                    <td><?= e($issue['key'] === '' ? '(empty)' : $issue['key']) ?></td>
                                    <td><span class="cn-badge <?= $issue['level'] === 'error' ? 'cn-badge-rejected' : 'cn-badge-unpaid' ?>"><?= e($issue['level']) ?></span></td>
                                    <td><?= e($issue['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <a href="<?= e($def['back']) ?>" class="btn btn-navy"><i class="fa-solid fa-arrow-left me-1"></i>Back to the list</a>
        <a href="<?= e($selfUrl) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-file-csv me-1"></i>Import another file</a>
    </div>
<?php endif; ?>
