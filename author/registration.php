<?php
/**
 * Form 1 — registrasi satu paper (terbuka setelah confirmation form / form 0):
 *   - presenter (dipilih dari author paper) + mode offline/online
 *   - kategori biaya presenter (student / IEEE) + bukti
 *   - paper tambahan dari first author yang sama → biaya registrasi 50%
 *     (invoice, LoA, receipt tetap per paper)
 *   - peserta tambahan non-presenter ("keranjang"): co-author atau orang luar
 *   - ukuran baju per orang yang datang (bila diaktifkan di Settings)
 *   - pertanyaan tambahan dari Form Builder
 *
 * Author bisa menyimpan sebagai draft (save) atau mengirim (submit).
 * Setelah submitted/verified form menjadi read-only; bila ditolak, form
 * terbuka kembali beserta alasan penolakannya.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Mailer;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\AttendanceRepo;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\ConfirmationRepo;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\FileRepo;
use Cenim\Domain\FormRepo;
use Cenim\Domain\PaperAuthorRepo;
use Cenim\Domain\ParticipantRepo;
use Cenim\Domain\RegistrationRepo;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$author    = current_author();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0 || $author === null) {
    Response::abort(503, 'Registration is not available right now.');
}

if (Cenim\Domain\AuthorRepo::isWithdrawn($author)) {
    Session::flash('info', 'This paper has been withdrawn, so no registration is needed.');
    Response::redirect(author_url('index.php'));
}

// Paper edisi lama tanpa registrasi: tidak ada yang bisa ditampilkan/dibuat.
if (edition_is_archive() && RegistrationRepo::findByAuthor((int) $author['id']) === null) {
    Session::flash('info', ($edition['name'] ?? 'This edition') . ' has ended; this paper was not registered.');
    Response::redirect(author_url('index.php'));
}

// Peserta non-presenter (bukan paper): form sendiri yang lebih sederhana.
if (ParticipantRepo::isParticipant($author)) {
    define('CENIM_PARTICIPANT_REGISTRATION', true);
    require __DIR__ . '/_registration_participant.php';
    exit;
}

// Confirmation form (form 0) dulu; registrasi yang sudah dikirim tidak ditahan.
if (ConfirmationRepo::blocksRegistration($editionId, $author, RegistrationRepo::findByAuthor((int) $author['id']))) {
    Session::flash('info', 'Please submit the confirmation form first. The registration form opens right after it.');
    Response::redirect(author_url('confirmation.php'));
}

$adminMode = false;
$selfUrl   = author_url('registration.php');
$backUrl   = author_url('index.php');

require __DIR__ . '/_registration_form.php';
