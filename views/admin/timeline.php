<?php
/**
 * @var array<int,array<string,mixed>> $items
 */

use Cenim\Core\Csrf;
use Cenim\Domain\TimelineRepo;
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
       href="<?= e(app_url('site/preview.php?page=home')) ?>">
        <i class="fa-solid fa-eye me-1"></i>Preview landing
    </a>
    <span class="text-muted ms-auto" style="font-size:12px">
        Dates are free text, so “12 August 2026” or “24 - 25 November 2026” both work.
    </span>
</div>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-timeline text-muted"></i>
        <h2>Schedule items</h2>
    </div>
    <div class="cn-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:13px">
                <thead>
                    <tr>
                        <th style="width:70px">Order</th>
                        <th>Title</th>
                        <th style="width:280px">Date (history shown struck through)</th>
                        <th style="width:90px">Visible</th>
                        <th class="text-end" style="width:230px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $history = TimelineRepo::history($item); ?>
                        <tr>
                            <form method="post" class="m-0">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <td>
                                    <input type="number" class="form-control form-control-sm" name="sort"
                                           value="<?= e((string) (int) $item['sort']) ?>" style="width:70px">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="title"
                                           value="<?= e((string) $item['title']) ?>" maxlength="190">
                                    <input type="text" class="form-control form-control-sm mt-1" name="note"
                                           value="<?= e((string) ($item['note'] ?? '')) ?>" maxlength="500"
                                           placeholder="optional note shown under the date">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="current_date_text"
                                           value="<?= e((string) $item['current_date_text']) ?>" maxlength="190">
                                    <?php if ($history !== []): ?>
                                        <div class="mt-1" style="font-size:11.5px">
                                            <?php foreach ($history as $old): ?>
                                                <del class="text-muted d-block"><?= e($old) ?></del>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_visible" value="1"
                                            <?= (int) $item['is_visible'] === 1 ? 'checked' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Save">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button>
                            </form>
                                    <button type="button" class="btn btn-sm btn-navy cn-extend"
                                            data-id="<?= e((string) $item['id']) ?>"
                                            data-title="<?= e((string) $item['title']) ?>"
                                            data-current="<?= e((string) $item['current_date_text']) ?>"
                                            data-extends="<?= count($history) ?>">
                                        <i class="fa-solid fa-calendar-plus me-1"></i>Extend
                                    </button>
                                    <?php if ($history !== []): ?>
                                        <form method="post" class="d-inline m-0"
                                              onsubmit="return confirm('Undo the last extension?')">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="undo">
                                            <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Undo last extension">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline m-0"
                                          onsubmit="return confirm('Delete this item?')">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No schedule item yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-plus text-muted"></i>
        <h2>Add item</h2>
    </div>
    <div class="cn-card-body">
        <form method="post" class="row g-2 align-items-end">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-2">
                <label class="form-label" for="sort" style="font-size:12px">Order</label>
                <input type="number" class="form-control form-control-sm" id="sort" name="sort" value="<?= e((string) ((count($items) + 1) * 10)) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="title" style="font-size:12px">Title</label>
                <input type="text" class="form-control form-control-sm" id="title" name="title" maxlength="190" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="current_date_text" style="font-size:12px">Date</label>
                <input type="text" class="form-control form-control-sm" id="current_date_text" name="current_date_text"
                       maxlength="190" placeholder="12 August 2026" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-navy w-100">
                    <i class="fa-solid fa-plus me-1"></i>Add
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal extend -->
<div class="modal fade" id="extendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="extend">
            <input type="hidden" name="id" id="extendId">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:15px">Extend “<span id="extendTitle"></span>”</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:12.5px">
                    The current date <b><span id="extendCurrent"></span></b> will be kept and shown with a
                    strikethrough on the landing page, exactly like previous CENIM editions.
                </p>
                <div class="row g-2">
                    <div class="col-5">
                        <label class="form-label" for="extendLabel" style="font-size:12.5px">Label</label>
                        <select class="form-select" id="extendLabel" name="label"></select>
                    </div>
                    <div class="col-7">
                        <label class="form-label" for="newDate" style="font-size:12.5px">New date</label>
                        <input type="text" class="form-control" id="newDate" name="new_date" maxlength="190"
                               placeholder="e.g. 31 August 2026" required>
                    </div>
                </div>
                <div class="form-text" style="font-size:11.5px">
                    Shown as <b id="extendPreview"></b> on the landing page, each date on its own line.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-navy btn-sm">
                    <i class="fa-solid fa-calendar-plus me-1"></i>Extend
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function preview() {
    var label = document.getElementById('extendLabel').value;
    var date = document.getElementById('newDate').value.trim() || '…';
    document.getElementById('extendPreview').textContent = (label ? '(' + label + ') ' : '') + date;
}
document.getElementById('extendLabel').addEventListener('change', preview);
document.getElementById('newDate').addEventListener('input', preview);
document.querySelectorAll('.cn-extend').forEach(function (button) {
    button.addEventListener('click', function () {
        document.getElementById('extendId').value = this.dataset.id;
        document.getElementById('extendTitle').textContent = this.dataset.title;
        document.getElementById('extendCurrent').textContent = this.dataset.current;
        document.getElementById('newDate').value = '';
        var n = parseInt(this.dataset.extends || '0', 10) + 1;
        var ord = n + (n % 10 === 1 && n % 100 !== 11 ? 'st' : n % 10 === 2 && n % 100 !== 12 ? 'nd' : n % 10 === 3 && n % 100 !== 13 ? 'rd' : 'th');
        var select = document.getElementById('extendLabel');
        select.innerHTML = '';
        [[ord + ' Extend', ord + ' Extend'], ['Final Extend', 'Final Extend'], ['', 'No label']].forEach(function (o) {
            var opt = document.createElement('option'); opt.value = o[0]; opt.textContent = o[1]; select.appendChild(opt);
        });
        preview();
        new bootstrap.Modal(document.getElementById('extendModal')).show();
    });
});
</script>
