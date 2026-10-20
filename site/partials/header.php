<?php $guideUrl = user_guide_url(site_edition_id()); ?>
<!doctype html>
<html class="no-js" lang="zxx" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title><?= e($pageTitle ?? (site_edition()['name'] ?? 'CENIM')) ?></title>
    <meta name="author" content="Donat">
    <meta name="description" content="<?= e(site_edition()['name'] ?? 'CENIM') ?> - Conference Website">
    <meta name="keywords" content="<?= e(site_edition()['name'] ?? 'CENIM') ?>, Conference, Event, Academic, Research, Technology, Innovation">
    <meta name="robots" content="INDEX,FOLLOW">

    <!-- Mobile Specific Metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Favicons - Place favicon.ico in the root directory -->
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">

    <!--==============================
	  Google Fonts
	============================== -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400..700&family=Nunito+Sans:ital,opsz,wght@0,6..12,200..1000;1,6..12,200..1000&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">

    <!--==============================
	    All CSS File
	============================== -->
    <!-- Bootstrap -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <!-- Fontawesome Icon -->
    <link rel="stylesheet" href="assets/css/fontawesome.min.css">
    <!-- Magnific Popup -->
    <link rel="stylesheet" href="assets/css/magnific-popup.min.css">
    <!-- Swiper Js -->
    <link rel="stylesheet" href="assets/css/swiper-bundle.min.css">
    <!-- Theme Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<!--==============================
    Preloader
==============================-->
    <div class="preloader ">
        <button class="th-btn style2 preloaderCls">Cancel Preloader </button>
        <div class="preloader-inner">
            <span class="loader">
                <?= e(site_edition()['name'] ?? 'CENIM') ?>
                <span class="loading-text"><?= e(site_edition()['name'] ?? 'CENIM') ?></span>
            </span>
        </div>
    </div>
<!--==============================
    Mobile Menu
============================== -->
    <div class="th-menu-wrapper">
        <div class="th-menu-area text-center">
            <button class="th-menu-toggle"><i class="fal fa-times"></i></button>
            <div class="th-mobile-menu">
                <ul>
                    <li>
                        <a href="<?= e(base_url()) ?>">Home</a>
                    </li>
                    <li>
                        <a href="#about-sec">About Us</a>
                    </li>
                    <li>
                        <a href="#schedule-sec">Schedules</a>
                    </li>
                    <li>
                        <a href="#keynote-sec">Keynote Speakers</a>
                    </li>
                    <li>
                        <a href="#comittee-sec">Comittee</a>
                    </li>
                    <li>
                        <a href="#vanue-sec">Vanue</a>
                    </li>
                    <li>
                        <a href="<?= e(base_url('workshop.php')) ?>"> Workshop</a>
                    </li>
                    <li>
                        <a href="<?= e(base_url('registration.php')) ?>">Registration</a>
                    </li>
                    <li>
                        <a href="<?= e(base_url('submission.php')) ?>">Submission</a>
                    </li>
                    <li>
                        <a href="<?= e(author_url()) ?>">Portal</a>
                    </li>
                    <?php if ($guideUrl !== null): ?>
                        <li>
                            <a href="<?= e($guideUrl) ?>" target="_blank" rel="noopener">Portal User Guide (PDF)</a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?= e(base_url('verify.php')) ?>">Certificate Verification</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
<!--==============================
	Header Area
