<?php
/**
 * Landing page CENIM (halaman "home") — markup tema, isi dari CMS.
 *
 * Dirender oleh /index.php di web root (sumbernya cenim-app/deploy/index.php)
 * dan oleh cenim-app/site/preview.php untuk pratinjau admin.
 *
 * Nilai bawaan setiap blok ada di src/Domain/ContentDefaults.php (isinya = teks
 * landing sebelum ada CMS), jadi halaman tidak pernah kosong walau belum diedit.
 */

declare(strict_types=1);

$pageTitle = site_name() . ' — ' . cfg('app_long_name');

site_partial('header');
?>
<!--==============================
Hero Area
==============================-->
<?php
/**
 * Hero — daftar slide bisa diedit admin (Landing Content → hero.slides).
 */
$heroSlides = cms_list('hero.slides');

$heroTitle  = cms_text('hero.title');
$heroText   = cms_text('hero.text');
$heroButton = cms_text('hero.button_label');
$heroLink   = cms_text('hero.button_url');
?>
    <div class="th-hero-wrapper hero-4" id="hero">
        <div class="swiper th-slider hero-slider4" id="heroSlider4" data-slider-options='{"effect":"fade","autoHeight": "true"}'>
            <div class="swiper-wrapper">
                <?php foreach ($heroSlides as $slide): ?>
                <div class="swiper-slide">
                    <div class="hero-inner" data-bg-src="<?= e(asset((string) ($slide['bg'] ?? 'image/header-1.jpg'))) ?>">
                        <div class="hero-bg-shape4-1">
                            <img src="<?= e(asset('img/hero/hero-bg-shape4-1.png')) ?>" alt="img">
                        </div>
                        <div class="container">
                            <div class="row">
                                <div class="col-xl-9">
                                    <div class="hero-style4">
                                        <span class="sub-title after-none" data-ani="slideinup" data-ani-delay="0.2s"><?= e(site_name()) ?></span>
                                        <h3 class="hero-title text-white">
                                            <span class="title1" data-ani="slideinup" data-ani-delay="0.4s"><?= $heroTitle ?></span>
                                        </h3>
                                        <p class="hero-text text-white" data-ani="slideinup" data-ani-delay="0.6s"><?= $heroText ?></p>
                                        <div class="btn-wrap" data-ani="slideinup" data-ani-delay="0.7s">
                                            <a href="<?= $heroLink ?>" class="th-btn"><?= $heroButton ?><i class="fa-solid fa-arrow-up-right ms-2"></i></a>
                                            <?php if (($slide['video'] ?? '') !== ''): ?>
                                                <a href="<?= e((string) $slide['video']) ?>" class="play-btn style3 popup-video"><i class="fas fa-play"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hero-thumb-tab" data-slider-tab=".hero-slider4">
            <?php foreach ($heroSlides as $index => $slide): ?>
                <div class="tab-btn <?= $index === 1 ? 'active' : '' ?>">
                    <img src="<?= e(asset((string) ($slide['bg'] ?? 'image/header-1.jpg'))) ?>" alt="thumb">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<!--======== / Hero Section ========-->

<!--==============================
Important Information Area  
==============================-->
<?php
$infoCards = cms_list('info.cards');
?>
    <div class="bg-smoke2 pt-80 pb-80">
        <div class="container">
            <div class="counter-wrap">
                <?php foreach ($infoCards as $card): ?>
                    <div class="counter-card">
                        <div class="media-body">
                            <h2 class="box-number <?= e((string) ($card['theme'] ?? 'text-theme')) ?>"><?= e((string) $card['value']) ?></h2>
                            <p class="box-text"><?= e((string) $card['label']) ?></p>
                        </div>
                    </div>
                    <div class="divider"></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<!--==============================
