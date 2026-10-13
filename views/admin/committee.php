<?php
/**
 * @var array<int,string> $divisions
 */
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= e(admin_url('committee-edit.php')) ?>" class="btn btn-navy btn-sm">
        <i class="fa-solid fa-plus me-1"></i>Add member
    </a>
    <a href="<?= e(admin_url('import.php?kind=committee')) ?>" class="btn btn-outline-secondary btn-sm" title="Name, email, affiliation, role — committee members and reviewers">
        <i class="fa-solid fa-file-import me-1"></i>Import CSV
    </a>
    <a href="<?= e(admin_url('certificates.php')) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-certificate me-1"></i>Committee certificates
    </a>
</div>

<div class="cn-card">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-user-tie text-muted"></i>
        <h2>Committee members</h2>
        <?php if ($divisions !== []): ?>
            <div class="ms-auto">
                <select id="fDivision" class="form-select form-select-sm" style="width:auto">
                    <option value="">All divisions</option>
                    <?php foreach ($divisions as $division): ?>
                        <option value="<?= e($division) ?>"><?= e($division) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
    <div class="cn-card-body">
        <div class="table-responsive">
            <table id="committeeTable" class="table table-sm table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:70px">Order</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Division</th>
                        <th>Email</th>
                        <th>Landing</th>
                        <th>Certificate</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
