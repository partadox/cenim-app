<?php
/**
 * @var string $recipient
 * @var string $paperId
 * @var string $categoryLabel
 * @var string $amount
 * @var string $loginUrl
 */
?>
<p style="margin:0 0 14px">Dear <?= e($recipient) ?>,</p>

<p style="margin:0 0 14px">
    Good news — your registration for paper <b><?= e($paperId) ?></b> has been <b>verified</b> by the committee.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%"
       style="background:#e6f6ed;border:1px solid #cbeada;border-radius:8px;margin:0 0 16px">
    <tr>
        <td style="padding:14px 16px;font-size:13px">
            <div style="color:#12704a">Verified category</div>
            <div style="padding-bottom:8px"><?= e($categoryLabel) ?></div>
            <div style="color:#12704a">Amount payable</div>
            <div style="font-size:16px;font-weight:bold;color:#12704a"><?= e($amount) ?></div>
        </td>
    </tr>
</table>

<p style="margin:0 0 14px">
    Your invoice is being prepared and will be available in the author area. After paying, please return to the
    author area and submit your proof of payment so we can issue your receipt.
</p>

<p style="margin:0 0 20px">
    <a href="<?= e($loginUrl) ?>"
       style="background:#003981;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block;font-size:13px">
        Open the author area
    </a>
</p>
