<?php
/**
 * Edisi konferensi: buat edisi baru dengan menyalin pengaturan edisi
 * sebelumnya, salin pengaturan ke edisi yang sudah ada, dan aktifkan edisi.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\EditionRepo;
use Cenim\Support\EditionCloner;

/** Ringkas laporan penyalinan untuk pesan flash. */
$summary = static function (array $report): string {
    $parts = [];
    foreach ($report as $group => $count) {
        $parts[] = EditionCloner::GROUPS[$group][0] . ' (' . $count . ')';
    }

    return $parts === [] ? 'nothing copied' : 'copied: ' . implode(', ', $parts);
};

if (is_post()) {
    Csrf::verify();

    $action = (string) input('action', '');
    $groups = array_map('strval', (array) ($_POST['groups'] ?? []));

    try {
        if ($action === 'create') {
            $year   = input_int('year');
            $fromId = input_int('from');

            $newId  = Db::transaction(static fn (): int => EditionRepo::create($year, (string) input('name', '')));
            $report = $fromId > 0 ? EditionCloner::copy($fromId, $newId, $groups, input_bool('update_years')) : [];

            if (input_bool('activate')) {
                EditionRepo::activate($newId);
            }

            Audit::log('edition.created', ['meta' => ['year' => $year, 'from' => $fromId, 'report' => $report]]);
            Session::flash('success', 'CENIM ' . $year . ' created — ' . $summary($report)
                . (input_bool('activate') ? '. It is now the active edition.' : '. Activate it when you are ready.'));
        } elseif ($action === 'copy') {
            $report = EditionCloner::copy(input_int('from'), input_int('to'), $groups, input_bool('update_years'));
            Audit::log('edition.copied', ['meta' => ['from' => input_int('from'), 'to' => input_int('to'), 'report' => $report]]);
            Session::flash('success', 'Settings ' . $summary($report) . '.');
        } elseif ($action === 'activate') {
            EditionRepo::activate(input_int('id'));
            $active = EditionRepo::find(input_int('id'));
            Audit::log('edition.activated', ['meta' => ['year' => $active['year'] ?? null]]);
            Session::flash('success', ($active['name'] ?? 'Edition') . ' is now the active edition.');
        }
    } catch (InvalidArgumentException $e) {
        Session::flash('error', $e->getMessage());
    }

    Response::redirect(admin_url('editions.php'));
}

$editions = EditionRepo::all();
foreach ($editions as &$row) {
    $row['papers']        = Db::count('authors', 'edition_id = ?', [(int) $row['id']]);
    $row['registrations'] = Db::count('registrations', 'edition_id = ?', [(int) $row['id']]);
    $row['certificates']  = Db::count('certificates', 'edition_id = ?', [(int) $row['id']]);
}
unset($row);

View::render('admin/editions', [
    'title'    => 'Editions',
    'subtitle' => 'Create the next edition by copying the settings of a previous one',
    'active'   => 'editions',
    'edition'  => current_edition(),
    'editions' => $editions,
    'nextYear' => max(array_map(static fn (array $e): int => (int) $e['year'], $editions) ?: [(int) date('Y')]) + 1,
], 'admin');
