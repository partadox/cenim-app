<?php
/**
 * Pertanyaan tambahan dari Form Builder (dipakai form registrasi & attendance).
 *
 * @var array<int,array<string,mixed>> $extraFields
 * @var array<string,mixed>            $extraValues  jawaban tersimpan
 * @var array<string,string>           $errors       kunci "extra_<field_key>"
 * @var string                         $disabled     '' atau 'disabled'
 */

use Cenim\Domain\FormRepo;

$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>'
        : '';
};
$values = ['extra' => $extraValues];
?>
<div class="row g-3">
    <?php foreach ($extraFields as $field): ?>
        <?php
        $key     = (string) $field['field_key'];
        $value   = $values['extra'][$key] ?? null;
        $invalid = isset($errors['extra_' . $key]) ? ' is-invalid' : '';
        $options = FormRepo::options($field);
        ?>
        <div class="col-md-<?= $field['type'] === 'textarea' ? '12' : '6' ?>">
            <label class="form-label" for="extra_<?= e($key) ?>">
                <?= e((string) $field['label']) ?>
                <?php if ((int) $field['is_required'] === 1): ?><span class="text-danger">*</span><?php endif; ?>
            </label>

            <?php if ($field['type'] === 'textarea'): ?>
                <textarea class="form-control<?= $invalid ?>" id="extra_<?= e($key) ?>"
                          name="extra[<?= e($key) ?>]" rows="3"
                          placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>" <?= $disabled ?>><?= e((string) $value) ?></textarea>

            <?php elseif ($field['type'] === 'select'): ?>
                <select class="form-select<?= $invalid ?>" id="extra_<?= e($key) ?>"
                        name="extra[<?= e($key) ?>]" <?= $disabled ?>>
                    <option value="">— choose —</option>
                    <?php foreach ($options as $option): ?>
                        <option value="<?= e($option) ?>" <?= (string) $value === $option ? 'selected' : '' ?>>
                            <?= e($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($field['type'] === 'radio'): ?>
                <?php foreach ($options as $i => $option): ?>
                    <div class="form-check">
                        <input class="form-check-input<?= $invalid ?>" type="radio"
                               id="extra_<?= e($key) ?>_<?= e((string) $i) ?>"
                               name="extra[<?= e($key) ?>]" value="<?= e($option) ?>"
                            <?= (string) $value === $option ? 'checked' : '' ?> <?= $disabled ?>>
                        <label class="form-check-label" for="extra_<?= e($key) ?>_<?= e((string) $i) ?>">
                            <?= e($option) ?>
                        </label>
                    </div>
                <?php endforeach; ?>

            <?php elseif ($field['type'] === 'checkbox'): ?>
                <div class="form-check">
                    <input class="form-check-input<?= $invalid ?>" type="checkbox" value="1"
                           id="extra_<?= e($key) ?>" name="extra[<?= e($key) ?>]"
                        <?= $value ? 'checked' : '' ?> <?= $disabled ?>>
                    <label class="form-check-label" for="extra_<?= e($key) ?>">
                        <?= e((string) ($field['placeholder'] ?? 'Yes')) ?>
                    </label>
                </div>

            <?php else: ?>
                <input type="<?= e($field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text')) ?>"
                       class="form-control<?= $invalid ?>" id="extra_<?= e($key) ?>"
                       name="extra[<?= e($key) ?>]" value="<?= e((string) $value) ?>"
                       placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>" <?= $disabled ?>>
            <?php endif; ?>

            <?php if (($field['help_text'] ?? '') !== ''): ?>
                <div class="form-text" style="font-size:11.5px"><?= e((string) $field['help_text']) ?></div>
            <?php endif; ?>
            <?= $err('extra_' . $key) ?>
        </div>
    <?php endforeach; ?>
</div>