About Area  
==============================-->
    <div class="story-area-1 overflow-hidden space overflow-hidden bg-smoke2" id="about-sec">
        <div class="container">
            <div class="row gy-40 justify-content-between flex-row-reverse align-items-center">
                <div class="col-xl-7">
                    <div class="story-img-box1">
                        <div class="box-wrap d-inline-block">
                            <div class="img1">
                                <img src="<?= e(asset(cms_html('about.image'))) ?>" alt="img" style="width: 100%; max-width: 100%; height: auto; display: block;">
                            </div>
                            <div class="story-shape1-1 jump-reverse">
                                <img src="<?= e(asset('img/shape/story_shape1_1.png')) ?>" alt="img">
                            </div>
                            <div class="year-counter">
                                <p class="year-counter_text">Conference <span>Edition</span></p>
                                <?php $cnEdition = trim(html_entity_decode(cms_text('about.edition_number'))); ?>
                                <style>
                                    .story-img-box1 .year-counter .year-counter_number { white-space: nowrap; }
                                    .year-counter_number .cn-ordinal { font-size: .4em; margin-left: 2px; vertical-align: top; position: relative; top: .3em; }
                                </style>
                                <div class="year-counter_number"><span class="counter-number"><?= e($cnEdition) ?></span><?php if (ctype_digit($cnEdition)): ?><span class="cn-ordinal"><?= e(ordinal_suffix((int) $cnEdition)) ?></span><?php endif; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="story-wrap1">
                        <div class="title-area mb-0">
                            <span class="sub-title"><?= e(site_name()) ?></span>
                            <h4 class="sec-title"><?= cms_text('about.title') ?></h4>
                            <p class="mt-30">
                                <?= cms_html('about.body') ?>
                            </p>
                            <?php
                            $cnPoster = Cenim\Domain\GalleryRepo::posterUrl(site_edition_id());
                            $cnPhotos = Cenim\Domain\GalleryRepo::photos(site_edition_id(), true);
                            ?>
                            <?php if ($cnPoster !== null || $cnPhotos !== []): ?>
                            <div class="btn-wrap mt-35">
                                <?php if ($cnPoster !== null): ?>
                                    <a href="#" class="th-btn style-border" data-bs-toggle="modal" data-bs-target="#cfpPosterModal"><?= cms_text('about.poster_label') ?> <i class="fas fa-arrow-up-right ms-2"></i></a>
                                <?php endif; ?>
                                <?php if ($cnPhotos !== []): ?>
                                    <a href="#" class="th-btn style3" data-bs-toggle="modal" data-bs-target="#memoriesModal"><i class="fas fa-images me-2"></i><?= cms_text('about.gallery_label') ?></a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
<!--==============================
Our Conference Area  
==============================-->
    <div class="position-relative space overflow-hidden">
        <div class="cta4-bg-thumb">
            <img src="<?= e(asset(cms_html('highlight.image'))) ?>" alt="img">
        </div>

        <div class="cta-bg-shape4-1 shape-mockup jump" data-top="-22%" data-left="2%"><img src="<?= e(asset('img/shape/cta_shape2_1.png')) ?>" alt="img"></div>
        <div class="cta-bg-shape4-2 shape-mockup jump-reverse" data-top="-12%" data-right="-5%"><img src="<?= e(asset('img/shape/cta_shape2_2.png')) ?>" alt="img"></div>
        <div class="cta-bg-shape4-3 shape-mockup jump-reverse" data-bottom="0" data-left="0"><img src="<?= e(asset('img/shape/cta_shape4_1.png')) ?>" alt="img"></div>
        <div class="container">
            <div class="row">
                <div class="col-xl-12">
                    <div class="cta-wrap4">
                        <div class="cta-title-wrap">
                            <div class="row justify-content-between">
                                <div class="col-xxl-6 col-xl-7 col-lg-6 col-md-8">
                                    <h2 class="sec-title text-white"><?= cms_text('highlight.title') ?></h2>
                                </div>
                                <div class="col-md-auto">
                                    <div class="cta-play-btn">
                                        <a href="<?= cms_text('highlight.video') ?>" class="play-btn style8 popup-video"><i class="fa-sharp fa-solid fa-play"></i></a>
                                        <span class="title">Watch Video</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="cta-content-wrap">
                            <h5 class="title"><?= cms_html('highlight.body') ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!--==============================
