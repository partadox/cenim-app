<?php
/**
 * @var array|null $member
 * @var array $values
 * @var array<string,string> $errors
 */

use Cenim\Core\Csrf;

$err = static fn (string $f): string => isset($errors[$f])
    ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$f]) . '</div>' : '';
$cls = static fn (string $f): string => isset($errors[$f]) ? ' is-invalid' : '';
?>

<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" novalidate>
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= e((string) ($member['id'] ?? 0)) ?>">

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-user-tie text-muted"></i>
                    <h2>Committee member</h2>
                </div>
                <div class="cn-card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="name">Full name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $cls('name') ?>" id="name" name="name"
                                   value="<?= e((string) $values['name']) ?>" maxlength="190" required>
                            <?= $err('name') ?>
                            <div class="form-text" style="font-size:11.5px">
                                Printed on the certificate exactly as written here, including titles.
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control<?= $cls('email') ?>" id="email" name="email"
                                   value="<?= e((string) $values['email']) ?>" maxlength="190">
                            <?= $err('email') ?>
                            <div class="form-text" style="font-size:11.5px">Used to sign in (6-digit code) and download the committee certificate.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="affiliation">Affiliation</label>
                            <input type="text" class="form-control" id="affiliation" name="affiliation"
                                   value="<?= e((string) ($values['affiliation'] ?? '')) ?>" maxlength="255">
                            <div class="form-text" style="font-size:11.5px">Printed on the certificate when the template shows it.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="role">Role</label>
                            <input type="text" class="form-control" id="role" name="role"
                                   value="<?= e((string) $values['role']) ?>" maxlength="190"
                                   placeholder="e.g. General Chair">
                            <div class="form-text" style="font-size:11.5px">Shown on the certificate as the contribution.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="division">Division</label>
                            <input type="text" class="form-control" id="division" name="division"
                                   value="<?= e((string) $values['division']) ?>" maxlength="190"
                                   placeholder="e.g. Technical Program">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="sort">Order</label>
                            <input type="number" class="form-control" id="sort" name="sort"
                                   value="<?= e((string) (int) $values['sort']) ?>" min="0" max="9999">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="show_on_landing"
                                       name="show_on_landing" value="1"
                                    <?= (int) $values['show_on_landing'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="show_on_landing">
                                    Show on the public committee list (landing page)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-navy">
                    <i class="fa-solid fa-floppy-disk me-1"></i><?= $member !== null ? 'Save changes' : 'Add member' ?>
                </button>
                <a href="<?= e(admin_url('committee.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
