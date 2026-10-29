<?php
/**
 * @var array  $rows
 * @var int    $total
 * @var array<string,int> $counts  confirmed|missing|offline|online|extra|people|nonpresenters
 * @var string $filter
 * @var int    $included
 * @var string $deadline
 * @var bool   $isOpen
 * @var bool   $shirtOn
 * @var array<string,int> $shirts  rekap ukuran baju (konfirmasi offline)
 */

use Cenim\Domain\ConfirmationRepo;

$tabs = [
    ''          => ['All accepted papers', $total],
    'confirmed' => ['Confirmed', $counts['confirmed']],
    'missing'   => ['Not confirmed yet', $counts['missing']],
    'offline'   => ['Offline', $counts['offline']],
    'online'    => ['Online', $counts['online']],
    'extra'     => ['Extra pages', $counts['extra']],
];
$cards = [
    ['Confirmed', $counts['confirmed'] . ' / ' . $total, 'fa-clipboard-check'],
    ['Offline papers', $counts['offline'], 'fa-people-group'],
    ['Online papers', $counts['online'], 'fa-video'],
    ['People expected at the venue', $counts['people'] . ' (' . $counts['nonpresenters'] . ' non-presenter)', 'fa-users'],
];
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="cn-badge <?= $isOpen ? 'cn-badge-verified' : 'cn-badge-draft' ?>">
        <i class="fa-solid <?= $isOpen ? 'fa-lock-open' : 'fa-lock' ?> me-1"></i><?= $isOpen ? 'Open' : 'Closed' ?>
        <?= $deadline !== '' ? ' · deadline ' . e(fmt_date($deadline . ' 00:00:00')) : '' ?>
    </span>
    <a class="text-muted" style="font-size:12px" href="<?= e(admin_url('settings.php')) ?>">change in Settings</a>
    <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e(admin_url('final-pages-import.php')) ?>">
        <i class="fa-solid fa-file-import me-1"></i>Import final manuscript pages
    </a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('confirmations.php?export=1' . ($filter !== '' ? '&show=' . $filter : ''))) ?>">
        <i class="fa-solid fa-file-excel me-1" style="color:#1d6f42"></i>Export Excel
    </a>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $value, $icon]): ?>
        <div class="col-6 col-lg-3">
            <div class="cn-card h-100">
                <div class="cn-card-body cn-stat">
                    <span class="cn-stat-icon"><i class="fa-solid <?= e($icon) ?>"></i></span>
                    <span>
                        <span class="cn-stat-value d-block"><?= e((string) $value) ?></span>
                        <span class="cn-stat-label"><?= e($label) ?></span>
                    </span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($shirtOn || array_sum($shirts) > 0): ?>
    <div class="cn-card mb-3">
        <div class="cn-card-body d-flex flex-wrap align-items-center gap-3" style="font-size:13px">
            <span><i class="fa-solid fa-shirt me-1 text-muted"></i><b>Shirt sizes</b> <span class="text-muted">(confirmed offline attendees)</span></span>
            <?php foreach ($shirts as $size => $n): ?>
                <span class="border rounded px-2 py-1"><?= e((string) $size) ?> <b><?= (int) $n ?></b></span>
            <?php endforeach; ?>
            <span class="text-muted">total <b><?= array_sum($shirts) ?></b></span>
        </div>
    </div>
<?php endif; ?>

<div class="btn-group btn-group-sm flex-wrap mb-3">
    <?php foreach ($tabs as $key => [$label, $n]): ?>
        <a class="btn <?= $filter === $key ? 'btn-navy' : 'btn-outline-secondary' ?>"
           href="<?= e(admin_url('confirmations.php' . ($key !== '' ? '?show=' . $key : ''))) ?>"><?= e($label) ?> <span class="badge text-bg-light ms-1"><?= (int) $n ?></span></a>
    <?php endforeach; ?>
</div>

