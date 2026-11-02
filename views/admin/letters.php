<?php
/**
 * @var array<int,array<string,mixed>> $letters
 */

use Cenim\Core\Csrf;
use Cenim\Domain\LetterRepo;
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="text-muted" style="font-size:12.5px">
        Letters with the conference letterhead and the signature of the chair or the treasurer, e.g. invitation or visa letters.
    </span>
    <a class="btn btn-sm btn-navy ms-auto" href="<?= e(admin_url('letter-edit.php')) ?>">
        <i class="fa-solid fa-plus me-1"></i>New letter
    </a>
</div>

<div class="cn-card">
    <div class="cn-card-body">
        <table class="table table-sm align-middle mb-0" id="lettersTable" style="font-size:12.5px;width:100%">
            <thead class="table-light">
                <tr><th class="text-center">No</th><th>Letter</th><th>Number</th><th>Paper</th><th>Signed by</th><th>Date</th><th>Updated</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($letters as $i => $letter): ?>
                    <tr>
                        <td class="text-center text-muted"><?= $i + 1 ?></td>
                        <td><b><?= e((string) $letter['title']) ?></b></td>
                        <td><?= ($letter['number'] ?? '') !== '' ? '<code>' . e((string) $letter['number']) . '</code>' : '<span class="text-muted">&mdash;</span>' ?></td>
                        <td>
                            <?php if (($letter['paper_id'] ?? null) !== null): ?>
                                <code><?= e((string) $letter['paper_id']) ?></code>
                                <div class="text-muted" style="font-size:11.5px"><?= e((string) $letter['first_author_name']) ?></div>
                            <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                        </td>
                        <td><?= e(LetterRepo::SIGNERS[(string) $letter['signer']] ?? (string) $letter['signer']) ?></td>
                        <td data-order="<?= e((string) ($letter['letter_date'] ?? '')) ?>"><?= ($letter['letter_date'] ?? null) !== null ? e(fmt_date($letter['letter_date'] . ' 00:00:00')) : '<span class="text-muted">&mdash;</span>' ?></td>
                        <td data-order="<?= e((string) ($letter['updated_at'] ?? $letter['created_at'])) ?>" class="text-muted"><?= e(fmt_dt((string) ($letter['updated_at'] ?? $letter['created_at']))) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="Open PDF"
                               href="<?= e(admin_url('letter-pdf.php?id=' . (int) $letter['id'])) ?>"><i class="fa-solid fa-file-pdf"></i></a>
                            <a class="btn btn-sm btn-outline-secondary" title="Edit"
                               href="<?= e(admin_url('letter-edit.php?id=' . (int) $letter['id'])) ?>"><i class="fa-solid fa-pen"></i></a>
                            <form method="post" class="d-inline" onsubmit="return confirm(<?= e(json_encode('Delete the letter "' . $letter['title'] . '"? This cannot be undone.')) ?>);">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $letter['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = new DataTable('#lettersTable', {
        order: [[6, 'desc']],
        pageLength: 25,
        columnDefs: [
            { targets: 0, orderable: false, searchable: false },
            { targets: -1, orderable: false, searchable: false }
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search letter, number, paper…',
            emptyTable: 'No letter yet. Click "New letter" to create one.',
            zeroRecords: 'No letter matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ letters',
            infoEmpty: 'No letters',
            infoFiltered: '(filtered from _MAX_ total)'
        }
    });
    table.on('draw', function () {
        var start = table.page.info().start;
        table.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, i) {
            cell.textContent = start + i + 1;
        });
    });
    table.draw(false);
});
</script>
