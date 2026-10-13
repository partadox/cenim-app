<?php
/**
 * Layout panel author.
 *
 * @var string $content
 * @var string $title
 * @var array  $author  Baris tabel authors (opsional, tersedia setelah Fase 1)
 */

use Cenim\Core\Session;

$title = $title ?? 'Author Area';
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
<body class="cn-panel">
<header class="cn-author-header">
    <div class="container d-flex align-items-center gap-3">
        <a class="cn-brand d-flex align-items-center gap-2 text-decoration-none" href="<?= e(author_url('index.php')) ?>">
            <img src="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>" alt="CENIM" style="width:34px;height:34px;object-fit:contain">
            <span>
                <strong class="d-block" style="font-size:15px;line-height:1.2"><?= e(cfg('app_name')) ?> Author Area</strong>
                <span style="font-size:11px;color:var(--cn-gold);letter-spacing:.8px;text-transform:uppercase">
                    <?= e(isset($edition['year']) ? 'Edition ' . $edition['year'] : 'Registration & Payment') ?>
                </span>
            </span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <?php if (!empty($author['paper_id'])): ?>
                <span class="d-none d-md-inline" style="font-size:12.5px">
                    <i class="fa-regular fa-file-lines me-1"></i><?= e($author['paper_id']) ?>
                </span>
            <?php endif; ?>
            <a class="text-white" href="<?= e(base_url()) ?>" title="Conference website">
                <i class="fa-solid fa-globe"></i>
            </a>
            <?php if (Cenim\Core\AuthorAuth::isLoggedIn()): ?>
                <form method="post" action="<?= e(author_url('logout.php')) ?>" class="m-0">
                    <?= Cenim\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-light" title="Sign out">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span class="d-none d-md-inline ms-1">Sign out</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="cn-author-wrap">
    <?php if (function_exists('edition_is_archive') && edition_is_archive()): ?>
        <div class="alert alert-secondary d-flex align-items-start gap-2 py-2 px-3" style="font-size:13px">
            <i class="fa-solid fa-box-archive mt-1"></i>
            <div>
                <b>Archive — <?= e((string) ($edition['name'] ?? 'past edition')) ?></b>.
                This edition has ended: your LoA, invoice, receipt and certificates remain available, but the forms can no longer be changed.
            </div>
        </div>
    <?php endif; ?>
    <?= $content ?>
</div>

<footer class="text-center pb-4" style="font-size:12px;color:var(--cn-muted)">
    <?= e(cfg('app_long_name')) ?><br>
    Institut Teknologi Sepuluh Nopember (ITS), Surabaya, Indonesia
</footer>

<script src="<?= e(cfg('cdn.jquery_js')) ?>"></script>
<script src="<?= e(cfg('cdn.bootstrap_js')) ?>"></script>
<script src="<?= e(cfg('cdn.sweetalert_js')) ?>"></script>
<script src="<?= e(cfg('cdn.toastify_js')) ?>"></script>
<script>
    window.CENIM = {
        csrf: <?= json_encode(Cenim\Core\Csrf::token()) ?>,
        authorUrl: <?= json_encode(author_url()) ?>
    };

    function cnToast(message, type) {
        var colors = { success: '#12704a', error: '#a3231f', warning: '#8a5a06', info: '#003981' };
        Toastify({
            text: message,
            duration: 4500,
            close: true,
            gravity: 'top',
            position: 'right',
            style: { background: colors[type] || colors.info, borderRadius: '8px', fontSize: '13px' }
        }).showToast();
    }

    <?php foreach ($flash as $item): ?>
    cnToast(<?= json_encode($item['message']) ?>, <?= json_encode($item['type']) ?>);
    <?php endforeach; ?>
</script>
<?= $scripts ?? '' ?>
<?= Cenim\Core\View::partial('layout/_upload_guard') ?>
</body>
</html>
