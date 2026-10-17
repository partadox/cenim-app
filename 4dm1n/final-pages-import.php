<?php
/**
 * Import jumlah halaman final manuscript — HANYA mengubah authors.final_pages.
 *
 *   1. unggah CSV (ekspor EDAS atau CSV sederhana: paper ID + jumlah halaman)
 *   2. pratinjau perubahan per paper
 *   3. terapkan; registrasi yang sudah dikirim tetapi belum ber-invoice ikut
 *      dihitung ulang supaya total biayanya memakai halaman final
 *
 * Data lain (judul, author, email, status) tidak disentuh.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Support\Csv;

const FINAL_PAGES_SESSION_KEY = 'import_final_pages';

/** Header kolom paper ID dan jumlah halaman yang dikenali (urutan = prioritas). */
const FINAL_PAGES_ID_HEADERS    = ['#', 'paper id', 'paper_id', 'paperid', 'submission id', 'id'];
const FINAL_PAGES_PAGES_HEADERS = ['final manuscript: pages', 'final pages', 'final_pages', 'final manuscript pages',
                                   'pages', 'total pages', 'total_pages', 'number of pages', 'jumlah halaman'];

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$action    = (string) input('action', '');

if ($editionId === 0) {
    Session::flash('error', 'No active edition.');
    Response::redirect(admin_url('index.php'));
}

$columnIndex = static function (array $headers, array $names): ?int {
    $norm = array_map(static fn ($h): string => mb_strtolower(trim((string) $h)), $headers);
    foreach ($names as $name) {
        $i = array_search($name, $norm, true);
        if ($i !== false) {
            return (int) $i;
        }
    }

    return null;
};

// ── Langkah 3: terapkan ─────────────────────────────────────────────────────
if (is_post() && $action === 'confirm') {
    Csrf::verify();

    $state = Session::get(FINAL_PAGES_SESSION_KEY);
    if (!is_array($state) || !is_array($state['changes'] ?? null)) {
        Session::flash('error', 'The preview has expired. Please upload the file again.');
        Response::redirect(admin_url('final-pages-import.php'));
    }

    $updated     = 0;
    $recomputed  = 0;
    Db::transaction(static function () use ($state, $editionId, &$updated, &$recomputed): void {
        foreach ($state['changes'] as $change) {
            $author = AuthorRepo::find((int) $change['author_id']);
            if ($author === null || (int) $author['edition_id'] !== $editionId) {
                continue;
            }
            AuthorRepo::setFinalPages((int) $author['id'], (int) $change['pages']);
            $updated++;

            // Registrasi terkirim tanpa invoice: simpan ulang hitungan biaya dengan halaman final.
            $registration = RegistrationRepo::findByAuthor((int) $author['id']);
            if ($registration === null
                || (string) $registration['status'] === RegistrationRepo::STATUS_DRAFT
                || InvoiceRepo::findForRegistration((int) $registration['id']) !== null) {
                continue;
            }
            $author['final_pages'] = (int) $change['pages'];
            $quote = FeeEngine::quote($editionId, RegistrationRepo::quoteInput($registration, $author));
            if ($quote['error'] === null) {
                Db::update('registrations', [
                    'extra_pages' => (int) $quote['extra_pages'],
                    'subtotal'    => (float) $quote['subtotal'],
                    'total'       => (float) $quote['total'],
                    'updated_at'  => now(),
                ], 'id = :target_id', ['target_id' => (int) $registration['id']]);
                $recomputed++;
            }
        }
    });

    Audit::log('authors.final_pages_imported', ['meta' => [
        'file' => (string) ($state['name'] ?? ''), 'updated' => $updated, 'registrations_recomputed' => $recomputed,
    ]]);
    Session::forget(FINAL_PAGES_SESSION_KEY);

    Session::flash('success', 'Final manuscript pages updated for ' . $updated . ' paper(s)'
        . ($recomputed > 0 ? '; ' . $recomputed . ' submitted registration(s) recalculated' : '') . '.');
    Response::redirect(admin_url('confirmations.php'));
}

