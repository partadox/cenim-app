<?php
/**
 * @var array      $rows
 * @var array<string,int> $counts  accounts|submitted|paid|people|paper_guests|paper_guests_paid|total
 * @var array      $guests  non-presenter yang dibawa author (ParticipantRepo::paperGuests)
 * @var array|null $editing
 * @var array      $values
 * @var array<string,string> $errors
 * @var array      $countries
 * @var bool       $selfOpen
 */

use Cenim\Core\Csrf;
use Cenim\Domain\RegistrationRepo;

$err = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>' : '';
$cls = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$cards = [
    ['Total non-presenters attending', $counts['total'], 'fa-users', 'self-registered + brought by authors (submitted registrations)'],
    ['Self-registered participants', $counts['accounts'], 'fa-id-badge', $counts['people'] . ' people incl. their guests · ' . $counts['paid'] . ' paid'],
    ['Brought by authors', $counts['paper_guests'], 'fa-people-group', $counts['paper_guests_paid'] . ' paid'],
    ['Registrations submitted', $counts['submitted'], 'fa-clipboard-list', 'self-registered participants'],
];
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3" style="font-size:12.5px">
    <span class="cn-badge <?= $selfOpen ? 'cn-badge-verified' : 'cn-badge-draft' ?>">
        Self-registration <?= $selfOpen ? 'open' : 'closed' ?>
    </span>
    <a class="text-muted" href="<?= e(admin_url('settings.php')) ?>">change in Settings</a>
    <span class="text-muted ms-2">Public form: <a href="<?= e(author_url('register.php')) ?>" target="_blank" rel="noopener"><?= e(full_url(author_url('register.php'))) ?></a></span>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($cards as [$label, $value, $icon, $hint]): ?>
        <div class="col-6 col-lg-3">
            <div class="cn-card h-100">
                <div class="cn-card-body cn-stat">
                    <span class="cn-stat-icon"><i class="fa-solid <?= e($icon) ?>"></i></span>
                    <span><span class="cn-stat-value d-block"><?= (int) $value ?></span><span class="cn-stat-label"><?= e($label) ?></span>
                        <span class="d-block text-muted" style="font-size:11px"><?= e($hint) ?></span></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="cn-card">
            <div class="cn-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="font-size:12.5px">
                        <thead class="table-light">
                            <tr><th>ID</th><th>Participant</th><th>Affiliation</th><th>Registration</th><th class="text-center">People</th><th>Invoice</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $status = (string) ($row['registration_status'] ?? ''); ?>
                                <tr>
                                    <td><code><?= e((string) $row['paper_id']) ?></code>
                                        <div class="text-muted" style="font-size:10.5px"><?= str_ends_with((string) $row['imported_batch'], 'admin') ? 'added by admin' : 'self-registered' ?></div></td>
                                    <td><b><?= e((string) $row['name']) ?></b>
                                        <div class="text-muted"><?= e((string) $row['email']) ?></div></td>
                                    <td><?= e((string) ($row['affiliation'] ?? '')) ?>
                                        <div class="text-muted"><?= e((string) ($row['country'] ?? '')) ?></div></td>
                                    <td>
                                        <?php if ($status === '' || ($status === RegistrationRepo::STATUS_DRAFT && $row['registration_id'] === null)): ?>
                                            <span class="text-muted">not started</span>
                                        <?php else: ?>
                                            <a href="<?= e(admin_url('registration-view.php?id=' . (int) $row['registration_id'])) ?>" class="cn-badge <?= e(RegistrationRepo::statusBadgeClass($status)) ?>"><?= e(ucfirst($status)) ?></a>
                                            <?php if ($status !== RegistrationRepo::STATUS_DRAFT): ?>
                                                <div class="text-muted" style="font-size:11px"><?= e(fmt_money((float) $row['total'], (string) $row['currency'])) ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $row['registration_id'] !== null ? 1 + (int) $row['guests'] : '&mdash;' ?></td>
                                    <td>
                                        <?php if (($row['invoice_number'] ?? '') !== ''): ?>
                                            <?= e((string) $row['invoice_number']) ?>
                                            <div><span class="cn-badge cn-badge-<?= $row['invoice_status'] === 'paid' ? 'verified' : 'unpaid' ?>"><?= e(ucfirst((string) $row['invoice_status'])) ?></span></div>
                                        <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                                    </td>
                                    <td class="text-end" style="white-space:nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('participants.php?edit=' . (int) $row['id'])) ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                        <?php $hasData = $status !== '' && $status !== RegistrationRepo::STATUS_DRAFT; ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm(<?= e(json_encode('Delete participant ' . $row['paper_id'] . ($hasData ? '? The registration, invoice, payment, receipt and certificates are deleted too. This cannot be undone.' : '?'))) ?>);">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($rows === []): ?>
                                <tr><td colspan="7" class="text-muted p-3">No participant yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <p class="text-muted mt-2" style="font-size:12px">
            <i class="fa-solid fa-circle-info me-1"></i>
            Participants sign in to the portal with their email (6-digit code), add the people who come with them, and pay
            like an author. Verify the registration in <a href="<?= e(admin_url('registrations.php')) ?>">Registrations</a> and
            issue the invoice as usual. Their attendance certificate is the <b>Participant</b> certificate.
        </p>
    </div>

    <div class="col-xl-4">
        <form method="post" class="cn-card" novalidate>
            <?= Csrf::field() ?>
            <div class="cn-card-head">
                <i class="fa-solid <?= $editing !== null ? 'fa-pen' : 'fa-user-plus' ?> text-muted"></i>
                <h2><?= $editing !== null ? 'Edit ' . e((string) $editing['paper_id']) : 'Add a participant' ?></h2>
            </div>
            <div class="cn-card-body">
                <label class="form-label" for="name">Full name</label>
                <input type="text" class="form-control form-control-sm<?= $cls('name') ?>" id="name" name="name" maxlength="190" value="<?= e($values['name']) ?>">
                <?= $err('name') ?>

                <label class="form-label mt-2" for="email">Email</label>
                <input type="email" class="form-control form-control-sm<?= $cls('email') ?>" id="email" name="email" maxlength="190" value="<?= e($values['email']) ?>">
                <?= $err('email') ?>

                <label class="form-label mt-2" for="affiliation">Affiliation</label>
                <input type="text" class="form-control form-control-sm<?= $cls('affiliation') ?>" id="affiliation" name="affiliation" maxlength="255" value="<?= e($values['affiliation']) ?>">
                <?= $err('affiliation') ?>

                <label class="form-label mt-2" for="region">Participant from</label>
                <?= Cenim\Core\View::partial('author/_region_select', ['values' => $values, 'errors' => $errors, 'countries' => $countries, 'size' => 'sm']) ?>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-sm btn-navy"><i class="fa-solid fa-floppy-disk me-1"></i><?= $editing !== null ? 'Save' : 'Add' ?></button>
                    <?php if ($editing !== null): ?>
                        <a href="<?= e(admin_url('participants.php')) ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
                <p class="text-muted mt-2 mb-0" style="font-size:11.5px">Names and affiliations are saved with capital first letters.</p>
            </div>
        </form>
    </div>
