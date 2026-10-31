<?php
/**
 * @var array|null $edition
 * @var array      $stats
 * @var array<int,array{label:string,people:int,papers:int}> $byCountry
 * @var array<int,array{label:string,people:int,papers:int}> $byAffiliation
 * @var array      $applied
 * @var array      $pending
 */

$people = max(1, (int) $stats['people']);

$paperCards = [
    ['label' => 'Papers',          'value' => $stats['papers'],       'icon' => 'fa-file-lines',
     'note' => ($stats['papers'] > 0 ? round($stats['accepted'] / $stats['papers'] * 100) . '% accepted · ' : '') . $stats['withdrawn'] . ' withdrawn', 'href' => admin_url('authors.php')],
    ['label' => 'Accepted papers', 'value' => $stats['accepted'],     'icon' => 'fa-circle-check', 'note' => '', 'href' => admin_url('authors.php')],
    ['label' => 'Authors',         'value' => $stats['people'],       'icon' => 'fa-users',
     'note' => $stats['author_slots'] . ' authorship(s)' . ($stats['no_email'] > 0 ? ' · ' . $stats['no_email'] . ' without email' : ''), 'href' => admin_url('authors.php')],
    ['label' => 'Countries',       'value' => $stats['countries'],    'icon' => 'fa-earth-asia', 'note' => 'of the authors', 'href' => '#byCountry'],
    ['label' => 'Affiliations',    'value' => $stats['affiliations'], 'icon' => 'fa-building-columns', 'note' => 'distinct names', 'href' => '#byAffiliation'],
];

$flowCards = [
    ['label' => 'Awaiting review',   'value' => $stats['submitted'],    'icon' => 'fa-hourglass-half',       'href' => admin_url('registrations.php')],
    ['label' => 'Verified',          'value' => $stats['verified'],     'icon' => 'fa-user-check',           'href' => admin_url('registrations.php')],
    ['label' => 'Unpaid invoices',   'value' => $stats['unpaid'],       'icon' => 'fa-file-invoice-dollar',  'href' => admin_url('invoices.php')],
    ['label' => 'Payments to check', 'value' => $stats['pending_pay'],  'icon' => 'fa-money-check-dollar',   'href' => admin_url('payments.php')],
    ['label' => 'Paid',              'value' => $stats['paid'],         'icon' => 'fa-receipt',              'href' => admin_url('invoices.php')],
    ['label' => 'Certificates',      'value' => $stats['certificates'], 'icon' => 'fa-certificate',          'href' => admin_url('certificates.php')],
];

$statusColor = ['accepted' => '#1a9c62', 'pending' => '#14509b', 'rejected' => '#a3231f', 'withdrawn' => '#8a94a8'];

/** Daftar batang horizontal (jumlah orang), dengan jumlah paper di kanan. */
$bars = static function (array $rows, int $total): string {
    if ($rows === []) {
        return '<div class="cn-empty"><i class="fa-solid fa-inbox"></i>No data yet — import the papers first.</div>';
    }
    $max = max(array_map(static fn (array $r): int => (int) $r['people'], $rows));
    $out = '<ul class="list-unstyled mb-0 cn-bars">';
    foreach ($rows as $r) {
        $pct  = $max > 0 ? round((int) $r['people'] / $max * 100) : 0;
        $out .= '<li>'
            . '<div class="d-flex gap-2" style="font-size:12.5px"><span class="text-truncate" title="' . e($r['label']) . '">' . e($r['label']) . '</span>'
            . '<span class="ms-auto text-nowrap"><b>' . (int) $r['people'] . '</b> <span class="text-muted">author(s) · ' . (int) $r['papers'] . ' paper(s) · '
            . round((int) $r['people'] / $total * 100) . '%</span></span></div>'
            . '<div class="cn-bar"><span style="width:' . $pct . '%"></span></div>'
            . '</li>';
    }

    return $out . '</ul>';
};
?>

<?php if ($edition === null): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <div>
            <strong>Belum ada edisi aktif.</strong>
            Jalankan <code>php cenim-app/db/migrate.php</code> lalu
            <code>php cenim-app/db/seed.php cenim2026</code>.
        </div>
    </div>
<?php endif; ?>

<h6 class="text-muted text-uppercase mb-2" style="font-size:11px;letter-spacing:.08em">Papers &amp; authors</h6>
<div class="row g-3 mb-4">
    <?php foreach ($paperCards as $card): ?>
        <div class="col-6 col-md-4 col-xl">
            <a class="cn-card h-100 d-block text-reset text-decoration-none" href="<?= e($card['href']) ?>">
                <div class="cn-card-body cn-stat">
                    <span class="cn-stat-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></span>
                    <span>
                        <span class="cn-stat-value d-block"><?= e((string) $card['value']) ?></span>
                        <span class="cn-stat-label"><?= e($card['label']) ?></span>
                        <?php if ($card['note'] !== ''): ?>
                            <span class="d-block text-muted" style="font-size:11px"><?= e($card['note']) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<h6 class="text-muted text-uppercase mb-2" style="font-size:11px;letter-spacing:.08em">Registration &amp; payment</h6>
