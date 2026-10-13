<?php
/**
 * Daftar panitia (sumber data sertifikat panitia, dan nanti bagian Committee
 * di landing page).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\View;
use Cenim\Domain\CommitteeRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

View::render('admin/committee', [
    'title'     => 'Committee',
    'subtitle'  => ($edition['name'] ?? 'No edition') . ' — ' . CommitteeRepo::countByEdition($editionId) . ' member(s)',
    'active'    => 'committee',
    'edition'   => $edition,
    'divisions' => CommitteeRepo::divisions($editionId),
    'assets'    => ['datatables'],
    'scripts'   => View::partial('admin/committee_scripts'),
], 'admin');
