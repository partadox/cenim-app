<?php
/**
 * @var int    $year
 * @var array<string,string> $texts
 * @var array<string,array{path:string,size:int,time:int}> $files
 * @var string|null $inUse
 */

use Cenim\Core\Csrf;
use Cenim\Domain\BrandingRepo;
use Cenim\Domain\FileRepo;

/** @var array $kopLogos */
?>

<div class="cn-card mb-3" id="pdf-letterhead">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-file-pdf text-muted"></i>
        <h2>PDF letterhead — LoA, invoice &amp; receipt (<?= e((string) ($edition['name'] ?? '')) ?>)</h2>
        <a class="btn btn-sm btn-outline-secondary ms-auto" target="_blank" rel="noopener" href="<?= e(admin_url('kop-pdf-preview.php')) ?>">
            <i class="fa-regular fa-eye me-1"></i>Preview PDF
        </a>
    </div>
    <div class="cn-card-body">
        <p class="text-muted" style="font-size:12.5px">
            The letterhead is composed on every PDF: <b>left logos</b>, the <b>texts</b> (edited below) in the middle, and <b>right logos</b>,
            followed by the navy/gold line. Order is left-to-right within each side. The "Edition logo" entry always follows the
            logo set on the <a href="<?= e(admin_url('logo.php')) ?>">Logo</a> page.
        </p>
        <div class="table-responsive">
            <table class="table table-sm align-middle" style="font-size:12.5px">
                <thead class="table-light">
                    <tr><th style="width:120px">Logo</th><th>Name</th><th style="width:110px">Side</th><th style="width:120px">Height (mm)</th><th style="width:90px" class="text-center">Order</th><th style="width:96px"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($kopLogos as $logo): ?>
                        <?php $fid = 'kl' . (int) $logo['id']; ?>
                        <tr>
                            <td>
                                <?php if ($logo['path'] !== null): ?>
                                    <img src="<?= e((string) $logo['url']) ?>" alt="" style="height:38px;max-width:110px;object-fit:contain">
                                <?php else: ?>
                                    <span class="text-danger" style="font-size:11.5px"><i class="fa-solid fa-triangle-exclamation me-1"></i>image missing</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input form="<?= $fid ?>" class="form-control form-control-sm" name="label" value="<?= e((string) $logo['label']) ?>" maxlength="120">
                                <?php if ($logo['kind'] === 'edition_logo'): ?><div class="text-muted" style="font-size:11px">follows the edition logo</div><?php endif; ?>
                            </td>
                            <td>
                                <select form="<?= $fid ?>" class="form-select form-select-sm" name="side">
                                    <option value="left" <?= $logo['side'] === 'left' ? 'selected' : '' ?>>Left</option>
                                    <option value="right" <?= $logo['side'] === 'right' ? 'selected' : '' ?>>Right</option>
                                </select>
                            </td>
                            <td><input form="<?= $fid ?>" type="number" class="form-control form-control-sm" name="height_mm" value="<?= e((string) $logo['height_mm']) ?>" min="6" max="30" step="0.5"></td>
                            <td class="text-center text-nowrap">
                                <button form="<?= $fid ?>" class="btn btn-sm btn-outline-secondary" name="action" value="logo_left" title="Move left"><i class="fa-solid fa-arrow-left"></i></button>
                                <button form="<?= $fid ?>" class="btn btn-sm btn-outline-secondary" name="action" value="logo_right" title="Move right"><i class="fa-solid fa-arrow-right"></i></button>
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="post" id="<?= $fid ?>" class="d-inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="logo_id" value="<?= (int) $logo['id'] ?>">
                                    <input type="hidden" name="year" value="<?= e((string) $year) ?>">
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="logo_update" title="Save"><i class="fa-solid fa-floppy-disk"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="logo_delete" title="Remove"
                                            onclick="return confirm('Remove this logo from the letterhead?')"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($kopLogos === []): ?>
                        <tr><td colspan="6" class="text-muted">No logos — the letterhead shows only the texts.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="logo_add">
            <input type="hidden" name="year" value="<?= e((string) $year) ?>">
            <div class="col-md-4">
                <label class="form-label" for="kop_logo" style="font-size:12px">Add a logo</label>
                <input type="file" class="form-control form-control-sm" id="kop_logo" name="logo" accept="image/png,image/jpeg" required data-max-bytes="<?= upload_max_bytes(BrandingRepo::LOGO_MAX_BYTES) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="kop_logo_label" style="font-size:12px">Name</label>
                <input class="form-control form-control-sm" id="kop_logo_label" name="label" placeholder="e.g. IEEE Indonesia Section" maxlength="120">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="kop_logo_side" style="font-size:12px">Side</label>
                <select class="form-select form-select-sm" id="kop_logo_side" name="side"><option value="right">Right</option><option value="left">Left</option></select>
            </div>
            <div class="col-md-1">
                <label class="form-label" for="kop_logo_h" style="font-size:12px">Height</label>
                <input type="number" class="form-control form-control-sm" id="kop_logo_h" name="height_mm" value="12" min="6" max="30" step="0.5">
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-navy w-100"><i class="fa-solid fa-plus me-1"></i>Add</button>
            </div>
        </form>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <div class="form-text m-0" style="font-size:11.5px">
                PNG (transparent background) or JPG, at least <?= BrandingRepo::KOP_LOGO_MIN_PX ?> px on the shortest side, max
                <?= e(upload_max_label(BrandingRepo::LOGO_MAX_BYTES)) ?>; ~600 px tall is plenty. Height 10–14 mm suits partner logos, 18–24 mm the main logo.
            </div>
            <form method="post" class="ms-auto m-0" onsubmit="return confirm('Replace the logos with the default set (edition logo, ITS, Computer Engineering, BME)?')">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="logo_reset">
                <input type="hidden" name="year" value="<?= e((string) $year) ?>">
                <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-rotate-left me-1"></i>Reset to default</button>
            </form>
        </div>
    </div>
