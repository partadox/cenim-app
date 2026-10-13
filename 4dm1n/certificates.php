<?php
/**
 * Daftar sertifikat + penerbitan massal per jenis.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\View;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\CertificateRepo;
use Cenim\Domain\CertificateTemplateRepo;
use Cenim\Domain\CommitteeRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

View::render('admin/certificates', [
    'title'      => 'Certificates',
    'subtitle'   => ($edition['name'] ?? 'No edition') . ' — '
        . array_sum(CertificateRepo::countsByType($editionId)) . ' issued',
    'active'     => 'certificates',
    'edition'    => $edition,
    'counts'     => CertificateRepo::countsByType($editionId),
    'pending'    => CertificateRepo::pendingCounts($editionId),
    'templates'  => CertificateTemplateRepo::allEnsured($editionId),
    'released'   => CertificateRepo::isReleased($edition),
    'committee'  => CommitteeRepo::countByEdition($editionId),
    'authorCount' => AuthorRepo::countByEdition($editionId),
    'assets'     => ['datatables'],
    'scripts'    => View::partial('admin/certificates_scripts'),
], 'admin');
