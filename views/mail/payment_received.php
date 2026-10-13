<?php
/**
 * @var string $recipient
 * @var string $paperId
 * @var string $invoiceNumber
 * @var string $amount
 * @var string $loginUrl
 */
?>
<p style="margin:0 0 14px">Dear <?= e($recipient) ?>,</p>

<p style="margin:0 0 14px">
    We have received your proof of payment for invoice <b><?= e($invoiceNumber) ?></b> (paper
    <?= e($paperId) ?>). The committee will verify it against our bank statement.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%"
       style="background:#f7f9fc;border:1px solid #e3e8f0;border-radius:8px;margin:0 0 16px">
    <tr>
        <td style="padding:14px 16px;font-size:13px">
            <div style="color:#6b7893">Amount you reported</div>
            <div style="font-size:16px;font-weight:bold;color:#003981"><?= e($amount) ?></div>
        </td>
    </tr>
</table>

<p style="margin:0 0 14px">
    You will receive your official receipt once the payment is confirmed. If anything is unclear, we will email you
    with what needs fixing.
</p>

<p style="margin:0 0 20px">
    <a href="<?= e($loginUrl) ?>"
       style="background:#003981;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block;font-size:13px">
        Open the author area
    </a>
</p>