==============================-->
    <header class="th-header header-layout3">
        <div class="sticky-wrapper">
            <!-- Main Menu Area -->
            <div class="container">
                <div class="menu-area">
                    <div class="header-logo">
                        <a href="<?= e(base_url()) ?>"><img src="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>" width="100px" alt="<?= e(site_edition()['name'] ?? 'CENIM') ?>"></a>
                    </div>
                    <div class="menu-area-wrap">
                        <nav class="main-menu d-none d-lg-block">
                            <ul>
                                <li>
                                    <a href="<?= e(base_url()) ?>">Home</a>
                                </li>
                                <li>
                                    <a href="#about-sec">About Us</a>
                                </li>
                                <li>
                                    <a href="#schedule-sec">Schedules</a>
                                </li>
                                <li>
                                    <a href="#keynote-sec">Keynote Speakers</a>
                                </li>
                                <li>
                                    <a href="#comittee-sec">Comittee</a>
                                </li>
                                <li>
                                    <a href="#vanue-sec">Vanue</a>
                                </li>
                                <li>
                                    <a href="<?= e(base_url('workshop.php')) ?>"><i class="fas fa-arrow-up-right"></i> Workshop</a>
                                </li>
                                <li>
                                    <a href="<?= e(base_url('registration.php')) ?>"><i class="fas fa-arrow-up-right"></i> Registration</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <div class="header-button">
                        <a href="<?= e(base_url('submission.php')) ?>" class="th-btn style3 d-xl-block d-none"><i class="fas fa-arrow-right me-2"></i> Submission</a>
                        <div class="cn-portal d-none d-xl-block">
                            <button type="button" class="th-btn cn-portal-toggle" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-circle-user me-2"></i>Portal<i class="fas fa-chevron-down cn-caret"></i>
                            </button>
                            <ul class="cn-portal-menu" role="menu">
                                <li><a href="<?= e(author_url()) ?>" role="menuitem"><i class="fas fa-right-to-bracket"></i><span>Sign in to the portal<small>Registration, payment, LoA &amp; certificates</small></span></a></li>
                                <?php if ($guideUrl !== null): ?>
                                    <li><a href="<?= e($guideUrl) ?>" target="_blank" rel="noopener" role="menuitem"><i class="fas fa-book-open"></i><span>Portal user guide<small>How to use the conference management system (PDF)</small></span></a></li>
                                <?php endif; ?>
                                <li><a href="<?= e(base_url('verify.php')) ?>" role="menuitem"><i class="fas fa-certificate"></i><span>Certificate verification<small>Check a certificate number</small></span></a></li>
                            </ul>
                        </div>
                        <button type="button" class="icon-btn th-menu-toggle d-lg-none"><i class="far fa-bars"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <style>
        .header-button { display: flex; align-items: center; gap: 10px; }
        /* Dua tombol (Submission + Portal) di kanan: rapatkan supaya menu tetap satu baris. */
        .header-layout3 .menu-area .header-button .th-btn { padding: 22.5px 24px; }
        .header-layout3 .menu-area .menu-area-wrap { padding: 0 12px 0 20px; }
        .header-layout3 .main-menu > ul { white-space: nowrap; }
        @media (max-width: 1500px) {
            .header-layout3 .main-menu > ul > li { margin: 0 6px; }
            .header-layout3 .main-menu > ul > li > a { font-size: 15px; }
            .header-layout3 .menu-area .header-button .th-btn { padding: 21px 18px; }
        }
        @media (max-width: 1399px) {
            .header-layout3 .header-logo img { width: 80px; }
            .header-layout3 .main-menu > ul > li { margin: 0 4px; }
            .header-layout3 .main-menu > ul > li > a { font-size: 14px; }
            .header-layout3 .menu-area .header-button .th-btn { padding: 19px 14px; font-size: 13px; }
            .header-layout3 .menu-area-wrap { margin-left: 12px; }
            .cn-portal .cn-portal-toggle .fa-circle-user { display: none; }
        }
        /* Hero: tema memberi padding kiri 80px + sudut bulat kiri hanya di layar 1501–1921px,
           sehingga hero tampak bergeser ke kanan. Samakan dengan ukuran lain: penuh selebar layar. */
        .hero-4 { padding-left: 0; }
        .hero-slider4 { border-radius: 0; }
        /* Kartu Conference Topics & Organizer: kolom judul lebar tetap, judul panjang
           turun baris sendiri (tema membuatnya selebar teks sehingga kartu tidak sejajar). */
        @media (min-width: 1200px) {
            .price-card2 .price-card-title-wrap { flex: 0 0 380px; width: 380px; }
            .price-card2 .price-card_title { overflow-wrap: break-word; }
        }
        /* Tombol prev/next Previous Conference: selalu terlihat (tema menyembunyikan
           .slider-arrow sampai di-hover, dan sama sekali di layar <= 991px). */
        .cn-slider-nav .slider-arrow { opacity: 1 !important; visibility: visible !important; transform: none !important;
            display: inline-block !important; margin: 0 !important; --icon-size: 52px; }
        .cn-slider-nav .slider-arrow:disabled, .cn-slider-nav .slider-arrow.swiper-button-disabled { opacity: .35 !important; cursor: default; }
        .cn-portal { position: relative; }
        .cn-portal .cn-portal-toggle { padding-left: 24px; padding-right: 22px; white-space: nowrap; }
        .cn-portal .cn-caret { margin-left: 10px; font-size: .8em; transition: transform .2s; }
        .cn-portal.is-open .cn-caret { transform: rotate(180deg); }
        .cn-portal-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); min-width: 310px; margin: 0; padding: 6px;
            list-style: none; background: #fff; border-radius: 10px; box-shadow: 0 12px 32px rgba(0, 20, 60, .2); z-index: 1000; }
        .cn-portal.is-open .cn-portal-menu { display: block; }
        .cn-portal-menu a { display: flex; gap: 12px; align-items: flex-start; padding: 10px 12px; border-radius: 8px;
            color: #0b1e3f; font-weight: 600; font-size: 15px; line-height: 1.3; }
        .cn-portal-menu a:hover { background: #f1f5fb; color: #003981; }
        .cn-portal-menu i { width: 18px; margin-top: 2px; color: #003981; text-align: center; }
        .cn-portal-menu small { display: block; font-weight: 400; font-size: 12.5px; color: #6b7893; margin-top: 2px; }
    </style>
    <script>
    (function () {
        var box = document.querySelector('.cn-portal');
        if (!box) { return; }
        var toggle = box.querySelector('.cn-portal-toggle');
        function set(open) { box.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
        toggle.addEventListener('click', function (e) { e.stopPropagation(); set(!box.classList.contains('is-open')); });
        document.addEventListener('click', function (e) { if (!box.contains(e.target)) { set(false); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { set(false); } });
    })();
    </script>
