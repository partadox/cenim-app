<?php
/**
 * @var string $recipient
 * @var string $paperId
 * @var string $reason
 * @var string $loginUrl
 */
?>
<p style="margin:0 0 14px">Dear <?= e($recipient) ?>,</p>

<p style="margin:0 0 14px">
    We reviewed the registration details for paper <b><?= e($paperId) ?></b> and need you to correct something
    before we can issue your invoice.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%"
       style="background:#fdeaea;border:1px solid #f7cfcf;border-radius:8px;margin:0 0 16px">
    <tr>
        <td style="padding:14px 16px;font-size:13px">
            <div style="color:#a3231f;padding-bottom:4px">What needs to be fixed</div>
            <div><?= nl2br(e($reason)) ?></div>
        </td>
    </tr>
</table>

<p style="margin:0 0 14px">
    Please sign in to the author area, update your registration form, and submit it again. Nothing else is needed —
    your paper itself is not affected.
</p>

<p style="margin:0 0 20px">
    <a href="<?= e($loginUrl) ?>"
       style="background:#003981;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block;font-size:13px">
        Correct my registration
    </a>
</p>
