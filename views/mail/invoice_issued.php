<?php
/**
 * @var string $recipient
 * @var string $paperId
 * @var string $number
 * @var string $amount
 * @var string $dueDate
 * @var string $loginUrl
 */
?>
<p style="margin:0 0 14px">Dear <?= e($recipient) ?>,</p>

<p style="margin:0 0 14px">
    Your invoice for paper <b><?= e($paperId) ?></b> has been issued. You can download the PDF from the author area.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%"
       style="background:#f7f9fc;border:1px solid #e3e8f0;border-radius:8px;margin:0 0 16px">
    <tr>
        <td style="padding:14px 16px;font-size:13px">
            <div style="color:#6b7893">Invoice number</div>
            <div style="padding-bottom:8px"><b><?= e($number) ?></b></div>
            <div style="color:#6b7893">Amount due</div>
            <div style="font-size:16px;font-weight:bold;color:#003981;padding-bottom:8px"><?= e($amount) ?></div>
            <div style="color:#6b7893">Payment due date</div>
            <div><?= e($dueDate) ?></div>
        </td>
    </tr>
</table>

<p style="margin:0 0 14px">
    Please transfer the full amount and mention the invoice number in the transfer description. Bank details are
    printed on the invoice. After paying, sign in again and submit your proof of payment so we can confirm it and
    issue your official receipt.
</p>

<p style="margin:0 0 20px">
    <a href="<?= e($loginUrl) ?>"
       style="background:#003981;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block;font-size:13px">
        Download my invoice
    </a>
</p>
