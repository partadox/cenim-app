<?php
/**
 * @var array|null          $author
 * @var array<string,mixed> $values
 * @var array<string,string> $errors
 * @var array<int,string>   $statuses
 * @var int                 $duplicateEmail
 */

use Cenim\Core\Csrf;

/** Bantu render pesan error per field. */
$err = static function (string $field) use ($errors): string {
    return isset($errors[$field])
        ? '<div class="invalid-feedback d-block" style="font-size:12px">' . e($errors[$field]) . '</div>'
        : '';
};
$cls = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>

<div class="row g-3">
    <div class="col-xl-8">
        <form method="post" novalidate>
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= e((string) ($author['id'] ?? 0)) ?>">

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-file-lines text-muted"></i>
                    <h2>Paper</h2>
                </div>
                <div class="cn-card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="paper_id">Paper ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $cls('paper_id') ?>" id="paper_id" name="paper_id"
                                   value="<?= e((string) $values['paper_id']) ?>" maxlength="40" required>
                            <?= $err('paper_id') ?>
                            <div class="form-text" style="font-size:11.5px">Used together with the email as author login.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="paper_title">Paper title</label>
                            <input type="text" class="form-control<?= $cls('paper_title') ?>" id="paper_title" name="paper_title"
                                   value="<?= e((string) $values['paper_title']) ?>" maxlength="500">
                            <?= $err('paper_title') ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="track">Track / topic</label>
                            <input type="text" class="form-control" id="track" name="track"
                                   value="<?= e((string) $values['track']) ?>" maxlength="190">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="total_pages">Total pages</label>
                            <input type="number" class="form-control<?= $cls('total_pages') ?>" id="total_pages" name="total_pages"
                                   value="<?= e((string) $values['total_pages']) ?>" min="0" max="200">
                            <?= $err('total_pages') ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="paper_status">Paper status</label>
                            <select class="form-select<?= $cls('paper_status') ?>" id="paper_status" name="paper_status">
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $values['paper_status'] === $status ? 'selected' : '' ?>>
                                        <?= e(ucfirst($status)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?= $err('paper_status') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cn-card mb-3">
                <div class="cn-card-head">
                    <i class="fa-solid fa-user text-muted"></i>
                    <h2>First author</h2>
                </div>
                <div class="cn-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="first_author_name">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control<?= $cls('first_author_name') ?>" id="first_author_name"
                                   name="first_author_name" value="<?= e((string) $values['first_author_name']) ?>" maxlength="190" required>
                            <?= $err('first_author_name') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control<?= $cls('email') ?>" id="email" name="email"
                                   value="<?= e((string) $values['email']) ?>" maxlength="190" required>
                            <?= $err('email') ?>
                            <?php if ($duplicateEmail > 0): ?>
                                <div class="form-text text-warning" style="font-size:11.5px">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                    This email is also used by <?= e((string) $duplicateEmail) ?> other paper(s) — allowed, but the author
                                    will need the matching paper ID for each one.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="affiliation">Affiliation</label>
                            <input type="text" class="form-control" id="affiliation" name="affiliation"
                                   value="<?= e((string) $values['affiliation']) ?>" maxlength="255">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="country">Country</label>
                            <input type="text" class="form-control" id="country" name="country"
                                   value="<?= e((string) $values['country']) ?>" maxlength="100">
                            <div class="form-text" style="font-size:11.5px">Determines IDR or USD fee.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="phone">Phone / WhatsApp</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?= e((string) $values['phone']) ?>" maxlength="50">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="is_presenter" name="is_presenter"
                                    <?= (int) $values['is_presenter'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_presenter">
                                    Presenter (eligible for the presenter certificate)
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="note">Internal note</label>
                            <textarea class="form-control" id="note" name="note" rows="2" maxlength="1000"><?= e((string) $values['note']) ?></textarea>
                            <div class="form-text" style="font-size:11.5px">Only visible to the committee.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-navy">
                    <i class="fa-solid fa-floppy-disk me-1"></i><?= $author !== null ? 'Save changes' : 'Add author' ?>
                </button>
                <a href="<?= e(admin_url('authors.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="col-xl-4">
        <?php if ($author !== null): ?>
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-circle-info text-muted"></i>
                    <h2>Record</h2>
                </div>
                <div class="cn-card-body" style="font-size:13px">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted fw-normal">Created</dt>
                        <dd class="col-7"><?= e(fmt_dt((string) $author['created_at'])) ?></dd>
                        <dt class="col-5 text-muted fw-normal">Updated</dt>
                        <dd class="col-7"><?= e(fmt_dt($author['updated_at'] ?? null)) ?></dd>
                        <dt class="col-5 text-muted fw-normal">Source</dt>
                        <dd class="col-7"><?= e((string) ($author['imported_batch'] ?? 'manual')) ?></dd>
                    </dl>
                </div>
            </div>
        <?php else: ?>
            <div class="cn-card">
                <div class="cn-card-head">
                    <i class="fa-solid fa-lightbulb text-muted"></i>
                    <h2>Tip</h2>
                </div>
                <div class="cn-card-body text-muted" style="font-size:13px">
                    For many authors at once, use
                    <a href="<?= e(admin_url('authors-import.php')) ?>">Import CSV</a> — it maps the columns of your
                    submission-system export and updates existing papers by paper ID.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($author !== null): ?>
    <?php $paperRef = (int) $author['id']; ?>
    <div class="cn-card mt-3" id="authors">
        <div class="cn-card-head flex-wrap">
            <i class="fa-solid fa-users-line text-muted"></i>
            <h2>All authors of this paper (<?= count($paperAuthors) ?>)</h2>
            <span class="text-muted" style="font-size:12px">
                Everyone with an email can sign in, fill in the forms, and download LoA, invoice, receipt and certificates.
            </span>
            <?php if ((string) $author['paper_status'] === 'accepted'): ?>
                <a class="btn btn-sm btn-outline-secondary ms-auto" target="_blank" rel="noopener"
                   href="<?= e(admin_url('loa-pdf.php?id=' . $paperRef)) ?>">
                    <i class="fa-solid fa-envelope-open-text me-1"></i>Letter of Acceptance
                </a>
            <?php endif; ?>
        </div>
        <div class="cn-card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2" style="font-size:12.5px">
                    <thead>
                        <tr>
                            <th style="width:70px">Order</th><th>Name</th><th>Email (sign-in)</th><th>Affiliation</th>
                            <th style="width:120px">Country</th><th style="width:90px" class="text-center">Presenter</th><th style="width:96px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paperAuthors as $pa): ?>
                            <?php $formId = 'pa' . (int) $pa['id']; ?>
                            <tr>
                                <td><input form="<?= $formId ?>" type="number" min="1" class="form-control form-control-sm" name="position" value="<?= (int) $pa['position'] ?>"></td>
                                <td><input form="<?= $formId ?>" class="form-control form-control-sm" name="name" value="<?= e((string) $pa['name']) ?>" required></td>
                                <td><input form="<?= $formId ?>" type="email" class="form-control form-control-sm" name="email" value="<?= e((string) ($pa['email'] ?? '')) ?>"></td>
                                <td><input form="<?= $formId ?>" class="form-control form-control-sm" name="affiliation" value="<?= e((string) ($pa['affiliation'] ?? '')) ?>"></td>
                                <td><input form="<?= $formId ?>" class="form-control form-control-sm" name="country" value="<?= e((string) ($pa['country'] ?? '')) ?>"></td>
                                <td class="text-center"><input form="<?= $formId ?>" class="form-check-input" type="checkbox" name="is_presenter" value="1" <?= (int) $pa['is_presenter'] === 1 ? 'checked' : '' ?>></td>
                                <td class="text-end text-nowrap">
                                    <form method="post" action="<?= e(admin_url('paper-authors.php')) ?>" id="<?= $formId ?>" class="d-inline">
                                        <?= Cenim\Core\Csrf::field() ?>
                                        <input type="hidden" name="paper" value="<?= $paperRef ?>">
                                        <input type="hidden" name="id" value="<?= (int) $pa['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" name="action" value="update" title="Save"><i class="fa-solid fa-floppy-disk"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" title="Remove" formnovalidate
                                                onclick="return confirm('Remove this author from the paper?')"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light">
                            <td><input form="paNew" type="number" min="1" class="form-control form-control-sm" name="position" placeholder="<?= count($paperAuthors) + 1 ?>"></td>
                            <td><input form="paNew" class="form-control form-control-sm" name="name" placeholder="New author name" required></td>
                            <td><input form="paNew" type="email" class="form-control form-control-sm" name="email" placeholder="email"></td>
                            <td><input form="paNew" class="form-control form-control-sm" name="affiliation" placeholder="affiliation"></td>
                            <td><input form="paNew" class="form-control form-control-sm" name="country" placeholder="country"></td>
                            <td class="text-center"><input form="paNew" class="form-check-input" type="checkbox" name="is_presenter" value="1"></td>
                            <td class="text-end">
                                <form method="post" action="<?= e(admin_url('paper-authors.php')) ?>" id="paNew" class="d-inline">
                                    <?= Cenim\Core\Csrf::field() ?>
                                    <input type="hidden" name="paper" value="<?= $paperRef ?>">
                                    <button class="btn btn-sm btn-navy" name="action" value="add"><i class="fa-solid fa-plus me-1"></i>Add</button>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-muted mb-0" style="font-size:11.5px">
                Order 1 is the first author (also shown in the paper fields above). Presenter = receives the presenter certificate.
                For many papers at once use <a href="<?= e(admin_url('import.php?kind=paper_authors')) ?>">Import author list</a>.
            </p>
        </div>
    </div>
<?php endif; ?>
