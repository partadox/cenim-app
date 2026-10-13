<?php
/**
 * Import author dari CSV, tiga langkah:
 *   1. unggah file
 *   2. petakan kolom CSV → field sistem (ditebak otomatis) + pratinjau
 *   3. jalankan import (upsert berdasarkan paper_id) + laporan per baris
 *
 * Ekspor paper EDAS (kolom "#", "Title", "Author N given/surname/email/…")
 * dikenali otomatis: langkah pemetaan dilewati, dan sekali import mengisi
 * paper beserta SEMUA author-nya (lihat Cenim\Support\EdasImport).
 *
 * File CSV disimpan sementara di storage/tmp dan dihapus setelah selesai
 * atau dibatalkan.
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
use Cenim\Support\Csv;
use Cenim\Support\EdasImport;

const IMPORT_SESSION_KEY = 'import_authors';
const IMPORT_PREVIEW_ROWS = 8;
const IMPORT_MAX_ISSUES = 100;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0) {
    Session::flash('error', 'No active edition. Run the seeder first.');
    Response::redirect(admin_url('index.php'));
}

$action = (string) input('action', '');

/** Hapus file sementara + state di session. */
$discard = static function (): void {
    $state = Session::get(IMPORT_SESSION_KEY);
    if (is_array($state) && isset($state['path']) && is_file((string) $state['path'])) {
        @unlink((string) $state['path']);
    }
    Session::forget(IMPORT_SESSION_KEY);
};

/** Bersihkan sisa file import yang tertinggal lebih dari sehari. */
$cleanTmp = static function (): void {
    foreach (glob(storage_path('tmp/import-*.csv')) ?: [] as $file) {
        if (is_file($file) && (time() - (int) filemtime($file)) > 86400) {
            @unlink($file);
        }
    }
};

// ── EDAS: pratinjau & import paper + semua author ─────────────────────────
$edasPreview = static function () use ($edition, $discard): never {
    $state = Session::get(IMPORT_SESSION_KEY);
    if (!is_array($state) || !is_file((string) ($state['path'] ?? ''))) {
        Session::flash('error', 'The uploaded file has expired. Please upload it again.');
        Response::redirect(admin_url('authors-import.php'));
    }

    $csv    = Csv::read((string) $state['path']);
    $papers = EdasImport::parse($csv['headers'], $csv['rows']);
    $editionId = (int) ($edition['id'] ?? 0);
    foreach ($papers as &$p) {
        $p['exists'] = AuthorRepo::findByPaperId($editionId, $p['paper_id']) !== null;
    }
    unset($p);

    View::render('admin/authors_import_edas', [
        'title'     => 'Import authors',
        'subtitle'  => 'Step 2 of 3 — check the EDAS papers',
        'active'    => 'authors',
        'edition'   => $edition,
        'fileName'  => (string) ($state['name'] ?? ''),
        'papers'    => $papers,
        'truncated' => $csv['truncated'],
    ], 'admin');
    exit;
};

if (!is_post() && $action === 'edas') {
    $edasPreview();
}

if (is_post() && $action === 'confirm_edas') {
    Csrf::verify();

    $state = Session::get(IMPORT_SESSION_KEY);
    if (!is_array($state) || !is_file((string) ($state['path'] ?? ''))) {
        Session::flash('error', 'The uploaded file has expired. Please upload it again.');
        Response::redirect(admin_url('authors-import.php'));
    }

    $csv    = Csv::read((string) $state['path']);
    $papers = EdasImport::parse($csv['headers'], $csv['rows']);
    $batch  = 'edas-' . gmdate('Ymd-His');

    // Paper yang tidak dicentang di pratinjau tidak diikutkan.
    $excluded = 0;
    if (input('has_selection') === '1') {
        $include  = array_map('strval', (array) ($_POST['include'] ?? []));
        $all      = count($papers);
        $papers   = array_values(array_filter($papers, static fn (array $p): bool => in_array($p['paper_id'], $include, true)));
        $excluded = $all - count($papers);
    }
    if ($papers === []) {
        Session::flash('error', 'No paper selected. Tick at least one paper to import.');
        Response::redirect(admin_url('authors-import.php?action=edas'));
    }
    $result = [];

    Db::transaction(static function () use ($editionId, $papers, $batch, &$result): void {
        $result = EdasImport::import($editionId, $papers, $batch, input_bool('accepted_only'), input('mode') !== 'skip');
    });

    Audit::log('author.imported', [
        'meta' => ['batch' => $batch, 'file' => $state['name'] ?? '', 'source' => 'edas', 'excluded' => $excluded]
            + array_diff_key($result, ['issues' => true]),
    ]);

    $discard();

    View::render('admin/authors_import_result', [
        'title'     => 'Import authors',
        'subtitle'  => 'Batch ' . $batch . ' — ' . $result['authors'] . ' author(s) in the imported papers'
            . ($excluded > 0 ? ', ' . $excluded . ' paper(s) left out in the preview' : ''),
        'active'    => 'authors',
        'edition'   => $edition,
        'inserted'  => $result['inserted'],
        'updated'   => $result['updated'],
        'skipped'   => $result['skipped'],
        'warnings'  => $result['warnings'],
        'issues'    => $result['issues'],
        'truncated' => $csv['truncated'],
        'batch'     => $batch,
    ], 'admin');
    exit;
}

