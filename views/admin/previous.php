<?php
/**
 * @var array      $rows
 * @var array|null $edition
 */

use Cenim\Core\Csrf;
use Cenim\Domain\PreviousConferenceRepo;

$activeYear = (int) ($edition['year'] ?? 0);
?>

<div class="alert alert-light border" style="font-size:12.5px">
    <i class="fa-solid fa-circle-info me-1" style="color:var(--cn-navy)"></i>
    The landing page shows every visible card <b>older than the active edition (<?= e((string) $activeYear) ?>)</b>, oldest first.
    When a new edition becomes active, the previous one is <b>added here automatically</b> with its edition logo —
    just fill in its IEEE Xplore link once the proceedings are published.
    A site link like <code>2024/</code> points to the old static site (kept in <code>cenim-old/</code>).
    Card image: <?= e(upload_hint('PNG or JPG')) ?>.
    Card image: PNG/JPG, landscape around <b>800 × 600 px</b>; without an image the edition logo is used.
</div>

<div class="cn-card mb-3">
    <div class="cn-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:12.5px">
                <thead class="table-light">
                    <tr>
                        <th style="width:96px">Image</th><th style="width:84px">Year</th><th>Title</th>
                        <th>Site link</th><th>IEEE Xplore link</th><th style="width:70px" class="text-center">Visible</th><th style="width:96px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $formId = 'pc' . (int) $row['id']; $past = (int) $row['year'] < $activeYear; ?>
                        <tr class="<?= $past ? '' : 'text-muted' ?>">
                            <td>
                                <img src="<?= e(PreviousConferenceRepo::imageUrl($row)) ?>" alt="" style="width:80px;height:52px;object-fit:contain;background:#f4f6fa" class="rounded border d-block mb-1">
                                <input form="<?= $formId ?>" type="file" name="image" accept="image/png,image/jpeg" class="form-control form-control-sm" style="width:80px;font-size:10px">
                            </td>
                            <td><input form="<?= $formId ?>" type="number" class="form-control form-control-sm" name="year" value="<?= (int) $row['year'] ?>" min="2000" max="2100"></td>
                            <td><input form="<?= $formId ?>" class="form-control form-control-sm" name="title" value="<?= e((string) $row['title']) ?>"></td>
                            <td><input form="<?= $formId ?>" class="form-control form-control-sm" name="site_url" value="<?= e((string) ($row['site_url'] ?? '')) ?>" placeholder="2024/ or https://…"></td>
                            <td><input form="<?= $formId ?>" class="form-control form-control-sm" name="xplore_url" value="<?= e((string) ($row['xplore_url'] ?? '')) ?>" placeholder="https://ieeexplore.ieee.org/…"></td>
                            <td class="text-center">
                                <input form="<?= $formId ?>" class="form-check-input" type="checkbox" name="is_visible" value="1" <?= (int) $row['is_visible'] === 1 ? 'checked' : '' ?>>
                                <?php if (!$past): ?><div style="font-size:10.5px">not yet past</div><?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="post" enctype="multipart/form-data" id="<?= $formId ?>" class="d-inline">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="save" title="Save"><i class="fa-solid fa-floppy-disk"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="delete" title="Remove" formnovalidate
                                            onclick="return confirm('Remove this card? It comes back automatically if it belongs to an edition of this system.')"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-light">
                        <td><input form="pcNew" type="file" name="image" accept="image/png,image/jpeg" class="form-control form-control-sm" style="width:80px;font-size:10px"></td>
                        <td><input form="pcNew" type="number" class="form-control form-control-sm" name="year" min="2000" max="2100" placeholder="Year" required></td>
                        <td><input form="pcNew" class="form-control form-control-sm" name="title" placeholder="CENIM 20xx"></td>
                        <td><input form="pcNew" class="form-control form-control-sm" name="site_url" placeholder="2023/ or https://…"></td>
                        <td><input form="pcNew" class="form-control form-control-sm" name="xplore_url" placeholder="https://ieeexplore.ieee.org/…"></td>
                        <td class="text-center"><input form="pcNew" class="form-check-input" type="checkbox" name="is_visible" value="1" checked></td>
                        <td class="text-end">
                            <form method="post" enctype="multipart/form-data" id="pcNew" class="d-inline">
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
