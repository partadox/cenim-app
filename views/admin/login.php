<?php
/**
 * @var string|null $error
 * @var int         $locked      sisa detik lockout (0 = tidak terkunci)
 * @var bool        $showCaptcha
 */

use Cenim\Core\Csrf;
use Cenim\Core\RateLimit;
use Cenim\Core\Turnstile;
?>

<div class="text-center mb-4">
    <span class="d-inline-grid mb-3" style="width:56px;height:56px;place-items:center;border-radius:14px;background:rgba(0,57,129,.09);color:var(--cn-navy);font-size:22px">
        <i class="fa-solid fa-shield-halved"></i>
    </span>
    <h1>Administrator</h1>
    <p class="cn-auth-sub">
        <?= e(cfg('app_name')) ?> management panel &middot; authorised committee only
    </p>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-danger py-2 px-3" style="font-size:13px">
        <i class="fa-solid fa-circle-exclamation me-1"></i><?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" autocomplete="off" novalidate>
    <?= Csrf::field() ?>

    <label class="form-label" for="admin_key" style="font-size:13px;font-weight:600">Admin key</label>
    <div class="input-group mb-3">
        <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
        <input type="password"
               class="form-control"
               id="admin_key"
               name="admin_key"
               placeholder="Enter the admin key"
               autocomplete="current-password"
               <?= $locked > 0 ? 'disabled' : 'autofocus' ?>
               required>
        <button class="btn btn-outline-secondary" type="button" id="toggleKey" tabindex="-1" aria-label="Show key">
            <i class="fa-regular fa-eye"></i>
        </button>
    </div>

    <?php if ($showCaptcha): ?>
        <div class="cf-turnstile mb-3" data-sitekey="<?= e(Turnstile::siteKey()) ?>" data-theme="light"></div>
    <?php endif; ?>

    <?php if ($locked > 0): ?>
        <div class="alert alert-warning py-2 px-3" style="font-size:13px">
            <i class="fa-solid fa-lock me-1"></i>
            Locked for <?= e(RateLimit::humanRetryAfter($locked)) ?> after repeated failed attempts.
        </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-navy w-100 py-2" <?= $locked > 0 ? 'disabled' : '' ?>>
        <i class="fa-solid fa-right-to-bracket me-2"></i>Sign in
    </button>
</form>

<p class="text-center text-muted mt-4 mb-0" style="font-size:12px">
    Are you an author? <a href="<?= e(author_url('login.php')) ?>" style="color:var(--cn-navy)">Use the author sign in</a>
</p>

<?php if ($showCaptcha): ?>
    <script src="<?= e(cfg('cdn.turnstile_js')) ?>" async defer></script>
<?php endif; ?>
<script>
    document.getElementById('toggleKey')?.addEventListener('click', function () {
        var input = document.getElementById('admin_key');
        var icon = this.querySelector('i');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    });
</script>
