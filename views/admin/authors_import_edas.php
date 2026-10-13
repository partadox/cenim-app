<?php
/**
 * Pratinjau import ekspor paper EDAS (satu baris per paper, author 1..N).
 *
 * @var string $fileName
 * @var array<int,array<string,mixed>> $papers  hasil EdasImport::parse + 'exists'
 * @var bool   $truncated
 */

use Cenim\Core\Csrf;

$accepted = count(array_filter($papers, static fn (array $p): bool => $p['status'] === 'accepted'));
$authors  = array_sum(array_map(static fn (array $p): int => count($p['authors']), $papers));
$existing = count(array_filter($papers, static fn (array $p): bool => $p['exists']));
$noEmail  = 0;
foreach ($papers as $p) {
    foreach ($p['authors'] as $a) {
        $noEmail += $a['email'] === '' ? 1 : 0;
    }
}
$statuses = array_count_values(array_map(static fn (array $p): string => $p['edas_status'] !== '' ? $p['edas_status'] : '(empty)', $papers));
?>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-file-csv text-muted"></i>
        <h2>EDAS papers export detected</h2>
        <span class="cn-badge cn-badge-submitted ms-auto">Step 2 of 3</span>
    </div>
    <div class="cn-card-body" style="font-size:13px">
        <p class="mb-2">
            <i class="fa-regular fa-file-lines me-1 text-muted"></i>
            <b><?= e($fileName) ?></b> &middot; <?= e((string) count($papers)) ?> paper(s) with <?= e((string) $authors) ?> author(s).
            Columns are read automatically: <code>#</code> = paper ID, <code>Title</code>, <code>Status</code>,
            and <code>Author 1…N</code> (name, email, affiliation, country). Author 1 becomes the first author.
        </p>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <?php foreach ($statuses as $status => $count): ?>
                <span class="cn-badge cn-badge-<?= Cenim\Support\EdasImport::status((string) $status) === 'accepted' ? 'verified' : 'unpaid' ?>">
                    <?= e((string) $status) ?>: <?= e((string) $count) ?>
                </span>
            <?php endforeach; ?>
            <?php if ($existing > 0): ?>
                <span class="cn-badge cn-badge-submitted"><?= e((string) $existing) ?> already in the system</span>
            <?php endif; ?>
            <?php if ($noEmail > 0): ?>
                <span class="cn-badge cn-badge-rejected"><?= e((string) $noEmail) ?> author(s) without email</span>
            <?php endif; ?>
        </div>
        <?php if ($truncated): ?>
            <div class="alert alert-warning py-2 px-3 mb-2">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>The file is very large; only the first rows will be imported.
            </div>
        <?php endif; ?>
        <ul class="text-muted mb-0 ps-3" style="font-size:12.5px">
            <li>Country codes (<code>ID</code>) become country names (<code>Indonesia</code>). An affiliation of "none" is taken from the
                "Authors with affiliation and country" column.</li>
            <li>Existing papers are matched by paper ID and their authors by email, so importing a newer EDAS export again is safe.
                Pages, phone, presenter and notes already entered here are kept. Authors removed in EDAS are not deleted — they are listed in the report.</li>
            <li>Every author with an email can sign in at the portal with a 6-digit code.</li>
        </ul>
    </div>
</div>

<form method="post" class="cn-card mb-3" id="edasForm">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="confirm_edas">
    <input type="hidden" name="has_selection" value="1">
    <div class="cn-card-body d-flex flex-wrap align-items-center gap-3" style="font-size:13px">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="accepted_only" name="accepted_only" value="1"
                   <?= $accepted > 0 ? 'checked' : '' ?>>
            <label class="form-check-label" for="accepted_only">Import <b>accepted</b> papers only (<?= e((string) $accepted) ?>)</label>
        </div>
        <select class="form-select form-select-sm" name="mode" style="width:auto">
            <option value="update">Update papers that already exist</option>
            <option value="skip">Leave existing papers unchanged</option>
        </select>
        <div class="ms-auto d-flex gap-2">
            <button type="submit" class="btn btn-gold">
                <i class="fa-solid fa-file-import me-1"></i>Import <span id="edasCount"><?= e((string) count($papers)) ?></span> paper(s) &amp; authors
            </button>
            <button type="submit" name="action" value="cancel" class="btn btn-outline-secondary" formnovalidate>Cancel</button>
        </div>
    </div>
</form>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-eye text-muted"></i>
        <h2>Preview</h2>
        <span class="text-muted ms-auto" style="font-size:12px">Uncheck a paper to leave it out of the import.</span>
    </div>
    <div class="table-responsive" style="max-height:620px;overflow-y:auto">
        <table class="table table-sm align-top mb-0" style="font-size:12.5px">
            <thead class="table-light" style="position:sticky;top:0">
                <tr>
                    <th style="width:42px" class="text-center">
                        <input class="form-check-input" type="checkbox" id="edasAll" checked title="Include / exclude all">
                    </th>
                    <th style="width:110px">Paper ID</th>
                    <th style="width:28%">Title</th>
                    <th style="width:90px">Status</th>
                    <th>Authors (in order)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($papers as $p): ?>
                    <tr class="cn-edas-row">
                        <td class="text-center">
                            <input class="form-check-input cn-edas-pick" type="checkbox" form="edasForm" name="include[]"
                                   value="<?= e($p['paper_id']) ?>" checked title="Uncheck to leave this paper out of the import">
                        </td>
                        <td>
                            <code><?= e($p['paper_id']) ?></code>
                            <?php if ($p['exists']): ?><span class="d-block text-muted" style="font-size:11px">exists</span><?php endif; ?>
                        </td>
                        <td><?= e($p['title']) ?></td>
                        <td>
                            <span class="cn-badge cn-badge-<?= $p['status'] === 'accepted' ? 'verified' : 'unpaid' ?>"><?= e($p['edas_status'] ?: '-') ?></span>
                        </td>
                        <td>
                            <?php foreach ($p['authors'] as $a): ?>
                                <div>
                                    <span class="text-muted"><?= e($a['position']) ?>.</span>
                                    <b><?= e($a['name']) ?></b>
                                    <span class="text-muted">
                                        &middot; <?= $a['email'] !== '' ? e($a['email']) : '<span class="text-danger">no email</span>' ?>
                                        &middot; <?= e(trim($a['affiliation'] . ($a['country'] !== '' ? ', ' . $a['country'] : ''), ', ')) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach ($p['warnings'] as $w): ?>
                                <div class="text-warning-emphasis" style="font-size:11.5px"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= e($w) ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .cn-edas-row.is-excluded > td { opacity: .45; }
    .cn-edas-row.is-excluded > td:first-child { opacity: 1; }
</style>

<script>
(function () {
    var picks = Array.prototype.slice.call(document.querySelectorAll('.cn-edas-pick'));
    var all   = document.getElementById('edasAll');
    var count = document.getElementById('edasCount');
    function sync() {
        var n = 0;
        picks.forEach(function (box) {
            box.closest('tr').classList.toggle('is-excluded', !box.checked);
            if (box.checked) { n++; }
        });
        count.textContent = n;
        all.checked = n === picks.length;
        all.indeterminate = n > 0 && n < picks.length;
    }
    picks.forEach(function (box) { box.addEventListener('change', sync); });
    all.addEventListener('change', function () {
        picks.forEach(function (box) { box.checked = all.checked; });
        sync();
    });
    sync();
})();
</script>
