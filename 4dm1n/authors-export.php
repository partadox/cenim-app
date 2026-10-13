<?php
/**
 * Export data author ke CSV. Kolomnya sama dengan yang diterima importer,
 * jadi hasil export bisa diedit lalu diimpor kembali (round-trip).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Domain\AuthorRepo;
use Cenim\Support\Csv;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$year      = (int) ($edition['year'] ?? date('Y'));

$fields = array_keys(AuthorRepo::FIELDS);
$rows   = [];

foreach (AuthorRepo::allForExport($editionId) as $author) {
    $row = [];
    foreach ($fields as $field) {
        $row[] = match ($field) {
            'is_presenter' => ((int) $author['is_presenter'] === 1 ? 'yes' : 'no'),
            default        => (string) ($author[$field] ?? ''),
        };
    }
    $rows[] = $row;
}

Audit::log('author.exported', ['meta' => ['count' => count($rows)]]);

Csv::download(
    sprintf('cenim-%d-authors-%s.csv', $year, gmdate('Ymd-Hi')),
    $fields,
    $rows
);
