<?php
/**
 * Layout panel admin.
 *
 * @var string $content  HTML halaman
 * @var string $title    Judul halaman (topbar + <title>)
 * @var string $subtitle Keterangan di bawah judul (opsional)
 * @var string $active   Key menu aktif (opsional)
 * @var string $scripts  HTML tambahan sebelum </body> (opsional)
 * @var array  $edition  Baris tabel editions (opsional)
 */

use Cenim\Core\Session;

$title    = $title    ?? 'Admin';
$subtitle = $subtitle ?? '';
$active   = $active   ?? '';
$flash    = Session::takeFlash();

// Menu: 'soon' = fase berikutnya, dirender non-aktif agar peta fitur tetap terlihat.
$menu = [
    'Overview' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'url' => admin_url('index.php')],
    ],
    'Participants' => [
        ['key' => 'authors',       'label' => 'Authors',       'icon' => 'fa-users',          'url' => admin_url('authors.php')],
        ['key' => 'confirmations', 'label' => 'Confirmations', 'icon' => 'fa-clipboard-check', 'url' => admin_url('confirmations.php')],
        ['key' => 'registrations', 'label' => 'Registrations', 'icon' => 'fa-clipboard-list', 'url' => admin_url('registrations.php')],
        ['key' => 'payments',      'label' => 'Payments',      'icon' => 'fa-money-check-dollar', 'url' => admin_url('payments.php')],
        ['key' => 'attendance',    'label' => 'Presentations', 'icon' => 'fa-person-chalkboard',     'url' => admin_url('attendance.php')],
    ],
    'Documents' => [
        ['key' => 'invoices',     'label' => 'Invoices',     'icon' => 'fa-file-invoice',        'url' => admin_url('invoices.php')],
        ['key' => 'receipts',     'label' => 'Receipts',     'icon' => 'fa-receipt',             'url' => admin_url('receipts.php')],
        ['key' => 'certificates', 'label' => 'Certificates', 'icon' => 'fa-certificate',         'url' => admin_url('certificates.php')],
    ],
    'Website' => [
        ['key' => 'content',   'label' => 'Landing Content', 'icon' => 'fa-newspaper',    'url' => admin_url('content.php')],
        ['key' => 'timeline',  'label' => 'Timeline',        'icon' => 'fa-timeline',     'url' => admin_url('timeline.php')],
        ['key' => 'speakers',  'label' => 'Speakers',        'icon' => 'fa-microphone',   'url' => admin_url('speakers.php')],
        ['key' => 'committee', 'label' => 'Committee',       'icon' => 'fa-user-tie',     'url' => admin_url('committee.php')],
        ['key' => 'previous',  'label' => 'Previous Conferences', 'icon' => 'fa-clock-rotate-left', 'url' => admin_url('previous.php')],
        ['key' => 'sponsors',  'label' => 'Sponsors',        'icon' => 'fa-handshake',    'url' => admin_url('sponsors.php')],
        ['key' => 'gallery',   'label' => 'Poster & Gallery', 'icon' => 'fa-images',      'url' => admin_url('gallery.php')],
        ['key' => 'fees',      'label' => 'Fees & Packages', 'icon' => 'fa-tags',         'url' => admin_url('fees.php')],
        ['key' => 'forms',     'label' => 'Form Builder',    'icon' => 'fa-square-check', 'url' => admin_url('forms.php')],
    ],
    'Tools' => [
        ['key' => 'logo', 'label' => 'Logo',              'icon' => 'fa-shapes',        'url' => admin_url('logo.php')],
        ['key' => 'kop',  'label' => 'Letterhead (Kop)',  'icon' => 'fa-file-lines',    'url' => admin_url('kop.php')],
        ['key' => 'data', 'label' => 'Import / Export', 'icon' => 'fa-file-csv',     'url' => admin_url('authors-import.php')],
    ],
    'System' => [
        ['key' => 'editions', 'label' => 'Editions', 'icon' => 'fa-layer-group',    'url' => admin_url('editions.php')],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fa-sliders',        'url' => admin_url('settings.php')],
        ['key' => 'logs',     'label' => 'Logs',     'icon' => 'fa-list-check',     'url' => admin_url('logs.php')],
        ['key' => 'health',   'label' => 'Health',   'icon' => 'fa-heart-pulse',    'url' => admin_url('health.php')],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> &middot; <?= e(cfg('app_name')) ?> Admin</title>
    <link rel="icon" type="image/png" href="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.bootstrap_css')) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.fontawesome_css')) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.toastify_css')) ?>">
    <link rel="stylesheet" href="<?= e(app_asset('css/panel.css')) ?>">
    <?php if (in_array('datatables', $assets ?? [], true)): ?>
        <link rel="stylesheet" href="<?= e(cfg('cdn.datatables_css')) ?>">
    <?php endif; ?>
    <?= $head ?? '' ?>
</head>
<body class="cn-panel">
<div class="cn-shell">
    <aside class="cn-sidebar" id="cnSidebar">
        <a class="cn-brand" href="<?= e(admin_url('index.php')) ?>">
            <img src="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>" alt="CENIM">
            <span class="d-block">
                <strong><?= e(cfg('app_name')) ?> Admin</strong>
                <span><?= e(isset($edition['year']) ? 'Edition ' . $edition['year'] : 'Management Panel') ?></span>
            </span>
        </a>
        <nav class="cn-nav">
            <?php foreach ($menu as $group => $items): ?>
                <div class="cn-nav-label"><?= e($group) ?></div>
                <?php foreach ($items as $item): ?>
                    <?php $soon = !empty($item['soon']); ?>
                    <a href="<?= e($soon ? '#' : (string) $item['url']) ?>"
                       class="<?= $soon ? 'disabled' : '' ?> <?= $active === $item['key'] ? 'active' : '' ?>"
                       <?= $soon ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                        <i class="fa-solid <?= e($item['icon']) ?>"></i><?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="cn-main">
        <header class="cn-topbar">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" id="cnNavToggle" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <h1><?= e($title) ?></h1>
                <?php if ($subtitle !== ''): ?><p class="cn-sub"><?= e($subtitle) ?></p><?php endif; ?>
            </div>
            <div class="cn-topbar-right">
                <?php $cnEditions = Cenim\Domain\EditionRepo::all(); ?>
                <?php if (count($cnEditions) > 1 && Cenim\Core\AdminAuth::isLoggedIn()): ?>
                    <form method="post" action="<?= e(admin_url('index.php')) ?>" class="m-0" title="Open another edition (archive)">
                        <?= Cenim\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="view_edition">
                        <select name="edition_view" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto">
                            <?php foreach ($cnEditions as $cnE): ?>
                                <option value="<?= (int) $cnE['id'] ?>" <?= (int) $cnE['id'] === Cenim\Domain\EditionRepo::contextId() ? 'selected' : '' ?>>
                                    <?= e((string) $cnE['name']) ?><?= (int) $cnE['is_active'] === 1 ? ' (active)' : ' (archive)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
                <?php if (is_local()): ?>
                    <span class="cn-badge cn-badge-unpaid"><i class="fa-solid fa-flask me-1"></i>local</span>
                <?php endif; ?>
                <span class="text-muted d-none d-md-inline"><i class="fa-regular fa-clock me-1"></i><?= e(fmt_dt(now(), 'd M Y H:i')) ?></span>
                <?php if (Cenim\Core\AdminAuth::isLoggedIn()): ?>
                    <span class="text-muted d-none d-lg-inline" style="font-size:12px" title="Signed in at <?= e(fmt_dt(Cenim\Core\AdminAuth::loginAt())) ?>">
                        <i class="fa-solid fa-user-shield me-1"></i>Administrator
                    </span>
                    <form method="post" action="<?= e(admin_url('logout.php')) ?>" class="m-0">
                        <?= Cenim\Core\Csrf::field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sign out">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            <span class="d-none d-md-inline ms-1">Sign out</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </header>

        <main class="cn-content">
            <?php if (Cenim\Domain\EditionRepo::isArchive()): ?>
                <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 px-3" style="font-size:13px">
                    <i class="fa-solid fa-box-archive"></i>
                    <span>You are viewing the archive of <b><?= e((string) (Cenim\Domain\EditionRepo::context()['name'] ?? '')) ?></b>.
                        Everything on these pages — and any change you make — belongs to that edition.</span>
                    <form method="post" action="<?= e(admin_url('index.php')) ?>" class="ms-auto m-0">
                        <?= Cenim\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="view_edition">
                        <input type="hidden" name="edition_view" value="0">
                        <button class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-arrow-rotate-left me-1"></i>Back to <?= e((string) (Cenim\Domain\EditionRepo::active()['name'] ?? 'the active edition')) ?></button>
                    </form>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>

<script src="<?= e(cfg('cdn.jquery_js')) ?>"></script>
<script src="<?= e(cfg('cdn.bootstrap_js')) ?>"></script>
<script src="<?= e(cfg('cdn.sweetalert_js')) ?>"></script>
<script src="<?= e(cfg('cdn.toastify_js')) ?>"></script>
<?php if (in_array('datatables', $assets ?? [], true)): ?>
    <script src="<?= e(cfg('cdn.datatables_js')) ?>"></script>
    <script src="<?= e(cfg('cdn.datatables_bs5')) ?>"></script>
<?php endif; ?>
<script>
    window.CENIM = {
        csrf: <?= json_encode(Cenim\Core\Csrf::token()) ?>,
        adminUrl: <?= json_encode(admin_url()) ?>,
        apiUrl: <?= json_encode(app_url('api/')) ?>
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

    document.getElementById('cnNavToggle')?.addEventListener('click', function () {
        document.getElementById('cnSidebar').classList.toggle('is-open');
    });

    <?php foreach ($flash as $item): ?>
    cnToast(<?= json_encode($item['message']) ?>, <?= json_encode($item['type']) ?>);
    <?php endforeach; ?>
</script>
<?= $scripts ?? '' ?>
<?= Cenim\Core\View::partial('layout/_upload_guard') ?>
</body>
</html>
