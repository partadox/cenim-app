<?php
/**
 * Registration form peserta non-presenter.
 *
 * @var array  $author        baris authors peserta (paper_id NP-xxxx)
 * @var array  $person        baris paper_authors orangnya
 * @var array  $registration
 * @var bool   $editable
 * @var bool   $isOpen
 * @var array  $values        presenter_shirt, attendees, extra
 * @var array<string,string> $errors
 * @var array  $quote
 * @var array  $extraFields
 * @var bool   $shirtOn
 * @var array  $shirtSizes
 * @var array  $feeData       currency, baseLabel, base, attendeeFee
 * @var int    $maxAttendees
 */

use Cenim\Core\Csrf;
use Cenim\Domain\RegistrationRepo;

$status   = (string) $registration['status'];
$disabled = $editable && $isOpen ? '' : 'disabled';
$currency = (string) $feeData['currency'];

$err = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>'
    : '';

$shirtSelect = static function (string $name, string $value, string $id = '') use ($shirtSizes, $disabled): string {
    $html = '<select class="form-select form-select-sm" name="' . e($name) . '"' . ($id !== '' ? ' id="' . e($id) . '"' : '') . ' ' . $disabled . '>'
        . '<option value="">Size…</option>';
    foreach ($shirtSizes as $size) {
        $html .= '<option value="' . e($size) . '"' . ($size === $value ? ' selected' : '') . '>' . e($size) . '</option>';
    }

    return $html . '</select>';
};

