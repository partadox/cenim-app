<!--==============================
	Footer Area
==============================-->
    <footer class="footer-wrapper footer-default" data-bg-src="assets/img/bg/footer-default-bg-mask.png">
        <div class="footer-bg-shape3 shape-mockup d-none" data-bottom="0" data-right="0">
            <img src="assets/img/shape/footer-bg-shape2.png" alt="img">
        </div>
        <div class="widget-area">
            <div class="container">
                <div class="row justify-content-between">
                    <div class="col-md-6 col-xl-auto">
                        <div class="widget footer-widget">
                            <div class="th-widget-about">
                                <div class="about-logo">
                                    <a href="<?= e(base_url()) ?>"><img src="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>" alt="Donat"></a>
                                </div>
                                <p class="about-text"> International Conference on Computer Engineering,Network and Intelligent Multimedia.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-auto">
                        <div class="widget widget_nav_menu footer-widget">
                            <h3 class="widget_title">Office Address</h3>
                            <div class="menu-all-pages-container">
                                <p>Departement of Computer Engineering, Institut Teknologi Sepuluh Nopember, Sukolilo Campus, Surabaya, Indonesia 60111</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-auto">
                        <div class="widget footer-widget">
                            <div class="th-widget-contact">
                                <h3 class="widget_title">Contact Us</h3>
                                <div class="info-card">
                                    <div class="box-icon">
                                        <i class="fal fa-envelope-open"></i>
                                        <div class="bg-shape1" data-mask-src="assets/img/shape/info_card_icon_bg_shape_1_1.png"></div>
                                        <div class="bg-shape2" data-mask-src="assets/img/shape/info_card_icon_bg_shape_1_1.png"></div>
                                    </div>
                                    <div class="box-content">
                                        <p class="box-text">Email us any time:</p>
                                        <h4 class="box-title"><a href="mailto:info.cenim@its.ac.id">info.cenim@its.ac.id</a></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="copyright-wrap">
            <div class="container">
                <div class="row justify-content-center gy-3 align-items-center">
                    <div class="col-lg-12">
                        <p class="copyright-text text-center">
                            <i class="fal fa-copyright"></i> Copyright <?= e((string) (site_edition()['year'] ?? date('Y'))) ?> <?= e(site_edition()['name'] ?? 'CENIM') ?>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

<!--********************************
			Code End  Here 
******************************** -->

    <!-- Scroll To Top -->
    <div class="scroll-top">
        <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" style="transition: stroke-dashoffset 10ms linear 0s; stroke-dasharray: 307.919, 307.919; stroke-dashoffset: 307.919;"></path>
        </svg>
    </div>

<!--==============================
    All Js File
============================== -->
    <!-- Jquery -->
    <script src="assets/js/vendor/jquery-3.7.1.min.js"></script>
    <!-- Swiper Js -->
    <script src="assets/js/swiper-bundle.min.js"></script>
    <!-- Bootstrap -->
    <script src="assets/js/bootstrap.min.js"></script>
    <!-- Magnific Popup -->
    <script src="assets/js/jquery.magnific-popup.min.js"></script>
    <!-- Counter Up -->
    <script src="assets/js/jquery.counterup.min.js"></script>
    <!-- Range Slider -->
    <script src="assets/js/jquery-ui.min.js"></script>
    <!-- Isotope Filter -->
    <script src="assets/js/imagesloaded.pkgd.min.js"></script>
    <script src="assets/js/isotope.pkgd.min.js"></script>

    <!-- Main Js File -->
    <script src="assets/js/main.js"></script>

<?php
// Galeri & poster dari Website → Poster & Gallery (per edisi).
$cnGallery = Cenim\Domain\GalleryRepo::photos(site_edition_id(), true);
$cnPosterUrl = Cenim\Domain\GalleryRepo::posterUrl(site_edition_id());
?>
<?php if ($cnGallery !== []): ?>
<!-- Gallery Modal -->
<div class="modal fade" id="memoriesModal" tabindex="-1" aria-labelledby="memoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: #1a3a6e; border: none; padding: 16px 24px;">
                <h5 class="modal-title text-white" id="memoriesModalLabel"><i class="fas fa-images me-2"></i><?= cms_text('about.gallery_title') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="background: #f8f9fa; padding: 24px;">
                <div class="row g-3" id="memoriesGallery">
                    <?php foreach ($cnGallery as $cnPhoto): ?>
                        <?php $cnPhotoUrl = Cenim\Domain\GalleryRepo::photoUrl($cnPhoto); if ($cnPhotoUrl === null) { continue; } ?>
                        <div class="col-md-6">
                            <a href="<?= e($cnPhotoUrl) ?>" target="_blank" rel="noopener">
                                <img src="<?= e($cnPhotoUrl) ?>" alt="<?= e((string) ($cnPhoto['caption'] ?? 'CENIM conference photo')) ?>" class="img-fluid w-100" loading="lazy"
                                     style="border-radius: 8px; object-fit: cover; height: 260px; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            </a>
                            <?php if (($cnPhoto['caption'] ?? '') !== ''): ?>
                                <div class="text-center mt-2" style="font-size: 14px; color: #475569;"><?= e((string) $cnPhoto['caption']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($cnPosterUrl !== null): ?>
<!-- CFP Poster Modal -->
<div class="modal fade" id="cfpPosterModal" tabindex="-1" aria-labelledby="cfpPosterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: fit-content;">
        <div class="modal-content" style="background: transparent; border: none;">
            <div class="modal-header" style="border: none; padding: 0 0 8px 0; justify-content: flex-end;">
                <a href="<?= e($cnPosterUrl) ?>" download class="btn btn-sm btn-light me-2"><i class="fas fa-download me-1"></i>Download</a>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <img src="<?= e($cnPosterUrl) ?>" alt="Call for Papers poster <?= e(site_edition()['name'] ?? 'CENIM') ?>" style="max-height: 85vh; max-width: 90vw; width: auto; height: auto; display: block; border-radius: 8px;">
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
</body>

</html>