</div>

<h3 class="mb-2 mt-4" style="font-size:14px;color:#3d4a66"><i class="fa-solid fa-image me-1"></i>Letterhead image (PNG/PDF download; also the certificate background when a certificate type has no template image)</h3>

<div class="cn-card mb-3">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-file-lines text-muted"></i>
        <h2>Preview <?= e((string) $year) ?></h2>
        <form method="get" class="d-flex gap-2 align-items-center ms-auto m-0">
            <input type="number" class="form-control form-control-sm" name="year" style="width:110px"
                   value="<?= e((string) $year) ?>" min="2018" max="2100">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-eye me-1"></i>Preview
            </button>
        </form>
        <form method="post" class="m-0"<?= raster_form_attrs(admin_url('kop-preview.php?year=' . $year), $rasterTargets) ?>>
            <?= Csrf::field() ?>
            <input type="hidden" name="year" value="<?= e((string) $year) ?>">
            <button type="submit" class="btn btn-sm btn-navy">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generate &amp; use
            </button>
        </form>
    </div>
    <div class="cn-card-body">
        <img src="<?= e(admin_url('kop-preview.php?year=' . $year . '&w=1600')) ?>"
             alt="Letterhead <?= e((string) $year) ?>"
             style="width:100%;border:1px solid var(--cn-border);border-radius:6px">
        <div class="form-text mt-2" style="font-size:11.5px">
            Canvas 2480×758 px — same size and layout as the official 2026 letterhead. The CENIM logo is generated
            for the chosen year; the ITS, Computer Engineering, and BME logos come from the official artwork.
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-pen text-muted"></i>
                <h2>Letterhead texts</h2>
            </div>
            <div class="cn-card-body">
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="texts">
                    <input type="hidden" name="year" value="<?= e((string) $year) ?>">

                    <div class="mb-2">
                        <label class="form-label" for="kop_title" style="font-size:12.5px">Conference title</label>
                        <textarea class="form-control form-control-sm" id="kop_title" name="kop_title" rows="2"><?= e($texts['title']) ?></textarea>
                        <div class="form-text" style="font-size:11.5px">
                            Printed in capitals. Wrapped onto two lines automatically — or press Enter where the PDF letterhead should break the line.
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="kop_organized_by" style="font-size:12.5px">Organised by</label>
                        <input type="text" class="form-control form-control-sm" id="kop_organized_by"
                               name="kop_organized_by" value="<?= e($texts['organized']) ?>" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="kop_institute" style="font-size:12.5px">Institution line (italic)</label>
                        <input type="text" class="form-control form-control-sm" id="kop_institute"
                               name="kop_institute" value="<?= e($texts['institute']) ?>" maxlength="255">
                    </div>

                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save texts
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-circle-check text-muted"></i>
                <h2>Currently used by documents</h2>
            </div>
            <div class="cn-card-body" style="font-size:12.5px">
                <?php if ($inUse === null): ?>
                    <span class="text-muted">No letterhead available.</span>
                <?php else: ?>
                    <code><?= e(str_replace(dirname(CENIM_ROOT) . '/', '', $inUse)) ?></code>
                    <div class="form-text" style="font-size:11.5px">
                        <?= str_contains($inUse, 'generated/kop')
                            ? 'This is the generated letterhead.'
                            : 'Still the reference artwork — press “Generate &amp; use” to switch to the generated one.' ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-folder-open text-muted"></i>
                <h2>Saved files</h2>
            </div>
            <div class="cn-card-body p-0">
                <?php if ($files === []): ?>
                    <div class="cn-empty">
                        <i class="fa-regular fa-folder-open"></i>
                        Nothing generated for <?= e((string) $year) ?> yet.
                    </div>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0" style="font-size:13px">
                        <tbody>
                            <?php foreach ($files as $name => $info): ?>
                                <tr>
                                    <td><code><?= e($name) ?></code></td>
                                    <td class="text-muted" style="width:100px"><?= e(FileRepo::humanSize($info['size'])) ?></td>
                                    <td class="text-end" style="width:110px">
                                        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                                           href="<?= e(admin_url('generated-file.php?path=' . urlencode($info['path']))) ?>">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?= e(admin_url('generated-file.php?path=' . urlencode($info['path']) . '&download=1')) ?>">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($rasterTargets !== []) require __DIR__ . '/_raster_generate.php'; ?>