// ── Langkah 3: jalankan import ──────────────────────────────────────────────
if (is_post() && $action === 'confirm') {
    Csrf::verify();

    $state = Session::get(IMPORT_SESSION_KEY);
    if (!is_array($state) || !is_file((string) ($state['path'] ?? ''))) {
        Session::flash('error', 'The uploaded file has expired. Please upload it again.');
        Response::redirect(admin_url('authors-import.php'));
    }

    $mode    = input('mode') === 'skip' ? 'skip' : 'update';
    $rawMap  = $_POST['map'] ?? [];
    $mapping = [];

    foreach (array_keys(AuthorRepo::FIELDS) as $field) {
        $index = isset($rawMap[$field]) ? (int) $rawMap[$field] : -1;
        $mapping[$field] = $index >= 0 ? $index : null;
    }

    $missing = array_values(array_filter(
        AuthorRepo::REQUIRED_FIELDS,
        static fn (string $field): bool => $mapping[$field] === null
    ));

    if ($missing !== []) {
        Session::flash('error', 'These required fields are not mapped: ' . implode(', ', $missing) . '.');
        Response::redirect(admin_url('authors-import.php'));
    }

    try {
        $csv = Csv::read((string) $state['path']);
    } catch (Throwable $e) {
        Logger::error('Import author: gagal membaca CSV', ['error' => $e->getMessage()]);
        $discard();
        Session::flash('error', 'The CSV could not be read: ' . $e->getMessage());
        Response::redirect(admin_url('authors-import.php'));
    }

    $batch    = 'csv-' . gmdate('Ymd-His');
    $inserted = 0;
    $updated  = 0;
    $skipped  = 0;
    $warnings = 0;
    $issues   = [];
    $seen     = [];

    Db::transaction(static function () use (
        $csv, $mapping, $editionId, $batch, $mode,
        &$inserted, &$updated, &$skipped, &$warnings, &$issues, &$seen
    ): void {
        foreach ($csv['rows'] as $i => $row) {
            $lineNumber = $i + 2; // +1 header, +1 supaya mulai dari 1

            $data = [];
            foreach ($mapping as $field => $index) {
                $data[$field] = $index === null ? '' : (string) ($row[$index] ?? '');
            }

            $paperId = trim((string) $data['paper_id']);
            $key     = mb_strtolower($paperId);

            $note = static function (string $level, string $message) use (&$issues, $lineNumber, $paperId): void {
                if (count($issues) < IMPORT_MAX_ISSUES) {
                    $issues[] = ['line' => $lineNumber, 'paper_id' => $paperId, 'level' => $level, 'message' => $message];
                }
            };

            if ($paperId !== '' && isset($seen[$key])) {
                $skipped++;
                $note('error', 'Duplicate paper ID inside the file (first occurrence kept).');
                continue;
            }

            $existing = $paperId === '' ? null : AuthorRepo::findByPaperId($editionId, $paperId);

            if ($existing !== null && $mode === 'skip') {
                $skipped++;
                $note('warning', 'Already exists — left unchanged (mode: do not update).');
                $seen[$key] = true;
                continue;
            }

            $check = AuthorRepo::validateForImport($data, $editionId, (int) ($existing['id'] ?? 0));

            if ($check['errors'] !== []) {
                $skipped++;
                $note('error', implode(' ', $check['errors']));
                continue;
            }

            $result = AuthorRepo::upsert($editionId, $data, $batch);
            $result['action'] === 'inserted' ? $inserted++ : $updated++;
            $seen[$key] = true;

            if ($check['warnings'] !== []) {
                $warnings++;
                $note('warning', implode(' ', $check['warnings']));
            }
        }
    });

    Audit::log('author.imported', [
        'meta' => [
            'batch'    => $batch,
            'file'     => $state['name'] ?? '',
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'warnings' => $warnings,
        ],
    ]);

    $discard();

    View::render('admin/authors_import_result', [
        'title'     => 'Import authors',
        'subtitle'  => 'Batch ' . $batch,
        'active'    => 'authors',
        'edition'   => $edition,
        'inserted'  => $inserted,
        'updated'   => $updated,
        'skipped'   => $skipped,
        'warnings'  => $warnings,
        'issues'    => $issues,
        'truncated' => $csv['truncated'],
        'batch'     => $batch,
    ], 'admin');
    exit;
}

