<?php
/**
 * Pendaftaran terbuka peserta non-presenter (datang offline, tidak terkait
 * paper mana pun): email, nama, afiliasi, negara — dilindungi Turnstile.
 * Setelah terdaftar, peserta login lewat Portal (OTP) seperti author.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\Audit;
use Cenim\Core\AuthorAuth;
use Cenim\Core\Csrf;
use Cenim\Core\RateLimit;
use Cenim\Core\Response;
use Cenim\Core\Turnstile;
use Cenim\Core\View;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\ParticipantRepo;
use Cenim\Domain\SettingRepo;
use Cenim\Support\Countries;

const PARTICIPANT_REGISTER_KIND = 'participant_register';

panel_boot('author');

if (AuthorAuth::isLoggedIn()) {
    Response::redirect(author_url('index.php'));
}

$edition   = EditionRepo::active();
$editionId = (int) ($edition['id'] ?? 0);
$open      = $editionId > 0 && ParticipantRepo::registrationOpen($editionId);
$errors    = [];
$done      = null;
$values    = ['email' => '', 'name' => '', 'affiliation' => '', 'region' => '', 'country' => ''];

if (is_post() && $open) {
    Csrf::verify();

    $values = [
        'email'       => mb_strtolower(trim((string) input('email', ''))),
        'name'        => trim((string) input('name', '')),
        'affiliation' => trim((string) input('affiliation', '')),
        'region'      => (string) input('region', ''),
        'country'     => ParticipantRepo::countryFromInput((string) input('region', ''), (string) input('country', '')),
    ];

    $wait = RateLimit::retryAfter(PARTICIPANT_REGISTER_KIND, client_ip());
    if ($wait > 0) {
        $errors['form'] = 'Too many registrations from this network. Please try again in ' . RateLimit::humanRetryAfter($wait) . '.';
    } elseif (($turnstileError = Turnstile::check()) !== null) {
        $errors['form'] = $turnstileError;
    } else {
        $errors = ParticipantRepo::validate($values, $editionId);
    }

    if ($errors === []) {
        $id = ParticipantRepo::create($editionId, $values, 'self');
        // Batasi jumlah pendaftaran per IP (dihitung sebagai percobaan).
        RateLimit::record(PARTICIPANT_REGISTER_KIND, client_ip(), false);
        Audit::log('participant.registered', [
            'actor_type'  => 'participant',
            'actor_id'    => $values['email'],
            'target_type' => 'author',
            'target_id'   => $id,
        ]);
        $done = $values['email'];
    }
}

View::render('author/register', [
    'title'       => 'Participant registration',
    'edition'     => $edition,
    'open'        => $open,
    'values'      => $values,
    'errors'      => $errors,
    'done'        => $done,
    'countries'   => Countries::all(),
    'showCaptcha' => Turnstile::enabled(),
    'contact'     => $editionId > 0 ? SettingRepo::getString($editionId, 'contact_email') : '',
], 'auth');
