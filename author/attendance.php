<?php
/**
 * Form 5 — Video Presentation: link video presentasi (Google Drive / YouTube).
 *
 * Presenter dan mode offline/online diisi di Registration form; di sini hanya
 * link video — wajib untuk presentasi online, opsional (cadangan) untuk
 * offline. Terbuka setelah pembayaran lunas, selama Settings → attendance_open
 * aktif dan batas waktunya belum lewat. Semua author paper boleh mengisi.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\AuthorAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Mailer;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\AttendanceRepo;
use Cenim\Domain\FormRepo;
use Cenim\Domain\InvoiceRepo;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$paper     = current_author();
$editionId = (int) ($edition['id'] ?? 0);

if ($paper === null || $editionId === 0) {
    Response::abort(503, 'The video presentation form is not available right now.');
}

$invoice = InvoiceRepo::findForAuthor((int) $paper['id']);
if (!AttendanceRepo::isAvailable($invoice)) {
    Session::flash('info', 'The video presentation form opens once the payment of this paper is confirmed.');
    Response::redirect(author_url('index.php'));
}

$registration = RegistrationRepo::findByAuthor((int) $paper['id']) ?? [];
$mode         = (string) ($registration['presentation_mode'] ?? 'offline');
$isOnline     = $mode === 'online';
$presenter    = ($registration['presenter_id'] ?? null) !== null ? PaperAuthorRepo::find((int) $registration['presenter_id']) : null;
$attendance   = AttendanceRepo::findForPaper((int) $paper['id']);
$isOpen       = AttendanceRepo::isOpen($editionId) && !edition_is_archive();
$extraFields  = FormRepo::fields($editionId, FormRepo::FORM_ATTENDANCE);
$errors       = [];

$values = [
    'video_url' => (string) ($attendance['video_url'] ?? ''),
    'note'      => (string) ($attendance['note'] ?? ''),
    'extra'     => FormRepo::decode($attendance['extra_json'] ?? null),
];

if (is_post()) {
    Csrf::verify();

    if (!$isOpen) {
        Session::flash('error', 'The video presentation form is closed. Please contact the secretariat for changes.');
        Response::redirect(author_url('attendance.php'));
    }

    $check = AttendanceRepo::validate($_POST, $isOnline);
    $extra = FormRepo::validate($extraFields, (array) ($_POST['extra'] ?? []));

    $errors = $check['errors'];
    foreach ($extra['errors'] as $key => $message) {
        $errors['extra_' . $key] = $message;
    }

    $values = [
        'video_url' => (string) ($_POST['video_url'] ?? ''),
        'note'      => (string) ($_POST['note'] ?? ''),
        'extra'     => $extra['values'],
    ];

    if ($errors === []) {
        $person = current_person();
        $id     = AttendanceRepo::save($paper, $registration, $check['values'], $extra['values'], $person);

        Audit::log('video.submitted', [
            'actor_type'  => 'author',
            'actor_id'    => (string) AuthorAuth::email(),
            'target_type' => 'attendance',
            'target_id'   => $id,
            'meta'        => ['paper_id' => $paper['paper_id'], 'mode' => $mode, 'video' => $check['values']['video_url'] !== null],
        ]);

        Mailer::send(
            (string) ($person['email'] ?? $paper['email']),
            (string) ($person['name'] ?? $paper['first_author_name']),
            'Presentation video received — ' . ($edition['name'] ?? 'CENIM') . ' (paper ' . $paper['paper_id'] . ')',
            'attendance_submitted',
            [
                'paperId'    => (string) $paper['paper_id'],
                'paperTitle' => (string) ($paper['paper_title'] ?? ''),
                'mode'       => RegistrationRepo::MODES[$mode] ?? ucfirst($mode),
                'presenter'  => (string) ($presenter['name'] ?? '-'),
                'videoUrl'   => (string) ($check['values']['video_url'] ?? ''),
                'loginUrl'   => full_url(author_url('attendance.php')),
            ]
        );

        Session::flash('success', 'Thank you — the presentation video has been recorded. You can still change it while the form is open.');
        Response::redirect(author_url('index.php'));
    }

    Session::flash('error', 'Please fix ' . count($errors) . ' item(s) below.');
}

View::render('author/attendance', [
    'title'        => 'Video presentation',
    'edition'      => $edition,
    'author'       => $paper,
    'attendance'   => $attendance,
    'registration' => $registration,
    'presenter'    => $presenter,
    'attendees'    => $registration !== [] ? RegistrationRepo::attendees((int) $registration['id']) : [],
    'isOnline'     => $isOnline,
    'isOpen'       => $isOpen,
    'deadline'     => SettingRepo::getString($editionId, 'attendance_deadline'),
    'values'       => $values,
    'errors'       => $errors,
    'extraFields'  => $extraFields,
], 'author');
