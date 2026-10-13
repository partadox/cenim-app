<?php
/**
 * @var string $recipient
 * @var string $paperId
 * @var string $paperTitle
 * @var string $mode
 * @var string $presenter
 * @var string $videoUrl
 * @var string $loginUrl
 */
?>
<p style="margin:0 0 14px">Dear <?= e($recipient) ?>,</p>

<p style="margin:0 0 14px">
    Thank you. The presentation details for paper <b><?= e($paperId) ?></b> have been recorded.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" width="100%"
       style="background:#f7f9fc;border:1px solid #e3e8f0;border-radius:8px;margin:0 0 16px">
    <tr>
        <td style="padding:14px 16px;font-size:13px">
            <div style="color:#6b7893">Paper</div>
            <div style="padding-bottom:8px"><?= e($paperTitle !== '' ? $paperTitle : '(title not recorded)') ?></div>
            <div style="color:#6b7893">Presentation</div>
            <div style="padding-bottom:8px"><?= e($mode) ?></div>
            <div style="color:#6b7893">Presenter</div>
            <div style="padding-bottom:<?= $videoUrl !== '' ? '8' : '0' ?>px"><?= e($presenter) ?></div>
            <?php if ($videoUrl !== ''): ?>
                <div style="color:#6b7893">Presentation video</div>
                <div><a href="<?= e($videoUrl) ?>" style="color:#003981"><?= e($videoUrl) ?></a></div>
            <?php endif; ?>
        </td>
    </tr>
</table>

<?php if ($videoUrl !== ''): ?>
    <p style="margin:0 0 14px">
        Please keep the video link accessible (Google Drive: "Anyone with the link"; YouTube: Unlisted) until the conference is over.
    </p>
<?php endif; ?>

<p style="margin:0 0 20px">
    <a href="<?= e($loginUrl) ?>"
       style="background:#003981;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:6px;display:inline-block;font-size:13px">
        Review or change the video link
    </a>
</p>