<div class="cn-card">
    <div class="cn-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="confirmTable" style="font-size:12.5px">
                <thead class="table-light">
                    <tr><th class="text-center">No</th><th>Paper</th><th>Presentation</th><th class="text-center">Pages</th><th>Extra page fee</th><th>Note</th><th>Registration</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row): ?>
                        <?php
                        $confirmed = $row['confirmation_id'] !== null;
                        $review    = (int) $row['total_pages'];
                        $final     = (int) ($row['final_pages'] ?? 0);
                        // Urutan sort kolom Presentation: belum konfirmasi, online, offline (lalu jumlah orang).
                        $modeOrder = !$confirmed ? 0 : ((string) $row['presentation_mode'] === 'online' ? 1 : 2 + (int) $row['attendees']);
                        ?>
                        <tr>
                            <td class="text-center text-muted"><?= $i + 1 ?></td>
                            <td style="max-width:280px" data-order="<?= e((string) $row['paper_id']) ?>">
                                <a href="<?= e(admin_url('author-edit.php?id=' . (int) $row['author_id'])) ?>"><b><?= e((string) $row['paper_id']) ?></b></a>
                                <div class="text-muted text-truncate" title="<?= e((string) $row['paper_title']) ?>"><?= e((string) $row['paper_title']) ?></div>
                                <div class="text-muted" style="font-size:11.5px"><?= e((string) $row['first_author_name']) ?></div>
                            </td>
                            <td data-order="<?= $modeOrder ?>">
                                <?php if (!$confirmed): ?>
                                    <span class="cn-badge cn-badge-draft">not confirmed</span>
                                <?php elseif ((string) $row['presentation_mode'] === 'online'): ?>
                                    <span class="cn-badge cn-badge-submitted"><i class="fa-solid fa-video me-1"></i>online</span>
                                <?php else: ?>
                                    <span class="cn-badge cn-badge-verified"><i class="fa-solid fa-people-group me-1"></i>offline</span>
                                    <div style="font-size:11.5px" class="mt-1">
                                        <?= (int) $row['attendees'] ?> <?= (int) $row['attendees'] === 1 ? 'person' : 'people' ?>
                                        <span class="text-muted">(1 presenter<?= (int) $row['attendees'] > 1 ? ' + ' . ((int) $row['attendees'] - 1) . ' non-presenter' : '' ?>)</span>
                                    </div>
                                    <?php if (ConfirmationRepo::shirts($row) !== []): ?>
                                        <div class="mt-1">
                                            <?php foreach (ConfirmationRepo::shirts($row) as $i => $size): ?>
                                                <span class="cn-badge cn-badge-draft" title="<?= $i === 0 ? 'Presenter' : 'Non-presenter ' . $i ?>"><i class="fa-solid fa-shirt me-1"></i><?= e($size) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center" style="white-space:nowrap" data-order="<?= $final > 0 ? $final : $review ?>">
                                <?php if ($final > 0 && $final !== $review): ?>
                                    <span class="text-muted text-decoration-line-through" title="Reviewed manuscript"><?= $review ?></span>
                                    <b title="Final manuscript"><?= $final ?></b>
                                <?php else: ?>
                                    <b class="<?= max($review, $final) > $included ? 'text-danger' : '' ?>"><?= $final > 0 ? $final : $review ?></b>
                                <?php endif; ?>
                                <div class="text-muted" style="font-size:10.5px"><?= $final > 0 ? 'final' : 'review' ?></div>
                            </td>
                            <td style="white-space:nowrap" data-order="<?= $confirmed ? (float) $row['extra_page_fee'] : -1 ?>">
                                <?php if ($confirmed && (int) $row['extra_pages'] > 0): ?>
                                    <?= e(fmt_money((float) $row['extra_page_fee'], (string) $row['currency'])) ?>
                                    <div class="text-muted" style="font-size:11px"><?= (int) $row['extra_pages'] ?> extra page(s) at confirmation</div>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px;max-width:260px" data-order="<?= $confirmed ? e((string) ($row['updated_at'] ?? $row['submitted_at'])) : '' ?>">
                                <?php if (($row['note'] ?? '') !== ''): ?>
                                    <div><i class="fa-regular fa-note-sticky me-1"></i><?= nl2br(e((string) $row['note'])) ?></div>
                                <?php endif; ?>
                                <?php if ($confirmed): ?>
                                    <div class="text-muted" style="font-size:11px">
                                        <?= e(fmt_dt((string) ($row['updated_at'] ?? $row['submitted_at']))) ?>
                                        <?= ($row['filled_by_name'] ?? '') !== '' ? ' · ' . e((string) $row['filled_by_name']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="cn-badge cn-badge-<?= e((string) ($row['registration_status'] ?? 'draft')) ?>"><?= e(ucfirst((string) ($row['registration_status'] ?? 'not started'))) ?></span>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('confirmation-edit.php?id=' . (int) $row['author_id'])) ?>"
                                   title="<?= $confirmed ? 'Edit confirmation' : 'Fill in the confirmation' ?>"><i class="fa-solid <?= $confirmed ? 'fa-pen' : 'fa-plus' ?>"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = new DataTable('#confirmTable', {
        order: [[1, 'asc']],
        pageLength: 50,
        lengthMenu: [25, 50, 100, { label: 'All', value: -1 }],
        columnDefs: [
            { targets: 0, orderable: false, searchable: false },
            { targets: -1, orderable: false, searchable: false }
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search paper ID, title, author, note…',
            emptyTable: 'No paper here.',
            zeroRecords: 'No paper matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ papers',
            infoEmpty: 'No papers',
            infoFiltered: '(filtered from _MAX_ total)'
        }
    });

    // Kolom No mengikuti urutan & hasil pencarian yang sedang tampil.
    table.on('draw', function () {
        var start = table.page.info().start;
        table.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, i) {
            cell.textContent = start + i + 1;
        });
    });
    table.draw(false);
});
</script>
