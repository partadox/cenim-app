<?php
/**
 * Editor jadwal/timeline, termasuk aksi EXTEND yang khas CENIM:
 * tanggal lama tidak dihapus, hanya dicoret di landing page.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\TimelineRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if (is_post()) {
    Csrf::verify();

    $action = (string) input('action', '');
    $id     = input_int('id');

    switch ($action) {
        case 'create':
            $input  = [
                'title'             => (string) input('title', ''),
                'current_date_text' => (string) input('current_date_text', ''),
                'note'              => (string) input('note', ''),
                'sort'              => input_int('sort'),
                'is_visible'        => true,
            ];
            $errors = TimelineRepo::validate($input);

            if ($errors !== []) {
                Session::flash('error', implode(' ', $errors));
                break;
            }

            $newId = TimelineRepo::create($editionId, $input);
            Audit::log('timeline.created', ['target_type' => 'timeline', 'target_id' => $newId, 'meta' => ['title' => $input['title']]]);
            Session::flash('success', 'Timeline item added.');
            break;

        case 'update':
            $item = TimelineRepo::find($id);

            if ($item === null || (int) $item['edition_id'] !== $editionId) {
                Session::flash('error', 'Item not found.');
                break;
            }

            $input  = [
                'title'             => (string) input('title', ''),
                'current_date_text' => (string) input('current_date_text', ''),
                'note'              => (string) input('note', ''),
                'sort'              => input_int('sort'),
                'is_visible'        => input_bool('is_visible'),
            ];
            $errors = TimelineRepo::validate($input);

            if ($errors !== []) {
                Session::flash('error', implode(' ', $errors));
                break;
            }

            TimelineRepo::update($id, $input);
            Session::flash('success', 'Timeline item updated.');
            break;

        case 'extend':
            $newDate = trim((string) input('new_date', ''));
            $label   = trim((string) input('label', ''));
            // Label perpanjangan ala situs lama: "(1st Extend) 31 August 2026".
            if ($newDate !== '' && $label !== '' && !str_starts_with($newDate, '(')) {
                $newDate = '(' . mb_substr($label, 0, 40) . ') ' . $newDate;
            }

            if ($newDate === '') {
                Session::flash('error', 'Please write the new date.');
                break;
            }

            if (TimelineRepo::extend($id, $newDate)) {
                Session::flash('success', 'Deadline extended — the previous date now shows with a strikethrough on the landing page.');
            } else {
                Session::flash('error', 'Could not extend that item.');
            }
            break;

        case 'undo':
            if (TimelineRepo::undoExtend($id)) {
                Session::flash('success', 'Last extension undone.');
            } else {
                Session::flash('info', 'This item has no extension history.');
            }
            break;

        case 'delete':
            $item = TimelineRepo::find($id);

            if ($item !== null && (int) $item['edition_id'] === $editionId) {
                TimelineRepo::delete($id);
                Audit::log('timeline.deleted', ['target_type' => 'timeline', 'target_id' => $id, 'meta' => ['title' => $item['title']]]);
                Session::flash('success', 'Timeline item deleted.');
            }
            break;

        default:
            Session::flash('error', 'Unknown action.');
    }

    Response::redirect(admin_url('timeline.php'));
}

View::render('admin/timeline', [
    'title'    => 'Timeline',
    'subtitle' => ($edition['name'] ?? '') . ' — dates shown on the landing page',
    'active'   => 'timeline',
    'edition'  => $edition,
    'items'    => TimelineRepo::all($editionId),
], 'admin');
