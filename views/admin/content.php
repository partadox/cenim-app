<?php
/**
 * @var string $page
 * @var array  $pages
 * @var array  $groups
 * @var array<string,array{value:string,custom:bool,updated_at:?string}> $stored
 */

use Cenim\Core\Csrf;
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        <?php foreach ($pages as $key => $definition): ?>
            <a class="btn <?= $key === $page ? 'btn-navy' : 'btn-outline-secondary' ?>"
               href="<?= e(admin_url('content.php?page=' . $key)) ?>"><?= e($definition['label']) ?></a>
        <?php endforeach; ?>
    </div>
    <a class="btn btn-sm btn-outline-secondary ms-auto" target="_blank" rel="noopener"
       href="<?= e(app_url('site/preview.php?page=' . $page)) ?>">
        <i class="fa-solid fa-eye me-1"></i>Preview page
    </a>
</div>

<div class="alert alert-secondary" style="font-size:12.5px">
    <i class="fa-solid fa-circle-info me-1"></i>
    Every field below shows the text that is <b>live on the public page right now</b> — edit it and the
    change is exactly what visitors will see. Blocks marked
    <span class="cn-badge cn-badge-draft">default</span> still use the built-in text;
    once you change one it becomes <span class="cn-badge cn-badge-submitted">edited</span> and gets a
    <b>Reset</b> link. Clearing a field (or saving text identical to the default) puts it back to
    <span class="cn-badge cn-badge-draft">default</span>.
    <div class="mt-2">
        These placeholders stay live after you edit the sentence around them:
        <code>{included_pages}</code> <code>{extra_paper_percent}</code>
        <code>{edition_name}</code> <code>{edition_year}</code>.
    </div>
</div>

