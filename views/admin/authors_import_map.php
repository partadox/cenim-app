<?php
/**
 * @var string               $fileName
 * @var array<int,string>    $headers
 * @var array<int,array<int,string>> $preview
 * @var int                  $rowCount
 * @var bool                 $truncated
 * @var array<string,int|null> $mapping   field => indeks kolom CSV
 * @var array<int,string>    $fields
 * @var array<int,string>    $required
 */

use Cenim\Core\Csrf;
?>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-diagram-project text-muted"></i>
        <h2>Map columns</h2>
        <span class="cn-badge cn-badge-submitted ms-auto">Step 2 of 3</span>
    </div>
    <div class="cn-card-body">
        <p class="mb-3" style="font-size:13px">
            <i class="fa-regular fa-file-lines me-1 text-muted"></i>
            <b><?= e($fileName) ?></b> &middot; <?= e((string) $rowCount) ?> data row(s), <?= e((string) count($headers)) ?> column(s).
            The mapping below was detected automatically — please verify it.
            <?php if ($truncated): ?>
                <span class="text-warning d-block mt-1">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    The file is very large; only the first <?= e((string) $rowCount) ?> rows will be imported.
                </span>
            <?php endif; ?>
        </p>

        <form method="post" id="mapForm">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="confirm">

            <div class="row g-3">
                <?php foreach ($fields as $field): ?>
                    <?php $isRequired = in_array($field, $required, true); ?>
                    <div class="col-md-6 col-xl-4">
                        <label class="form-label" for="map_<?= e($field) ?>" style="font-size:12.5px">
                            <code><?= e($field) ?></code>
                            <?php if ($isRequired): ?><span class="text-danger">*</span><?php endif; ?>
                        </label>
                        <select class="form-select form-select-sm cn-map-select"
                                id="map_<?= e($field) ?>"
                                name="map[<?= e($field) ?>]"
                                data-required="<?= $isRequired ? '1' : '0' ?>">
                            <option value="-1">&mdash; not mapped &mdash;</option>
                            <?php foreach ($headers as $index => $header): ?>
                                <option value="<?= e((string) $index) ?>" <?= $mapping[$field] === $index ? 'selected' : '' ?>>
                                    <?= e(($header === '' ? '(column ' . ($index + 1) . ')' : $header)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-3">

            <div class="mb-3">
                <span class="form-label d-block" style="font-size:12.5px">If a paper ID already exists</span>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="mode" id="mode_update" value="update" checked>
                    <label class="form-check-label" for="mode_update" style="font-size:13px">Update the existing record</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="mode" id="mode_skip" value="skip">
                    <label class="form-check-label" for="mode_skip" style="font-size:13px">Skip it (only add new papers)</label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-navy" id="runImport">
                    <i class="fa-solid fa-play me-1"></i>Run import
                </button>
                <button type="submit" class="btn btn-outline-secondary" name="action" value="cancel" formnovalidate>
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-eye text-muted"></i>
        <h2>File preview</h2>
        <span class="text-muted ms-auto" style="font-size:12px">first <?= e((string) count($preview)) ?> row(s)</span>
    </div>
    <div class="cn-card-body">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:12px">
                <thead>
                    <tr>
                        <th style="width:44px">#</th>
                        <?php foreach ($headers as $index => $header): ?>
                            <th>
                                <span class="text-muted"><?= e((string) ($index + 1)) ?>.</span>
                                <?= e($header === '' ? '(no header)' : $header) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview as $i => $row): ?>
                        <tr>
                            <td class="text-muted"><?= e((string) ($i + 2)) ?></td>
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
