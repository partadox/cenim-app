<?php
/**
 * Logout admin. Hanya menerima POST + CSRF agar tidak bisa dipaksa
 * lewat tautan/gambar dari situs lain.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AdminAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Response;

panel_boot('admin');

if (!is_post()) {
    Response::redirect(admin_url('index.php'));
}

Csrf::verify();
AdminAuth::logout();

Response::redirect(admin_url('login.php?signed_out=1'));
