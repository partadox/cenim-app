<?php
/**
 * Pilihan Indonesia / International (+ negara bila International) untuk form
 * peserta non-presenter. Indonesia ditagih IDR, International USD.
 *
 * @var array  $values     region, country
 * @var array  $errors
 * @var array  $countries
 * @var string $size       '' atau 'sm'
 */

$sm      = ($size ?? '') === 'sm' ? '-sm' : '';
$region  = (string) ($values['region'] ?? '');
$invalid = isset($errors['country']) ? ' is-invalid' : '';
?>
<div class="cn-region">
    <select class="form-select form-select<?= $sm ?> mb-1<?= $invalid ?>" id="region" name="region" required>
        <option value="" <?= $region === '' ? 'selected' : '' ?> disabled>Choose…</option>
        <option value="indonesia" <?= $region === 'indonesia' ? 'selected' : '' ?>>Indonesia (billed in IDR)</option>
        <option value="international" <?= $region === 'international' ? 'selected' : '' ?>>International (billed in USD)</option>
    </select>
    <div class="cn-region-country"<?= $region === 'international' ? '' : ' hidden' ?>>
        <select class="form-select form-select<?= $sm ?> mb-1<?= $invalid ?>" id="country" name="country">
            <option value="">Choose your country…</option>
            <?php foreach ($countries as $country): ?>
                <?php if ($country === 'Indonesia') { continue; } ?>
                <option value="<?= e($country) ?>" <?= $country === (string) ($values['country'] ?? '') && $region === 'international' ? 'selected' : '' ?>><?= e($country) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (isset($errors['country'])): ?>
        <div class="invalid-feedback d-block" style="font-size:12px"><?= e($errors['country']) ?></div>
    <?php endif; ?>
</div>
<script>
(function () {
    var region = document.getElementById('region');
    var box = document.querySelector('.cn-region-country');
    region.addEventListener('change', function () { box.hidden = region.value !== 'international'; });
})();
</script>
