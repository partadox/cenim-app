<?php
/**
 * Logout author. Hanya menerima POST + CSRF.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AuthorAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Response;

panel_boot('author');

if (!is_post()) {
    Response::redirect(author_url('index.php'));
}

Csrf::verify();
AuthorAuth::logout();

Response::redirect(author_url('login.php?signed_out=1'));