Topic Area  
==============================-->
<?php
$topicGroups = cms_list('topics.groups');
?>
    <section class="space overflow-hidden">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-7">
                    <div class="title-area text-center">
                        <span class="sub-title"><?= e(site_name()) ?></span>
                        <h2 class="sec-title"><?= cms_text('topics.title') ?></h2>
                    </div>
                </div>
            </div>
            <div class="row gy-30 justify-content-center">
                <?php foreach ($topicGroups as $group): ?>
                    <div class="col-xl-12 col-lg-4 col-md-6">
                        <div class="price-card2">
                            <div class="price-card-title-wrap">
                                <h3 class="price-card_title">
                                    <?= e((string) $group['title']) ?> </h3>
                            </div>
                            <div class="price-card_content">
                                <div class="checklist">
                                    <ul>
                                        <?php foreach ((array) ($group['items'] ?? []) as $item): ?>
                                            <li><i class="fas fa-circle-check"></i> <?= e((string) $item) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

<!--==============================
Timeline Area  
==============================-->
<?php
/**
 * Jadwal. Tanggal yang sudah diperpanjang tampil ber-strikethrough — riwayatnya
 * disimpan di timeline_items.history_json dan tidak pernah dihapus.
 */
$timeline = site_timeline();
$half     = (int) ceil(count($timeline) / 2);
$rowOne   = array_slice($timeline, 0, $half);
$rowTwo   = array_slice($timeline, $half);
?>
    <div class="overflow-hidden campaign-area-1 space-top bg-gray" data-bg-src="<?= e(asset('img/bg/gray-bg4.png')) ?>" id="schedule-sec">
        <div class="shape-mockup d-xl-block d-none campaign-bg-shape1-2" data-top="0" data-right="0">
            <img src="<?= e(asset('img/shape/campaign-bg-shape1-1.png')) ?>" alt="shape">
        </div>
        <div class="shape-mockup d-xl-block d-none campaign-bg-shape1-3" data-bottom="0" data-right="0" data-left="0">
            <img class="w-100" src="<?= e(asset('img/shape/campaign-bg-shape1-2.png')) ?>" alt="shape">
        </div>
        <div class="container">
            <div class="row align-items-center justify-content-between">
                <div class="col-lg-6">
                    <div class="title-area">
                        <span class="sub-title"><?= e(site_name()) ?></span>
                        <h2 class="sec-title"><?= cms_text('schedule.title') ?></h2>
                    </div>
                </div>
            </div>
            <div class="campaign-wrap1">
                <?php
                // Ruang di bawah tiap baris mengikuti label tertinggi (tanggal yang di-extend
                // berbaris-baris), supaya baris 1 tidak menutupi baris 2. Perkiraan di server,
                // lalu diukur ulang di browser.
                $cnRowPad = static function (array $items): int {
                    $lines = 1;
                    foreach ($items as $item) {
                        $lines = max($lines, count(Cenim\Domain\TimelineRepo::history($item)) + 1 + (trim((string) ($item['note'] ?? '')) !== '' ? 1 : 0));
                    }
                    return max(130, 72 + 30 + $lines * 22 + 28);
                };
                ?>
                <section class="schedule-wrapper" id="cnSchedule" style="--ps-row1: <?= $cnRowPad($rowOne) ?>px; --ps-row2: <?= $cnRowPad($rowTwo) ?>px;">
                    <div class="container">
                        <?php if ($timeline === []): ?>
                            <p class="text-center">The schedule will be announced soon.</p>
                        <?php else: ?>
                            <div class="ps-row-wrap">
                                <ol class="ps-timeline">
                                    <?php foreach ($rowOne as $index => $item): ?>
                                        <li>
                                            <span class="ps-node"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                                            <div class="ps-label">
                                                <h5 class="ps-title"><?= e((string) $item['title']) ?></h5>
                                                <p><?= site_timeline_dates($item) ?></p>
                                                <?php if (trim((string) ($item['note'] ?? '')) !== ''): ?>
                                                    <p class="ps-note"><?= e((string) $item['note']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>

                            <?php if ($rowTwo !== []): ?>
                                <div class="ps-snake-connector"></div>

                                <div class="ps-row-wrap">
                                    <ol class="ps-timeline ps-timeline-reversed">
                                        <?php foreach ($rowTwo as $index => $item): ?>
                                            <li>
                                                <span class="ps-node"><?= e(str_pad((string) ($half + $index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                                                <div class="ps-label">
                                                    <h5 class="ps-title"><?= e((string) $item['title']) ?></h5>
                                                    <p><?= site_timeline_dates($item) ?></p>
                                                    <?php if (trim((string) ($item['note'] ?? '')) !== ''): ?>
                                                        <p class="ps-note"><?= e((string) $item['note']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
                <style>
                    @media (min-width: 768px) {
                        #cnSchedule .ps-row-wrap:first-child { padding-bottom: var(--ps-row1, 130px); }
                        #cnSchedule .ps-row-wrap:last-child  { padding-bottom: var(--ps-row2, 130px); }
                        #cnSchedule .ps-snake-connector::before { top: calc(-1 * var(--ps-row1, 130px)); height: calc(var(--ps-row1, 130px) + 40px); }
                    }
                </style>
                <script>
                (function () {
                    var box = document.getElementById('cnSchedule');
                    if (!box) { return; }
                    function fit() {
                        if (window.innerWidth < 768) { return; }
                        box.querySelectorAll('.ps-row-wrap').forEach(function (row, i) {
                            var ol = row.querySelector('ol');
                            var need = 0;
                            row.querySelectorAll('.ps-label').forEach(function (label) {
                                need = Math.max(need, label.offsetTop + label.offsetHeight);
                            });
                            if (ol && need > 0) {
                                box.style.setProperty(i === 0 ? '--ps-row1' : '--ps-row2', Math.max(130, need + 36) + 'px');
                            }
                        });
                    }
                    window.addEventListener('load', fit);
                    window.addEventListener('resize', fit);
                    fit();
                })();
                </script>
            </div>
        </div>
    </div>
<!--==============================
Keynote Speaker Area  
==============================-->
<?php $speakers = site_speakers(); ?>
    <div class="space" id="keynote-sec" data-bg-src="<?= e(asset('img/bg/gray-bg2.png')) ?>">

        <div class="shape-mockup team-bg-shape3-4 jump d-xxl-block d-none" data-top="18%" data-right="10%">
            <img src="<?= e(asset('img/hero/hero-bg-shape2-1.png')) ?>" alt="img">
        </div>
        <div class="container">
            <div class="title-area text-center">
                <span class="sub-title"><?= e(site_name()) ?></span>
                <h2 class="sec-title"><?= cms_text('keynote.title') ?></h2>
            </div>
            <?php if ($speakers === []): ?>
                <p class="text-center">Keynote speakers will be announced soon.</p>
            <?php else: ?>
                <div class="slider-area">
                    <div class="swiper th-slider has-shadow" id="teamSlider3" data-slider-options='{"breakpoints":{"0":{"slidesPerView":1},"576":{"slidesPerView":"2"},"768":{"slidesPerView":"2"},"992":{"slidesPerView":"3"},"1200":{"slidesPerView":"4"}}}'>
                        <div class="swiper-wrapper">
                            <?php foreach ($speakers as $speaker): ?>
                                <div class="swiper-slide">
                                    <div class="th-team team-card3">
                                        <div class="team-img">
                                            <img src="<?= e(site_speaker_photo($speaker)) ?>" alt="<?= e((string) $speaker['name']) ?>">
                                        </div>
                                        <div class="team-card-content">
                                            <h3 class="box-title">
                                                <?php if (($speaker['profile_url'] ?? '') !== ''): ?>
                                                    <a href="<?= e((string) $speaker['profile_url']) ?>" target="_blank" rel="noopener"><?= e((string) $speaker['name']) ?></a>
                                                <?php else: ?>
                                                    <?= e((string) $speaker['name']) ?>
                                                <?php endif; ?>
                                            </h3>
                                            <span class="team-desig"><?= e((string) ($speaker['affiliation'] ?? '')) ?></span>
                                            <?php $links = Cenim\Domain\SpeakerRepo::links($speaker); ?>
                                            <?php if ($links !== []): ?>
                                                <div class="th-social style2">
                                                    <?php foreach ($links as $kind => $url): ?>
                                                        <a target="_blank" rel="noopener" href="<?= e($url) ?>" title="<?= e(ucfirst($kind)) ?>">
                                                            <i class="fas <?= e(match ($kind) {
                                                                'scholar'  => 'fa-graduation-cap',
                                                                'scopus'   => 'fa-user-graduate',
                                                                'linkedin' => 'fa-address-card',
                                                                default    => 'fa-globe',
                                                            }) ?>"></i>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<!--==============================
Organizer Area  
==============================-->
<?php $committeeGroups = site_committee_groups(); ?>
    <div class="space overflow-hidden" id="comittee-sec">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-7">
                    <div class="title-area text-center">
                        <span class="sub-title"><?= e(site_name()) ?></span>
                        <h2 class="sec-title"><?= cms_text('committee.title') ?></h2>
                    </div>
                </div>
            </div>
            <div class="row gy-30 justify-content-center">
                <?php if ($committeeGroups === []): ?>
                    <div class="col-12"><p class="text-center">The committee will be announced soon.</p></div>
                <?php endif; ?>
                <?php foreach ($committeeGroups as $division => $members): ?>
                    <div class="col-xl-12 col-lg-4 col-md-6">
                        <div class="price-card2">
                            <div class="price-card-title-wrap">
                                <h3 class="price-card_title">
                                    <?= e((string) $division) ?> </h3>
                            </div>
                            <div class="price-card_content">
                                <div class="checklist">
                                    <ul>
                                        <?php foreach ($members as $member): ?>
                                            <li><i class="fas fa-user"></i> <span><?= site_committee_line($member) ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<!--==============================
Vanue Location
==============================-->
    <div class="" id="vanue-sec">
        <div class="cta-wrap3 style2 bg-theme-dark">
            <div class="row gx-0">
                <div class="col-xl-5">
                    <div class="cta-content-wrap">
                        <div class="title-area text-xl-start text-center mb-40">
                            <h2 class="sec-title text-white"><?= cms_text('venue.title') ?></h2>
                        </div>
                        <div class="cta-content">
                            <ul style="color: #ffffff; font-size: 1.1rem; line-height: 1.8; list-style: none; padding: 0; margin: 0;">
                                <li><i class="fas fa-location-dot me-2"></i><?= cms_text('venue.name') ?></li>
                                <li style="font-size: 1rem; color: #d0d8e8;"><?= cms_text('venue.address') ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7">
                    <div class="contact-map">
                        <iframe src="<?= cms_text('venue.map_embed') ?>" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!--==============================
Previous Conference Area  
==============================-->
    <section class="space overflow-hidden" data-bg-src="assets/img/bg/gray-bg3.png" id="donation-sec">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="title-area text-center">
                        <span class="sub-title">CENIM</span>
                        <h2 class="sec-title">Previous Conference</h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="container th-container2">
            <div class="slider-area">
                <div class="swiper th-slider has-shadow" id="donationSlider4" data-slider-options='{"loop":false,"rewind":true,"speed":700,"autoplay":false,"grabCursor":true,"breakpoints":{"0":{"slidesPerView":1},"576":{"slidesPerView":"1"},"768":{"slidesPerView":"2"},"992":{"slidesPerView":"2"},"1200":{"slidesPerView":"3"},"1400":{"slidesPerView":"4"}}}'>
                    <div class="swiper-wrapper">
                        <?php
                        // Data dari tabel previous_conferences (admin → Previous conferences);
                        // edisi sistem ini otomatis masuk begitu sudah lewat.
                        $cnPrevColors = ['', 'var(--theme-color2)', '#FF5528', '#122F2A'];
                        foreach (Cenim\Domain\PreviousConferenceRepo::forLanding() as $cnPrevIndex => $cnPrev):
                            $cnPrevSite = Cenim\Domain\PreviousConferenceRepo::siteUrl($cnPrev);
                        ?>
                        <div class="swiper-slide">
                            <div class="donation-card style4" data-theme-color="<?= e($cnPrevColors[$cnPrevIndex % count($cnPrevColors)]) ?>">
                                <div class="box-thumb">
                                    <img src="<?= e(Cenim\Domain\PreviousConferenceRepo::imageUrl($cnPrev)) ?>" alt="<?= e((string) $cnPrev['title']) ?>">
                                </div>
                                <h3 class="box-title">
                                    <?php if ($cnPrevSite !== null): ?>
                                        <a href="<?= e($cnPrevSite) ?>" target="_blank"><?= e((string) $cnPrev['title']) ?></a>
                                    <?php else: ?>
                                        <?= e((string) $cnPrev['title']) ?>
                                    <?php endif; ?>
                                </h3>
                                <?php if (($cnPrev['xplore_url'] ?? '') !== ''): ?>
                                    <div class="box-content">
                                        <a href="<?= e((string) $cnPrev['xplore_url']) ?>" class="th-btn style6" target="_blank" rel="noopener">Visit IEEExplore<i class="fas fa-arrow-up-right ms-2"></i></a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    </div>
                </div>
                <div class="d-flex justify-content-center gap-3 mt-40 cn-slider-nav">
                    <button type="button" class="slider-arrow default" data-slider-prev="#donationSlider4" aria-label="Previous conferences">
                        <i class="far fa-arrow-left"></i>
                    </button>
                    <button type="button" class="slider-arrow default" data-slider-next="#donationSlider4" aria-label="Next conferences">
                        <i class="far fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>
<!--==============================
Sponsor Institutional Area  
==============================-->
    <?php $cnSponsors = array_values(array_filter(
        Cenim\Domain\SponsorRepo::all(site_edition_id(), true),
        static fn (array $row): bool => Cenim\Domain\SponsorRepo::logoUrl($row) !== null
    )); ?>
    <?php if ($cnSponsors !== []): ?>
    <div class="bg-smoke2 overflow-hidden pt-60 pb-60 brand-area-1" id="sponsors">
        <div class="container">
            <div class="brand-wrap1 p-0 m-0 text-center">
                <div class="swiper th-slider" id="brandSlider1" data-slider-options='{"breakpoints":{"0":{"slidesPerView":2},"576":{"slidesPerView":"2"},"768":{"slidesPerView":"2"},"992":{"slidesPerView":"3"},"1200":{"slidesPerView":"4"},"1400":{"slidesPerView":"5", "spaceBetween": "90"}}}'>
                    <div class="swiper-wrapper">
                        <?php foreach ($cnSponsors as $cnSponsor): ?>
                            <?php $cnSponsorAlt = (site_edition()['name'] ?? 'CENIM') . ' - ' . (($cnSponsor['role'] ?? '') !== '' ? $cnSponsor['role'] . ': ' : '') . $cnSponsor['name']; ?>
                            <div class="swiper-slide">
                                <?php if (($cnSponsor['url'] ?? '') !== ''): ?>
                                    <a href="<?= e((string) $cnSponsor['url']) ?>" class="brand-box" target="_blank" rel="noopener" title="<?= e((string) $cnSponsor['name']) ?>">
                                        <img src="<?= e((string) Cenim\Domain\SponsorRepo::logoUrl($cnSponsor)) ?>" alt="<?= e($cnSponsorAlt) ?>">
                                    </a>
                                <?php else: ?>
                                    <span class="brand-box" title="<?= e((string) $cnSponsor['name']) ?>">
                                        <img src="<?= e((string) Cenim\Domain\SponsorRepo::logoUrl($cnSponsor)) ?>" alt="<?= e($cnSponsorAlt) ?>">
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php site_partial('footer'); ?>
