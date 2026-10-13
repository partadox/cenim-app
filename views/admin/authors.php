<?php
/**
 * @var array|null       $edition
 * @var array<int,string> $countries
 * @var array<int,string> $batches
 * @var array<int,string> $statuses
 */
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(admin_url('author-edit.php')) ?>" class="btn btn-navy btn-sm">
        <i class="fa-solid fa-plus me-1"></i>Add paper
    </a>
    <a href="<?= e(admin_url('authors-import.php')) ?>" class="btn btn-outline-secondary btn-sm" title="One row per paper: paper ID, title, first author, pages, status">
        <i class="fa-solid fa-file-import me-1"></i>Import papers
    </a>
    <a href="<?= e(admin_url('import.php?kind=paper_authors')) ?>" class="btn btn-outline-secondary btn-sm" title="One row per author: paper ID, name, email, affiliation, country">
        <i class="fa-solid fa-users-line me-1"></i>Import author list
    </a>
    <a href="<?= e(admin_url('authors-export.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-file-export me-1"></i>Export CSV
    </a>
</div>

<div class="cn-card">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-users text-muted"></i>
        <h2>Author list</h2>

        <div class="ms-auto d-flex flex-wrap gap-2">
            <select id="fPresenter" class="form-select form-select-sm" style="width:auto">
                <option value="">All types</option>
                <option value="yes">Presenter</option>
                <option value="no">Non-presenter</option>
            </select>

            <select id="fStatus" class="form-select form-select-sm" style="width:auto">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>"><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($countries !== []): ?>
                <select id="fCountry" class="form-select form-select-sm" style="width:auto">
                    <option value="">All countries</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?= e($country) ?>"><?= e($country) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <?php if ($batches !== []): ?>
                <select id="fBatch" class="form-select form-select-sm" style="width:auto">
                    <option value="">All import batches</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?= e($batch) ?>"><?= e($batch) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <div class="cn-card-body">
        <div class="table-responsive">
            <table id="authorsTable" class="table table-sm table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th title="Number of authors — click to expand"><i class="fa-solid fa-users"></i></th>
                        <th>Paper ID</th>
                        <th>First author</th>
                        <th>Email</th>
                        <th>Title</th>
                        <th>Affiliation</th>
                        <th>Country</th>
                        <th>Pages</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<style>
    .cn-coauthors-row > td { background: #f6f8fc; padding: 10px 16px !important; }
    .cn-coauthors .table { background: transparent; }
    .cn-coauthors .table th { color: #6b7893; font-weight: 600; }
</style>
