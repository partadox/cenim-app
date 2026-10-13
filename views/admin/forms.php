<?php
/**
 * @var string $formKey
 * @var array<int,array<string,mixed>> $fields
 */

use Cenim\Core\Csrf;
use Cenim\Domain\FormRepo;

$forms = [
    FormRepo::FORM_REGISTRATION => 'Registration form',
    FormRepo::FORM_PAYMENT      => 'Payment form',
    FormRepo::FORM_ATTENDANCE   => 'Video presentation form',
];
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        <?php foreach ($forms as $key => $label): ?>
            <a class="btn <?= $key === $formKey ? 'btn-navy' : 'btn-outline-secondary' ?>"
               href="<?= e(admin_url('forms.php?form=' . $key)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <span class="text-muted ms-auto" style="font-size:12px">
        Answers appear on the registration review page and in the author's record.
    </span>
</div>

<?php if ($formKey === FormRepo::FORM_PAYMENT): ?>
    <div class="alert alert-secondary" style="font-size:12.5px">
        <i class="fa-solid fa-circle-info me-1"></i>
        Extra fields on the payment form are stored but not yet rendered on the author payment page —
        the registration form is the one that renders them today.
    </div>
<?php endif; ?>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-square-check text-muted"></i>
        <h2>Fields</h2>
    </div>
    <div class="cn-card-body p-0">
        <?php if ($fields === []): ?>
            <div class="cn-empty">
                <i class="fa-regular fa-square"></i>
                No extra field yet — the author form shows only the standard questions.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:13px">
                    <thead>
                        <tr>
                            <th style="width:70px">Order</th>
                            <th style="width:150px">Key</th>
                            <th>Label &amp; help text</th>
                            <th style="width:130px">Type</th>
                            <th style="width:180px">Options (one per line)</th>
                            <th style="width:150px">Flags</th>
                            <th class="text-end" style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                            <tr>
                                <form method="post" class="m-0">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                                    <td>
                                        <input type="number" class="form-control form-control-sm" name="sort"
                                               value="<?= e((string) (int) $field['sort']) ?>">
                                    </td>
                                    <td><code><?= e((string) $field['field_key']) ?></code></td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="label"
                                               value="<?= e((string) $field['label']) ?>" maxlength="190">
                                        <input type="text" class="form-control form-control-sm mt-1" name="help_text"
                                               value="<?= e((string) ($field['help_text'] ?? '')) ?>" maxlength="500"
                                               placeholder="help text (optional)">
                                        <input type="text" class="form-control form-control-sm mt-1" name="placeholder"
                                               value="<?= e((string) ($field['placeholder'] ?? '')) ?>" maxlength="190"
                                               placeholder="placeholder / checkbox label">
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="type">
                                            <?php foreach (FormRepo::TYPES as $type): ?>
                                                <option value="<?= e($type) ?>" <?= $field['type'] === $type ? 'selected' : '' ?>>
                                                    <?= e($type) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <textarea class="form-control form-control-sm" name="options" rows="3"
                                                  placeholder="only for select / radio"><?= e(implode("\n", FormRepo::options($field))) ?></textarea>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_required" value="1"
                                                <?= (int) $field['is_required'] === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" style="font-size:12px">Required</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_visible" value="1"
                                                <?= (int) $field['is_visible'] === 1 ? 'checked' : '' ?>>
                                            <label class="form-check-label" style="font-size:12px">Visible</label>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Save">
                                            <i class="fa-solid fa-floppy-disk"></i>
                                        </button>
                                </form>
                                        <form method="post" class="d-inline m-0" onsubmit="return confirm('Delete this field?')">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-plus text-muted"></i>
        <h2>Add field</h2>
    </div>
    <div class="cn-card-body">
        <form method="post" class="row g-2 align-items-end">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create">
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Field key</label>
                <input type="text" class="form-control form-control-sm" name="field_key" maxlength="40"
                       placeholder="dietary_needs" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">Label</label>
                <input type="text" class="form-control form-control-sm" name="label" maxlength="190" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Type</label>
                <select class="form-select form-select-sm" name="type">
                    <?php foreach (FormRepo::TYPES as $type): ?>
                        <option value="<?= e($type) ?>"><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">Options (one per line)</label>
                <textarea class="form-control form-control-sm" name="options" rows="2"></textarea>
            </div>
            <div class="col-md-1">
                <label class="form-label" style="font-size:12px">Order</label>
                <input type="number" class="form-control form-control-sm" name="sort" value="<?= e((string) ((count($fields) + 1) * 10)) ?>">
            </div>
            <div class="col-md-1">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="is_required" value="1">
                    <label class="form-check-label" style="font-size:12px">Req.</label>
                </div>
                <button type="submit" class="btn btn-sm btn-navy w-100"><i class="fa-solid fa-plus"></i></button>
            </div>
        </form>
    </div>
</div>
