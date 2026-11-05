<?php
/**
 * @var array<string,int>   $counts
 * @var array<string,float> $verifiedTotals
 * @var array<int,string>   $statuses
 * @var array<int,array<string,mixed>> $notStarted  paper accepted tanpa registrasi
 */

use Cenim\Domain\RegistrationRepo;

$cards = [
    ['label' => 'Not started',      'value' => count($notStarted),                          'icon' => 'fa-circle-pause',   'filter' => 'not_started'],
    ['label' => 'Draft',            'value' => $counts[RegistrationRepo::STATUS_DRAFT],     'icon' => 'fa-pen-ruler',      'filter' => 'draft'],
    ['label' => 'Awaiting review',  'value' => $counts[RegistrationRepo::STATUS_SUBMITTED], 'icon' => 'fa-hourglass-half', 'filter' => 'submitted'],
    ['label' => 'Verified',         'value' => $counts[RegistrationRepo::STATUS_VERIFIED],  'icon' => 'fa-circle-check',   'filter' => 'verified'],
    ['label' => 'Rejected',         'value' => $counts[RegistrationRepo::STATUS_REJECTED],  'icon' => 'fa-circle-xmark',   'filter' => 'rejected'],
];
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group">
        <a href="<?= e(admin_url('registrations-export.php')) ?>" class="btn btn-sm btn-outline-secondary"
           title="Every registered person: presenters, co-authors and guests (draft registrations excluded)">
            <i class="fa-solid fa-file-excel me-1" style="color:#1d6f42"></i>Export participants (Excel)
        </a>
        <a href="<?= e(admin_url('registrations-export.php?status=paid')) ?>" class="btn btn-sm btn-outline-secondary"
           title="Only registrations whose invoice is paid">Paid only</a>
    </div>
</div>

<div class="row g-3 mb-3 row-cols-2 row-cols-lg-5">
    <?php foreach ($cards as $card): ?>
        <div class="col">
            <a class="text-decoration-none" href="#" data-status-filter="<?= e($card['filter']) ?>">
                <div class="cn-card h-100">
                    <div class="cn-card-body cn-stat">
                        <span class="cn-stat-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></span>
                        <span>
                            <span class="cn-stat-value d-block"><?= e((string) $card['value']) ?></span>
                            <span class="cn-stat-label"><?= e($card['label']) ?></span>
                        </span>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($verifiedTotals !== []): ?>
    <div class="cn-card mb-3">
        <div class="cn-card-body d-flex flex-wrap align-items-center gap-4">
            <span class="cn-stat-label">Verified value</span>
            <?php foreach ($verifiedTotals as $currency => $amount): ?>
                <span style="font-size:15px;font-weight:600;color:var(--cn-navy)">
                    <?= e(fmt_money($amount, (string) $currency)) ?>
                </span>
            <?php endforeach; ?>
            <span class="text-muted" style="font-size:12px">
                <i class="fa-solid fa-circle-info me-1"></i>Amount already verified, to be invoiced.
            </span>
        </div>
    </div>
<?php endif; ?>

<div class="cn-card">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-clipboard-list text-muted"></i>
        <h2>Registration list</h2>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <select id="fStatus" class="form-select form-select-sm" style="width:auto">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>"><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="fType" class="form-select form-select-sm" style="width:auto">
                <option value="">All participants</option>
                <option value="presenter">Presenter</option>
                <option value="non_presenter">Non-presenter</option>
            </select>
            <select id="fCurrency" class="form-select form-select-sm" style="width:auto">
                <option value="">All currencies</option>
                <option value="IDR">IDR</option>
                <option value="USD">USD</option>
            </select>
        </div>
    </div>
    <div class="cn-card-body">
        <div class="table-responsive">
            <table id="registrationsTable" class="table table-sm table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>Paper ID</th>
                        <th>First author</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Docs</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Note</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="cn-card mt-3" id="notStarted">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-circle-pause text-muted"></i>
        <h2>Not started yet (<?= count($notStarted) ?>)</h2>
        <span class="text-muted" style="font-size:12px">Accepted papers that have not opened the registration form. Fill it in for the author if needed.</span>
    </div>
    <div class="cn-card-body">
        <table id="notStartedTable" class="table table-sm align-middle mb-0" style="width:100%;font-size:12.5px">
            <thead class="table-light">
                <tr><th class="text-center">No</th><th>Paper ID</th><th>First author</th><th>Title</th><th>Confirmation</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($notStarted as $i => $paper): ?>
                    <tr>
                        <td class="text-center text-muted"><?= $i + 1 ?></td>
                        <td><code><?= e((string) $paper['paper_id']) ?></code></td>
                        <td><?= e((string) $paper['first_author_name']) ?>
                            <div class="text-muted" style="font-size:11.5px"><?= e((string) $paper['email']) ?></div></td>
                        <td style="max-width:360px"><?= e((string) $paper['paper_title']) ?></td>
                        <td>
                            <?php if ($paper['confirmation_id'] === null): ?>
                                <span class="cn-badge cn-badge-draft">not confirmed</span>
                            <?php else: ?>
                                <span class="cn-badge cn-badge-verified"><?= e(ucfirst((string) $paper['presentation_mode'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('registration-edit.php?author=' . (int) $paper['id'])) ?>">
                                <i class="fa-solid fa-pen-to-square me-1"></i>Fill in
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
