<?php
/**
 * @var array $popup  PopupRepo::get()
 */

use Cenim\Core\Csrf;
use Cenim\Domain\PopupRepo;
?>

<form method="post" id="popupForm">
    <?= Csrf::field() ?>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-regular fa-window-restore text-muted"></i>
                    <h2>Announcement</h2>
                    <span class="cn-badge <?= $popup['enabled'] ? 'cn-badge-verified' : 'cn-badge-draft' ?> ms-auto">
                        <?= $popup['enabled'] ? 'Shown on the website' : 'Off' ?>
                    </span>
                </div>
                <div class="cn-card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?= $popup['enabled'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enabled">Show this pop-up on the Home, Workshop and Registration pages</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input type="text" class="form-control" id="title" name="title" maxlength="150" value="<?= e($popup['title']) ?>" placeholder="Announcement">
                    </div>

                    <label class="form-label" for="body">Content</label>
                    <textarea class="form-control" id="body" name="body" rows="12"><?= e($popup['body']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-sliders text-muted"></i>
                    <h2>Display</h2>
                </div>
                <div class="cn-card-body" style="font-size:13px">
                    <label class="form-label">When to show it</label>
                    <?php foreach (PopupRepo::FREQUENCIES as $key => $label): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="radio" name="frequency" id="freq_<?= e($key) ?>" value="<?= e($key) ?>" <?= $popup['frequency'] === $key ? 'checked' : '' ?>>
                            <label class="form-check-label" for="freq_<?= e($key) ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>

                    <label class="form-label mt-3" for="button">Close button label</label>
                    <input type="text" class="form-control form-control-sm" id="button" name="button" maxlength="40" value="<?= e($popup['button']) ?>">

                    <p class="text-muted mt-3 mb-0" style="font-size:12px">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Visitors close it with the X in the top-right corner, the close button, or by clicking outside it.
                        It works on phones, tablets and computers. One pop-up per edition.
                    </p>
                    <?php if ($popup['updated_at'] !== ''): ?>
                        <p class="text-muted mt-2 mb-0" style="font-size:11.5px">Last saved <?= e(fmt_dt($popup['updated_at'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-navy"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
                <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= e(base_url('?popup=preview')) ?>">
                    <i class="fa-solid fa-eye me-1"></i>Open the home page
                </a>
            </div>
        </div>
    </div>
</form>

<script src="<?= e(cfg('cdn.tinymce_js')) ?>"></script>
<script>
(function () {
    if (typeof tinymce === 'undefined') { return; }
    tinymce.init({
        selector: '#body',
        promotion: false,
        branding: false,
        menubar: 'edit insert format table',
        plugins: 'code link lists table image autoresize',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table | removeformat | code',
        block_formats: 'Paragraph=p; Heading 3=h3; Heading 4=h4; Heading 5=h5',
        min_height: 320,
        max_height: 720,
        convert_urls: false,
        entity_encoding: 'raw',
        content_style: 'body{font-family:system-ui,sans-serif;font-size:15px;padding:12px} img{max-width:100%;height:auto}',
        images_upload_handler: function (blobInfo) {
            var data = new FormData();
            data.append('file', blobInfo.blob(), blobInfo.filename());
            data.append('csrf_token', window.CENIM.csrf);
            return fetch(<?= json_encode(admin_url('editor-upload.php')) ?>, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (!json.location) { throw new Error(json.error || 'Upload failed.'); }
                    return json.location;
                });
        }
    });
    document.getElementById('popupForm').addEventListener('submit', function () { tinymce.triggerSave(); });
})();
</script>
