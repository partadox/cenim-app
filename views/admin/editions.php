<?php
/**
 * @var array      $editions
 * @var array|null $edition   edisi aktif
 * @var int        $nextYear
 */

use Cenim\Core\Csrf;
use Cenim\Support\EditionCloner;

$activeId = (int) ($edition['id'] ?? 0);

/** Kotak centang bagian yang disalin. */
$groupChecks = static function (string $prefix): void {
    foreach (EditionCloner::GROUPS as $key => [$label, $hint, $default]) {
        $id = $prefix . '_' . $key;
        echo '<div class="col-md-6"><div class="form-check">'
            . '<input class="form-check-input" type="checkbox" name="groups[]" value="' . e($key) . '" id="' . e($id) . '"' . ($default ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="' . e($id) . '" style="font-size:12.5px"><b>' . e($label) . '</b>'
            . '<div class="text-muted" style="font-size:11px">' . e($hint) . '</div></label></div></div>';
    }
};
?>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-layer-group text-muted"></i>
        <h2>Editions</h2>
    </div>
    <div class="cn-card-body p-0">
        <table class="table table-sm align-middle mb-0" style="font-size:12.5px">
            <thead class="table-light">
                <tr><th>Edition</th><th>Conference dates</th><th class="text-end">Papers</th><th class="text-end">Registrations</th><th class="text-end">Certificates</th><th style="width:150px"></th></tr>
            </thead>
            <tbody>
                <?php foreach ($editions as $row): ?>
                    <tr>
                        <td>
                            <b><?= e((string) $row['name']) ?></b>
                            <?php if ((int) $row['id'] === $activeId): ?><span class="cn-badge cn-badge-verified ms-1">active</span><?php endif; ?>
                        </td>
                        <td><?= e(($row['conference_start'] ?? '') !== '' ? fmt_date($row['conference_start'] . ' 00:00:00') . (($row['conference_end'] ?? '') !== '' ? ' – ' . fmt_date($row['conference_end'] . ' 00:00:00') : '') : '—') ?></td>
                        <td class="text-end"><?= (int) $row['papers'] ?></td>
                        <td class="text-end"><?= (int) $row['registrations'] ?></td>
                        <td class="text-end"><?= (int) $row['certificates'] ?></td>
                        <td class="text-end">
                            <?php if ((int) $row['id'] !== $activeId): ?>
                                <form method="post" class="m-0" onsubmit="return confirm('Make <?= e((string) $row['name']) ?> the active edition? The landing page, author sign-in, and this admin panel switch to it immediately.')">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-power-off me-1"></i>Activate</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="cn-card h-100">
            <div class="cn-card-head">
                <i class="fa-solid fa-plus text-muted"></i>
                <h2>Create a new edition</h2>
            </div>
            <div class="cn-card-body">
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label" for="new_year" style="font-size:12.5px">Year</label>
                            <input type="number" class="form-control form-control-sm" id="new_year" name="year" value="<?= (int) $nextYear ?>" min="2018" max="2100" required
                                   oninput="document.getElementById('new_name').placeholder = 'CENIM ' + this.value">
                        </div>
                        <div class="col-8">
                            <label class="form-label" for="new_name" style="font-size:12.5px">Name</label>
                            <input type="text" class="form-control form-control-sm" id="new_name" name="name" placeholder="CENIM <?= (int) $nextYear ?>" maxlength="120">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="new_from" style="font-size:12.5px">Copy settings from</label>
                            <select class="form-select form-select-sm" id="new_from" name="from">
                                <?php foreach ($editions as $row): ?>
                                    <option value="<?= (int) $row['id'] ?>" <?= (int) $row['id'] === $activeId ? 'selected' : '' ?>><?= e((string) $row['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="0">— start empty —</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2"><?php $groupChecks('new'); ?></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="update_years" value="1" id="new_years" checked>
                        <label class="form-check-label" for="new_years" style="font-size:12.5px">
                            Update years in texts (<?= e((string) ($edition['name'] ?? 'CENIM 2026')) ?> → the new name, dates moved forward)
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="activate" value="1" id="new_activate">
                        <label class="form-check-label" for="new_activate" style="font-size:12.5px">Activate it right away</label>
                    </div>
                    <p class="text-muted" style="font-size:11.5px">
                        Participant data (papers, authors, registrations, invoices, payments, issued certificates) is never copied.
                        Leave the new edition inactive while you review its settings, then activate it here.
                    </p>
                    <button class="btn btn-navy"><i class="fa-solid fa-plus me-1"></i>Create edition</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="cn-card h-100">
            <div class="cn-card-head">
                <i class="fa-regular fa-copy text-muted"></i>
                <h2>Copy settings into an existing edition</h2>
            </div>
            <div class="cn-card-body">
                <form method="post" onsubmit="return confirm('The ticked parts of the target edition will be REPLACED. Continue?')">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="copy">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="cp_from" style="font-size:12.5px">From</label>
                            <select class="form-select form-select-sm" id="cp_from" name="from">
                                <?php foreach ($editions as $i => $row): ?>
                                    <option value="<?= (int) $row['id'] ?>" <?= $i === 1 ? 'selected' : '' ?>><?= e((string) $row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="cp_to" style="font-size:12.5px">Into</label>
                            <select class="form-select form-select-sm" id="cp_to" name="to">
                                <?php foreach ($editions as $i => $row): ?>
                                    <option value="<?= (int) $row['id'] ?>" <?= $i === 0 ? 'selected' : '' ?>><?= e((string) $row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2"><?php $groupChecks('cp'); ?></div>
                    <div class="form-check mt-2 mb-3">
                        <input class="form-check-input" type="checkbox" name="update_years" value="1" id="cp_years" checked>
                        <label class="form-check-label" for="cp_years" style="font-size:12.5px">Update years in texts</label>
                    </div>
                    <p class="text-muted" style="font-size:11.5px">
                        Useful when an edition was created empty, or to re-copy one part (e.g. only the landing content).
                        Fees and certificate templates cannot be replaced once the target edition has registrations or issued certificates.
                    </p>
                    <button class="btn btn-outline-secondary"><i class="fa-regular fa-copy me-1"></i>Copy</button>
                </form>
            </div>
        </div>
    </div>
</div>
