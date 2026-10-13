<?php
/**
 * @var int   $year
 * @var array<int,int> $sizes
 * @var array<string,array{path:string,size:int,time:int}> $files
 * @var string $font
 */

use Cenim\Core\Csrf;
use Cenim\Domain\BrandingRepo;
use Cenim\Domain\FileRepo;
?>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-image text-muted"></i>
        <h2>Edition logo in use — <?= e((string) ($edition['name'] ?? '')) ?></h2>
        <span class="cn-badge <?= $logoFile !== null ? 'cn-badge-verified' : 'cn-badge-draft' ?> ms-auto">
            <?= $logoFile !== null ? 'custom' : 'default' ?>
        </span>
    </div>
    <div class="cn-card-body">
        <div class="row g-3 align-items-start">
            <div class="col-md-3 text-center">
                <div class="border rounded p-3" style="background:repeating-conic-gradient(#f1f3f7 0% 25%, #fff 0% 50%) 50% / 16px 16px">
                    <img src="<?= e($logoUrl) ?>" alt="Edition logo" style="max-width:100%;max-height:150px;object-fit:contain">
                </div>
                <div class="text-muted mt-1" style="font-size:11.5px">
                    <?= $logoFile !== null ? e((string) $logoFile['original_name']) : 'Default theme logo' ?>
                </div>
            </div>
            <div class="col-md-9">
                <p style="font-size:13px" class="mb-2">
                    Used on the landing page (header, footer, favicon), the author &amp; admin panels, the PDF letterhead
                    (LoA, invoice, receipt), and — once this edition is over — its card in <b>Previous Conference</b>.
                </p>
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end mb-2">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="year" value="<?= e((string) $year) ?>">
                    <div class="col-md-8">
                        <label class="form-label" for="logo" style="font-size:12.5px">Upload your own logo</label>
                        <input type="file" class="form-control form-control-sm" id="logo" name="logo" accept="image/png,image/jpeg" required data-max-bytes="<?= upload_max_bytes(BrandingRepo::LOGO_MAX_BYTES) ?>">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-sm btn-navy w-100"><i class="fa-solid fa-upload me-1"></i>Upload &amp; use</button>
                    </div>
                </form>
                <div class="alert alert-light border py-2 px-3 mb-2" style="font-size:12px">
                    <b><i class="fa-solid fa-circle-info me-1"></i>Logo requirements</b>
                    <ul class="mb-0 ps-3">
                        <li>Format: <b>PNG with a transparent background</b> (recommended) or JPG. SVG/WebP are not accepted — PDF documents cannot embed them.</li>
                        <li>Size: <b>1024 × 1024 px</b> recommended (square); the shortest side must be at least <b><?= BrandingRepo::LOGO_MIN_PX ?> px</b>.</li>
                        <li>Wide logos also work (e.g. 2048 × 1024 px); keep the artwork close to the edges, without large empty margins.</li>
                        <li>File size: at most <b><?= e(upload_max_label(BrandingRepo::LOGO_MAX_BYTES)) ?></b>.</li>
                    </ul>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (isset($files['logo-cenim-' . $year . '-1024.png'])): ?>
                        <form method="post" class="m-0">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="use_generated">
                            <input type="hidden" name="year" value="<?= e((string) $year) ?>">
                            <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Use the generated <?= e((string) $year) ?> logo</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($logoFile !== null): ?>
                        <form method="post" class="m-0" onsubmit="return confirm('Remove the edition logo and use the default logo?')">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="clear">
                            <input type="hidden" name="year" value="<?= e((string) $year) ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-rotate-left me-1"></i>Use the default logo</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<h3 class="mb-2" style="font-size:14px;color:#3d4a66"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generator (CENIM concept — only the year changes)</h3>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-shapes text-muted"></i>
                <h2>Generate</h2>
            </div>
            <div class="cn-card-body">
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-7">
                        <label class="form-label" for="year" style="font-size:12.5px">Year</label>
                        <input type="number" class="form-control form-control-sm" id="year" name="year"
                               value="<?= e((string) $year) ?>" min="2018" max="2100">
                    </div>
                    <div class="col-5">
                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="fa-solid fa-eye me-1"></i>Preview
                        </button>
                    </div>
                </form>

                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="year" value="<?= e((string) $year) ?>">
                    <button type="submit" class="btn btn-navy w-100">
                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generate &amp; save all formats
                    </button>
                </form>

                <div class="form-text mt-2" style="font-size:11.5px">
                    Saves PNG <?= e(implode(', ', array_map('strval', $sizes))) ?> px (transparent),
                    a white-background PNG, a 192 px favicon, and an SVG.
                </div>

                <hr class="my-3">

                <dl class="row mb-0" style="font-size:12.5px">
                    <dt class="col-5 text-muted fw-normal">Year font</dt>
                    <dd class="col-7"><code><?= e($font) ?></code></dd>
                    <dt class="col-5 text-muted fw-normal">Shape source</dt>
                    <dd class="col-7 mb-0"><code>reference/logo-base.png</code></dd>
                </dl>
                <div class="form-text" style="font-size:11.5px">
                    The frame and the “CENIM” lettering come from the official template; only the year is drawn.
                    To use a different digit font, drop a .ttf into <code>vendor-lite/fonts/</code> and set
                    <code>logo_year_font</code> in the settings table.
                </div>
            </div>
        </div>

        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-download text-muted"></i>
                <h2>Direct download</h2>
            </div>
            <div class="cn-card-body d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('logo-svg.php?year=' . $year)) ?>">
                    <i class="fa-solid fa-file-code me-1"></i>SVG (font embedded)
                </a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('logo-svg.php?year=' . $year . '&nofont=1')) ?>">
                    <i class="fa-solid fa-file-code me-1"></i>SVG (light)
                </a>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                   href="<?= e(admin_url('logo-preview.php?year=' . $year . '&size=2048')) ?>">
                    <i class="fa-solid fa-image me-1"></i>PNG 2048
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-regular fa-image text-muted"></i>
                <h2>Preview <?= e((string) $year) ?></h2>
            </div>
            <div class="cn-card-body">
                <div class="d-flex flex-wrap align-items-end gap-4">
                    <div class="text-center">
                        <img src="<?= e(admin_url('logo-preview.php?year=' . $year . '&size=320')) ?>"
                             alt="Logo <?= e((string) $year) ?>" style="width:200px;height:auto">
                        <div class="text-muted mt-1" style="font-size:11.5px">transparent</div>
                    </div>
                    <div class="text-center p-2" style="background:#f0f2f7;border-radius:8px">
                        <img src="<?= e(admin_url('logo-preview.php?year=' . $year . '&size=160')) ?>"
                             alt="Logo small" style="width:96px;height:auto">
                        <div class="text-muted mt-1" style="font-size:11.5px">96 px</div>
                    </div>
                    <div class="text-center p-2" style="background:#0b2b57;border-radius:8px">
                        <img src="<?= e(admin_url('logo-preview.php?year=' . $year . '&size=96')) ?>"
                             alt="Logo tiny" style="width:48px;height:auto">
                        <div class="mt-1" style="font-size:11.5px;color:#cbd6ea">48 px on dark</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-folder-open text-muted"></i>
                <h2>Saved files</h2>
                <span class="text-muted ms-auto" style="font-size:12px">storage/generated/logo/<?= e((string) $year) ?></span>
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
                                    <td class="text-muted" style="width:110px"><?= e(FileRepo::humanSize($info['size'])) ?></td>
                                    <td class="text-end" style="width:150px">
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
