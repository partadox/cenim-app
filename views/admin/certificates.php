<?php
/**
 * @var array<string,int>   $counts
 * @var array<string,int>   $pending
 * @var array<string,array> $templates
 * @var bool                $released
 * @var int                 $committee
 * @var array|null          $edition
 */

use Cenim\Core\Csrf;
use Cenim\Domain\CertificateRepo;
?>

<?php if (!$released): ?>
    <div class="alert alert-warning" style="font-size:13px">
        <i class="fa-solid fa-clock me-1"></i>
        <b>Certificates are not released to authors yet.</b>
        They become downloadable in the author area after
        <code><?= e((string) ($edition['certificate_release_at'] ?? 'not set')) ?></code> (UTC).
        You can still issue and download them here.
        <div class="mt-1 text-muted">
            Change it with <code>php cenim-app/db/setting.php</code> … or the Settings page (Phase 7).
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach (CertificateRepo::types() as $type): ?>
        <?php
        $template = $templates[$type] ?? [];
        $active   = (int) ($template['is_active'] ?? 0) === 1;
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="cn-card h-100">
                <div class="cn-card-head">
                    <i class="fa-solid fa-certificate text-muted"></i>
                    <h2><?= e(CertificateRepo::label($type)) ?></h2>
                    <span class="cn-badge <?= $active ? 'cn-badge-verified' : 'cn-badge-draft' ?> ms-auto">
                        <?= $active ? 'active' : 'off' ?>
                    </span>
                </div>
                <div class="cn-card-body">
                    <div class="d-flex align-items-baseline gap-3 mb-2">
                        <span>
                            <span class="cn-stat-value"><?= e((string) ($counts[$type] ?? 0)) ?></span>
                            <span class="cn-stat-label">issued</span>
                        </span>
                        <?php if (($pending[$type] ?? 0) > 0): ?>
                            <span class="cn-badge cn-badge-unpaid">
                                <?= e((string) $pending[$type]) ?> waiting
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="text-muted" style="font-size:12px">
                        <?php if ($type === CertificateRepo::TYPE_COMMITTEE): ?>
                            From the committee list (<?= e((string) $committee) ?> member(s)) — no payment required.
                        <?php elseif ($type === CertificateRepo::TYPE_AWARD): ?>
                            Issued manually per recipient using the form below.
                        <?php elseif ($type === CertificateRepo::TYPE_PARTICIPANT): ?>
                            Author marked as presenter of a paid non-presenter registration.
                        <?php elseif ($type === CertificateRepo::TYPE_PRESENTER): ?>
                            Authors marked as presenter of a paid registration.
                        <?php else: ?>
                            Every author (first &amp; co-authors) of a paid presenter registration.
                        <?php endif; ?>
                    </p>

                    <form method="post" action="<?= e(admin_url('certificate-template.php?type=' . $type)) ?>" class="mb-2">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="number_base">
                        <label class="form-label mb-1" style="font-size:11.5px;font-weight:600">Certificate number base</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="number_base" maxlength="120"
                                   value="<?= e((string) ($template['number_base'] ?? '')) ?>"
                                   placeholder="e.g. 1234/IT2.VII/HK.01.00/2026">
                            <button class="btn btn-outline-secondary" type="submit" title="Save — numbers become base-001, base-002, …">
                                <i class="fa-solid fa-floppy-disk"></i>
                            </button>
                        </div>
                    </form>

                    <div class="d-flex flex-wrap gap-1">
                        <?php if ($type !== CertificateRepo::TYPE_AWARD): ?>
                            <form method="post" action="<?= e(admin_url('certificate-issue.php')) ?>" class="m-0">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="type" value="<?= e($type) ?>">
                                <input type="hidden" name="scope" value="all">
                                <button type="submit" class="btn btn-sm btn-navy">
                                    <i class="fa-solid fa-bolt me-1"></i>Issue all
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (($counts[$type] ?? 0) > 0): ?>
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                               href="<?= e(admin_url('certificates-print.php?type=' . $type)) ?>"
                               title="All valid certificates of this type on one page — print or save as one PDF">
                                <i class="fa-solid fa-print me-1"></i>Print all
                            </a>
                        <?php endif; ?>

                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= e(admin_url('certificate-template.php?type=' . $type)) ?>">
                            <i class="fa-solid fa-pen-ruler me-1"></i>Template
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-award text-muted"></i>
        <h2>Issue an award certificate</h2>
    </div>
    <div class="cn-card-body">
        <form method="post" action="<?= e(admin_url('certificate-issue.php')) ?>" class="row g-2 align-items-end">
            <?= Csrf::field() ?>
            <input type="hidden" name="type" value="<?= e(CertificateRepo::TYPE_AWARD) ?>">
            <div class="col-md-4">
                <label class="form-label" for="award_author" style="font-size:12.5px">Recipient (paper ID / name)</label>
                <input type="text" class="form-control form-control-sm" id="award_author" name="author_query"
                       placeholder="Type at least 2 characters…" autocomplete="off">
                <input type="hidden" name="author_id" id="award_author_id">
                <div id="awardSuggestions" class="list-group position-absolute" style="z-index:30;max-width:340px"></div>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="award_title" style="font-size:12.5px">Award title</label>
                <input type="text" class="form-control form-control-sm" id="award_title" name="award_title"
                       maxlength="190" placeholder="e.g. Best Paper Award — Track: Intelligent Multimedia">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-navy w-100">
                    <i class="fa-solid fa-plus me-1"></i>Issue award
                </button>
            </div>
        </form>
    </div>
</div>

<div class="cn-card">
    <div class="cn-card-head flex-wrap">
        <i class="fa-solid fa-list text-muted"></i>
        <h2>Issued certificates</h2>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <select id="fType" class="form-select form-select-sm" style="width:auto">
                <option value="">All types</option>
                <?php foreach (CertificateRepo::types() as $type): ?>
                    <option value="<?= e($type) ?>"><?= e(CertificateRepo::label($type)) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="fState" class="form-select form-select-sm" style="width:auto">
                <option value="">Valid &amp; revoked</option>
                <option value="valid">Valid only</option>
                <option value="revoked">Revoked only</option>
            </select>
        </div>
    </div>
    <div class="cn-card-body">
        <div class="table-responsive">
            <table id="certificatesTable" class="table table-sm table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Issued</th>
                        <th class="text-center">Downloads</th>
                        <th>State</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Modal revoke -->
<div class="modal fade" id="revokeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= e(admin_url('certificate-revoke.php')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" id="revokeId">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:15px">Revoke certificate <span id="revokeCode"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:12.5px">
                    The record and its code stay in the system for the audit trail. The verification page will show it
                    as <b>not valid</b>, and the author can no longer download it.
                </p>
                <label class="form-label" for="revokeReason" style="font-size:12.5px">Reason</label>
                <input type="text" class="form-control" id="revokeReason" name="reason" maxlength="300"
                       placeholder="e.g. issued with a misspelled name" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-ban me-1"></i>Revoke
                </button>
            </div>
        </form>
    </div>
</div>
