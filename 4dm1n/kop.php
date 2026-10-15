<?php
/**
 * Generator kop surat per tahun. Setelah disimpan, kop ini otomatis dipakai
 * sebagai letterhead invoice, receipt, dan sertifikat.
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
use Cenim\Domain\SettingRepo;
use Cenim\Generator\KopGenerator;
use Cenim\Generator\LogoGenerator;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$year      = input_int('year') > 0 ? input_int('year') : (int) ($edition['year'] ?? date('Y'));

if (is_post()) {
    Csrf::verify();

    $year   = max(2018, min(2100, input_int('year')));
    $action = (string) input('action', 'generate');

    if ($action === 'texts') {
        foreach (['kop_title', 'kop_organized_by', 'kop_institute'] as $key) {
            SettingRepo::set($editionId, $key, trim((string) input($key, '')));
        }

        Session::flash('success', 'Letterhead texts saved — LoA, invoices, and receipts use them right away. '
            . 'Generate the letterhead image again if you also use the PNG/PDF files.');
        Response::redirect(admin_url('kop.php?year=' . $year));
    }

    // ── Logo kop PDF ────────────────────────────────────────────────────────
    if (str_starts_with($action, 'logo_')) {
        $logoId = input_int('logo_id');
        $owned  = $logoId === 0 || Cenim\Core\Db::value('SELECT 1 FROM kop_logos WHERE id = ? AND edition_id = ?', [$logoId, $editionId]) !== null;

        try {
            if (!$owned) {
                throw new InvalidArgumentException('Logo not found.');
            }
            match ($action) {
                'logo_add'    => BrandingRepo::addKopLogo($editionId, (int) ($edition['year'] ?? $year), $_FILES['logo'] ?? [],
                    (string) input('side', 'right'), (float) input('height_mm', '13'), (string) input('label', '')),
                'logo_update' => BrandingRepo::updateKopLogo($logoId, (string) input('side', 'right'), (float) input('height_mm', '13'), (string) input('label', '')),
                'logo_left'   => BrandingRepo::moveKopLogo($logoId, -1),
                'logo_right'  => BrandingRepo::moveKopLogo($logoId, 1),
                'logo_delete' => BrandingRepo::deleteKopLogo($logoId),
                'logo_reset'  => BrandingRepo::resetKopLogos($editionId),
                default       => throw new InvalidArgumentException('Unknown action.'),
            };
            Audit::log('kop.' . $action, ['meta' => ['logo_id' => $logoId]]);
            Session::flash('success', $action === 'logo_reset' ? 'Letterhead logos reset to the default.' : 'Letterhead logos updated.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }

        Response::redirect(admin_url('kop.php?year=' . $year) . '#pdf-letterhead');
    }

    try {
        $files = KopGenerator::build($year, $editionId, LogoGenerator::postedPngs());

        Audit::log('kop.generated', ['meta' => ['year' => $year, 'files' => array_keys($files)]]);
        Session::flash('success', 'Letterhead generated. Invoices, receipts, and certificates now use it.');
    } catch (Throwable $e) {
        Logger::error('Generator kop gagal', ['year' => $year, 'error' => $e->getMessage()]);
        Session::flash('error', 'Letterhead could not be generated: ' . $e->getMessage());
    }

    Response::redirect(admin_url('kop.php?year=' . $year));
}

View::render('admin/kop', [
    'title'    => 'Letterhead (kop surat)',
    'subtitle' => 'Logos and texts of the LoA, invoice, and receipt header — plus the letterhead image generator',
    'kopLogos' => BrandingRepo::kopLogos($editionId),
    'active'   => 'kop',
    'edition'  => $edition,
    'year'     => $year,
    'rasterTargets' => LogoGenerator::serverRender() ? [] : array_map(
        static fn (string $name, int $width): array => ['name' => $name] + KopGenerator::rasterGeometry($width) + ['bg' => '#ffffff'],
        array_keys(KopGenerator::pngTargets()),
        KopGenerator::pngTargets()
    ),
    'texts'    => KopGenerator::texts($year, $editionId),
    'files'    => KopGenerator::existing($year),
    'inUse'    => Cenim\Pdf\PdfBase::letterheadPath(),
], 'admin');
