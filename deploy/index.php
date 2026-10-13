<?php
/**
 * SUMBER untuk <web root>/index.php — copy file ini ke sana (produksi:
 * public_html/conf/cenim/index.php), berdampingan dengan .htaccess dari
 * deploy/root.htaccess.
 *
 * Satu pintu untuk semua halaman publik. .htaccess meneruskan
 * registration.php, submission.php, workshop.php, dan verify.php ke sini,
 * jadi URL publik (termasuk URL verifikasi yang tertanam di QR sertifikat)
 * tetap sama tanpa file stub terpisah. Isinya sengaja tipis: markup ada di
 * cenim-app/site/, sehingga file ini hampir tidak pernah perlu di-copy ulang.
 */

declare(strict_types=1);

require __DIR__ . '/cenim-app/site/boot.php';

$cenimPage = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));

switch ($cenimPage) {
    case 'registration.php':
    case 'submission.php':
    case 'workshop.php':
        site_page(substr($cenimPage, 0, -4));
        break;

    case 'verify.php':
        require __DIR__ . '/cenim-app/site/verify.php';
        cenim_render_verify();
        break;

    default:
        site_page('home');
}
