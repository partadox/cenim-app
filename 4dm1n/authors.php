<?php
/**
 * Daftar author dengan DataTables server-side.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\View;
use Cenim\Domain\AuthorRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

View::render('admin/authors', [
    'title'     => 'Authors',
    'subtitle'  => ($edition['name'] ?? 'No edition') . ' — ' . AuthorRepo::countByEdition($editionId) . ' record(s)',
    'active'    => 'authors',
    'edition'   => $edition,
    'countries' => AuthorRepo::countries($editionId),
    'batches'   => AuthorRepo::batches($editionId),
    'statuses'  => AuthorRepo::PAPER_STATUSES,
    'assets'    => ['datatables'],
    'scripts'   => View::partial('admin/authors_scripts'),
], 'admin');
