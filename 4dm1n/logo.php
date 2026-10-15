<?php
/**
 * Logo edisi: unggah logo sendiri ATAU buat dengan generator (konsep logo
 * CENIM, hanya angka tahun yang berubah), lalu pasang sebagai logo edisi.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\BrandingRepo;
use Cenim\Generator\LogoGenerator;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$year      = input_int('year') > 0 ? input_int('year') : (int) ($edition['year'] ?? date('Y'));

if (is_post()) {
    Csrf::verify();

    $year   = max(2018, min(2100, input_int('year')));
    $action = (string) input('action', 'generate');

    if (in_array($action, ['upload', 'use_generated', 'clear'], true)) {
        try {
            match ($action) {
                'upload'        => BrandingRepo::setLogoFromUpload($editionId, (int) ($edition['year'] ?? $year), $_FILES['logo'] ?? []),
                'use_generated' => BrandingRepo::setLogoFromGenerated($editionId, $year),
                'clear'         => BrandingRepo::clearLogo($editionId),
            };
            Audit::log('logo.' . $action, ['meta' => ['year' => $year]]);
            Session::flash('success', match ($action) {
                'upload'        => 'Logo uploaded and now used across the website, panels, and documents.',
                'use_generated' => 'The generated ' . $year . ' logo is now the edition logo.',
                default         => 'Edition logo removed — the default logo is used again.',
            });
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        Response::redirect(admin_url('logo.php?year=' . $year));
    }

    try {
        $files = LogoGenerator::build($year, $editionId, LogoGenerator::postedPngs());

        Audit::log('logo.generated', ['meta' => ['year' => $year, 'files' => count($files)]]);
        Session::flash('success', count($files) . ' logo file(s) generated for ' . $year . '.');
    } catch (Throwable $e) {
        Logger::error('Generator logo gagal', ['year' => $year, 'error' => $e->getMessage()]);
        Session::flash('error', 'Logo could not be generated: ' . $e->getMessage());
    }

    Response::redirect(admin_url('logo.php?year=' . $year));
}

View::render('admin/logo', [
    'title'    => 'Logo',
    'subtitle' => 'Upload the edition logo, or generate it from the CENIM concept',
    'logoFile' => BrandingRepo::logoFile($editionId),
    'logoUrl'  => BrandingRepo::logoUrl($edition),
    'active'   => 'logo',
    'edition'  => $edition,
    'year'     => $year,
    'rasterTargets' => LogoGenerator::serverRender() ? [] : array_map(
        static fn (string $name, array $t): array => ['name' => $name] + LogoGenerator::rasterGeometry($t[0]) + ['bg' => $t[1] ? null : '#ffffff'],
        array_keys(LogoGenerator::pngTargets($year)),
        LogoGenerator::pngTargets($year)
    ),
    'sizes'    => LogoGenerator::SIZES,
    'files'    => LogoGenerator::existing($year),
    'font'     => basename(LogoGenerator::fontPath($editionId)),
], 'admin');
