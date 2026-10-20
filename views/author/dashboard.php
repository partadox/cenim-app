<?php
/**
 * @var array|null $edition
 * @var array|null $author        paper terpilih (baris authors) atau null
 * @var array|null $person        baris paper_authors milik orang yang login
 * @var string     $email
 * @var array      $papers        semua paper orang ini
 * @var array      $paperAuthors  semua author paper terpilih
 * @var array      $committee     baris panitia/reviewer orang ini
 * @var string     $tab           papers | committee
 * @var array|null $registration
 * @var array|null $invoice
 * @var array|null $receipt
 * @var bool       $loaAvailable
 * @var array      $certificates  sertifikat semua author paper terpilih (termasuk award)
 * @var array      $committeeCertificates
 * @var bool       $certificatesReleased
 * @var array      $steps
 * @var array|null $step0         confirmation form, null bila tidak berlaku
 * @var bool       $isParticipant peserta non-presenter (bukan paper)
 * @var array      $guests        orang yang diajak peserta (registration_attendees)
 * @var array      $guestCertificates
 */

use Cenim\Core\Csrf;
use Cenim\Domain\CertificateRepo;
use Cenim\Domain\RegistrationRepo;

$status      = (string) ($registration['status'] ?? RegistrationRepo::STATUS_DRAFT);
$displayName = (string) ($person['name'] ?? $committee[0]['name'] ?? $email);

/** Daftar tombol sertifikat (dipakai di kedua tab). */
$certificateList = static function (array $items, bool $released, string $from, string $emptyNote): void {
    if ($items === []) {
        echo '<div class="cn-empty"><i class="fa-regular fa-file-lines"></i>'
            . e($released ? $emptyNote : 'Certificates will be available after the conference.') . '</div>';
        return;
    }

    echo '<div class="d-flex flex-column gap-2">';
    foreach ($items as $certificate) {
        $revoked = ($certificate['revoked_at'] ?? null) !== null;
        echo '<div class="d-flex flex-wrap align-items-center gap-2 border rounded px-3 py-2" style="font-size:13px">'
            . '<i class="fa-solid fa-certificate" style="color:var(--cn-gold)"></i>'
            . '<div><b>' . e(CertificateRepo::label((string) $certificate['type'])) . ' certificate</b>'
            . '<div class="text-muted" style="font-size:11.5px">'
            . e((string) $certificate['recipient_name'])
            . (($certificate['number'] ?? '') !== '' ? ' &middot; No. ' . e((string) $certificate['number']) : '')
            . ' &middot; ' . e((string) $certificate['code']) . '</div></div>';

        if ($revoked) {
            echo '<span class="cn-badge cn-badge-rejected ms-auto">Revoked</span>';
        } else {
            echo '<a class="btn btn-sm btn-gold ms-auto" target="_blank" rel="noopener" href="'
                . e(author_url('certificate.php?id=' . (int) $certificate['id'] . '&from=' . $from)) . '">'
                . '<i class="fa-regular fa-eye me-1"></i>View &amp; download</a>';
        }
        echo '</div>';
    }
    echo '</div>';
};
?>

<div class="cn-card mb-3">
    <div class="cn-card-body d-flex flex-wrap align-items-center gap-3">
        <div class="cn-stat-icon"><i class="fa-solid fa-user"></i></div>
        <div>
            <div style="font-size:15px;font-weight:600;color:#101e41"><?= e($displayName) ?></div>
            <div class="text-muted" style="font-size:12.5px">
                <?= e($email) ?>
                <?php if (($person['affiliation'] ?? $committee[0]['affiliation'] ?? '') !== ''): ?>
                    &middot; <?= e((string) ($person['affiliation'] ?? $committee[0]['affiliation'])) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($edition !== null): ?>
            <span class="cn-badge cn-badge-submitted ms-auto">
                <i class="fa-regular fa-calendar me-1"></i><?= e($edition['name']) ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-tabs mb-3" style="font-size:13.5px">
    <?php if ($papers !== []): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'papers' ? 'active' : '' ?>" href="<?= e(author_url('index.php')) ?>">
                <?php $onlyParticipant = array_filter($papers, static fn (array $p): bool => !Cenim\Domain\ParticipantRepo::isParticipant($p)) === []; ?>
                <i class="fa-regular fa-file-lines me-1"></i><?= $onlyParticipant ? 'Registration' : 'Papers' ?>
                <?php if (!$onlyParticipant): ?><span class="badge text-bg-light border ms-1"><?= count($papers) ?></span><?php endif; ?>
            </a>
        </li>
    <?php endif; ?>
    <?php if ($committee !== []): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'committee' ? 'active' : '' ?>" href="<?= e(author_url('index.php?tab=committee')) ?>">
                <i class="fa-solid fa-people-group me-1"></i>Committee
            </a>
        </li>
    <?php endif; ?>