// ── Batal ───────────────────────────────────────────────────────────────────
if (is_post() && $action === 'cancel') {
    Csrf::verify();
    $discard();
    Session::flash('info', 'Import cancelled.');
    Response::redirect(admin_url('authors-import.php'));
}

// ── Langkah 1 → 2: unggah file ──────────────────────────────────────────────
if (is_post() && $action === 'upload') {
    Csrf::verify();
    $cleanTmp();
    $discard();

    $file  = $_FILES['csv'] ?? null;
    $error = null;

    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error = 'Please choose a CSV file.';
    } elseif ((int) $file['error'] !== UPLOAD_ERR_OK) {
        $error = match ((int) $file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server upload limit (max ' . upload_max_label() . ').',
            UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Please try again.',
            default                                   => 'Upload failed (error code ' . (int) $file['error'] . ').',
        };
    } elseif ((int) $file['size'] > upload_max_bytes()) {
        $error = 'Maximum file size is ' . upload_max_label() . '.';
    } elseif (!in_array(strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
        $error = 'Only .csv (or .txt) files are accepted. Export your spreadsheet as CSV first.';
    } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
        $error = 'Invalid upload.';
    }

    if ($error !== null) {
        Session::flash('error', $error);
        Response::redirect(admin_url('authors-import.php'));
    }

    if (!ensure_dir(storage_path('tmp'))) {
        Logger::error('Import author: storage/tmp tidak writable');
        Session::flash('error', 'Temporary storage is not writable. Check the storage folder permissions.');
        Response::redirect(admin_url('authors-import.php'));
    }

    $target = storage_path('tmp/import-' . random_token(12) . '.csv');

    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        Session::flash('error', 'The uploaded file could not be stored.');
        Response::redirect(admin_url('authors-import.php'));
    }

    try {
        $csv = Csv::read($target);
    } catch (Throwable $e) {
        @unlink($target);
        Session::flash('error', 'The CSV could not be read: ' . $e->getMessage());
        Response::redirect(admin_url('authors-import.php'));
    }

    Session::put(IMPORT_SESSION_KEY, [
        'path'    => $target,
        'name'    => (string) $file['name'],
        'headers' => $csv['headers'],
        'rows'    => count($csv['rows']),
        'at'      => now(),
    ]);

    if (EdasImport::detect($csv['headers'])) {
        $edasPreview();
    }

    View::render('admin/authors_import_map', [
        'title'     => 'Import authors',
        'subtitle'  => 'Step 2 of 3 — map the columns',
        'active'    => 'authors',
        'edition'   => $edition,
        'fileName'  => (string) $file['name'],
        'headers'   => $csv['headers'],
        'preview'   => array_slice($csv['rows'], 0, IMPORT_PREVIEW_ROWS),
        'rowCount'  => count($csv['rows']),
        'truncated' => $csv['truncated'],
        'mapping'   => Csv::guessMapping($csv['headers'], AuthorRepo::FIELDS),
        'fields'    => array_keys(AuthorRepo::FIELDS),
        'required'  => AuthorRepo::REQUIRED_FIELDS,
    ], 'admin');
    exit;
}

// ── Langkah 1: form unggah ──────────────────────────────────────────────────
$discard();
$cleanTmp();

View::render('admin/authors_import_upload', [
    'title'    => 'Import authors',
    'subtitle' => 'Step 1 of 3 — upload the CSV file',
    'active'   => 'authors',
    'edition'  => $edition,
    'fields'   => AuthorRepo::FIELDS,
    'required' => AuthorRepo::REQUIRED_FIELDS,
    'maxMb'    => upload_max_label(),
], 'admin');
