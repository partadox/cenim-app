<?php
/**
 * Layout halaman login (dipakai admin/login.php dan author/login.php — Fase 1).
 * Sengaja dipisah dari layout panel agar halaman login admin dan author
 * benar-benar berbeda file dan berbeda tampilan.
 *
 * @var string $content
 * @var string $title
 */

use Cenim\Core\Session;

$title = $title ?? 'Sign in';
$flash = Session::takeFlash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> &middot; <?= e(cfg('app_name')) ?></title>
    <link rel="icon" type="image/png" href="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.bootstrap_css')) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.fontawesome_css')) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.toastify_css')) ?>">
    <link rel="stylesheet" href="<?= e(app_asset('css/panel.css')) ?>">
    <?= $head ?? '' ?>
</head>
<body class="cn-auth">
<div>
    <div class="cn-auth-card">
        <?= $content ?>
    </div>
    <p class="cn-auth-foot">
        <a href="<?= e(base_url()) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back to conference website</a>
    </p>
</div>

<script src="<?= e(cfg('cdn.bootstrap_js')) ?>"></script>
<script src="<?= e(cfg('cdn.toastify_js')) ?>"></script>
<script>
    function cnToast(message, type) {
        var colors = { success: '#12704a', error: '#a3231f', warning: '#8a5a06', info: '#003981' };
        Toastify({
            text: message,
            duration: 5000,
            close: true,
            gravity: 'top',
            position: 'center',
            style: { background: colors[type] || colors.info, borderRadius: '8px', fontSize: '13px' }
        }).showToast();
    }

    <?php foreach ($flash as $item): ?>
    cnToast(<?= json_encode($item['message']) ?>, <?= json_encode($item['type']) ?>);
    <?php endforeach; ?>
</script>
<?= $scripts ?? '' ?>
</body>
</html>
