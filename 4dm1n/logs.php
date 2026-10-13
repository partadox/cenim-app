<?php
/**
 * Logs: jejak audit, riwayat email, berkas log aplikasi, dan status backup.
 *
 * Halaman ini hanya membaca — tidak ada tombol yang mengubah atau menghapus,
 * karena audit log justru harus tidak bisa dirapikan dari dalam panel.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Db;
use Cenim\Core\View;
use Cenim\Support\Backup;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

$tab = (string) input('tab', 'audit');

if (!in_array($tab, ['audit', 'mail', 'files'], true)) {
    $tab = 'audit';
}

// Pilihan filter diambil dari data yang benar-benar ada.
$actions = array_column(
    Db::all(
        'SELECT DISTINCT action FROM audit_logs
          WHERE (edition_id = ? OR edition_id IS NULL) ORDER BY action',
        [$editionId]
    ),
    'action'
);

$actors = array_column(
    Db::all(
        'SELECT DISTINCT actor_type FROM audit_logs
          WHERE (edition_id = ? OR edition_id IS NULL) AND actor_type IS NOT NULL ORDER BY actor_type',
        [$editionId]
    ),
    'actor_type'
);

$templates = array_column(
    Db::all('SELECT DISTINCT template FROM mail_log WHERE edition_id = ? ORDER BY template', [$editionId]),
    'template'
);

// Tab "files": berkas log aplikasi + email tersimpan + arsip backup.
$appLogs = [];
foreach (glob(storage_path('logs') . '/app-*.log') ?: [] as $path) {
    $appLogs[] = [
        'name' => basename($path),
        'size' => (int) filesize($path),
        'time' => (int) filemtime($path),
    ];
}
usort($appLogs, static fn (array $a, array $b): int => $b['name'] <=> $a['name']);

$mailFiles = count(glob(storage_path('logs/mail') . '/*.{html,txt}', GLOB_BRACE) ?: []);

$counts = [
    'audit' => (int) Db::value(
        'SELECT COUNT(*) FROM audit_logs WHERE (edition_id = ? OR edition_id IS NULL)',
        [$editionId]
    ),
    'mail'  => (int) Db::value('SELECT COUNT(*) FROM mail_log WHERE edition_id = ?', [$editionId]),
    'files' => count($appLogs) + $mailFiles,
];

View::render('admin/logs', [
    'title'    => 'Logs',
    'subtitle' => ($edition['name'] ?? 'No edition') . ' — ' . number_format($counts['audit'], 0, ',', '.')
        . ' audit entry, ' . number_format($counts['mail'], 0, ',', '.') . ' email',
    'active'   => 'logs',
    'edition'  => $edition,
    'tab'      => $tab,
    'counts'   => $counts,
    'actions'  => $actions,
    'actors'   => $actors,
    'templates' => $templates,
    'appLogs'  => $appLogs,
    'mailFiles' => $mailFiles,
    'backups'  => Backup::all(),
    'assets'   => ['datatables'],
    'scripts'  => View::partial('admin/logs_scripts', ['tab' => $tab]),
], 'admin');
