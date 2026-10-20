<?php
/**
 * Dashboard admin: statistik paper & author, alur registrasi/pembayaran, status migrasi.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Db;
use Cenim\Core\Migrator;
use Cenim\Core\View;

$edition   = current_edition();
$editionId = $edition['id'] ?? 0;

$stats = [
    'authors'       => Db::count('authors', 'edition_id = ?', [$editionId]),
    'submitted'     => Db::count('registrations', 'edition_id = ? AND status = ?', [$editionId, 'submitted']),
    'verified'      => Db::count('registrations', 'edition_id = ? AND status = ?', [$editionId, 'verified']),
    'unpaid'        => Db::count('invoices', 'edition_id = ? AND status = ?', [$editionId, 'unpaid']),
    'paid'          => Db::count('invoices', 'edition_id = ? AND status = ?', [$editionId, 'paid']),
    'pending_pay'   => Db::count('payments', 'edition_id = ? AND status = ?', [$editionId, 'submitted']),
    'certificates'  => Db::count('certificates', 'edition_id = ?', [$editionId]),
];

// ── Statistik paper & author (per orang = email unik, atau nama bila tanpa email) ──
$person  = "COALESCE(NULLIF(LOWER(TRIM(pa.email)), ''), LOWER(TRIM(pa.name)))";
$papers  = Db::all("SELECT paper_status, COUNT(*) AS n FROM authors WHERE edition_id = ? AND paper_status != 'attendee' GROUP BY paper_status", [$editionId]);
$byStatus = array_column($papers, 'n', 'paper_status');

$stats += [
    'papers'          => array_sum(array_map('intval', $byStatus)),
    'accepted'        => (int) ($byStatus['accepted'] ?? 0),
    'paper_statuses'  => $byStatus,
    'people'          => (int) Db::value("SELECT COUNT(DISTINCT $person) FROM paper_authors pa WHERE pa.edition_id = ? AND pa.author_id NOT IN (SELECT id FROM authors WHERE paper_status = 'attendee')", [$editionId]),
    'author_slots'    => (int) Db::value("SELECT COUNT(*) FROM paper_authors WHERE edition_id = ? AND author_id NOT IN (SELECT id FROM authors WHERE paper_status = 'attendee')", [$editionId]),
    'no_email'        => (int) Db::value("SELECT COUNT(*) FROM paper_authors WHERE edition_id = ? AND author_id NOT IN (SELECT id FROM authors WHERE paper_status = 'attendee') AND (email IS NULL OR email = '')", [$editionId]),
    'countries'       => (int) Db::value("SELECT COUNT(DISTINCT LOWER(TRIM(country))) FROM paper_authors WHERE edition_id = ? AND author_id NOT IN (SELECT id FROM authors WHERE paper_status = 'attendee') AND TRIM(IFNULL(country, '')) != ''", [$editionId]),
    'affiliations'    => (int) Db::value("SELECT COUNT(DISTINCT LOWER(TRIM(affiliation))) FROM paper_authors WHERE edition_id = ? AND author_id NOT IN (SELECT id FROM authors WHERE paper_status = 'attendee') AND TRIM(IFNULL(affiliation, '')) != ''", [$editionId]),
];

$byCountry = Db::all(
    "SELECT TRIM(pa.country) AS label, COUNT(DISTINCT $person) AS people, COUNT(DISTINCT pa.author_id) AS papers
     FROM paper_authors pa WHERE pa.edition_id = ? AND pa.author_id NOT IN (SELECT id FROM authors WHERE paper_status = 'attendee') AND TRIM(IFNULL(pa.country, '')) != ''
     GROUP BY LOWER(TRIM(pa.country)) ORDER BY people DESC, label LIMIT 12",
    [$editionId]
);
$byAffiliation = Db::all(
    "SELECT TRIM(pa.affiliation) AS label, COUNT(DISTINCT $person) AS people, COUNT(DISTINCT pa.author_id) AS papers
     FROM paper_authors pa WHERE pa.edition_id = ? AND pa.author_id NOT IN (SELECT id FROM authors WHERE paper_status = 'attendee') AND TRIM(IFNULL(pa.affiliation, '')) != ''
     GROUP BY LOWER(TRIM(pa.affiliation)) ORDER BY people DESC, label LIMIT 12",
    [$editionId]
);

$migrator = new Migrator();

View::render('admin/dashboard', [
    'title'    => 'Dashboard',
    'subtitle' => $edition !== null
        ? $edition['name'] . (Cenim\Domain\EditionRepo::isArchive() ? ' — archive (past edition)' : ' — active edition')
        : 'No active edition yet. Run: php cenim-app/db/seed.php cenim2026',
    'active'   => 'dashboard',
    'edition'  => $edition,
    'stats'    => $stats,
    'byCountry'     => $byCountry,
    'byAffiliation' => $byAffiliation,
    'applied'  => $migrator->status(),
    'pending'  => $migrator->pending(),
], 'admin');
