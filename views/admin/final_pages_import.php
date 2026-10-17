<?php
/**
 * @var string $step   upload | preview
 * @var string $maxMb
 * @var string $fileName
 * @var string $pagesHeader
 * @var array  $rows     per baris: line, paper_id, new, author, status, registration, invoiced
 * @var int    $changes
 * @var int    $included
 */

use Cenim\Core\Csrf;

$labels = [
    'change'    => ['cn-badge-submitted', 'will update'],
    'same'      => ['cn-badge-draft', 'no change'],
    'unknown'   => ['cn-badge-rejected', 'paper not found'],
    'invalid'   => ['cn-badge-rejected', 'invalid pages'],
    'duplicate' => ['cn-badge-rejected', 'duplicate row'],
];
?>

<?php if ($step === 'upload'): ?>
    <div class="row g-3">
        <div class="col-xl-7">
            <form method="post" enctype="multipart/form-data" novalidate class="cn-card">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="upload">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-csv text-muted"></i>
                    <h2>Upload CSV</h2>
                    <span class="cn-badge cn-badge-submitted ms-auto">Step 1 of 2</span>
                </div>
                <div class="cn-card-body">
                    <p style="font-size:13px">
                        Updates <b>only the number of pages of the final manuscript</b>. Titles, authors, emails and statuses
                        are not touched. The registration form and invoices then use these pages instead of the reviewed manuscript.
                    </p>
                    <label class="form-label" for="csv">CSV file</label>
                    <input class="form-control" type="file" id="csv" name="csv" accept=".csv,.txt,text/csv" required>
                    <div class="form-text" style="font-size:12px">Maximum <?= e($maxMb) ?>. Comma, semicolon or tab separated.</div>

                    <div class="alert alert-light border py-2 px-3 mt-3 mb-3" style="font-size:12.5px">
                        <b>Accepted columns</b>
                        <ul class="mb-0 ps-3">
                            <li>Paper ID: <code>#</code> (EDAS) or <code>Paper ID</code></li>
                            <li>Pages: <code>final manuscript: pages</code> (EDAS), <code>Final pages</code>, or <code>Pages</code></li>
                        </ul>
                        An EDAS export with "review manuscript: pages" only is refused, so the reviewed page count is never imported as final by mistake.
                    </div>

                    <button type="submit" class="btn btn-navy"><i class="fa-solid fa-arrow-right me-1"></i>Preview the changes</button>
                    <a href="<?= e(admin_url('confirmations.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="cn-card mb-3">
        <div class="cn-card-body d-flex flex-wrap align-items-center gap-3" style="font-size:13px">
            <span><i class="fa-solid fa-file-csv me-1 text-muted"></i><b><?= e($fileName) ?></b> &middot; column <code><?= e($pagesHeader) ?></code></span>
            <span class="text-muted"><?= count($rows) ?> row(s), <b><?= $changes ?></b> paper(s) will be updated</span>
            <form method="post" class="ms-auto d-flex gap-2">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="confirm">
                <a href="<?= e(admin_url('final-pages-import.php')) ?>" class="btn btn-sm btn-outline-secondary">Upload another file</a>
                <button class="btn btn-sm btn-navy" <?= $changes === 0 ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-check me-1"></i>Update <?= $changes ?> paper(s)
                </button>
            </form>
        </div>
    </div>

    <div class="cn-card">
        <div class="cn-card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:12.5px">
                    <thead class="table-light">
                        <tr><th>Line</th><th>Paper</th><th class="text-center">Review</th><th class="text-center">Final now</th><th class="text-center">Final new</th><th>Result</th><th>Registration</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php [$cls, $label] = $labels[$row['status']] ?? ['cn-badge-draft', $row['status']]; ?>
                            <tr>
                                <td class="text-muted"><?= (int) $row['line'] ?></td>
                                <td style="max-width:320px">
                                    <b><?= e((string) $row['paper_id']) ?></b>
                                    <?php if ($row['author'] !== null): ?>
                                        <div class="text-muted text-truncate"><?= e((string) ($row['author']['paper_title'] ?? '')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= $row['author'] !== null ? (int) $row['author']['total_pages'] : '' ?></td>
                                <td class="text-center"><?= $row['author'] !== null && (int) ($row['author']['final_pages'] ?? 0) > 0 ? (int) $row['author']['final_pages'] : '<span class="text-muted">&mdash;</span>' ?></td>
                                <td class="text-center"><b class="<?= (int) $row['new'] > $included ? 'text-danger' : '' ?>"><?= (int) $row['new'] ?: '' ?></b></td>
                                <td><span class="cn-badge <?= e($cls) ?>"><?= e($label) ?></span></td>
                                <td style="font-size:11.5px">
                                    <?php if (($row['registration'] ?? '') !== ''): ?>
                                        <?= e(ucfirst((string) $row['registration'])) ?>
                                        <?php if (!empty($row['invoiced'])): ?>
                                            <div class="text-danger">invoice already issued &mdash; void and reissue it to apply the new pages</div>
                                        <?php elseif ($row['status'] === 'change' && $row['registration'] !== 'draft'): ?>
                                            <div class="text-muted">fee will be recalculated</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">not started</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