</ul>

<?php if ($tab === 'papers' && $author !== null): ?>

    <?php if (count($papers) > 1): ?>
        <form method="post" class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="select_paper">
            <label for="paper" class="text-muted" style="font-size:12.5px">You are an author of <?= count($papers) ?> papers:</label>
            <select class="form-select form-select-sm" id="paper" name="paper" style="max-width:520px" onchange="this.form.submit()">
                <?php $group = null; ?>
                <?php foreach ($papers as $paper): ?>
                    <?php $label = (string) ($paper['edition_name'] ?? '') . ((int) ($paper['edition_active'] ?? 1) === 1 ? '' : ' (archive)'); ?>
                    <?php if ($label !== $group): ?>
                        <?= $group !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($label) ?>">
                        <?php $group = $label; ?>
                    <?php endif; ?>
                    <option value="<?= (int) $paper['id'] ?>" <?= (int) $paper['id'] === (int) $author['id'] ? 'selected' : '' ?>>
                        <?= e($paper['paper_id'] . ' - ' . (Cenim\Domain\ParticipantRepo::isParticipant($paper) ? 'Participant registration (non-presenter)' : mb_strimwidth((string) ($paper['paper_title'] ?? ''), 0, 80, '…'))) ?>
                    </option>
                <?php endforeach; ?>
                <?= $group !== null ? '</optgroup>' : '' ?>
            </select>
            <noscript><button class="btn btn-sm btn-outline-secondary">Open</button></noscript>
        </form>
    <?php endif; ?>

    <?php if ($isParticipant): ?>
        <?php
        $partCertButton = static function (?array $certificate) use ($certificatesReleased): string {
            if ($certificate === null) {
                return '<span class="text-muted" style="font-size:11.5px">' . ($certificatesReleased ? 'After the registration is paid' : 'After the conference') . '</span>';
            }
            if (($certificate['revoked_at'] ?? null) !== null) {
                return '<span class="cn-badge cn-badge-rejected">Revoked</span>';
            }

            return '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="'
                . e(author_url('certificate.php?id=' . (int) $certificate['id'] . '&from=papers')) . '">'
                . '<i class="fa-solid fa-certificate me-1" style="color:var(--cn-gold)"></i>Attendance</a>';
        };
        $ownCertificate = null;
        foreach ($certificates as $certificate) {
            if ((string) $certificate['type'] === CertificateRepo::TYPE_PARTICIPANT) {
                $ownCertificate = $certificate;
            }
        }
        $guestCertificate = [];
        foreach ($guestCertificates as $certificate) {
            $guestCertificate[(int) $certificate['recipient_ref_id']] = $certificate;
        }
        ?>
        <div class="cn-card mb-3">
            <div class="cn-card-head">
                <i class="fa-solid fa-id-badge text-muted"></i>
                <h2>Participant <?= e((string) $author['paper_id']) ?></h2>
                <span class="cn-badge cn-badge-submitted">Non-presenter · offline</span>
                <span class="cn-badge <?= e(RegistrationRepo::statusBadgeClass($status)) ?> ms-auto">
                    Registration: <?= e(ucfirst($status)) ?>
                </span>
            </div>
            <div class="cn-card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="font-size:13px">
                        <thead>
                            <tr class="text-muted" style="font-size:11.5px">
                                <th>Attending</th><th>Affiliation</th><th class="text-end">Certificate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="--bs-table-bg:#fff9ea">
                                <td><b><?= e($displayName) ?></b> <span class="text-muted">(you)</span>
                                    <div class="text-muted" style="font-size:11.5px"><?= e($email) ?></div></td>
                                <td class="text-muted"><?= e((string) ($person['affiliation'] ?? '')) ?></td>
                                <td class="text-end"><?= $partCertButton($ownCertificate) ?></td>
                            </tr>
                            <?php foreach ($guests as $guest): ?>
                                <tr>
                                    <td><?= e((string) $guest['name']) ?>
                                        <div class="text-muted" style="font-size:11.5px"><?= e((string) ($guest['email'] ?? '')) ?></div></td>
                                    <td class="text-muted"><?= e((string) ($guest['affiliation'] ?? '')) ?></td>
                                    <td class="text-end"><?= $partCertButton($guestCertificate[(int) $guest['id']] ?? null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted mb-0 mt-2" style="font-size:11.5px">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    The people who come with you are added in the registration form. After the conference you can download
                    the attendance certificate of everyone listed here.
                </p>
            </div>
        </div>
    <?php else: ?>
    <div class="cn-card mb-3">
        <div class="cn-card-head">
            <i class="fa-regular fa-file-lines text-muted"></i>
            <h2>Paper <?= e((string) $author['paper_id']) ?></h2>
            <?php if (!empty($author['edition_name'])): ?>
                <span class="cn-badge <?= (int) ($author['edition_active'] ?? 1) === 1 ? 'cn-badge-submitted' : 'cn-badge-draft' ?>"><?= e((string) $author['edition_name']) ?><?= (int) ($author['edition_active'] ?? 1) === 1 ? '' : ' · archive' ?></span>
            <?php endif; ?>
            <span class="cn-badge <?= e(RegistrationRepo::statusBadgeClass($status)) ?> ms-auto">
                Registration: <?= e(ucfirst(str_replace('_', ' ', $status))) ?>
            </span>
        </div>
        <div class="cn-card-body">
            <?php if (!empty($author['paper_title'])): ?>
                <div style="font-size:14px;font-weight:600;color:#101e41" class="mb-2"><?= e($author['paper_title']) ?></div>
            <?php endif; ?>
            <?php
            $byPerson = [];
            $awards   = [];
            foreach ($certificates as $certificate) {
                if ((string) $certificate['type'] === CertificateRepo::TYPE_AWARD) {
                    $awards[] = $certificate;
                } else {
                    $byPerson[(int) $certificate['recipient_ref_id']][] = $certificate;
                }
            }
            $certButton = static function (array $certificate, string $label): string {
                if (($certificate['revoked_at'] ?? null) !== null) {
                    return '<span class="cn-badge cn-badge-rejected">' . e($label) . ' · revoked</span>';
                }

                return '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="'
                    . e($label . ' certificate · ' . (string) $certificate['code']) . '" href="'
                    . e(author_url('certificate.php?id=' . (int) $certificate['id'] . '&from=papers')) . '">'
                    . '<i class="fa-solid fa-certificate me-1" style="color:var(--cn-gold)"></i>' . e($label) . '</a>';
            };
            ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:13px">
                    <thead>
                        <tr class="text-muted" style="font-size:11.5px">
                            <th style="width:36px">#</th>
                            <th>Author</th>
                            <th>Affiliation</th>
                            <th class="text-end">Certificates</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paperAuthors as $pa): ?>
                            <?php $isMe = (int) $pa['id'] === (int) ($person['id'] ?? 0); ?>
                            <tr<?= $isMe ? ' style="--bs-table-bg:#fff9ea"' : '' ?>>
                                <td class="text-muted"><?= (int) $pa['position'] ?></td>
                                <td>
                                    <b><?= e((string) $pa['name']) ?></b>
                                    <?php if ((int) $pa['is_presenter'] === 1): ?>
                                        <span class="cn-badge cn-badge-submitted ms-1"><i class="fa-solid fa-person-chalkboard me-1"></i>Presenter</span>
                                    <?php endif; ?>
                                    <?php if ($isMe): ?><span class="text-muted">(you)</span><?php endif; ?>
                                    <?php if (($pa['email'] ?? '') !== ''): ?>
                                        <div class="text-muted" style="font-size:11.5px"><?= e((string) $pa['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= e((string) ($pa['affiliation'] ?? '')) ?></td>
                                <td class="text-end">
                                    <?php if (!empty($byPerson[(int) $pa['id']])): ?>
                                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                            <?php foreach ($byPerson[(int) $pa['id']] as $certificate): ?>
                                                <?= $certButton($certificate, CertificateRepo::label((string) $certificate['type'])) ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:11.5px">
                                            <?= $certificatesReleased ? 'After the registration is paid' : 'After the conference' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($awards !== []): ?>
                <div class="mt-3 mb-1" style="font-size:12.5px;font-weight:600;color:#101e41">
                    <i class="fa-solid fa-trophy me-1" style="color:var(--cn-gold)"></i>Awards
                </div>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($awards as $certificate): ?>
                        <div class="d-flex flex-wrap align-items-center gap-2 border rounded px-3 py-2" style="font-size:13px;background:#fffbf0">
                            <i class="fa-solid fa-award" style="color:var(--cn-gold)"></i>
                            <div>
                                <b><?= e((string) ($certificate['subject_title'] ?: 'Award')) ?></b>
                                <div class="text-muted" style="font-size:11.5px"><?= e((string) $certificate['recipient_name']) ?></div>
                            </div>
                            <div class="ms-auto"><?= $certButton($certificate, 'Certificate') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="text-muted mb-0 mt-2" style="font-size:11.5px">
                <i class="fa-solid fa-circle-info me-1"></i>
                Every author of this paper can fill in the registration and payment forms, sees the same documents,
                and can download the certificates of all authors. Each form shows who filled it in.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($status === RegistrationRepo::STATUS_REJECTED && ($registration['reject_reason'] ?? '') !== ''): ?>
        <div class="alert alert-danger" style="font-size:13px">
            <b><i class="fa-solid fa-circle-exclamation me-1"></i>Correction requested</b>
            <div class="mt-1"><?= nl2br(e((string) $registration['reject_reason'])) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($step0 !== null): ?>
        <?php $class0 = $step0['state'] === 'done' ? 'is-done' : 'is-active'; ?>
        <div class="cn-step <?= $class0 ?> mb-3 d-flex flex-wrap align-items-center gap-3">
            <div style="flex:1 1 320px">
                <div class="d-flex align-items-center gap-2">
                    <span class="cn-step-no">Step 0</span>
                    <?php if ($step0['state'] === 'done'): ?>
                        <i class="fa-solid fa-circle-check" style="color:#1a9c62"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-clipboard-check" style="color:var(--cn-navy)"></i>
                    <?php endif; ?>
                </div>
                <p class="cn-step-title">Confirmation Form</p>
                <p class="cn-step-note mb-0"><?= e($step0['note']) ?></p>
                <?php if (!empty($step0['filled_by'])): ?>
                    <p class="cn-step-note mb-0 mt-1"><i class="fa-solid fa-user-pen me-1"></i>Filled by <?= e((string) $step0['filled_by']) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($step0['url']) && !empty($step0['cta'])): ?>
                <a href="<?= e((string) $step0['url']) ?>" target="_blank" rel="noopener"
                   class="btn btn-sm <?= $step0['state'] === 'active' ? 'btn-gold' : 'btn-outline-secondary' ?>">
                    <?= e((string) $step0['cta']) ?><i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="cn-steps<?= count($steps) === 4 ? ' cn-steps-4' : '' ?> mb-3">
        <?php foreach ($steps as $step): ?>
            <?php $class = match ($step['state']) { 'done' => 'is-done', 'active' => 'is-active', default => '' }; ?>
            <div class="cn-step <?= $class ?> d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="cn-step-no">Step <?= e((string) $step['no']) ?></span>
                    <?php if ($step['state'] === 'done'): ?>
                        <i class="fa-solid fa-circle-check" style="color:#1a9c62"></i>
                    <?php elseif ($step['state'] === 'active'): ?>
                        <i class="fa-solid <?= e($step['icon']) ?>" style="color:var(--cn-navy)"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-lock text-muted"></i>
                    <?php endif; ?>
                </div>
                <p class="cn-step-title"><?= e($step['title']) ?></p>
                <p class="cn-step-note"><?= e($step['note']) ?></p>
                <?php if (!empty($step['filled_by'])): ?>
                    <p class="cn-step-note mb-2"><i class="fa-solid fa-user-pen me-1"></i>Filled by <?= e((string) $step['filled_by']) ?></p>
                <?php endif; ?>
                <?php if (!empty($step['url']) && !empty($step['cta'])): ?>
                    <a href="<?= e((string) $step['url']) ?>" target="_blank" rel="noopener"
                       class="btn btn-sm <?= $step['state'] === 'active' ? 'btn-gold' : 'btn-outline-secondary' ?> mt-auto align-self-start">
                        <?= e((string) $step['cta']) ?><i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="cn-card">
        <div class="cn-card-head">
            <i class="fa-regular fa-folder-open text-muted"></i>
            <h2>Documents</h2>
        </div>
        <div class="cn-card-body" style="font-size:13px">
            <?php
            $docs = array_values(array_filter([
                $isParticipant ? null : ['Letter of Acceptance', 'fa-envelope-open-text', $loaAvailable ? author_url('loa.php') : null,
                    $loaAvailable ? 'Available' : 'Available once the paper is accepted'],
                ['Invoice', 'fa-file-invoice', $invoice !== null ? author_url('invoice-pdf.php') : null,
                    $invoice !== null ? (string) $invoice['number'] : 'Not issued yet'],
                ['Receipt', 'fa-receipt', $receipt !== null ? author_url('receipt-pdf.php') : null,
                    $receipt !== null ? (string) $receipt['number'] : 'Issued after payment is confirmed'],
            ]));
            ?>
            <div class="row g-2">
                <?php foreach ($docs as [$label, $icon, $url, $note]): ?>
                    <div class="col-md-<?= count($docs) === 2 ? '6' : '4' ?>">
                        <div class="d-flex align-items-center gap-2 border rounded px-3 py-2 h-100">
                            <i class="fa-solid <?= e($icon) ?> text-muted" style="width:18px"></i>
                            <div><b><?= e($label) ?></b><div class="text-muted" style="font-size:11.5px"><?= e($note) ?></div></div>
                            <?php if ($url !== null): ?>
                                <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e($url) ?>" target="_blank" rel="noopener">Open<i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></a>
                            <?php else: ?>
                                <i class="fa-solid fa-lock text-muted ms-auto"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'committee' && $committee !== []): ?>

    <div class="cn-card mb-3">
        <div class="cn-card-head">
            <i class="fa-solid fa-people-group text-muted"></i>
            <h2>Your committee roles</h2>
        </div>
        <div class="cn-card-body d-flex flex-wrap gap-2" style="font-size:13px">
            <?php foreach ($committee as $row): ?>
                <span class="border rounded px-2 py-1">
                    <b><?= e((string) ($row['role'] ?: 'Committee')) ?></b>
                    <?php if (!empty($row['edition_name'])): ?><span class="text-muted">&middot; <?= e((string) $row['edition_name']) ?></span><?php endif; ?>
                    <?php if (($row['division'] ?? '') !== ''): ?><span class="text-muted">&middot; <?= e((string) $row['division']) ?></span><?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cn-card">
        <div class="cn-card-head">
            <i class="fa-solid fa-certificate text-muted"></i>
            <h2>Committee certificates</h2>
        </div>
        <div class="cn-card-body">
            <?php $certificateList($committeeCertificates, $certificatesReleased, 'committee', 'No certificate has been issued for your role yet.'); ?>
        </div>
    </div>

<?php endif; ?>
