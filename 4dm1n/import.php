<?php
/**
 * Import CSV generik, tiga langkah (unggah → petakan kolom → laporan):
 *
 *   ?kind=paper_authors  semua author per paper (nama, email, instansi, negara)
 *                        — satu baris per author, dicocokkan dengan paper ID
 *   ?kind=committee      panitia & reviewer (nama, email, instansi, peran)
 *
 * Import paper (first author, judul, halaman, status) tetap di authors-import.php.
 * File CSV disimpan sementara di storage/tmp dan dihapus setelah selesai/batal.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\CommitteeRepo;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Support\Csv;

const GENERIC_IMPORT_MAX_ISSUES = 200;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0) {
    Session::flash('error', 'No active edition. Run the seeder first.');
    Response::redirect(admin_url('index.php'));
}

// ── Definisi jenis import ───────────────────────────────────────────────────
$kinds = [
    'paper_authors' => [
        'title'    => 'Import author list',
        'active'   => 'authors',
        'back'     => admin_url('authors.php'),
        'fields'   => PaperAuthorRepo::FIELDS,
        'required' => PaperAuthorRepo::REQUIRED_FIELDS,
        'keyLabel' => 'Paper ID',
        'help'     => 'One row per author, including the first author. Authors are matched to papers by <b>paper ID</b> '
            . 'and to existing authors by <b>email</b> (or name when the email is empty), so re-importing is safe. '
            . 'Without a position column, the order of rows in the file is used as the author order. '
            . 'A paper ID that does not exist yet is created when the file also has a paper title column.',
        'options'  => [],
    ],
    'committee' => [
        'title'    => 'Import committee & reviewers',
        'active'   => 'committee',
        'back'     => admin_url('committee.php'),
        'fields'   => [
            'name'        => ['name', 'full name', 'nama', 'reviewer', 'reviewer name'],
            'email'       => ['email', 'e-mail', 'email address', 'mail'],
            'affiliation' => ['affiliation', 'institution', 'organization', 'university', 'instansi', 'institusi'],
            'role'        => ['role', 'position', 'jabatan', 'peran', 'type'],
            'division'    => ['division', 'committee', 'section', 'divisi', 'bidang', 'group'],
        ],
        'required' => ['name'],
        'keyLabel' => 'Name',
        'help'     => 'One row per person. Rows are matched to existing members by <b>email</b> (or name when the email is empty). '
            . 'The <b>email</b> is what they use to sign in (6-digit code) and download their certificate — rows without '
            . 'an email are imported but cannot sign in. Rows without a role or division get the values chosen below. '
            . 'Names and affiliations are capitalised automatically (INSTITUT TEKNOLOGI → Institut Teknologi; acronyms such as ITS or IEEE are kept).',
        'options'  => ['show_on_landing' => 'Show imported people in the committee list on the landing page'],
        // Isian untuk baris yang kolom role/division-nya kosong atau tidak dipetakan
        // (mis. daftar TPC dari EDAS: nama, instansi, negara, email saja).
        'defaults' => [
            'role'     => ['label' => 'Role for rows without one', 'value' => 'Reviewer', 'list' => []],
            'division' => ['label' => 'Division for rows without one', 'value' => '', 'list' => CommitteeRepo::divisions($editionId)],
        ],
    ],
];

$kind = (string) input('kind', 'paper_authors');
if (!isset($kinds[$kind])) {
    Response::abort(404, 'Unknown import type.');
}
$def        = $kinds[$kind];
$sessionKey = 'import_' . $kind;
$selfUrl    = admin_url('import.php?kind=' . $kind);
$action     = (string) input('action', '');

$discard = static function () use ($sessionKey): void {
    $state = Session::get($sessionKey);
    if (is_array($state) && isset($state['path']) && is_file((string) $state['path'])) {
        @unlink((string) $state['path']);
    }
    Session::forget($sessionKey);
};

$render = static function (string $step, array $data) use ($def, $kind, $edition): never {
    View::render('admin/import', $data + [
        'title'   => $def['title'],
        'subtitle' => match ($step) { 'upload' => 'Step 1 of 3 — upload the CSV file', 'map' => 'Step 2 of 3 — map the columns', default => 'Step 3 of 3 — result' },
        'active'  => $def['active'],
        'edition' => $edition,
        'step'    => $step,
        'kind'    => $kind,
        'def'     => $def,
    ], 'admin');
    exit;
};

// ── Langkah 3: jalankan import ──────────────────────────────────────────────
if (is_post() && $action === 'confirm') {
    Csrf::verify();

    $state = Session::get($sessionKey);
    if (!is_array($state) || !is_file((string) ($state['path'] ?? ''))) {
        Session::flash('error', 'The uploaded file has expired. Please upload it again.');
        Response::redirect($selfUrl);
    }

    $rawMap  = $_POST['map'] ?? [];
    $mapping = [];
    foreach (array_keys($def['fields']) as $field) {
        $index = isset($rawMap[$field]) ? (int) $rawMap[$field] : -1;
        $mapping[$field] = $index >= 0 ? $index : null;
    }

    $missing = array_values(array_filter($def['required'], static fn (string $f): bool => $mapping[$f] === null));
    if ($missing !== []) {
        Session::flash('error', 'These required fields are not mapped: ' . implode(', ', $missing) . '.');
        Response::redirect($selfUrl);
    }

    try {
        $csv = Csv::read((string) $state['path']);
    } catch (Throwable $e) {
        Logger::error('Import ' . $kind . ': gagal membaca CSV', ['error' => $e->getMessage()]);
        $discard();
        Session::flash('error', 'The CSV could not be read: ' . $e->getMessage());
        Response::redirect($selfUrl);
    }

    $stats  = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'warnings' => 0, 'papers_created' => 0];
    $issues = [];
    $options = ['show_on_landing' => input_bool('show_on_landing')];
    foreach (array_keys($def['defaults'] ?? []) as $field) {
        $options['default_' . $field] = mb_substr(trim((string) input('default_' . $field, '')), 0, 190);
    }

    Db::transaction(static function () use ($csv, $mapping, $kind, $editionId, $options, &$stats, &$issues): void {
        $orderInPaper = [];

        foreach ($csv['rows'] as $i => $row) {
            $line = $i + 2;
            $data = [];
            foreach ($mapping as $field => $index) {
                if ($index !== null) {
                    $data[$field] = trim((string) ($row[$index] ?? ''));
                }
            }

            $key  = (string) ($kind === 'paper_authors' ? ($data['paper_id'] ?? '') : ($data['name'] ?? ''));
            $note = static function (string $level, string $message) use (&$issues, $line, $key): void {
                if (count($issues) < GENERIC_IMPORT_MAX_ISSUES) {
                    $issues[] = ['line' => $line, 'key' => $key, 'level' => $level, 'message' => $message];
                }
            };

            if (implode('', $data) === '') {
                continue; // baris kosong
            }

            if ($kind === 'paper_authors') {
                $errors = PaperAuthorRepo::validate($data);
                if (($data['paper_id'] ?? '') === '') {
                    $errors[] = 'Paper ID is empty.';
                }
                if ($errors !== []) {
                    $stats['skipped']++;
                    $note('error', implode(' ', $errors));
                    continue;
                }

                $paper = AuthorRepo::findByPaperId($editionId, (string) $data['paper_id']);
                if ($paper === null) {
                    if (($data['paper_title'] ?? '') === '') {
                        $stats['skipped']++;
                        $note('error', 'Paper ID not found. Import the papers first, or add a paper title column to create it.');
                        continue;
                    }
                    $paperId = AuthorRepo::create($editionId, [
                        'paper_id'          => $data['paper_id'],
                        'paper_title'       => $data['paper_title'],
                        'first_author_name' => $data['name'],
                        'email'             => $data['email'] ?? '',
                        'affiliation'       => $data['affiliation'] ?? null,
                        'country'           => $data['country'] ?? null,
                        'paper_status'      => 'accepted',
                    ], 'csv-authors-' . gmdate('Ymd'));
                    $paper = AuthorRepo::find($paperId);
                    $stats['papers_created']++;
                }

                $pid = (int) $paper['id'];
                $orderInPaper[$pid] = ($orderInPaper[$pid] ?? 0) + 1;
                if (($data['position'] ?? '') === '') {
                    $data['position'] = (string) $orderInPaper[$pid];
                }

                $result = PaperAuthorRepo::upsertForPaper($pid, $data);
                $stats[$result['action']]++;

                if (($data['email'] ?? '') === '') {
                    $stats['warnings']++;
                    $note('warning', 'No email — this author cannot sign in.');
                }
                continue;
            }

            // ── committee ──
            $errors = CommitteeRepo::validate($data);
            if ($errors !== []) {
                $stats['skipped']++;
                $note('error', implode(' ', $errors));
                continue;
            }

            $email    = mb_strtolower((string) ($data['email'] ?? ''));
            $existing = $email !== ''
                ? (CommitteeRepo::forEmail($editionId, $email)[0] ?? null)
                : Db::one('SELECT * FROM committee WHERE edition_id = ? AND LOWER(name) = ? LIMIT 1', [$editionId, mb_strtolower((string) $data['name'])]);

            $data['email'] = $email;
            if (($data['role'] ?? '') === '') {
                $data['role'] = $existing['role'] ?? ($options['default_role'] !== '' ? $options['default_role'] : 'Reviewer');
            }
            if (($data['division'] ?? '') === '' && $existing === null && $options['default_division'] !== '') {
                $data['division'] = $options['default_division'];
            }

            if ($existing !== null) {
                CommitteeRepo::update((int) $existing['id'], array_filter($data, static fn ($v) => $v !== '') + $existing);
                $stats['updated']++;
            } else {
                CommitteeRepo::create($editionId, $data + [
                    'show_on_landing' => $options['show_on_landing'],
                    'sort'            => 900,
                ]);
                $stats['inserted']++;
            }

            if ($email === '') {
                $stats['warnings']++;
                $note('warning', 'No email — this person cannot sign in to download the certificate.');
            }
        }
    });

    Audit::log('import.' . $kind, ['meta' => $stats + ['file' => $state['name'] ?? '']]);
    $discard();

    $render('result', ['stats' => $stats, 'issues' => $issues, 'truncated' => $csv['truncated']]);
}

if (is_post() && $action === 'cancel') {
    Csrf::verify();
    $discard();
    Session::flash('info', 'Import cancelled.');
    Response::redirect($def['back']);
}

// ── Langkah 1 → 2: unggah ───────────────────────────────────────────────────
if (is_post() && $action === 'upload') {
    Csrf::verify();
    $discard();

    $file  = $_FILES['csv'] ?? null;
    $error = null;

    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error = 'Please choose a CSV file.';
    } elseif ((int) $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed (error code ' . (int) $file['error'] . ').';
    } elseif ((int) $file['size'] > upload_max_bytes()) {
        $error = 'Maximum file size is ' . upload_max_label() . '.';
    } elseif (!in_array(strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
        $error = 'Only .csv (or .txt) files are accepted. Export your spreadsheet as CSV first.';
    } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
        $error = 'Invalid upload.';
    }

    if ($error === null && !ensure_dir(storage_path('tmp'))) {
        $error = 'Temporary storage is not writable. Check the storage folder permissions.';
    }

    $target = storage_path('tmp/import-' . random_token(12) . '.csv');
    if ($error === null && !move_uploaded_file((string) $file['tmp_name'], $target)) {
        $error = 'The uploaded file could not be stored.';
    }

    if ($error === null) {
        try {
            $csv = Csv::read($target);
        } catch (Throwable $e) {
            @unlink($target);
            $error = 'The CSV could not be read: ' . $e->getMessage();
        }
    }

    if ($error !== null) {
        Session::flash('error', $error);
        Response::redirect($selfUrl);
    }

    Session::put($sessionKey, ['path' => $target, 'name' => (string) $file['name'], 'at' => now()]);

    // Ekspor paper EDAS (satu baris per paper) → importer khusus di authors-import.php.
    if ($kind === 'paper_authors' && Cenim\Support\EdasImport::detect($csv['headers'])) {
        Session::put('import_authors', Session::get($sessionKey));
        Session::forget($sessionKey);
        Response::redirect(admin_url('authors-import.php?action=edas'));
    }

    $render('map', [
        'fileName'  => (string) $file['name'],
        'headers'   => $csv['headers'],
        'preview'   => array_slice($csv['rows'], 0, 8),
        'rowCount'  => count($csv['rows']),
        'truncated' => $csv['truncated'],
        'mapping'   => Csv::guessMapping($csv['headers'], $def['fields']),
    ]);
}

// ── Langkah 1: form unggah ──────────────────────────────────────────────────
$discard();
$render('upload', ['maxMb' => upload_max_label()]);
