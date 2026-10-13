<?php
/**
 * Daftar registrasi (form 1) untuk diverifikasi admin.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\View;
use Cenim\Domain\RegistrationRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$counts = RegistrationRepo::countsByStatus($editionId);

View::render('admin/registrations', [
    'title'    => 'Registrations',
    'subtitle' => ($edition['name'] ?? 'No edition') . ' — ' . array_sum($counts) . ' registration(s)',
    'active'   => 'registrations',
    'edition'  => $edition,
    'counts'   => $counts,
    'verifiedTotals' => RegistrationRepo::totalsByCurrency($editionId, RegistrationRepo::STATUS_VERIFIED),
    'statuses' => RegistrationRepo::STATUSES,
    'assets'   => ['datatables'],
    'scripts'  => View::partial('admin/registrations_scripts'),
], 'admin');
