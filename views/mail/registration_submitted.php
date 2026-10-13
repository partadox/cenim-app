<?php
/**
 * @var string $recipient
 * @var string $paperId
 * @var string $paperTitle
 * @var string $categoryLabel
 * @var string $amount
 * @var string $loginUrl
 */
?>
<p style="margin:0 0 14px">Dear <?= e($recipient) ?>,</p>

<p style="margin:0 0 14px">
    Thank you. Your registration details for paper <b><?= e($paperId) ?></b> have been submitted and are now
    waiting for verification by the committee.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%"
       style="background:#f7f9fc;border:1px solid #e3e8f0;border-radius:8px;margin:0 0 16px">
    <tr>
        <td style="padding:14px 16px;font-size:13px">
            <div style="color:#6b7893">Paper</div>
            <div style="padding-bottom:8px"><?= e($paperTitle !== '' ? $paperTitle : '(title not recorded)') ?></div>
            <div style="color:#6b7893">Registration category</div>
            <div style="padding-bottom:8px"><?= e($categoryLabel) ?></div>
            <div style="color:#6b7893">Amount to be invoiced</div>
            <div style="font-size:16px;font-weight:bold;color:#003981"><?= e($amount) ?></div>
        </td>
    </tr>
</table>

<p style="margin:0 0 14px">
    We will review your submission — including the proof of student status or IEEE membership if you uploaded any —
    and issue your invoice. You will receive another email once that is done.
</p>

<p style="margin:0 0 20px">
    <a href="<?= e($loginUrl) ?>"
       style="background:#003981;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block;font-size:13px">
        Open the author area
    </a>
</p>

<p style="margin:0;color:#6b7893;font-size:12.5px">
    Sign in with your email; a 6-digit sign-in code will be sent to it.
</p>
