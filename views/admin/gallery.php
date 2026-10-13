<?php
/**
 * @var array|null $posterFile
 * @var string|null $posterUrl
 * @var bool       $posterEnabled
 * @var array      $photos
 * @var array      $labels
 */

use Cenim\Core\Csrf;
use Cenim\Domain\GalleryRepo;
?>

<div class="alert alert-light border" style="font-size:12.5px">
    <i class="fa-solid fa-circle-info me-1" style="color:var(--cn-navy)"></i>
    The About section shows two buttons: <b><?= e($labels['poster']) ?></b> (opens the poster) and
    <b><?= e($labels['gallery']) ?></b> (opens a photo window titled “<?= e($labels['title']) ?>”).
    Change these labels in <a href="<?= e(admin_url('content.php')) ?>">Landing Content → About</a>.
    A button is hidden automatically when it has nothing to show.
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="cn-card h-100">
            <div class="cn-card-head">
                <i class="fa-regular fa-image text-muted"></i>
                <h2>Call for Papers poster</h2>
                <span class="cn-badge <?= $posterEnabled ? 'cn-badge-verified' : 'cn-badge-draft' ?> ms-auto"><?= $posterEnabled ? 'shown' : 'hidden' ?></span>
            </div>
            <div class="cn-card-body">
                <?php if ($posterUrl !== null): ?>
                    <a href="<?= e($posterUrl) ?>" target="_blank" rel="noopener">
                        <img src="<?= e($posterUrl) ?>" alt="Poster" class="border rounded w-100 mb-2" style="max-height:340px;object-fit:contain;background:#f4f6fa">
                    </a>
                    <?php if ($posterFile === null): ?>
                        <div class="text-muted mb-2" style="font-size:11.5px">No poster uploaded yet — the About image is shown instead.</div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="mb-2">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="poster_upload">
                    <div class="input-group input-group-sm">
                        <input type="file" class="form-control" name="poster" accept="image/png,image/jpeg" required data-max-bytes="<?= upload_max_bytes(GalleryRepo::MAX_BYTES) ?>">
                        <button class="btn btn-navy"><i class="fa-solid fa-upload me-1"></i><?= $posterFile !== null ? 'Replace' : 'Upload' ?></button>
                    </div>
                    <div class="form-text" style="font-size:11px">
                        PNG or JPG, portrait (e.g. 1080 × 1350 px or A4 at 1240 × 1754 px), at least <?= GalleryRepo::POSTER_MIN_WIDTH ?> px wide, max <?= e(upload_max_label(GalleryRepo::MAX_BYTES)) ?>.
                        Visitors can view and download it.
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-2">
                    <form method="post" class="m-0">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="poster_toggle">
                        <input type="hidden" name="enabled" value="<?= $posterEnabled ? '0' : '1' ?>">
                        <button class="btn btn-sm btn-outline-secondary">
                            <i class="fa-regular <?= $posterEnabled ? 'fa-eye-slash' : 'fa-eye' ?> me-1"></i><?= $posterEnabled ? 'Hide button' : 'Show button' ?>
                        </button>
                    </form>
                    <?php if ($posterFile !== null): ?>
                        <form method="post" class="m-0" onsubmit="return confirm('Remove the uploaded poster?')">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="poster_remove">
                            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Remove</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-images text-muted"></i>
                <h2>Gallery photos (<?= count($photos) ?>)</h2>
            </div>
            <div class="cn-card-body">
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="photos_upload">
                    <div class="col-md-6">
                        <label class="form-label" for="photos" style="font-size:12px">Add photos (you can select several)</label>
                        <input type="file" class="form-control form-control-sm" id="photos" name="photos[]" accept="image/png,image/jpeg" multiple required data-max-bytes="<?= upload_max_bytes(GalleryRepo::MAX_BYTES) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="caption" style="font-size:12px">Caption (optional, for all selected)</label>
                        <input class="form-control form-control-sm" id="caption" name="caption" maxlength="190" placeholder="e.g. CENIM 2025, Surabaya">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-navy w-100"><i class="fa-solid fa-upload me-1"></i>Upload</button>
                    </div>
                    <div class="col-12 form-text" style="font-size:11px">
                        PNG or JPG, landscape works best (e.g. 1600 × 1067 px), at least <?= GalleryRepo::PHOTO_MIN_WIDTH ?> px wide, max <?= e(upload_max_label(GalleryRepo::MAX_BYTES)) ?> each (select several at once; if the upload fails, send fewer photos per batch).
                    </div>
                </form>

                <div class="row g-3">
                    <?php foreach ($photos as $photo): ?>
                        <?php $url = GalleryRepo::photoUrl($photo); $fid = 'gp' . (int) $photo['id']; ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="border rounded p-2 h-100 <?= (int) $photo['is_visible'] === 1 ? '' : 'opacity-50' ?>">
                                <?php if ($url !== null): ?>
                                    <img src="<?= e($url) ?>" alt="" class="w-100 rounded mb-2" style="height:130px;object-fit:cover">
                                <?php endif; ?>
                                <form method="post" id="<?= $fid ?>" class="m-0">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
                                    <input class="form-control form-control-sm mb-1" name="caption" value="<?= e((string) ($photo['caption'] ?? '')) ?>" placeholder="Caption" maxlength="190">
                                    <div class="d-flex align-items-center gap-1">
                                        <input type="number" class="form-control form-control-sm" name="sort" value="<?= (int) $photo['sort'] ?>" style="width:70px" title="Order">
                                        <div class="form-check m-0 ms-1" title="Visible">
                                            <input class="form-check-input" type="checkbox" name="is_visible" value="1" <?= (int) $photo['is_visible'] === 1 ? 'checked' : '' ?>>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary ms-auto" name="action" value="photo_save" title="Save"><i class="fa-solid fa-floppy-disk"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" name="action" value="photo_delete" title="Remove"
                                                onclick="return confirm('Remove this photo?')"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($photos === []): ?>
                        <div class="col-12 text-muted" style="font-size:12.5px">No photos — the gallery button is hidden on the landing page.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
