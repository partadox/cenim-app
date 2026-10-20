<?php
/**
 * Pendaftaran peserta non-presenter.
 *
 * @var array|null  $edition
 * @var bool        $open
 * @var array       $values
 * @var array<string,string> $errors
 * @var string|null $done       email yang baru terdaftar
 * @var array       $countries
 * @var bool        $showCaptcha
 * @var string      $contact
 */

use Cenim\Core\Csrf;
use Cenim\Core\Turnstile;

$err = static fn (string $field): string => isset($errors[$field])
    ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>'
    : '';
$cls = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>

<div class="text-center mb-4">
    <img class="cn-auth-logo" src="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>" alt="CENIM">
    <h1>PARTICIPANT REGISTRATION</h1>
</div>

<?php if ($done !== null): ?>
    <div class="alert alert-success" style="font-size:13px">
        <i class="fa-solid fa-circle-check me-1"></i>
        <b>You are registered.</b> Sign in to the portal with <b><?= e($done) ?></b> to choose how many people will come,
        receive your invoice, pay, and download your receipt and attendance certificate.
    </div>
    <a href="<?= e(author_url('login.php?email=' . rawurlencode($done))) ?>" class="btn btn-gold w-100 py-2">
        <i class="fa-solid fa-right-to-bracket me-2"></i>Sign in to the portal
    </a>
<?php elseif (!$open): ?>
    <div class="alert alert-warning" style="font-size:13px">
        <i class="fa-solid fa-lock me-1"></i>Participant registration is currently closed. Please contact the secretariat.
    </div>
<?php else: ?>
    <div class="alert alert-light border py-2 px-3" style="font-size:12.5px">
        <i class="fa-solid fa-circle-info me-1" style="color:var(--cn-navy)"></i>
        This form is for <b>non-presenters</b> who will attend the conference <b>offline</b> and are
        <b>not an author</b> of an accepted paper. Authors and co-authors sign in with the email in their paper instead.
    </div>

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-danger py-2 px-3" style="font-size:13px">
            <i class="fa-solid fa-circle-exclamation me-1"></i><?= e($errors['form']) ?>
        </div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= Csrf::field() ?>

        <label class="form-label" for="email" style="font-size:13px;font-weight:600">Email address</label>
        <input type="email" class="form-control<?= $cls('email') ?>" id="email" name="email" value="<?= e($values['email']) ?>"
               placeholder="name@institution.org" autocomplete="email" maxlength="190" required>
        <?= $err('email') ?>
        <div class="form-text mb-3" style="font-size:11.5px">You will use this email to sign in (we send a 6-digit code).</div>

        <label class="form-label" for="name" style="font-size:13px;font-weight:600">Full name</label>
        <input type="text" class="form-control mb-1<?= $cls('name') ?>" id="name" name="name" value="<?= e($values['name']) ?>"
               autocomplete="name" maxlength="190" required>
        <?= $err('name') ?>
        <div class="form-text mb-3" style="font-size:11.5px">As it should appear on the certificate.</div>

        <label class="form-label" for="affiliation" style="font-size:13px;font-weight:600">Affiliation</label>
        <input type="text" class="form-control mb-3<?= $cls('affiliation') ?>" id="affiliation" name="affiliation" value="<?= e($values['affiliation']) ?>"
               placeholder="University, company or institution" maxlength="255" required>
        <?= $err('affiliation') ?>

        <label class="form-label" for="region" style="font-size:13px;font-weight:600">Participant from</label>
        <?= Cenim\Core\View::partial('author/_region_select', ['values' => $values, 'errors' => $errors, 'countries' => $countries, 'size' => '']) ?>
        <div class="form-text mb-3" style="font-size:11.5px">Indonesia is billed in IDR (Rupiah); International is billed in USD.</div>

        <?php if ($showCaptcha): ?>
            <div class="cf-turnstile mb-3" data-sitekey="<?= e(Turnstile::siteKey()) ?>" data-theme="light"></div>
        <?php endif; ?>

        <button type="submit" class="btn btn-gold w-100 py-2">
            <i class="fa-solid fa-user-plus me-2"></i>Register
        </button>
    </form>
<?php endif; ?>

<p class="text-center mt-3 mb-0" style="font-size:12.5px">
    <a href="<?= e(author_url('login.php')) ?>" style="color:var(--cn-navy)"><i class="fa-solid fa-arrow-left me-1"></i>Back to sign in</a>
</p>
<?php if ($contact !== ''): ?>
    <p class="text-center text-muted mt-2 mb-0" style="font-size:12px">
        Questions? Contact <a href="mailto:<?= e($contact) ?>" style="color:var(--cn-navy)"><?= e($contact) ?></a>
    </p>
<?php endif; ?>

<?php if ($showCaptcha && $open && $done === null): ?>
    <script src="<?= e(cfg('cdn.turnstile_js')) ?>" async defer></script>
<?php endif; ?>
