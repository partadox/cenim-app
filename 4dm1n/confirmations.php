<?php
/**
 * Confirmations (form 0): semua paper accepted — sudah/belum konfirmasi,
 * offline/online, perkiraan jumlah orang yang datang, halaman manuskrip review
 * vs final, perkiraan biaya halaman tambahan, ukuran baju (rekap untuk vendor
 * kaos), dan catatan author.
 * ?export=1 → Excel.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\View;
use Cenim\Domain\ConfirmationRepo;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\RegistrationRepo;
use Cenim\Support\Xlsx;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$filter    = in_array(input('show'), ['confirmed', 'missing', 'offline', 'online', 'extra'], true) ? (string) input('show') : '';
$all       = ConfirmationRepo::overview($editionId);
$included  = FeeEngine::includedPages($editionId);
$shirtOn   = RegistrationRepo::shirtEnabled($editionId);

$counts = ['confirmed' => 0, 'missing' => 0, 'offline' => 0, 'online' => 0, 'extra' => 0, 'people' => 0, 'nonpresenters' => 0];
foreach ($all as $row) {
    if ($row['confirmation_id'] === null) {
        $counts['missing']++;
        continue;
    }
    $counts['confirmed']++;
    $counts[(string) $row['presentation_mode'] === 'online' ? 'online' : 'offline']++;
    $counts['people'] += (int) $row['attendees'];
    $counts['nonpresenters'] += max(0, (int) $row['attendees'] - 1);
    if ((int) $row['extra_pages'] > 0) {
        $counts['extra']++;
    }
}

$rows = array_values(array_filter($all, static fn (array $r): bool => match ($filter) {
    'confirmed' => $r['confirmation_id'] !== null,
    'missing'   => $r['confirmation_id'] === null,
    'offline'   => $r['confirmation_id'] !== null && (string) $r['presentation_mode'] === 'offline',
    'online'    => $r['confirmation_id'] !== null && (string) $r['presentation_mode'] === 'online',
    'extra'     => $r['confirmation_id'] !== null && (int) $r['extra_pages'] > 0,
    default     => true,
}));

if (input('export') !== null) {
    $lines = [];
    foreach ($rows as $i => $row) {
        $confirmed = $row['confirmation_id'] !== null;
        $lines[] = [
            $i + 1,
            (string) $row['paper_id'],
            (string) ($row['paper_title'] ?? ''),
            (string) $row['first_author_name'],
            (string) $row['email'],
            (string) ($row['country'] ?? ''),
            $confirmed ? 'Yes' : 'No',
            $confirmed ? ucfirst((string) $row['presentation_mode']) : '',
            $confirmed && (string) $row['presentation_mode'] === 'offline' ? (int) $row['attendees'] : '',
            $confirmed && (string) $row['presentation_mode'] === 'offline' ? max(0, (int) $row['attendees'] - 1) : '',
            (int) $row['total_pages'],
            (int) ($row['final_pages'] ?? 0) > 0 ? (int) $row['final_pages'] : '',
            $confirmed ? (int) $row['extra_pages'] : '',
            $confirmed && (int) $row['extra_pages'] > 0 ? (float) $row['extra_page_fee'] : '',
            $confirmed ? (string) $row['currency'] : '',
            implode(', ', ConfirmationRepo::shirts($row)),
            (string) ($row['note'] ?? ''),
            (string) ($row['filled_by_name'] ?? ''),
            $confirmed ? fmt_dt((string) ($row['updated_at'] ?? $row['submitted_at'])) : '',
            ucfirst((string) ($row['registration_status'] ?? 'not started')),
        ];
    }

    Xlsx::download(
        'confirmations-' . ($edition['slug'] ?? 'cenim') . ($filter !== '' ? '-' . $filter : '') . '-' . gmdate('Ymd') . '.xlsx',
        'Confirmations',
        ['No', 'Paper ID', 'Paper title', 'First author', 'Email', 'Country', 'Confirmed', 'Presentation',
         'People attending (offline)', 'Non-presenters', 'Pages (review)', 'Pages (final)', 'Extra pages',
         'Extra page fee', 'Currency', 'Shirt sizes (presenter first)', 'Note', 'Filled by', 'Confirmed at', 'Registration'],
        $lines
    );
}

View::render('admin/confirmations', [
    'title'    => 'Confirmations',
    'subtitle' => ($edition['name'] ?? 'No edition') . ' — ' . $counts['confirmed'] . ' of ' . count($all) . ' accepted papers confirmed',
    'active'   => 'confirmations',
    'edition'  => $edition,
    'rows'     => $rows,
    'total'    => count($all),
    'counts'   => $counts,
    'filter'   => $filter,
    'included' => $included,
    'deadline' => ConfirmationRepo::deadline($editionId),
    'isOpen'   => ConfirmationRepo::isOpen($editionId),
    'shirtOn'  => $shirtOn,
    'shirts'   => ConfirmationRepo::shirtSummary($all, RegistrationRepo::shirtSizes($editionId)),
], 'admin');
