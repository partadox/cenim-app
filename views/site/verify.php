<?php
/**
 * Halaman verifikasi sertifikat — berdiri sendiri (tanpa layout panel),
 * memakai tema warna CENIM agar selaras dengan situs.
 *
 * @var string     $code
 * @var array|null $certificate
 * @var string     $status  empty|not_found|valid|revoked|locked
 * @var int        $locked
 */

use Cenim\Core\RateLimit;
use Cenim\Domain\CertificateRepo;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate verification &middot; <?= e(cfg('app_name')) ?></title>
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/png" href="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.bootstrap_css')) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.fontawesome_css')) ?>">
    <style>
        body { background: #f4f6fa; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1d2433; }
        .vf-wrap { max-width: 640px; margin: 0 auto; padding: 40px 16px 60px; }
        .vf-card { background: #fff; border: 1px solid #e3e8f0; border-radius: 12px; overflow: hidden; }
        .vf-head { background: #003981; color: #fff; padding: 18px 22px; }
        .vf-head small { color: #febd06; text-transform: uppercase; letter-spacing: 1px; font-size: 11px; }
        .vf-body { padding: 22px; }
        .vf-state { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 10px; font-size: 14px; }
        .vf-valid { background: #e6f6ed; border: 1px solid #cbeada; color: #12704a; }
        .vf-bad { background: #fdeaea; border: 1px solid #f7cfcf; color: #a3231f; }
        .vf-warn { background: #fff5e0; border: 1px solid #f6e3b8; color: #8a5a06; }
        .vf-state i { font-size: 22px; }
        dt { color: #6b7893; font-weight: 400; font-size: 13px; }
        dd { font-size: 14px; }
        code { color: #003981; background: rgba(0,57,129,.06); padding: 1px 5px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="vf-wrap">
    <div class="vf-card">
        <div class="vf-head">
            <small>Certificate verification</small>
            <div style="font-size:17px;font-weight:600"><?= e(cfg('app_long_name')) ?></div>
        </div>
        <div class="vf-body">
            <form method="get" class="mb-4">
                <label class="form-label" for="code" style="font-size:13px">Verification code</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="code" name="code" value="<?= e($code) ?>"
                           placeholder="CENIM<?= e((string) (Cenim\Domain\EditionRepo::active()['year'] ?? date('Y'))) ?>-AUT-0001" required>
                    <button class="btn text-white" style="background:#003981" type="submit">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>Verify
                    </button>
                </div>
                <div class="form-text" style="font-size:12px">
                    The code is printed at the bottom of the certificate, next to the QR code.
                </div>
            </form>

            <?php if ($status === 'valid' && $certificate !== null): ?>
                <div class="vf-state vf-valid mb-3">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <b>This certificate is valid.</b><br>
                        Issued by the conference secretariat.
                    </div>
                </div>

                <dl class="row mb-0">
                    <dt class="col-sm-5">Certificate type</dt>
                    <dd class="col-sm-7"><?= e(CertificateRepo::label((string) $certificate['type'])) ?></dd>

                    <dt class="col-sm-5">Issued to</dt>
                    <dd class="col-sm-7"><b><?= e((string) $certificate['recipient_name']) ?></b></dd>

                    <?php if (($certificate['recipient_affiliation'] ?? '') !== ''): ?>
                        <dt class="col-sm-5">Affiliation</dt>
                        <dd class="col-sm-7"><?= e((string) $certificate['recipient_affiliation']) ?></dd>
                    <?php endif; ?>

                    <?php if (($certificate['number'] ?? '') !== ''): ?>
                        <dt class="col-sm-5">Certificate number</dt>
                        <dd class="col-sm-7"><?= e((string) $certificate['number']) ?></dd>
                    <?php endif; ?>

                    <?php if (($certificate['subject_title'] ?? '') !== ''): ?>
                        <dt class="col-sm-5">For</dt>
                        <dd class="col-sm-7"><?= e((string) $certificate['subject_title']) ?></dd>
                    <?php endif; ?>

                    <dt class="col-sm-5">Edition</dt>
                    <dd class="col-sm-7"><?= e((string) ($certificate['edition_name'] ?? '')) ?></dd>

                    <dt class="col-sm-5">Issued on</dt>
                    <dd class="col-sm-7"><?= e(fmt_date((string) $certificate['issued_at'])) ?></dd>

                    <dt class="col-sm-5">Code</dt>
                    <dd class="col-sm-7 mb-0"><code><?= e((string) $certificate['code']) ?></code></dd>
                </dl>

            <?php elseif ($status === 'revoked' && $certificate !== null): ?>
                <div class="vf-state vf-bad mb-3">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <div>
                        <b>This certificate is no longer valid.</b><br>
                        It was revoked by the secretariat<?= ($certificate['revoked_at'] ?? '') !== ''
                            ? ' on ' . e(fmt_date((string) $certificate['revoked_at'])) : '' ?>.
                    </div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-5">Code</dt>
                    <dd class="col-sm-7"><code><?= e((string) $certificate['code']) ?></code></dd>
                    <dt class="col-sm-5">Certificate type</dt>
                    <dd class="col-sm-7 mb-0"><?= e(CertificateRepo::label((string) $certificate['type'])) ?></dd>
                </dl>

            <?php elseif ($status === 'not_found'): ?>
                <div class="vf-state vf-bad">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        <b>No certificate found with that code.</b><br>
                        Check the code carefully, or contact the secretariat if you believe it should be valid.
                    </div>
                </div>

            <?php elseif ($status === 'locked'): ?>
                <div class="vf-state vf-warn">
                    <i class="fa-solid fa-lock"></i>
                    <div>
                        <b>Too many lookups.</b><br>
                        Please wait <?= e(RateLimit::humanRetryAfter($locked)) ?> and try again.
                    </div>
                </div>

            <?php else: ?>
                <div class="vf-state vf-warn">
                    <i class="fa-solid fa-qrcode"></i>
                    <div>
                        Enter a code above, or scan the QR code printed on the certificate.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-center mt-3" style="font-size:12px;color:#6b7893">
        <?= e(cfg('app_long_name')) ?><br>
        Institut Teknologi Sepuluh Nopember (ITS), Surabaya, Indonesia
    </p>
</div>
</body>
</html>
