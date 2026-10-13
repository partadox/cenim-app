<?php
/**
 * @var array $rows
 */

use Cenim\Core\Csrf;
use Cenim\Domain\SponsorRepo;
?>

<div class="alert alert-light border" style="font-size:12.5px">
    <i class="fa-solid fa-circle-info me-1" style="color:var(--cn-navy)"></i>
    Visible sponsors appear as a logo slider at the bottom of the landing page, in the order below (smallest number first).
    Logo: <b>PNG with a transparent background</b> (or JPG), about <b>400–600 px wide and 150–250 px tall</b>
    (at least <?= SponsorRepo::LOGO_MIN_HEIGHT ?> px tall), max <b><?= e(upload_max_label(SponsorRepo::LOGO_MAX_BYTES)) ?></b>, with little empty margin around the artwork.
    The link is optional — the logo opens it in a new tab.
</div>

<div class="cn-card">
    <div class="cn-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:12.5px">
                <thead class="table-light">
                    <tr>
                        <th style="width:150px">Logo</th><th>Name</th><th style="width:150px">Role</th><th>Link</th>
                        <th style="width:80px">Order</th><th style="width:70px" class="text-center">Visible</th><th style="width:96px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $fid = 'sp' . (int) $row['id']; $logo = SponsorRepo::logoUrl($row); ?>
                        <tr class="<?= (int) $row['is_visible'] === 1 ? '' : 'text-muted' ?>">
                            <td>
                                <?php if ($logo !== null): ?>
                                    <img src="<?= e($logo) ?>" alt="" class="rounded border d-block mb-1 p-1" style="width:130px;height:52px;object-fit:contain;background:#fff">
                                <?php endif; ?>
                                <input form="<?= $fid ?>" type="file" name="logo" accept="image/png,image/jpeg" data-max-bytes="<?= upload_max_bytes(SponsorRepo::LOGO_MAX_BYTES) ?>" class="form-control form-control-sm" style="width:130px;font-size:10px">
                            </td>
                            <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="name" value="<?= e((string) $row['name']) ?>" maxlength="190" required></td>
                            <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="role" value="<?= e((string) ($row['role'] ?? '')) ?>" list="cnSponsorRoles" maxlength="120"></td>
                            <td><input form="<?= $fid ?>" class="form-control form-control-sm" name="url" value="<?= e((string) ($row['url'] ?? '')) ?>" placeholder="https://…" maxlength="300"></td>
                            <td><input form="<?= $fid ?>" type="number" class="form-control form-control-sm" name="sort" value="<?= (int) $row['sort'] ?>"></td>
                            <td class="text-center"><input form="<?= $fid ?>" class="form-check-input" type="checkbox" name="is_visible" value="1" <?= (int) $row['is_visible'] === 1 ? 'checked' : '' ?>></td>
                            <td class="text-end text-nowrap">
                                <form method="post" enctype="multipart/form-data" id="<?= $fid ?>" class="d-inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="save" title="Save"><i class="fa-solid fa-floppy-disk"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="delete" title="Remove" formnovalidate
                                            onclick="return confirm('Remove this sponsor from the landing page?')"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-light">
                        <td><input form="spNew" type="file" name="logo" accept="image/png,image/jpeg" data-max-bytes="<?= upload_max_bytes(SponsorRepo::LOGO_MAX_BYTES) ?>" class="form-control form-control-sm" style="width:130px;font-size:10px" required></td>
                        <td><input form="spNew" class="form-control form-control-sm" name="name" placeholder="Sponsor name" maxlength="190" required></td>
                        <td><input form="spNew" class="form-control form-control-sm" name="role" list="cnSponsorRoles" placeholder="Sponsor" maxlength="120"></td>
                        <td><input form="spNew" class="form-control form-control-sm" name="url" placeholder="https://…" maxlength="300"></td>
                        <td><input form="spNew" type="number" class="form-control form-control-sm" name="sort" placeholder="auto"></td>
                        <td class="text-center"><input form="spNew" class="form-check-input" type="checkbox" name="is_visible" value="1" checked></td>
                        <td class="text-end">
                            <form method="post" enctype="multipart/form-data" id="spNew" class="d-inline">
                                <?= Csrf::field() ?>
                                <button class="btn btn-sm btn-navy" name="action" value="save"><i class="fa-solid fa-plus me-1"></i>Add</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<datalist id="cnSponsorRoles">
    <option value="Organizer"><option value="Host"><option value="Co-Host"><option value="Sponsor">
    <option value="Technical Co-Sponsor"><option value="Supported by"><option value="Media Partner">
</datalist>