<form method="post">
    <?= Csrf::field() ?>

    <?php foreach ($groups as $groupName => $blocks): ?>
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-newspaper text-muted"></i>
                <h2><?= e($groupName) ?></h2>
            </div>
            <div class="cn-card-body">
                <?php foreach ($blocks as $key => $definition): ?>
                    <?php $state = $stored[$key] ?? ['value' => '', 'custom' => false, 'updated_at' => null]; ?>
                    <div class="mb-3">
                        <label class="form-label d-flex flex-wrap align-items-center gap-2" for="b_<?= e($key) ?>" style="font-size:12.5px">
                            <?= e($definition['label']) ?>
                            <code><?= e($key) ?></code>
                            <?php if (!$state['custom']): ?>
                                <span class="cn-badge cn-badge-draft" title="Teks bawaan yang sedang tampil di halaman publik">default</span>
                            <?php endif; ?>
                            <?php if ($state['custom']): ?>
                                <span class="cn-badge cn-badge-submitted">edited</span>
                                <?php if ($state['updated_at'] !== null): ?>
                                    <span class="text-muted" style="font-size:11px"><?= e(fmt_dt($state['updated_at'])) ?></span>
                                <?php endif; ?>
                                <button type="submit" name="action" value="reset"
                                        formnovalidate class="btn btn-sm btn-link text-danger p-0"
                                        onclick="document.getElementById('resetKey').value='<?= e($key) ?>'">
                                    reset
                                </button>
                            <?php endif; ?>
                        </label>

                        <?php if ($definition['kind'] === 'text'): ?>
                            <input type="text" class="form-control form-control-sm" id="b_<?= e($key) ?>"
                                   name="block[<?= e($key) ?>]" value="<?= e($state['value']) ?>"
                                   placeholder="(kosongkan = kembali ke teks bawaan)">
                        <?php else: ?>
                            <textarea class="form-control form-control-sm<?= $definition['kind'] === 'html' ? ' cn-wysiwyg' : '' ?>" id="b_<?= e($key) ?>"
                                      name="block[<?= e($key) ?>]"
                                      rows="<?= max(
                                          $definition['kind'] === 'list' ? 8 : 5,
                                          min(28, (int) ceil(mb_strlen($state['value']) / 90) + substr_count($state['value'], "\n"))
                                      ) ?>"
                                      spellcheck="false"
                                      style="<?= $definition['kind'] === 'list' ? 'font-family:ui-monospace,monospace;font-size:12px' : '' ?>"
                                      placeholder="(kosongkan = kembali ke teks bawaan)"><?= e($state['value']) ?></textarea>
                        <?php endif; ?>

                        <?php if (($definition['hint'] ?? '') !== ''): ?>
                            <div class="form-text" style="font-size:11.5px"><?= e($definition['hint']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <input type="hidden" name="key" id="resetKey" value="">
    <input type="hidden" name="page" value="<?= e($page) ?>">

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-navy">
            <i class="fa-solid fa-floppy-disk me-1"></i>Save content
        </button>
        <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
           href="<?= e(app_url('site/preview.php?page=' . $page)) ?>">Preview</a>
    </div>
</form>

<?php
// Editor WYSIWYG (TinyMCE) untuk blok bertipe HTML. Tampilan isi editor memakai
// CSS tema supaya mirip halaman publik; markup tema (class grid, layout) dibiarkan
// utuh. Editor yang TIDAK diubah mengirim HTML aslinya apa adanya, supaya blok
// tidak tercatat "edited" hanya karena HTML-nya dirapikan editor.
?>
<script src="<?= e(cfg('cdn.tinymce_js')) ?>"></script>
<script>
(function () {
    if (typeof tinymce === 'undefined' || !document.querySelector('textarea.cn-wysiwyg')) {
        return;
    }

    var originals = {};
    var loaded = {};
    var themeBase = <?= json_encode(rtrim(asset(''), '/') . '/') ?>;
    document.querySelectorAll('textarea.cn-wysiwyg').forEach(function (ta) { originals[ta.id] = ta.value; });

    var ready = tinymce.init({
        selector: 'textarea.cn-wysiwyg',
        promotion: false,
        branding: false,
        menubar: 'edit insert format table',
        plugins: 'code link lists table image autoresize',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table | removeformat | code',
        block_formats: 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4; Heading 5=h5',
        min_height: 260,
        max_height: 720,
        autoresize_bottom_margin: 20,
        // Markup tema dipertahankan apa adanya (class, data-*, struktur grid).
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        valid_children: '+a[div|h1|h2|h3|h4|h5|h6|p|span|img|i]',
        verify_html: false,
        convert_urls: false,
        entity_encoding: 'raw',
        // Path relatif "assets/…" di konten dibaca dari web root situs, sama seperti di halaman publik.
        document_base_url: location.origin + <?= json_encode(rtrim(base_url(), '/') . '/') ?>, // harus absolut, kalau tidak di-resolve terhadap URL CDN
        content_css: [<?= json_encode(asset('css/bootstrap.min.css')) ?>, <?= json_encode(asset('css/fontawesome.min.css')) ?>, <?= json_encode(asset('css/style.css')) ?>],
        content_style: 'body{padding:12px;background:#fff} img{max-width:100%;height:auto}',
        image_caption: false,
        // Isi editor saat pertama dimuat (sudah dinormalisasi TinyMCE) — pembanding "diubah atau tidak".
        setup: function (editor) {
            // "assets/…" di konten = aset tema (di halaman publik diubah oleh
            // site_fix_asset_paths). Di editor ditampilkan dari URL tema, lalu
            // dikembalikan ke "assets/…" saat isinya diambil.
            editor.on('BeforeSetContent', function (e) {
                e.content = e.content.replace(/(["'(])assets\//g, '$1' + themeBase);
            });
            editor.on('GetContent', function (e) {
                e.content = e.content.split(themeBase).join('assets/');
            });
            editor.on('init', function () { loaded[editor.id] = editor.getContent(); });
        },
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

    // Saat submit, nilai textarea ditulis sendiri: isi editor bila berbeda dari
    // isi saat dimuat, HTML asli apa adanya bila tidak. (Status dirty TinyMCE
    // tidak dipakai — handler submit TinyMCE sendiri sudah mereset-nya.)
    var form = document.querySelector('textarea.cn-wysiwyg').form;
    ready.then(function () {
        form.addEventListener('submit', function () {
            tinymce.get().forEach(function (editor) {
                var ta = document.getElementById(editor.id);
                if (ta) {
                    var now = editor.getContent();
                    ta.value = now !== loaded[editor.id] ? now : originals[editor.id];
                }
            });
        });
    });
})();
</script>

