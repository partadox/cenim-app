<?php
/**
 * @var array<int,array<string,mixed>> $speakers
 */

use Cenim\Core\Csrf;
use Cenim\Domain\SpeakerRepo;

/** Satu blok form speaker (dipakai untuk edit dan tambah baru). */
$fields = static function (array $s = []) : void {
    $links = $s === [] ? [] : SpeakerRepo::links($s);
    ?>
    <div class="row g-2">
        <div class="col-md-5">
            <label class="form-label" style="font-size:12px">Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" name="name"
                   value="<?= e((string) ($s['name'] ?? '')) ?>" maxlength="190" required>
        </div>
        <div class="col-md-5">
            <label class="form-label" style="font-size:12px">Affiliation</label>
            <input type="text" class="form-control form-control-sm" name="affiliation"
                   value="<?= e((string) ($s['affiliation'] ?? '')) ?>" maxlength="255">
        </div>
        <div class="col-md-2">
            <label class="form-label" style="font-size:12px">Order</label>
            <input type="number" class="form-control form-control-sm" name="sort"
                   value="<?= e((string) (int) ($s['sort'] ?? 0)) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" style="font-size:12px">Profile page URL</label>
            <input type="url" class="form-control form-control-sm" name="profile_url"
                   value="<?= e((string) ($s['profile_url'] ?? '')) ?>" maxlength="300">
        </div>
        <div class="col-md-6">
            <label class="form-label" style="font-size:12px">Photo path inside /assets</label>
            <input type="text" class="form-control form-control-sm" name="photo_url"
                   value="<?= e((string) ($s['photo_url'] ?? '')) ?>" maxlength="300"
                   placeholder="image/keynote/1.png">
        </div>
        <div class="col-md-4">
            <label class="form-label" style="font-size:12px">Google Scholar</label>
            <input type="url" class="form-control form-control-sm" name="scholar"
                   value="<?= e((string) ($links['scholar'] ?? '')) ?>" maxlength="300">
        </div>
        <div class="col-md-4">
            <label class="form-label" style="font-size:12px">Scopus</label>
            <input type="url" class="form-control form-control-sm" name="scopus"
                   value="<?= e((string) ($links['scopus'] ?? '')) ?>" maxlength="300">
        </div>
        <div class="col-md-4">
            <label class="form-label" style="font-size:12px">Website</label>
            <input type="url" class="form-control form-control-sm" name="website"
                   value="<?= e((string) ($links['website'] ?? '')) ?>" maxlength="300">
        </div>
        <div class="col-md-6">
            <label class="form-label" style="font-size:12px">Upload photo (replaces the path above)</label>
            <input type="file" class="form-control form-control-sm" name="photo" accept=".jpg,.jpeg,.png">
            <div class="form-text" style="font-size:11px"><?= e(upload_hint('JPG or PNG')) ?></div>
        </div>
    </div>
    <?php
};
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
       href="<?= e(app_url('site/preview.php?page=home')) ?>#keynote-sec">
        <i class="fa-solid fa-eye me-1"></i>Preview landing
    </a>
</div>

<?php foreach ($speakers as $speaker): ?>
    <div class="cn-card mb-3">
        <div class="cn-card-head">
            <img src="<?= e(SpeakerRepo::photoUrl($speaker) ?? asset('img/normal/team1-1.jpg')) ?>"
                 alt="" style="width:34px;height:34px;object-fit:cover;border-radius:50%">
            <h2><?= e((string) $speaker['name']) ?></h2>
            <?php if ((int) $speaker['is_visible'] !== 1): ?>
                <span class="cn-badge cn-badge-draft ms-2">hidden</span>
            <?php endif; ?>
        </div>
        <div class="cn-card-body">
            <form method="post" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= e((string) $speaker['id']) ?>">
                <?php $fields($speaker); ?>
                <div class="d-flex align-items-center gap-3 mt-3">
                    <button type="submit" class="btn btn-sm btn-navy">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save
                    </button>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_visible" value="1"
                               id="vis<?= e((string) $speaker['id']) ?>"
                            <?= (int) $speaker['is_visible'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="vis<?= e((string) $speaker['id']) ?>" style="font-size:12.5px">
                            Show on landing
                        </label>
                    </div>
                </div>
            </form>
            <form method="post" class="mt-2" onsubmit="return confirm('Delete this speaker?')">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e((string) $speaker['id']) ?>">
                <button type="submit" class="btn btn-sm btn-link text-danger p-0">Delete speaker</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-plus text-muted"></i>
        <h2>Add speaker</h2>
    </div>
    <div class="cn-card-body">
        <form method="post" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create">
            <?php $fields(['sort' => (count($speakers) + 1) * 10]); ?>
            <button type="submit" class="btn btn-sm btn-navy mt-3">
                <i class="fa-solid fa-plus me-1"></i>Add speaker
            </button>
        </form>
    </div>
</div>
