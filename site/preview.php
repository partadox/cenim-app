<?php
/**
 * Pratinjau halaman publik dari dalam panel admin, tanpa perlu menyalin stub ke
 * web root lebih dulu:  /cenim-app/site/preview.php?page=home
 */

declare(strict_types=1);

require __DIR__ . '/boot.php';

use Cenim\Core\AdminAuth;
use Cenim\Core\Response;

panel_boot('admin');
AdminAuth::requireLogin();

$page = (string) input('page', 'home');

if (!in_array($page, ['home', 'registration', 'submission', 'workshop'], true)) {
    Response::abort(404, 'Unknown page.');
}

site_page($page);
