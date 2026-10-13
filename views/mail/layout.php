<?php
/**
 * Layout email. Sengaja memakai tabel + inline style karena klien email
 * (Outlook, Gmail) tidak bisa diandalkan untuk CSS modern.
 *
 * @var string $content
 * @var string $subject
 * @var string $editionName
 * @var array  $signature  baris penutup dari Settings → Email (Mailer::signatureLines)
 * @var string|null $replyTo
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($subject ?? '') ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f6fa;font-family:Arial,Helvetica,sans-serif;color:#1d2433">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fa;padding:24px 12px">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e3e8f0">
                <tr>
                    <td style="background:#003981;padding:18px 24px">
                        <div style="color:#ffffff;font-size:16px;font-weight:bold;letter-spacing:.3px">
                            <?= e($editionName ?? 'CENIM') ?>
                        </div>
                        <div style="color:#febd06;font-size:11px;text-transform:uppercase;letter-spacing:1px;padding-top:2px">
                            Conference Management System
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px 24px;font-size:14px;line-height:1.65">
                        <?= $content ?>

                        <?php if (!empty($signature)): ?>
                            <p style="margin:22px 0 0;font-size:13.5px;line-height:1.55;color:#1d2433">
                                <?php foreach ($signature as $i => $line): ?>
                                    <?= $i === 2 ? '<b>' . e($line) . '</b>' : e($line) ?><br>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="background:#f7f9fc;border-top:1px solid #e3e8f0;padding:16px 24px;font-size:11.5px;color:#6b7893">
                        <?= e(cfg('app_long_name')) ?><br>
                        Institut Teknologi Sepuluh Nopember (ITS), Surabaya, Indonesia<br><br>
                        <?php if (!empty($replyTo)): ?>
                            This message was sent automatically. Replies go to <?= e($replyTo) ?>.
                        <?php else: ?>
                            This message was sent automatically.
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