</div>

<div class="cn-card mt-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-people-group text-muted"></i>
        <h2>Non-presenters brought by authors <span class="text-muted fw-normal" style="font-size:12px">(from paper registrations: <?= count($guests) ?> people)</span></h2>
    </div>
    <div class="cn-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="guestTable" style="font-size:12.5px">
                <thead class="table-light">
                    <tr><th class="text-center">No</th><th>Name</th><th>Affiliation</th><th>Paper</th><th>Shirt</th><th>Registration</th><th>Invoice</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($guests as $i => $g): ?>
                        <?php $status = (string) $g['registration_status']; ?>
                        <tr>
                            <td class="text-center text-muted"><?= $i + 1 ?></td>
                            <td><b><?= e((string) $g['name']) ?></b>
                                <div class="text-muted"><?= e((string) ($g['email'] ?? '')) ?><?= $g['paper_author_id'] !== null ? ' · co-author' : '' ?></div></td>
                            <td><?= e((string) ($g['affiliation'] ?? '')) ?></td>
                            <td data-order="<?= e((string) $g['paper_id']) ?>">
                                <a href="<?= e(admin_url('author-edit.php?id=' . (int) $g['author_id'])) ?>"><b><?= e((string) $g['paper_id']) ?></b></a>
                                <div class="text-muted" style="font-size:11px"><?= e((string) $g['first_author_name']) ?></div>
                            </td>
                            <td><?= e((string) ($g['shirt_size'] ?? '')) ?></td>
                            <td><a href="<?= e(admin_url('registration-view.php?id=' . (int) $g['registration_id'])) ?>" class="cn-badge <?= e(RegistrationRepo::statusBadgeClass($status)) ?>"><?= e(ucfirst($status)) ?></a></td>
                            <td>
                                <?php if (($g['invoice_number'] ?? '') !== ''): ?>
                                    <?= e((string) $g['invoice_number']) ?>
                                    <div><span class="cn-badge cn-badge-<?= $g['invoice_status'] === 'paid' ? 'verified' : 'unpaid' ?>"><?= e(ucfirst((string) $g['invoice_status'])) ?></span></div>
                                <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<p class="text-muted mt-2" style="font-size:12px">
    <i class="fa-solid fa-circle-info me-1"></i>
    Authors add these people in their registration form. Only submitted or verified registrations are counted;
    the numbers planned in the confirmation form are in <a href="<?= e(admin_url('confirmations.php')) ?>">Confirmations</a>.
</p>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = new DataTable('#guestTable', {
        order: [[3, 'asc']],
        pageLength: 25,
        columnDefs: [{ targets: 0, orderable: false, searchable: false }],
        language: {
            search: '',
            searchPlaceholder: 'Search name, affiliation, paper…',
            emptyTable: 'No author has added a non-presenter yet.',
            zeroRecords: 'No one matches your search.',
            info: 'Showing _START_ to _END_ of _TOTAL_ people',
            infoEmpty: 'No people',
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
