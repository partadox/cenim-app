<?php
/**
 * Login dua langkah: email → kode OTP 6 digit.
 *
 * @var string|null $error
 * @var string      $step      email | code
 * @var string      $email
 * @var array|null  $edition
 * @var int         $locked
 * @var int         $resendIn  detik sampai boleh kirim ulang
 * @var int         $ttl       masa berlaku kode (menit)
 * @var bool        $showCaptcha
 * @var string      $contact
 * @var string|null $guideUrl  PDF petunjuk penggunaan (Settings → User guide)
 * @var bool        $participantOpen  pendaftaran peserta non-presenter dibuka
 */

use Cenim\Core\Csrf;
use Cenim\Core\RateLimit;
use Cenim\Core\Turnstile;
?>

<div class="text-center mb-4">
    <img class="cn-auth-logo" src="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>" alt="CENIM">
    <h1>PORTAL</h1>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-danger py-2 px-3" style="font-size:13px">
        <i class="fa-solid fa-circle-exclamation me-1"></i><?= e($error) ?>
    </div>
<?php endif; ?>

<?php if ($step === 'email'): ?>
    <form method="post" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="send">

        <label class="form-label" for="email" style="font-size:13px;font-weight:600">Email address</label>
        <div class="input-group mb-1">
            <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>"
                   placeholder="name@university.ac.id" autocomplete="email" autofocus required>
        </div>
        <p class="text-muted mb-3" style="font-size:11.5px">
            <i class="fa-solid fa-circle-info me-1"></i>
            Use the email listed for you as an author (first author or co-author), as a committee member / reviewer,
            or as a registered participant. We will send you a 6-digit sign-in code.
        </p>

        <?php if ($showCaptcha): ?>
            <div class="cf-turnstile mb-3" data-sitekey="<?= e(Turnstile::siteKey()) ?>" data-theme="light"></div>
        <?php endif; ?>

        <button type="submit" class="btn btn-gold w-100 py-2">
            <i class="fa-solid fa-paper-plane me-2"></i>Send sign-in code
        </button>
    </form>

    <?php if ($participantOpen): ?>
        <div class="d-flex align-items-center gap-2 my-3 text-muted" style="font-size:11.5px">
            <hr class="flex-grow-1 my-0"><span>Not an author?</span><hr class="flex-grow-1 my-0">
        </div>
        <a href="<?= e(author_url('register.php')) ?>" class="btn btn-outline-secondary w-100 py-2" style="font-size:13.5px">
            <i class="fa-solid fa-user-plus me-2"></i>Register as a participant (non-presenter)
        </a>
        <p class="text-muted text-center mt-1 mb-0" style="font-size:11px">For offline attendees who are not an author of an accepted paper.</p>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-light border py-2 px-3" style="font-size:13px">
        <i class="fa-solid fa-envelope-circle-check me-1" style="color:var(--cn-navy)"></i>
        A 6-digit code has been sent to <strong><?= e($email) ?></strong>.
        It is valid for <?= (int) $ttl ?> minutes. Check your spam folder if it does not arrive.
    </div>

    <form method="post" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="verify">

        <label class="form-label" for="code" style="font-size:13px;font-weight:600">Sign-in code</label>
        <div class="input-group mb-3">
            <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
            <input type="text" class="form-control text-center" id="code" name="code"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                   placeholder="000000" style="font-size:22px;letter-spacing:8px;font-weight:600"
                   <?= $locked > 0 ? 'disabled' : 'autofocus' ?> required>
        </div>

        <?php if ($locked > 0): ?>
            <div class="alert alert-warning py-2 px-3" style="font-size:13px">
                <i class="fa-solid fa-lock me-1"></i>
                Locked for <?= e(RateLimit::humanRetryAfter($locked)) ?> after repeated wrong codes.
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-gold w-100 py-2" <?= $locked > 0 ? 'disabled' : '' ?>>
            <i class="fa-solid fa-right-to-bracket me-2"></i>Sign in
        </button>
    </form>

    <div class="d-flex justify-content-between mt-3" style="font-size:12.5px">
        <form method="post" class="m-0">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="change">
            <button type="submit" class="btn btn-link p-0" style="font-size:12.5px;color:var(--cn-navy)">
                <i class="fa-solid fa-arrow-left me-1"></i>Use another email
            </button>
        </form>
        <form method="post" class="m-0">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="btn btn-link p-0" id="cnResend" style="font-size:12.5px;color:var(--cn-navy)"
                    data-wait="<?= (int) $resendIn ?>" <?= $resendIn > 0 ? 'disabled' : '' ?>>
                <i class="fa-solid fa-rotate-right me-1"></i><span>Resend code</span>
            </button>
        </form>
    </div>
<?php endif; ?>

<p class="text-center text-muted mt-4 mb-0" style="font-size:12px">
    <?php if ($contact !== ''): ?>
        Cannot sign in? Contact <a href="mailto:<?= e($contact) ?>" style="color:var(--cn-navy)"><?= e($contact) ?></a>
    <?php else: ?>
        Cannot sign in? Please contact the conference secretariat.
    <?php endif; ?>
</p>
<?php if ($guideUrl !== null): ?>
    <p class="text-center mt-2 mb-0" style="font-size:12px">
        <a href="<?= e($guideUrl) ?>" target="_blank" rel="noopener" style="color:var(--cn-navy)">
            <i class="fa-regular fa-file-pdf me-1"></i>User guide — how to use the portal (PDF)
        </a>
    </p>
<?php endif; ?>

<?php if ($showCaptcha && $step === 'email'): ?>
    <script src="<?= e(cfg('cdn.turnstile_js')) ?>" async defer></script>
<?php endif; ?>
<?php if ($step === 'code'): ?>
    <script>
        (function () {
            var button = document.getElementById('cnResend');
            var label  = button.querySelector('span');
            var wait   = parseInt(button.dataset.wait || '0', 10);
            function tick() {
                if (wait <= 0) { button.disabled = false; label.textContent = 'Resend code'; return; }
                label.textContent = 'Resend code (' + wait + 's)';
                wait--; setTimeout(tick, 1000);
            }
            tick();
            var code = document.getElementById('code');
            if (code) {
                code.addEventListener('input', function () { code.value = code.value.replace(/\D+/g, '').slice(0, 6); });
            }
        })();
    </script>
<?php endif; ?>