// ── Langkah 2: unggah & pratinjau ───────────────────────────────────────────
if (is_post() && $action === 'upload') {
    Csrf::verify();
    Session::forget(FINAL_PAGES_SESSION_KEY);

    $file = $_FILES['csv'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        Session::flash('error', 'Please choose a CSV file (max ' . upload_max_label() . ').');
        Response::redirect(admin_url('final-pages-import.php'));
    }
    if (!in_array(strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
        Session::flash('error', 'Only .csv (or .txt) files are accepted.');
        Response::redirect(admin_url('final-pages-import.php'));
    }

    try {
        $csv = Csv::read((string) $file['tmp_name']);
    } catch (Throwable $e) {
        Session::flash('error', 'The CSV could not be read: ' . $e->getMessage());
        Response::redirect(admin_url('final-pages-import.php'));
    }

    $idCol    = $columnIndex($csv['headers'], FINAL_PAGES_ID_HEADERS);
    $pagesCol = $columnIndex($csv['headers'], FINAL_PAGES_PAGES_HEADERS);

    if ($idCol === null || $pagesCol === null) {
        $hasReview = $columnIndex($csv['headers'], ['review manuscript: pages']) !== null;
        Session::flash('error', $idCol === null
            ? 'No paper ID column found (expected "#" or "Paper ID").'
            : ($hasReview
                ? 'This file only has "review manuscript: pages". Export the final manuscript pages from EDAS (column "final manuscript: pages"), or use a CSV with columns "Paper ID" and "Final pages".'
                : 'No pages column found (expected "final manuscript: pages", "Final pages" or "Pages").'));
        Response::redirect(admin_url('final-pages-import.php'));
    }

    $changes = [];
    $rows    = [];
    $seen    = [];
    foreach ($csv['rows'] as $i => $row) {
        $paperId = trim((string) ($row[$idCol] ?? ''));
        $raw     = trim((string) ($row[$pagesCol] ?? ''));
        if ($paperId === '') {
            continue;
        }
        $pages  = (int) preg_replace('/\D+/', '', $raw);
        $author = AuthorRepo::findByPaperId($editionId, $paperId);
        $line   = ['line' => $i + 2, 'paper_id' => $paperId, 'new' => $pages, 'author' => $author, 'status' => ''];

        if (isset($seen[$paperId])) {
            $line['status'] = 'duplicate';
        } elseif ($author === null) {
            $line['status'] = 'unknown';
        } elseif ($raw === '' || $pages < 1 || $pages > 200) {
            $line['status'] = 'invalid';
        } elseif ((int) ($author['final_pages'] ?? 0) === $pages) {
            $line['status'] = 'same';
        } else {
            $line['status'] = 'change';
            $changes[] = ['author_id' => (int) $author['id'], 'pages' => $pages];
        }
        $seen[$paperId] = true;

        if ($author !== null) {
            $registration = RegistrationRepo::findByAuthor((int) $author['id']);
            $line['registration'] = (string) ($registration['status'] ?? '');
            $line['invoiced']     = $registration !== null && InvoiceRepo::findForRegistration((int) $registration['id']) !== null;
        }
        $rows[] = $line;
    }

    Session::put(FINAL_PAGES_SESSION_KEY, ['name' => (string) $file['name'], 'changes' => $changes, 'at' => now()]);

    View::render('admin/final_pages_import', [
        'title'     => 'Import final manuscript pages',
        'subtitle'  => 'Step 2 of 2 — check the changes',
        'active'    => 'confirmations',
        'edition'   => $edition,
        'step'      => 'preview',
        'fileName'  => (string) $file['name'],
        'pagesHeader' => (string) $csv['headers'][$pagesCol],
        'rows'      => $rows,
        'changes'   => count($changes),
        'included'  => FeeEngine::includedPages($editionId),
    ], 'admin');
    exit;
}

// ── Langkah 1: form unggah ──────────────────────────────────────────────────
Session::forget(FINAL_PAGES_SESSION_KEY);

View::render('admin/final_pages_import', [
    'title'    => 'Import final manuscript pages',
    'subtitle' => 'Step 1 of 2 — upload the CSV file',
    'active'   => 'confirmations',
    'edition'  => $edition,
    'step'     => 'upload',
    'maxMb'    => upload_max_label(),
], 'admin');
