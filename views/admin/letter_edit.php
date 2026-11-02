<?php
/**
 * @var array|null $letter
 * @var array      $values
 * @var array<string,string> $errors
 * @var array<int,array<string,mixed>> $papers
 * @var array<string,array{name:string,title:string,has_image:bool}> $signers
 */

use Cenim\Core\Csrf;
use Cenim\Domain\LetterRepo;

$err = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>' : '';
$cls = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(admin_url('letters.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to list
    </a>
    <?php if ($letter !== null): ?>
        <a href="<?= e(admin_url('letter-pdf.php?id=' . (int) $letter['id'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="fa-solid fa-file-pdf me-1"></i>Open PDF
        </a>
    <?php endif; ?>
</div>

<form method="post" id="letterForm" novalidate>
    <?= Csrf::field() ?>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="cn-card mb-3">
                <div class="cn-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="title">Letter name <span class="text-danger">*</span></label>
                            <input class="form-control<?= $cls('title') ?>" id="title" name="title" maxlength="190" value="<?= e($values['title']) ?>"
                                   placeholder="e.g. Invitation Letter">
                            <?= $err('title') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="number">Letter number</label>
                            <input class="form-control<?= $cls('number') ?>" id="number" name="number" maxlength="190" value="<?= e($values['number']) ?>"
                                   placeholder="e.g. 012/CENIM/XI/2026">
                            <?= $err('number') ?>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="author_id">For paper</label>
                            <select class="form-select<?= $cls('author_id') ?>" id="author_id" name="author_id">
                                <option value="0">&mdash; No paper (the paper variables stay empty) &mdash;</option>
                                <?php foreach ($papers as $paper): ?>
                                    <option value="<?= (int) $paper['id'] ?>" <?= (int) $paper['id'] === (int) $values['author_id'] ? 'selected' : '' ?>>
                                        <?= e($paper['paper_id'] . ' - ' . $paper['first_author_name'] . ' - ' . mb_strimwidth((string) ($paper['paper_title'] ?? 'Participant'), 0, 70, '…')
                                            . ((string) $paper['paper_status'] !== 'accepted' ? ' (' . $paper['paper_status'] . ')' : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('author_id') ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="letter_date">Letter date</label>
                            <input type="date" class="form-control<?= $cls('letter_date') ?>" id="letter_date" name="letter_date" value="<?= e($values['letter_date']) ?>">
                            <?= $err('letter_date') ?>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="show_title" name="show_title" value="1" <?= $values['show_title'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show_title" style="font-size:13px">Show the letter name as the heading of the letter (with the number below it)</label>
                    </div>
                </div>
            </div>

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-pen-nib text-muted"></i>
                    <h2>Content</h2>
                </div>
                <div class="cn-card-body">
                    <textarea id="body" name="body" rows="16" class="form-control<?= $cls('body') ?>"><?= e($values['body']) ?></textarea>
                    <?= $err('body') ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-code text-muted"></i>
                    <h2>Variables</h2>
                </div>
                <div class="cn-card-body" style="font-size:12.5px">
                    <p class="text-muted mb-2">Click to insert at the cursor (or use <b>Insert variable</b> in the editor toolbar). They are replaced with the data of the chosen paper in the PDF.</p>
                    <div class="d-flex flex-column gap-1">
                        <?php foreach (LetterRepo::VARIABLES as $key => $label): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary text-start cn-var" data-var="<?= e($key) ?>">
                                <code>{<?= e($key) ?>}</code> <span class="text-muted ms-1"><?= e($label) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-signature text-muted"></i>
                    <h2>Signature</h2>
                </div>
                <div class="cn-card-body" style="font-size:13px">
                    <?php foreach (LetterRepo::SIGNERS as $key => $label): ?>
                        <?php $person = $signers[$key] ?? null; ?>
                        <label class="border rounded p-2 px-3 d-flex gap-2 mb-2" style="cursor:pointer">
                            <input class="form-check-input mt-1" type="radio" name="signer" value="<?= e($key) ?>" <?= $values['signer'] === $key ? 'checked' : '' ?>>
                            <span>
                                <b><?= e($label) ?></b>
                                <?php if ($person !== null): ?>
                                    <span class="d-block text-muted" style="font-size:12px">
                                        <?= $person['name'] !== '' ? e($person['name']) . ' &middot; ' . e($person['title']) : 'Name not set yet' ?>
                                    </span>
                                    <span class="d-block" style="font-size:11.5px">
                                        <?= $person['has_image']
                                            ? '<span class="text-success"><i class="fa-solid fa-check me-1"></i>signature image + stamp</span>'
                                            : '<span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>no signature image yet</span>' ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <?= $err('signer') ?>
                    <p class="text-muted mb-0" style="font-size:11.5px">Names, signatures and the stamp are set in <a href="<?= e(admin_url('settings.php')) ?>">Settings</a>.</p>
                </div>
            </div>

            <button type="submit" class="btn btn-navy w-100"><i class="fa-solid fa-floppy-disk me-1"></i>Save letter</button>
            <p class="text-muted mt-2" style="font-size:11.5px">
                The PDF supports paragraphs, headings, bold, italic, underline, alignment, lists and simple tables. Images and colours are not printed.
            </p>
        </div>
    </div>
</form>

<script src="<?= e(cfg('cdn.tinymce_js')) ?>"></script>
<script>
(function () {
    var variables = <?= json_encode(LetterRepo::VARIABLES) ?>;
    var textarea = document.getElementById('body');

    function insert(name) {
        var token = '{' + name + '}';
        var editor = typeof tinymce !== 'undefined' ? tinymce.get('body') : null;
        if (editor) {
            editor.insertContent(token);
            editor.focus();
            return;
        }
        var start = textarea.selectionStart || 0;
        textarea.value = textarea.value.slice(0, start) + token + textarea.value.slice(textarea.selectionEnd || start);
    }

    document.querySelectorAll('.cn-var').forEach(function (button) {
        button.addEventListener('click', function () { insert(button.dataset.var); });
    });

    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#body',
            promotion: false,
            branding: false,
            menubar: 'edit insert format table',
            plugins: 'code lists table autoresize',
            toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | table hr | variables | removeformat | code',
            block_formats: 'Paragraph=p; Heading 3=h3; Heading 4=h4',
            min_height: 420,
            max_height: 900,
            convert_urls: false,
            entity_encoding: 'raw',
            content_style: 'body{font-family:"PT Serif",Georgia,serif;font-size:15px;padding:12px;max-width:760px}',
            setup: function (editor) {
                editor.ui.registry.addMenuButton('variables', {
                    text: 'Insert variable',
                    fetch: function (callback) {
                        callback(Object.keys(variables).map(function (key) {
                            return { type: 'menuitem', text: '{' + key + '}  ' + variables[key], onAction: function () { editor.insertContent('{' + key + '}'); } };
                        }));
                    }
                });
            }
        });
    }

    document.getElementById('letterForm').addEventListener('submit', function () {
        if (typeof tinymce !== 'undefined') { tinymce.triggerSave(); }
    });
})();
</script>
