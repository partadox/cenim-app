<?php
/**
 * Unggah satu gambar pengesahan — tanda tangan chair/treasurer atau stempel
 * (dipakai di Settings).
 * Input-nya terikat ke form tersembunyi signature-form-<role> lewat atribut
 * form=, karena kartu ini berada di dalam form Settings utama.
 *
 * @var string     $role  'chair' | 'treasurer' | 'stamp'
 * @var array|null $file  baris files yang terpasang
 * @var string     $label label kolom
 * @var string     $help  teks bantuan (HTML aman)
 */
?>
<label class="form-label" for="signature-<?= e($role) ?>" style="font-size:12.5px"><?= e($label) ?></label>
<div class="d-flex flex-wrap align-items-start gap-3">
    <?php if (!empty($file)): ?>
        <div class="border rounded p-2 text-center" style="background:#fff;width:200px">
            <img src="<?= e(app_url('public/file.php?id=' . (int) $file['id'])) ?>" alt="<?= e($label) ?>"
                 style="max-width:100%;max-height:90px;object-fit:contain">
        </div>
    <?php endif; ?>
    <div class="flex-grow-1" style="min-width:220px">
        <div class="d-flex gap-2">
            <input type="file" class="form-control form-control-sm" id="signature-<?= e($role) ?>" name="signature" form="signature-form-<?= e($role) ?>"
                   accept="image/png,image/jpeg" data-max-bytes="<?= upload_max_bytes(Cenim\Domain\BrandingRepo::LOGO_MAX_BYTES) ?>">
            <button type="submit" form="signature-form-<?= e($role) ?>" class="btn btn-sm btn-navy text-nowrap">
                <i class="fa-solid fa-upload me-1"></i><?= !empty($file) ? 'Replace' : 'Upload' ?>
            </button>
            <?php if (!empty($file)): ?>
                <button type="submit" form="signature-remove-<?= e($role) ?>" class="btn btn-sm btn-outline-danger" title="Remove">
                    <i class="fa-solid fa-trash"></i>
                </button>
            <?php endif; ?>
        </div>
        <div class="form-text" style="font-size:11.5px">
            <?= $help ?>
            <b>PNG with a transparent background</b>, shortest side at least 150 px,
            <?= e(upload_max_label(Cenim\Domain\BrandingRepo::LOGO_MAX_BYTES)) ?> max.
        </div>
    </div>
</div>
