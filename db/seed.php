<?php
/**
 * Seed data awal satu edisi konferensi. Idempoten — aman dijalankan berulang,
 * data yang sudah ada tidak ditimpa.
 *
 *   php cenim-app/db/seed.php cenim2026
 *   php cenim-app/db/seed.php 2027
 *
 * Nilai biaya & timeline diambil dari website CENIM 2026 yang berjalan sekarang
 * (registration.php dan bagian Schedules di index.php).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan dari CLI.\n");
}

require __DIR__ . '/../bootstrap.php';

use Cenim\Core\Db;

$arg  = $argv[1] ?? 'cenim2026';
$year = (int) preg_replace('/\D+/', '', $arg);
if ($year < 2018 || $year > 2100) {
    exit("Tahun edisi tidak valid: {$arg}\n");
}

if (!Db::tableExists('editions')) {
    exit("Tabel belum ada. Jalankan dulu: php cenim-app/db/migrate.php\n");
}

$ts = now();

// ── Edisi ───────────────────────────────────────────────────────────────────
$edition = Db::one('SELECT * FROM editions WHERE year = ?', [$year]);

if ($edition === null) {
    // Edisi baru menjadi satu-satunya yang aktif; edisi sebelumnya otomatis
    // pindah ke "Previous Conference" di landing (PreviousConferenceRepo::sync).
    $previous = Db::all('SELECT name FROM editions WHERE is_active = 1 AND year < ?', [$year]);
    Db::run('UPDATE editions SET is_active = 0 WHERE year < ?', [$year]);
    foreach ($previous as $row) {
        echo "  - {$row['name']} dinonaktifkan (masuk Previous Conference)\n";
    }

    $editionId = Db::insert('editions', [
        'year'                   => $year,
        'name'                   => 'CENIM ' . $year,
        'slug'                   => 'cenim-' . $year,
        'is_active'              => 1,
        'timezone'               => (string) env('APP_TZ', 'Asia/Jakarta'),
        'conference_start'       => $year === 2026 ? $year . '-11-24' : null,
        'conference_end'         => $year === 2026 ? $year . '-11-25' : null,
        'certificate_release_at' => $year === 2026 ? $year . '-11-25 12:00:00' : null,
        'created_at'             => $ts,
    ]);
    echo "  + edition CENIM {$year}\n";
} else {
    $editionId = (int) $edition['id'];
    echo "  = edition CENIM {$year} sudah ada (id {$editionId})\n";
}

// ── Settings ────────────────────────────────────────────────────────────────
$settings = [
    'registration_open'    => true,
    'payment_open'         => true,
    'registration_period'  => ['start' => $year . '-09-26', 'end' => $year . '-10-31'],
    'extra_page_fee'       => ['USD' => 50, 'IDR' => 500000],
    'included_pages'       => 6,
    'extra_paper_percent'  => 50,
    'invoice_due_days'     => 14,
    'invoice_note'         => 'Payments must be made in full. Any bank charges or transfer fees are borne by the registrant. No refund will be provided.',
    'receipt_note'         => 'Thank you for your registration.',
    'bank_accounts'        => [
        ['currency' => 'IDR', 'bank_name' => '', 'account_no' => '', 'account_name' => '', 'swift' => ''],
        ['currency' => 'USD', 'bank_name' => '', 'account_no' => '', 'account_name' => '', 'swift' => ''],
    ],
    'contact_email'        => '',
    'contact_whatsapp'     => '',
    'signatory_name'       => '',
    'signatory_title'      => 'General Chair, CENIM ' . $year,
];

$newSettings = 0;
foreach ($settings as $key => $value) {
    $exists = Db::value('SELECT 1 FROM settings WHERE edition_id = ? AND "key" = ?', [$editionId, $key]);
    if ($exists !== null) {
        continue;
    }
    Db::insert('settings', [
        'edition_id' => $editionId,
        'key'        => $key,
        'value_json' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'updated_at' => $ts,
    ]);
    $newSettings++;
}
echo "  + {$newSettings} setting baru (" . count($settings) . " total didefinisikan)\n";

// ── Kategori biaya ──────────────────────────────────────────────────────────
// [code, label, currency, amount, is_student, is_ieee, is_presenter]
$fees = [
    ['usd_ieee',               'IEEE Member (International)',             'USD', 350,     0, 1, 1],
    ['usd_non_ieee',           'Non-IEEE Member (International)',         'USD', 400,     0, 0, 1],
    ['usd_student_ieee',       'Student IEEE Member (International)',     'USD', 300,     1, 1, 1],
    ['usd_student_non_ieee',   'Student Non-IEEE Member (International)', 'USD', 350,     1, 0, 1],
    ['usd_non_presenter',      'Non-presenter (International)',           'USD', 100,     0, 0, 0],
    ['idr_ieee',               'IEEE Member (Indonesia)',                 'IDR', 3500000, 0, 1, 1],
    ['idr_non_ieee',           'Non-IEEE Member (Indonesia)',             'IDR', 4000000, 0, 0, 1],
    ['idr_student_ieee',       'Student IEEE Member (Indonesia)',         'IDR', 3000000, 1, 1, 1],
    ['idr_student_non_ieee',   'Student Non-IEEE Member (Indonesia)',     'IDR', 3500000, 1, 0, 1],
    ['idr_non_presenter',      'Non-presenter (Indonesia)',               'IDR', 1000000, 0, 0, 0],
];

$newFees = 0;
foreach ($fees as $i => [$code, $label, $currency, $amount, $isStudent, $isIeee, $isPresenter]) {
    if (Db::value('SELECT 1 FROM fee_categories WHERE edition_id = ? AND code = ?', [$editionId, $code]) !== null) {
        continue;
    }
    Db::insert('fee_categories', [
        'edition_id'   => $editionId,
        'code'         => $code,
        'label'        => $label,
        'currency'     => $currency,
        'amount'       => $amount,
        'is_student'   => $isStudent,
        'is_ieee'      => $isIeee,
        'is_presenter' => $isPresenter,
        'sort'         => ($i + 1) * 10,
        'created_at'   => $ts,
    ]);
    $newFees++;
}
echo "  + {$newFees} kategori biaya\n";

// ── Timeline ────────────────────────────────────────────────────────────────
$timeline = $year === 2026 ? [
    ['Open for Submission',        '2 April 2026'],
    ['Submission Deadline',        '(Final Extend) 14 Sept 2026', ['12 August 2026', '(1st Extend) 31 August 2026']],
    ['Notification of Acceptance', '15 October 2026'],
    ['Final Manuscript',           '25 October 2026'],
    ['Registration Due',           '31 October 2026'],
    ['Workshop Registration',      '14 November 2026'],
    ['Conference Day',             '24 - 25 November 2026'],
] : [];

$newTimeline = 0;
foreach ($timeline as $i => $row) {
    [$title, $date] = $row;
    if (Db::value('SELECT 1 FROM timeline_items WHERE edition_id = ? AND title = ?', [$editionId, $title]) !== null) {
        continue;
    }
    Db::insert('timeline_items', [
        'edition_id'        => $editionId,
        'sort'              => ($i + 1) * 10,
        'title'             => $title,
        'current_date_text' => $date,
        'history_json'      => json_encode($row[2] ?? [], JSON_UNESCAPED_UNICODE),
        'created_at'        => $ts,
    ]);
    $newTimeline++;
}
echo "  + {$newTimeline} item timeline\n";

// ── Author contoh (khusus development) ──────────────────────────────────────
// Hanya jalan dengan flag --demo DAN APP_ENV=local, supaya tidak pernah
// mengotori database production.
if (in_array('--demo', $argv, true)) {
    if (!is_local()) {
        echo "  ! --demo diabaikan: hanya boleh di APP_ENV=local\n";
    } else {
        $demo = [
            ['1571000001', 'Deep Learning for Cardiac Signal Classification', 'Arta Kusuma', 'artakusuma@its.ac.id',       'Institut Teknologi Sepuluh Nopember', 'Indonesia',   8, 1],
            ['1571000002', 'Resilient SDN Routing under Link Failure',        'Siti Rahma',  'siti@example.ac.id',    'Universitas Brawijaya',              'Indonesia',   6, 1],
            ['1571000003', 'Edge Multimedia Caching for Smart Campus',        'John Tan',    'john.tan@example.com',  'Nanyang Technological University',   'Singapore',  10, 1],
        ];

        $newAuthors = 0;
        foreach ($demo as [$paperId, $paperTitle, $name, $email, $affiliation, $country, $pages, $isPresenter]) {
            if (Db::value('SELECT 1 FROM authors WHERE edition_id = ? AND paper_id = ?', [$editionId, $paperId]) !== null) {
                continue;
            }
            // Lewat AuthorRepo supaya first author ikut tercatat di paper_authors.
            Cenim\Domain\AuthorRepo::create($editionId, [
                'paper_id'          => $paperId,
                'paper_title'       => $paperTitle,
                'first_author_name' => $name,
                'email'             => $email,
                'affiliation'       => $affiliation,
                'country'           => $country,
                'total_pages'       => $pages,
                'is_presenter'      => $isPresenter,
            ], 'demo');
            $newAuthors++;
        }

        // Co-author paper pertama + seorang reviewer (domain example.test tidak
        // pernah menerima email; di lokal kode OTP dibaca dari logs/mail/).
        $first = Cenim\Domain\AuthorRepo::findByPaperId($editionId, '1571000001');
        if ($first !== null && Cenim\Domain\PaperAuthorRepo::findInPaper((int) $first['id'], 'coauthor@example.test') === null) {
            Cenim\Domain\PaperAuthorRepo::create((int) $first['id'], [
                'name' => 'Dewi Coauthor', 'email' => 'coauthor@example.test',
                'affiliation' => 'Universitas Contoh', 'country' => 'Indonesia',
            ]);
        }
        if (Cenim\Domain\CommitteeRepo::forEmail($editionId, 'reviewer@example.test') === []) {
            Cenim\Domain\CommitteeRepo::create($editionId, [
                'name' => 'Rudi Reviewer', 'email' => 'reviewer@example.test', 'affiliation' => 'Politeknik Contoh',
                'role' => 'Reviewer', 'show_on_landing' => 0, 'sort' => 900,
            ]);
        }

        echo "  + {$newAuthors} paper contoh, co-author coauthor@example.test, reviewer reviewer@example.test\n";
        echo "    (login: email → kode OTP di <STORAGE_PATH>/logs/mail/*.txt)\n";
    }
}

echo "Seed selesai untuk CENIM {$year}." . PHP_EOL;
