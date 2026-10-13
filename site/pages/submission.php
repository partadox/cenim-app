<?php
/**
 * Halaman submission publik. Judul dan isinya bisa diedit admin
 * (Landing Content → halaman "submission"); nilai bawaan di bawah adalah isi yang
 * tampil di website sebelum ada CMS.
 */

declare(strict_types=1);

$pageTitle = 'Submission — ' . site_name();

site_partial('header');

?>
<!--==============================
Content Area
==============================-->
    <section class="space">
        <div class="container">
            <?= cms_html('page.body', 'submission') ?>
        </div>
    </section>

<?php site_partial('footer'); ?>