<div class="row g-3 mb-4">
    <?php foreach ($flowCards as $card): ?>
        <div class="col-6 col-md-4 col-xxl-2">
            <a class="cn-card h-100 d-block text-reset text-decoration-none" href="<?= e($card['href']) ?>">
                <div class="cn-card-body cn-stat">
                    <span class="cn-stat-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></span>
                    <span>
                        <span class="cn-stat-value d-block"><?= e((string) $card['value']) ?></span>
                        <span class="cn-stat-label"><?= e($card['label']) ?></span>
                    </span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="cn-card h-100" id="byCountry">
            <div class="cn-card-head">
                <i class="fa-solid fa-earth-asia text-muted"></i>
                <h2>Authors by country</h2>
                <span class="text-muted ms-auto" style="font-size:12px"><?= e((string) $stats['countries']) ?> countries</span>
            </div>
            <div class="cn-card-body"><?= $bars($byCountry, $people) ?></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="cn-card h-100" id="byAffiliation">
            <div class="cn-card-head">
                <i class="fa-solid fa-building-columns text-muted"></i>
                <h2>Top affiliations</h2>
                <span class="text-muted ms-auto" style="font-size:12px"><?= e((string) $stats['affiliations']) ?> distinct</span>
            </div>
            <div class="cn-card-body">
                <?= $bars($byAffiliation, $people) ?>
                <p class="text-muted mt-2 mb-0" style="font-size:11px">
                    Counted as written in EDAS, so spelling variants (e.g. "Institut Teknologi Sepuluh Nopember" and
                    "Sepuluh Nopember Institute of Technology") appear separately.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="cn-card h-100">
            <div class="cn-card-head">
                <i class="fa-solid fa-chart-pie text-muted"></i>
                <h2>Paper status</h2>
            </div>
            <div class="cn-card-body">
                <?php if ($stats['papers'] === 0): ?>
                    <div class="cn-empty"><i class="fa-solid fa-inbox"></i>No papers yet — import the EDAS export in Authors &rarr; Import.</div>
                <?php else: ?>
                    <div class="d-flex rounded overflow-hidden mb-3" style="height:14px">
                        <?php foreach ($stats['paper_statuses'] as $status => $n): ?>
                            <span title="<?= e(ucfirst((string) $status) . ': ' . $n) ?>"
                                  style="width:<?= e((string) round($n / $stats['papers'] * 100, 2)) ?>%;background:<?= e($statusColor[$status] ?? '#8a94a8') ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-3" style="font-size:13px">
                        <?php foreach ($stats['paper_statuses'] as $status => $n): ?>
                            <span>
                                <i class="fa-solid fa-square me-1" style="color:<?= e($statusColor[$status] ?? '#8a94a8') ?>"></i>
                                <?= e(ucfirst((string) $status)) ?> <b><?= e((string) $n) ?></b>
                                <span class="text-muted">(<?= e((string) round($n / $stats['papers'] * 100)) ?>%)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted mt-3 mb-0" style="font-size:12px">
                        On average <?= e(number_format($stats['author_slots'] / $stats['papers'], 1)) ?> authors per paper.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="cn-card h-100">
            <div class="cn-card-head">
                <i class="fa-solid fa-database text-muted"></i>
                <h2>Database migrations</h2>
                <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e(admin_url('health.php')) ?>">
                    <i class="fa-solid fa-heart-pulse me-1"></i>Health
                </a>
            </div>
            <div class="cn-card-body">
                <?php if ($applied === [] && $pending === []): ?>
                    <div class="cn-empty">
                        <i class="fa-solid fa-inbox"></i>
                        Belum ada migrasi.
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0" style="font-size:13px">
                        <?php foreach ($applied as $row): ?>
                            <li class="d-flex align-items-center gap-2 py-1">
                                <i class="fa-solid fa-check" style="color:#1a9c62"></i>
                                <code><?= e($row['filename']) ?></code>
                                <span class="text-muted ms-auto"><?= e(fmt_dt($row['applied_at'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php foreach ($pending as $name): ?>
                            <li class="d-flex align-items-center gap-2 py-1">
                                <i class="fa-solid fa-clock text-warning"></i>
                                <code><?= e($name) ?></code>
                                <span class="text-muted ms-auto">pending</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .cn-bars li + li { margin-top: 10px; }
    .cn-bar { height: 6px; background: #eef1f7; border-radius: 3px; margin-top: 3px; overflow: hidden; }
    .cn-bar > span { display: block; height: 100%; background: var(--cn-navy); border-radius: 3px; }
</style>
