<?php
/**
 * Catatan dari panitia di atas form author (Admin → Form Builder → Note at
 * the top of the form). HTML dari editor WYSIWYG admin.
 *
 * @var string $formKey FormRepo::FORM_*
 */

$note = Cenim\Domain\FormRepo::note((int) (Cenim\Domain\EditionRepo::context()['id'] ?? 0), $formKey);
if (trim(strip_tags($note, '<img>')) === '') {
    return;
}
?>
<div class="alert alert-light border mb-3 cn-form-note" style="font-size:13.5px;border-left:4px solid var(--cn-gold) !important">
    <?= $note ?>
</div>
<style>.cn-form-note p:last-child,.cn-form-note ul:last-child,.cn-form-note ol:last-child{margin-bottom:0}.cn-form-note img{max-width:100%;height:auto}</style>
