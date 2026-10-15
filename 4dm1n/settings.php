<?php
/**
 * Pengaturan umum edisi: periode aktif, tanggal rilis sertifikat, rekening,
 * kontak, dan penanda tangan. (Penyimpanan berkas diatur di .env: STORAGE_DRIVER, TANDIKAT_*.)
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if ($editionId === 0) {
    Session::flash('error', 'No active edition yet.');
    Response::redirect(admin_url('index.php'));
}

if (is_post() && in_array(input('action'), ['guide_upload', 'guide_remove'], true)) {
    Csrf::verify();

    if (input('action') === 'guide_remove') {
        SettingRepo::set($editionId, 'user_guide_file_id', 0);
        Session::flash('success', 'User guide removed from the sign-in page.');
    } else {
        try {
            $upload = $_FILES['guide'] ?? [];
            if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                && (string) (mime_content_type((string) $upload['tmp_name']) ?: '') !== 'application/pdf') {
                throw new InvalidArgumentException('The user guide must be a PDF file.');
            }
            $fileId = Cenim\Domain\FileRepo::replace($upload, 'user_guide', 'edition', $editionId, $editionId, (int) ($edition['year'] ?? date('Y')));
            SettingRepo::set($editionId, 'user_guide_file_id', $fileId);
            Audit::log('settings.user_guide', ['meta' => ['file_id' => $fileId]]);
            Session::flash('success', 'User guide uploaded — it is now linked on the sign-in page.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', 'User guide: ' . $e->getMessage());
        }
    }

    Response::redirect(admin_url('settings.php') . '#user-guide');
}

if (is_post() && in_array(input('action'), ['signature_upload', 'signature_remove'], true)) {
    Csrf::verify();

    $role  = (string) input('role', 'chair');
    $label = match ($role) { 'treasurer' => 'Treasurer signature', 'stamp' => 'Stamp', default => 'Chair signature' };
    $docs  = match ($role) { 'treasurer' => 'invoices and receipts', 'stamp' => 'the LoA, invoices and receipts', default => 'the LoA' };

    if (!isset(Cenim\Domain\BrandingRepo::SIGNERS[$role])) {
        Session::flash('error', 'Unknown signer.');
    } elseif (input('action') === 'signature_remove') {
        Cenim\Domain\BrandingRepo::clearSignature($editionId, $role);
        Audit::log('settings.signature_removed', ['meta' => ['role' => $role]]);
        Session::flash('success', $label . ' removed from ' . $docs . '.');
    } else {
        try {
            $fileId = Cenim\Domain\BrandingRepo::setSignature($editionId, (int) ($edition['year'] ?? date('Y')), $role, $_FILES['signature'] ?? []);
            Audit::log('settings.signature', ['meta' => ['role' => $role, 'file_id' => $fileId]]);
            Session::flash('success', $label . ' uploaded — ' . $docs . ' now show it.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $label . ': ' . $e->getMessage());
        }
    }

    Response::redirect(admin_url('settings.php') . '#' . (isset(Cenim\Domain\BrandingRepo::SIGNERS[$role]) ? $role : 'chair'));
}

if (is_post()) {
    Csrf::verify();

    // ── Periode & tanggal ───────────────────────────────────────────────────
    SettingRepo::set($editionId, 'registration_open', input_bool('registration_open'));
    SettingRepo::set($editionId, 'payment_open', input_bool('payment_open'));
    SettingRepo::set($editionId, 'attendance_open', input_bool('attendance_open'));
    $attendanceDeadline = trim((string) input('attendance_deadline', ''));
    SettingRepo::set($editionId, 'attendance_deadline', preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendanceDeadline) === 1 ? $attendanceDeadline : '');
    SettingRepo::set($editionId, 'registration_period', [
        'start' => (string) input('period_start', ''),
        'end'   => (string) input('period_end', ''),
    ]);
    SettingRepo::set($editionId, 'invoice_due_days', max(0, input_int('invoice_due_days')));

    // ── Ukuran baju di Registration form ────────────────────────────────────
    SettingRepo::set($editionId, 'shirt_size_enabled', input_bool('shirt_size_enabled'));
    $sizes = array_values(array_unique(array_filter(array_map(
        static fn (string $s): string => mb_substr(trim($s), 0, 12),
        explode(',', (string) input('shirt_sizes', ''))
    ), static fn (string $s): bool => $s !== '')));
    SettingRepo::set($editionId, 'shirt_sizes', $sizes !== [] ? $sizes : Cenim\Domain\RegistrationRepo::DEFAULT_SHIRT_SIZES);

    // ── Kontak & tanda tangan ───────────────────────────────────────────────
    foreach (['contact_email', 'contact_whatsapp', 'signatory_name', 'signatory_title', 'treasurer_name', 'treasurer_title'] as $key) {
        SettingRepo::set($editionId, $key, trim((string) input($key, '')));
    }

    SettingRepo::set($editionId, 'stamp_position', [
        'size' => (float) input('stamp_size', (string) SettingRepo::STAMP_DEFAULT['size']),
        'dx'   => (float) input('stamp_dx', (string) SettingRepo::STAMP_DEFAULT['dx']),
        'dy'   => (float) input('stamp_dy', (string) SettingRepo::STAMP_DEFAULT['dy']),
    ]);

    SettingRepo::set($editionId, 'invoice_note', trim((string) input('invoice_note', '')));
    SettingRepo::set($editionId, 'receipt_note', trim((string) input('receipt_note', '')));
    // ── Email: nama pengirim, Reply-To, penutup ─────────────────────────────
    foreach (array_keys(Cenim\Core\Mailer::SETTINGS) as $key) {
        $value = mb_substr(trim((string) input($key, '')), 0, 190);
        if (in_array($key, ['mail_reply_to', 'mail_signature_email'], true) && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Email "' . $value . '" is not valid — the previous value was kept.');
            continue;
        }
        SettingRepo::set($editionId, $key, $value);
    }

    SettingRepo::set($editionId, 'loa_number_base', mb_substr(trim((string) input('loa_number_base', '')), 0, 120));
    SettingRepo::set($editionId, 'loa_city', mb_substr(trim((string) input('loa_city', '')), 0, 120));
    // Teks yang sama persis dengan bawaan disimpan kosong, jadi tetap ikut bawaan.
    $loaBody = trim(str_replace("\r\n", "\n", (string) input('loa_body', '')));
    SettingRepo::set($editionId, 'loa_body', $loaBody === Cenim\Domain\LoaRepo::DEFAULT_BODY ? '' : $loaBody);

    // ── Rekening bank ───────────────────────────────────────────────────────
    $accounts = [];
    foreach (['IDR', 'USD'] as $currency) {
        $accounts[] = [
            'currency'     => $currency,
            'bank_name'    => trim((string) input('bank_' . $currency . '_name', '')),
            'account_name' => trim((string) input('bank_' . $currency . '_holder', '')),
            'account_no'   => trim((string) input('bank_' . $currency . '_number', '')),
            'swift'        => trim((string) input('bank_' . $currency . '_swift', '')),
            'branch'       => trim((string) input('bank_' . $currency . '_branch', '')),
        ];
    }
    SettingRepo::set($editionId, 'bank_accounts', $accounts);
    SettingRepo::set($editionId, 'bank_public', input_bool('bank_public'));

    // ── Kolom pada tabel editions ───────────────────────────────────────────
    $release = trim((string) input('certificate_release_at', ''));

    Db::update('editions', [
        'conference_start'       => trim((string) input('conference_start', '')) ?: null,
        'conference_end'         => trim((string) input('conference_end', '')) ?: null,
        'certificate_release_at' => $release !== '' ? str_replace('T', ' ', $release) . (strlen($release) <= 10 ? ' 00:00:00' : '') : null,
        'updated_at'             => now(),
    ], 'id = :target_id', ['target_id' => $editionId]);

    Audit::log('settings.updated', ['meta' => ['edition' => $edition['name'] ?? '']]);
    Session::flash('success', 'Settings saved.');
    Response::redirect(admin_url('settings.php'));
}

$banks = [];
foreach (SettingRepo::getArray($editionId, 'bank_accounts', []) as $account) {
    if (is_array($account) && isset($account['currency'])) {
        $banks[(string) $account['currency']] = $account;
    }
}

View::render('admin/settings', [
    'guide'    => Cenim\Domain\FileRepo::find((int) SettingRepo::get($editionId, 'user_guide_file_id', 0)),
    'signatures' => [
        'chair'     => Cenim\Domain\BrandingRepo::signatureFile($editionId, 'chair'),
        'treasurer' => Cenim\Domain\BrandingRepo::signatureFile($editionId, 'treasurer'),
        'stamp'     => Cenim\Domain\BrandingRepo::signatureFile($editionId, 'stamp'),
    ],
    'stamp'     => SettingRepo::stamp($editionId),
    'title'    => 'Settings',
    'subtitle' => $edition['name'] ?? '',
    'active'   => 'settings',
    'edition'  => $edition,
    'period'   => SettingRepo::getArray($editionId, 'registration_period', ['start' => '', 'end' => '']),
    'banks'    => $banks,
    'values'   => [
        'registration_open'   => SettingRepo::getBool($editionId, 'registration_open', true),
        'payment_open'        => SettingRepo::getBool($editionId, 'payment_open', true),
        'attendance_open'     => SettingRepo::getBool($editionId, 'attendance_open', true),
        'attendance_deadline' => SettingRepo::getString($editionId, 'attendance_deadline'),
        'bank_public'         => SettingRepo::getBool($editionId, 'bank_public', false),
        'shirt_size_enabled'  => Cenim\Domain\RegistrationRepo::shirtEnabled($editionId),
        'shirt_sizes'         => implode(', ', Cenim\Domain\RegistrationRepo::shirtSizes($editionId)),
        'invoice_due_days'    => (int) SettingRepo::get($editionId, 'invoice_due_days', 14),
        'contact_email'       => SettingRepo::getString($editionId, 'contact_email'),
        'contact_whatsapp'    => SettingRepo::getString($editionId, 'contact_whatsapp'),
        // Chair: nilai lama dari Settings → Email ditampilkan bila belum diisi.
        'signatory_name'      => SettingRepo::getString($editionId, 'signatory_name')
            ?: SettingRepo::getString($editionId, 'mail_signature_name'),
        'signatory_title'     => SettingRepo::getString($editionId, 'signatory_title')
            ?: SettingRepo::getString($editionId, 'mail_signature_role', SettingRepo::DEFAULT_CHAIR_TITLE),
        'treasurer_name'      => SettingRepo::getString($editionId, 'treasurer_name'),
        'treasurer_title'     => SettingRepo::getString($editionId, 'treasurer_title', SettingRepo::DEFAULT_TREASURER_TITLE),
        'loa_city'            => SettingRepo::getString($editionId, 'loa_city', Cenim\Domain\LoaRepo::DEFAULT_CITY),
        'invoice_note'        => SettingRepo::getString($editionId, 'invoice_note'),
        'receipt_note'        => SettingRepo::getString($editionId, 'receipt_note'),
        'loa_number_base'     => SettingRepo::getString($editionId, 'loa_number_base'),
    ] + array_combine(
        array_keys(Cenim\Core\Mailer::SETTINGS),
        array_map(
            static fn (string $key): string => SettingRepo::getString($editionId, $key, Cenim\Core\Mailer::SETTINGS[$key]),
            array_keys(Cenim\Core\Mailer::SETTINGS)
        )
    ) + [
        'loa_body'            => SettingRepo::getString($editionId, 'loa_body') ?: Cenim\Domain\LoaRepo::DEFAULT_BODY,
    ],
], 'admin');