$guestRow = static function (string $i, array $row) use ($disabled, $shirtOn, $shirtSelect): string {
    $input = static fn (string $key, string $placeholder, string $type, int $max) =>
        '<input type="' . $type . '" class="form-control form-control-sm cn-guest-' . $key . '" name="attendees[' . $i . '][' . $key . ']"'
        . ' placeholder="' . $placeholder . '" maxlength="' . $max . '" value="' . e((string) ($row[$key] ?? '')) . '" ' . $disabled . '>';

    return '<div class="cn-guest border rounded p-2 mb-2"><div class="row g-2 align-items-center">'
        . '<div class="col-md-4">' . $input('name', 'Full name', 'text', 190) . '</div>'
        . '<div class="col-md-4">' . $input('affiliation', 'Affiliation', 'text', 255) . '</div>'
        . '<div class="col">' . $input('email', 'Email', 'email', 190) . '</div>'
        . ($shirtOn ? '<div class="col-auto" style="width:110px">' . $shirtSelect('attendees[' . $i . '][shirt_size]', (string) ($row['shirt_size'] ?? '')) . '</div>' : '')
        . ($disabled === '' ? '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger cn-guest-remove" title="Remove"><i class="fa-solid fa-xmark"></i></button></div>' : '')
        . '</div></div>';
};
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= e(author_url('index.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <span class="cn-badge <?= e(RegistrationRepo::statusBadgeClass($status)) ?>"><?= e(ucfirst($status)) ?></span>
</div>

<?php if ($status === RegistrationRepo::STATUS_REJECTED && ($registration['reject_reason'] ?? '') !== ''): ?>
    <div class="alert alert-danger" style="font-size:13px">
        <b><i class="fa-solid fa-circle-exclamation me-1"></i>The committee asked for a correction</b>
        <div class="mt-1"><?= nl2br(e((string) $registration['reject_reason'])) ?></div>
    </div>
<?php endif; ?>

<?php if (!$isOpen): ?>
    <div class="alert alert-warning" style="font-size:13px"><i class="fa-solid fa-lock me-1"></i>Registration is currently closed.</div>
<?php elseif (!$editable): ?>
    <div class="alert alert-info" style="font-size:13px">
        <i class="fa-solid fa-circle-info me-1"></i>Your registration has been submitted, so the form is read-only. Contact the secretariat if something needs changing.
    </div>
<?php endif; ?>

<form method="post" novalidate id="partForm">
    <?= Csrf::field() ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-id-badge text-muted"></i>
                    <h2>1. Participant</h2>
                    <span class="cn-badge cn-badge-submitted ms-auto"><?= e((string) $author['paper_id']) ?></span>
                </div>
                <div class="cn-card-body">
                    <dl class="row mb-0" style="font-size:13px">
                        <dt class="col-sm-4 text-muted fw-normal">Name</dt>
                        <dd class="col-sm-8"><?= e((string) $person['name']) ?></dd>
                        <dt class="col-sm-4 text-muted fw-normal">Email</dt>
                        <dd class="col-sm-8"><?= e((string) $person['email']) ?></dd>
                        <dt class="col-sm-4 text-muted fw-normal">Affiliation</dt>
                        <dd class="col-sm-8"><?= e((string) ($person['affiliation'] ?? '')) ?></dd>
                        <dt class="col-sm-4 text-muted fw-normal">Country</dt>
                        <dd class="col-sm-8 mb-0">
                            <?= e((string) ($author['country'] ?? '')) ?>
                            <span class="cn-badge cn-badge-submitted ms-1">bills in <?= e($currency) ?></span>
                        </dd>
                    </dl>
                    <div class="form-text mt-2" style="font-size:11.5px">
                        Attendance at the venue (offline) as a non-presenter. The name is printed on the attendance certificate;
                        contact the secretariat if it needs correcting.
                    </div>
                    <?php if ($shirtOn): ?>
                        <div class="mt-3" style="max-width:220px">
                            <label class="form-label" for="presenter_shirt">Your shirt size <span class="text-danger">*</span></label>
                            <?= $shirtSelect('presenter_shirt', (string) $values['presenter_shirt'], 'presenter_shirt') ?>
                            <?= $err('presenter_shirt') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-users text-muted"></i>
                    <h2>2. People coming with you</h2>
                </div>
                <div class="cn-card-body">
                    <p class="text-muted mb-2" style="font-size:12.5px">
                        Add everyone else who will come with you (colleagues, students).
                        <?php if ($feeData['attendeeFee'] !== null): ?>
                            Each pays the non-presenter fee of <b><?= e(fmt_money((float) $feeData['attendeeFee'], $currency)) ?></b>, added to your invoice.
                        <?php endif; ?>
                        Leave it empty if you come alone.
                    </p>
                    <?= $err('attendees') ?>
                    <?php foreach ($errors as $key => $message): ?>
                        <?php if (str_starts_with($key, 'attendee_')): ?>
                            <div class="invalid-feedback d-block" style="font-size:12px"><?= e($message) ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div id="guestList" class="mt-2">
                        <?php foreach ($values['attendees'] as $i => $row): ?>
                            <?= $guestRow((string) $i, $row) ?>
                        <?php endforeach; ?>
                    </div>
                    <div id="guestEmpty" class="text-muted" style="font-size:12.5px"<?= $values['attendees'] !== [] ? ' hidden' : '' ?>>
                        <i class="fa-regular fa-circle me-1"></i>Nobody added — only you attend.
                    </div>
                    <?php if ($disabled === ''): ?>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="guestAdd">
                                <i class="fa-solid fa-user-plus me-1"></i>Add a person
                            </button>
                            <span class="text-muted" style="font-size:11.5px">max <?= (int) $maxAttendees ?></span>
                        </div>
                        <template id="guestTemplate"><?= $guestRow('__i__', []) ?></template>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($extraFields !== []): ?>
                <div class="cn-card mb-3">
                    <div class="cn-card-head">
                        <i class="fa-solid fa-square-check text-muted"></i>
                        <h2>3. Additional questions</h2>
                    </div>
                    <div class="cn-card-body">
                        <?= Cenim\Core\View::partial('author/_extra_fields', [
                            'extraFields' => $extraFields,
                            'extraValues' => $values['extra'] ?? [],
                            'errors'      => $errors,
                            'disabled'    => $disabled,
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="cn-card mb-3" style="position:sticky;top:16px">
                <div class="cn-card-head">
                    <i class="fa-solid fa-receipt text-muted"></i>
                    <h2>Fee summary</h2>
                </div>
                <div class="cn-card-body">
                    <?php if (isset($errors['fee']) || $quote['error'] !== null): ?>
                        <div class="alert alert-warning py-2 px-3" style="font-size:12.5px"><?= e((string) ($errors['fee'] ?? $quote['error'])) ?></div>
                    <?php endif; ?>
                    <table class="table table-sm mb-2" style="font-size:13px">
                        <tbody id="feeItems">
                            <?php foreach ($quote['items'] as $item): ?>
                                <tr>
                                    <td><?= e((string) $item['description']) ?></td>
                                    <td class="text-end" style="white-space:nowrap"><?= e(fmt_money((float) $item['amount'], (string) $quote['currency'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="border-top:2px solid var(--cn-border)">
                                <th>Total</th>
                                <th class="text-end" id="feeTotal" style="white-space:nowrap;color:var(--cn-navy);font-size:15px">
                                    <?= e(fmt_money((float) $quote['total'], (string) $quote['currency'])) ?>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                    <p class="text-muted" style="font-size:11.5px">One invoice for you and everyone you add. The committee verifies the registration before issuing it.</p>

                    <?php if ($editable && $isOpen): ?>
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="submit" class="btn btn-navy">
                                <i class="fa-solid fa-paper-plane me-1"></i>Submit registration
                            </button>
                            <button type="submit" name="action" value="save" class="btn btn-outline-secondary">
                                <i class="fa-regular fa-floppy-disk me-1"></i>Save draft
                            </button>
                        </div>
                        <p class="text-muted mt-2 mb-0" style="font-size:11.5px">After submitting you cannot edit the form unless the committee asks for a correction.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var fee   = <?= json_encode($feeData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var list  = document.getElementById('guestList');
    var empty = document.getElementById('guestEmpty');
    var tpl   = document.getElementById('guestTemplate');
    var add   = document.getElementById('guestAdd');
    var max   = <?= (int) $maxAttendees ?>;
    var n     = list.children.length;

    function money(v) {
        if (fee.currency === 'IDR') { return 'Rp ' + Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
        return '$ ' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function recalc() {
        var rows = list.querySelectorAll('.cn-guest');
        empty.hidden = rows.length > 0;
        if (add) { add.disabled = rows.length >= max; }
        if (fee.base === null) { return; }
        var items = [[fee.baseLabel, fee.base]];
        rows.forEach(function (row) {
            var name = (row.querySelector('.cn-guest-name').value || '').trim();
            items.push(['Additional attendee (non-presenter): ' + (name || '(not filled in yet)'), fee.attendeeFee || 0]);
        });
        var total = 0, html = '';
        items.forEach(function (it) {
            total += it[1];
            html += '<tr><td>' + esc(it[0]) + '</td><td class="text-end" style="white-space:nowrap">' + money(it[1]) + '</td></tr>';
        });
        document.getElementById('feeItems').innerHTML = html;
        document.getElementById('feeTotal').textContent = money(total);
    }

    if (add && tpl) {
        add.addEventListener('click', function () {
            var wrap = document.createElement('div');
            wrap.innerHTML = tpl.innerHTML.split('__i__').join('n' + (n++)).trim();
            list.appendChild(wrap.firstElementChild);
            recalc();
            list.lastElementChild.querySelector('.cn-guest-name').focus();
        });
    }
    list.addEventListener('click', function (e) {
        var rm = e.target.closest('.cn-guest-remove');
        if (rm) { rm.closest('.cn-guest').remove(); recalc(); }
    });
    list.addEventListener('input', recalc);
    recalc();
})();
</script>
