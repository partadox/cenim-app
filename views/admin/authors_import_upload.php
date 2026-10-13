<?php
/**
 * @var array<string,array<int,string>> $fields   field => alias header
 * @var array<int,string>               $required
 * @var string                          $maxMb
 */

use Cenim\Core\Csrf;
?>

<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" enctype="multipart/form-data" novalidate>
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="upload">

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-csv text-muted"></i>
                    <h2>Upload CSV</h2>
                    <span class="cn-badge cn-badge-submitted ms-auto">Step 1 of 3</span>
                </div>
                <div class="cn-card-body">
                    <label class="form-label" for="csv">CSV file from your submission system</label>
                    <input class="form-control" type="file" id="csv" name="csv" accept=".csv,.txt,text/csv" required>
                    <div class="form-text" style="font-size:12px">
                        Maximum <?= e((string) $maxMb) ?> (server limit). Comma, semicolon, or tab separated — all are detected
                        automatically, including files exported from Excel.
                    </div>

                    <div class="alert alert-light border py-2 px-3 mt-3 mb-0" style="font-size:12.5px">
                        <i class="fa-solid fa-wand-magic-sparkles me-1" style="color:var(--cn-navy)"></i>
                        <b>EDAS:</b> upload the papers export as is (EDAS &rarr; Papers &rarr; download as CSV, with the
                        author columns). It is recognised automatically and imports every paper <b>together with all its
                        authors</b> (Author 1&hellip;N, email, affiliation, country) in one go &mdash; no column mapping needed.
                    </div>

                    <hr class="my-3">

                    <p class="mb-2" style="font-size:13px">
                        <i class="fa-solid fa-circle-info me-1 text-muted"></i>
                        Column names do not have to match exactly — you will map them in the next step. Existing papers
                        are matched by <b>paper ID</b> and updated, so re-importing an updated export is safe.
                    </p>

                    <button type="submit" class="btn btn-navy">
                        <i class="fa-solid fa-arrow-right me-1"></i>Continue to mapping
                    </button>
                    <a href="<?= e(admin_url('authors.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <div class="col-xl-5">
        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-table-columns text-muted"></i>
                <h2>Recognised columns</h2>
            </div>
            <div class="cn-card-body p-0">
                <table class="table table-sm mb-0" style="font-size:12.5px">
                    <thead>
                        <tr><th>Field</th><th>Header names detected automatically</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field => $aliases): ?>
                            <tr>
                                <td style="white-space:nowrap">
                                    <code><?= e($field) ?></code>
                                    <?php if (in_array($field, $required, true)): ?>
                                        <span class="text-danger" title="Required">*</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= e(implode(', ', array_slice($aliases, 0, 4))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted mt-2 mb-0" style="font-size:12px">
            <span class="text-danger">*</span> required. Tip: use
            <a href="<?= e(admin_url('authors-export.php')) ?>">Export CSV</a> to get a file with exactly these columns.
        </p>
    </div>
</div>
