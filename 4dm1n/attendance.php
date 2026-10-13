<?php
/**
 * Presentations: semua paper yang sudah lunas — presenter, offline/online,
 * peserta tambahan (dengan ukuran baju), dan video presentasi.
 * Data presenter/mode/peserta dari Registration form; video dari Video
 * Presentation form. ?export=1 → CSV (satu baris per orang yang datang).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\View;
use Cenim\Domain\AttendanceRepo;
use Cenim\Domain\FormRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Support\Csv;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$filter    = in_array(input('mode'), ['offline', 'online', 'missing'], true) ? (string) input('mode') : '';
$rows      = AttendanceRepo::overview($editionId, $filter);
$extra     = FormRepo::fields($editionId, FormRepo::FORM_ATTENDANCE);
$shirtOn   = RegistrationRepo::shirtEnabled($editionId);

if (input('export') !== null) {
    $headers = ['Paper ID', 'Title', 'Mode', 'Role', 'Name', 'Affiliation', 'Email'];
    if ($shirtOn) {
        $headers[] = 'Shirt size';
    }
    array_push($headers, 'Video host', 'Video link', 'Note');

    $lines = [];
    foreach ($rows as $row) {
        $people = [['Presenter', (string) ($row['presenter_name'] ?? ''), (string) ($row['presenter_affiliation'] ?? ''), (string) ($row['presenter_email'] ?? ''), (string) ($row['presenter_shirt'] ?? '')]];
        foreach ($row['attendees'] as $a) {
            $people[] = [$a['paper_author_id'] !== null ? 'Co-author (non-presenter)' : 'Guest (non-presenter)',
                (string) $a['name'], (string) ($a['affiliation'] ?? ''), (string) ($a['email'] ?? ''), (string) ($a['shirt_size'] ?? '')];
        }
        foreach ($people as $i => [$role, $name, $aff, $email, $shirt]) {
            $line = [$row['paper_id'], $row['paper_title'], $row['mode'], $role, $name, $aff, $email];
            if ($shirtOn) {
                $line[] = $shirt;
            }
            array_push(
                $line,
                $i === 0 ? (AttendanceRepo::VIDEO_HOSTS[$row['video_host'] ?? ''] ?? '') : '',
                $i === 0 ? (string) ($row['video_url'] ?? '') : '',
                $i === 0 ? (string) ($row['note'] ?? '') : ''
            );
            $lines[] = $line;
        }
    }

    Csv::download('presentations-' . ($edition['slug'] ?? 'cenim') . '-' . gmdate('Ymd') . '.csv', $headers, $lines);
}

$all    = AttendanceRepo::overview($editionId);
$counts = ['offline' => 0, 'online' => 0, 'missing' => 0, 'people' => 0];
foreach ($all as $row) {
    $counts[$row['mode'] === 'online' ? 'online' : 'offline']++;
    $counts['missing'] += $row['missing'] ? 1 : 0;
    $counts['people']  += 1 + count($row['attendees']);
}

View::render('admin/attendance', [
    'title'    => 'Presentations',
    'subtitle' => ($edition['name'] ?? '') . ' — paid papers: presenter, attendees and videos',
    'active'   => 'attendance',
    'edition'  => $edition,
    'rows'     => $rows,
    'counts'   => $counts,
    'total'    => count($all),
    'filter'   => $filter,
    'extra'    => $extra,
    'shirtOn'  => $shirtOn,
    'shirts'   => $shirtOn ? RegistrationRepo::shirtSummary($editionId, true) : [],
], 'admin');
