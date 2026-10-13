<?php
/**
 * Editor template sertifikat.
 *
 * @var string     $type
 * @var array<int,string> $types
 * @var array      $template
 * @var array<int,array<string,mixed>> $fields
 * @var array|null $background
 * @var array|null $signature
 * @var array|null $sample   sertifikat contoh untuk pratinjau PDF
 */

use Cenim\Core\Csrf;
use Cenim\Domain\CertificateRepo;
use Cenim\Domain\CertificateTemplateRepo;

$pageW = $template['orientation'] === 'P' ? 210 : 297;
$pageH = $template['orientation'] === 'P' ? 297 : 210;
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(admin_url('certificates.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back
    </a>
    <div class="btn-group btn-group-sm">
        <?php foreach ($types as $other): ?>
            <a class="btn <?= $other === $type ? 'btn-navy' : 'btn-outline-secondary' ?>"
               href="<?= e(admin_url('certificate-template.php?type=' . $other)) ?>">
                <?= e(CertificateRepo::label($other)) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($sample !== null): ?>
        <a class="btn btn-sm btn-outline-secondary ms-auto" target="_blank" rel="noopener"
           href="<?= e(admin_url('certificate-view.php?id=' . (int) $sample['id'])) ?>">
            <i class="fa-regular fa-eye me-1"></i>Preview a real certificate
        </a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="cn-card mb-3">
            <div class="cn-card-head flex-wrap">
                <i class="fa-solid fa-arrows-up-down-left-right text-muted"></i>
                <h2>Layout</h2>
                <span class="text-muted ms-auto" style="font-size:12px">
                    drag an element, or select it and use the arrow keys
                </span>
            </div>
            <div class="cn-card-body">
                <div id="cnCanvas"
                     data-page-w="<?= e((string) $pageW) ?>"
                     data-page-h="<?= e((string) $pageH) ?>"
                     style="position:relative;width:100%;aspect-ratio:<?= e((string) $pageW) ?>/<?= e((string) $pageH) ?>;
                            border:1px solid var(--cn-border);border-radius:6px;overflow:hidden;
                            background:#fff <?= $background !== null
                                ? 'url(' . e(app_url('public/file.php?id=' . (int) $background['id'])) . ') center/100% 100% no-repeat'
                                : '' ?>;">
                    <?php if ($background === null): ?>
                        <?php // Sama seperti kanvas bawaan views/certificate/document.php (bingkai + kop). ?>
                        <div style="position:absolute;inset:8px;border:2px solid var(--cn-navy)"></div>
                        <div style="position:absolute;inset:11px;border:1px solid var(--cn-gold)"></div>
                        <img src="<?= e(admin_url('certificate-letterhead.php')) ?>" alt="letterhead"
                             style="position:absolute;left:50%;transform:translateX(-50%);
                                    top:<?= e((string) round(14 / $pageH * 100, 2)) ?>%;
                                    width:<?= e((string) round(min(105, $pageW * 0.36) / $pageW * 100, 2)) ?>%">
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-navy" id="cnSaveLayout">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save layout
                    </button>
                    <form method="post" class="m-0" onsubmit="return confirm('Reset this layout to the default?')">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="reset_layout">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-rotate-left me-1"></i>Reset to default
                        </button>
                    </form>
                    <span class="text-muted ms-auto" style="font-size:12px">
                        Saving clears cached PDFs so everyone gets the new layout.
                    </span>
                </div>
            </div>
        </div>

        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-sliders text-muted"></i>
                <h2>Selected element</h2>
                <span class="text-muted ms-auto" id="cnSelectedLabel" style="font-size:12px">none</span>
            </div>
            <div class="cn-card-body">
                <div class="row g-2" id="cnFieldEditor" style="display:none">
                    <div class="col-md-6">
                        <label class="form-label" for="fText" style="font-size:12px">Text (static elements only)</label>
                        <input type="text" class="form-control form-control-sm" id="fText" maxlength="300">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="fSize" style="font-size:12px">Size (pt / mm for QR)</label>
                        <input type="number" class="form-control form-control-sm" id="fSize" min="5" max="60">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="fWidth" style="font-size:12px">Box width (% page)</label>
                        <input type="number" class="form-control form-control-sm" id="fWidth" min="5" max="100" step="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="fAlign" style="font-size:12px">Align</label>
                        <select class="form-select form-select-sm" id="fAlign">
                            <option value="L">Left</option>
                            <option value="C">Center</option>
                            <option value="R">Right</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="fFont" style="font-size:12px">Font</label>
                        <select class="form-select form-select-sm" id="fFont">
                            <option value="sans">Sans (Poppins)</option>
                            <option value="serif">Serif (Playfair Display)</option>
                            <option value="script">Script (Great Vibes)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="fColor" style="font-size:12px">Colour</label>
                        <input type="color" class="form-control form-control-sm form-control-color" id="fColor">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fBold">
                            <label class="form-check-label" for="fBold" style="font-size:12.5px">Bold</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="fHidden">
                            <label class="form-check-label" for="fHidden" style="font-size:12.5px">Hide element</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <span class="text-muted" style="font-size:12px">
                            Position: <code id="fPos">–</code> (percent of page width / height)
                        </span>
                    </div>
                </div>
                <div id="cnNoSelection" class="text-muted" style="font-size:13px">
                    Click an element on the layout to edit it.
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <form method="post" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="settings">

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-gear text-muted"></i>
                    <h2>Template settings</h2>
                </div>
                <div class="cn-card-body">
                    <div class="mb-2">
                        <label class="form-label" for="name" style="font-size:12.5px">Template name</label>
                        <input type="text" class="form-control form-control-sm" id="name" name="name"
                               value="<?= e((string) $template['name']) ?>" maxlength="190">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="page_size" style="font-size:12.5px">Page size</label>
                            <select class="form-select form-select-sm" id="page_size" name="page_size">
                                <?php foreach (CertificateTemplateRepo::PAGE_SIZES as $size): ?>
                                    <option value="<?= e($size) ?>" <?= $template['page_size'] === $size ? 'selected' : '' ?>>
                                        <?= e($size) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="orientation" style="font-size:12.5px">Orientation</label>
                            <select class="form-select form-select-sm" id="orientation" name="orientation">
                                <option value="L" <?= $template['orientation'] === 'L' ? 'selected' : '' ?>>Landscape</option>
                                <option value="P" <?= $template['orientation'] === 'P' ? 'selected' : '' ?>>Portrait</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                            <?= (int) $template['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active" style="font-size:12.5px">
                            Active — this type is issued and downloadable
                        </label>
                    </div>

                    <label class="form-label" for="background" style="font-size:12.5px">Background image</label>
                    <input class="form-control form-control-sm" type="file" id="background" name="background"
                           accept=".jpg,.jpeg,.png">
                    <div class="form-text" style="font-size:11.5px">
                        <?= e(upload_hint('JPG or PNG')) ?>. Optional. Use the exact page ratio (<?= e((string) $pageW) ?>×<?= e((string) $pageH) ?> mm).
                        Without it, a navy/gold frame plus the letterhead is drawn.
                    </div>
                    <?php if ($background !== null): ?>
                        <div class="d-flex align-items-center gap-2 mt-1" style="font-size:12px">
                            <a href="<?= e(app_url('public/file.php?id=' . (int) $background['id'])) ?>" target="_blank" rel="noopener">
                                <?= e((string) $background['original_name']) ?>
                            </a>
                            <button type="submit" name="remove_background" value="1" class="btn btn-sm btn-link text-danger p-0">
                                remove
                            </button>
                        </div>
                    <?php endif; ?>

                    <label class="form-label mt-3" for="signature" style="font-size:12.5px">Signature image</label>
                    <input class="form-control form-control-sm" type="file" id="signature" name="signature"
                           accept=".jpg,.jpeg,.png">
                    <div class="form-text" style="font-size:11.5px">
                        <?= e(upload_hint('JPG or PNG')) ?>. PNG with a transparent background works best.
                    </div>
                    <?php if ($signature !== null): ?>
                        <div class="d-flex align-items-center gap-2 mt-1" style="font-size:12px">
                            <a href="<?= e(app_url('public/file.php?id=' . (int) $signature['id'])) ?>" target="_blank" rel="noopener">
                                <?= e((string) $signature['original_name']) ?>
                            </a>
                            <button type="submit" name="remove_signature" value="1" class="btn btn-sm btn-link text-danger p-0">
                                remove
                            </button>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-sm btn-navy w-100 mt-3">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save settings
                    </button>
                </div>
            </div>
        </form>

        <div class="cn-card">
            <div class="cn-card-head">
                <i class="fa-solid fa-circle-info text-muted"></i>
                <h2>Elements</h2>
            </div>
            <div class="cn-card-body p-0">
                <table class="table table-sm mb-0" style="font-size:12.5px">
                    <tbody id="cnFieldList"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script id="cnFieldsData" type="application/json"><?= json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script id="cnSampleData" type="application/json"><?= json_encode([
    'name'    => $sample['recipient_name'] ?? 'Recipient Name',
    'subject' => $sample['subject_title'] ?? 'Paper title / award name',
    'code'    => $sample['code'] ?? 'CENIM2026-XXX-0000',
    'affiliation' => $sample['recipient_affiliation'] ?? 'Institut Teknologi Sepuluh Nopember',
    'number'  => $sample['number'] ?? ((($template['number_base'] ?? '') !== '') ? $template['number_base'] . '-001' : '0000/XX/2026-001'),
    'date'    => 'Surabaya, Indonesia, ' . fmt_date(now()),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
