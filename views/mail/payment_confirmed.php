<?php
/**
 * @var string $recipient
 * @var string $paperId
 * @var string $invoiceNumber
 * @var string $receiptNumber
 * @var string $amount
 * @var string $loginUrl
 */
?>
<p style="margin:0 0 14px">Dear <?= e($recipient) ?>,</p>

<p style="margin:0 0 14px">
    Your payment for paper <b><?= e($paperId) ?></b> has been <b>confirmed</b>. Thank you — your registration is now
    complete.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%"
       style="background:#e6f6ed;border:1px solid #cbeada;border-radius:8px;margin:0 0 16px">
    <tr>
        <td style="padding:14px 16px;font-size:13px">
            <div style="color:#12704a">Receipt number</div>
            <div style="padding-bottom:8px"><b><?= e($receiptNumber) ?></b></div>
            <div style="color:#12704a">Amount received</div>
            <div style="font-size:16px;font-weight:bold;color:#12704a;padding-bottom:8px"><?= e($amount) ?></div>
            <div style="color:#12704a">Against invoice</div>
            <div><?= e($invoiceNumber) ?></div>
        </td>
    </tr>
</table>

<p style="margin:0 0 14px">
    Your official receipt is available as a PDF in the author area. Certificates will be issued after the conference.
</p>

<p style="margin:0 0 20px">
    <a href="<?= e($loginUrl) ?>"
       style="background:#003981;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block;font-size:13px">
        Download my receipt
    </a>
</p>
