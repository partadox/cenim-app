<?php

declare(strict_types=1);

namespace Cenim\Core;

use Cenim\Domain\EditionRepo;
use Cenim\Domain\SettingRepo;
use Throwable;

/**
 * Pengirim email notifikasi. Setiap pengiriman dicatat di tabel mail_log.
 *
 * Transport dipilih otomatis:
 *   - MAIL_ENABLED=false  → "log": email TIDAK dikirim, hanya disimpan sebagai
 *     file .html / .txt di storage/logs/mail/ supaya bisa diperiksa saat development.
 *
 * Pengirim: SMTP_SENDER + SMTP_SENDER_NAME (nama lama MAIL_FROM/MAIL_FROM_NAME tetap dibaca).
 *   - SMTP_HOST diisi     → "smtp": klien SMTP sendiri (src/Core/Smtp.php).
 *   - selain itu          → "mail": fungsi mail() bawaan PHP (cocok di cPanel).
 */
final class Mailer
{
    /**
     * @param  array<string,mixed> $data variabel untuk view
     * @return bool true bila terkirim (atau tercatat, saat transport "log")
     */
    public static function send(string $toEmail, string $toName, string $subject, string $view, array $data = []): bool
    {
        $editionId = EditionRepo::contextId() ?: null;

        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            self::log($editionId, $toEmail, $subject, $view, 'failed', 'Alamat email tidak valid');
            return false;
        }

        try {
            $html = self::render($view, $data + [
                'subject'      => $subject,
                'recipient'    => $toName,
                'editionName'  => EditionRepo::context()['name'] ?? cfg('app_name'),
                'signature'    => self::signatureLines(),
                'replyTo'      => self::replyTo(),
            ]);
        } catch (Throwable $e) {
            Logger::error('Mailer: gagal merender template', ['view' => $view, 'error' => $e->getMessage()]);
            self::log($editionId, $toEmail, $subject, $view, 'failed', 'Template error: ' . $e->getMessage());
            return false;
        }

        return self::deliver($editionId, $toEmail, $toName, $subject, $html, true, $view);
    }

    /**
     * Kirim email TEKS BIASA (tanpa HTML, tanpa layout) — untuk pesan pendek
     * seperti kode OTP, supaya tidak terlihat seperti email promosi/spam.
     */
    public static function sendText(string $toEmail, string $toName, string $subject, string $text, string $tag): bool
    {
        $editionId = EditionRepo::contextId() ?: null;

        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            self::log($editionId, $toEmail, $subject, $tag, 'failed', 'Alamat email tidak valid');
            return false;
        }

        return self::deliver($editionId, $toEmail, $toName, $subject, $text, false, $tag);
    }

    /**
     * Pengaturan email yang bisa diubah admin (Settings → Email). Teks boleh
     * memakai token {edition_name} dan {edition_year}, jadi "CENIM 2026"
     * otomatis ikut edisi aktif.
     */
    public const SETTINGS = [
        'mail_sender_name'     => '{edition_name}',
        'mail_reply_to'        => '',            // kosong = Secretariat email (Settings)
        'mail_signature_email' => '',            // kosong = Secretariat email (Settings)
        'mail_signature_web'   => '',            // kosong = alamat situs (APP_URL + APP_BASE)
    ];

    /**
     * Alamat pengirim. Alamatnya dari .env (SMTP_SENDER — harus domain yang
     * terverifikasi di ZeptoMail); nama tampilannya dari Settings.
     */
    public static function from(): array
    {
        $name = self::setting('mail_sender_name');

        return [
            'email' => (string) env('SMTP_SENDER', env('MAIL_FROM', '')),
            'name'  => $name !== '' ? $name : (string) env('SMTP_SENDER_NAME', env('MAIL_FROM_NAME', '')),
        ];
    }

    /** Alamat balasan (Reply-To), atau null bila tidak diisi / tidak valid. */
    public static function replyTo(): ?string
    {
        $email = self::setting('mail_reply_to');
        if ($email === '') {
            $email = self::contactEmail();
        }
        if ($email === '') {
            $email = (string) env('SMTP_REPLY_TO', '');
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /** Baris penutup ("Regards, …") untuk semua email. @return array<int,string> */
    public static function signatureLines(): array
    {
        $web = self::setting('mail_signature_web');

        return array_values(array_filter([
            'Regards,',
            ...array_values(self::chair()),
            ($sigEmail = self::setting('mail_signature_email') ?: self::contactEmail()) !== '' ? 'Email: ' . $sigEmail : '',
            'Web: ' . ($web !== '' ? $web : rtrim(abs_url(''), '/') . '/'),
        ], static fn (string $line): bool => $line !== ''));
    }

    /** Jabatan & nama chair (Settings → Conference chair) untuk penutup email. @return array{title:string,name:string} */
    private static function chair(): array
    {
        $editionId = EditionRepo::contextId();
        $chair     = $editionId > 0 ? SettingRepo::chair($editionId) : ['name' => '', 'title' => ''];

        return ['title' => $chair['title'], 'name' => $chair['name']];
    }

    /** Email sekretariat (Settings → Secretariat email) — satu sumber kontak. */
    public static function contactEmail(): string
    {
        $editionId = EditionRepo::contextId();

        return $editionId > 0 ? SettingRepo::getString($editionId, 'contact_email') : '';
    }

    /** Nilai setting email (token edisi sudah diganti); bawaan dari SETTINGS. */
    public static function setting(string $key): string
    {
        $edition   = EditionRepo::context() ?? [];
        $editionId = (int) ($edition['id'] ?? 0);
        $value     = $editionId > 0
            ? SettingRepo::getString($editionId, $key, self::SETTINGS[$key] ?? '')
            : (self::SETTINGS[$key] ?? '');

        return trim(strtr($value, [
            '{edition_name}' => (string) ($edition['name'] ?? cfg('app_name')),
            '{edition_year}' => (string) ($edition['year'] ?? date('Y')),
        ]));
    }

    private static function deliver(
        ?int $editionId,
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        bool $isHtml,
        string $view
    ): bool {
        $transport = self::transport();
        $filePath  = null;

        try {
            match ($transport) {
                'smtp' => self::viaSmtp($toEmail, $toName, $subject, $body, $isHtml),
                'mail' => self::viaMail($toEmail, $toName, $subject, $body, $isHtml),
                default => $filePath = self::viaFile($toEmail, $subject, $view, $body, $isHtml),
            };
        } catch (Throwable $e) {
            Logger::error('Mailer: pengiriman gagal', [
                'transport' => $transport,
                'to'        => $toEmail,
                'error'     => $e->getMessage(),
            ]);
            self::log($editionId, $toEmail, $subject, $view, 'failed', $e->getMessage());
            return false;
        }

        self::log($editionId, $toEmail, $subject, $view, $transport === 'log' ? 'skipped' : 'sent', null, $filePath);

        return true;
    }

    public static function transport(): string
    {
        if (!env('MAIL_ENABLED', false)) {
            return 'log';
        }

        return ((string) env('SMTP_HOST', '')) !== '' ? 'smtp' : 'mail';
    }

    /** Render view email di dalam layout email. */
    public static function render(string $view, array $data): string
    {
        $content = View::partial('mail/' . $view, $data);

        return View::partial('mail/layout', $data + ['content' => $content]);
    }

    private static function viaSmtp(string $toEmail, string $toName, string $subject, string $body, bool $isHtml): void
    {
        $smtp = new Smtp(
            (string) env('SMTP_HOST', ''),
            (int) env('SMTP_PORT', 587),
            (string) env('SMTP_SECURE', 'tls'),
            (string) env('SMTP_USER', ''),
            (string) env('SMTP_PASS', '')
        );

        $smtp->send(self::from(), $toEmail, $toName, $subject, $body, $isHtml, self::replyTo());
    }

    private static function viaMail(string $toEmail, string $toName, string $subject, string $html, bool $isHtml): void
    {
        ['email' => $from, 'name' => $fromName] = self::from();
        $boundary = 'cenim-' . random_token(8);

        $headers = implode("\r\n", [
            'From: ' . Smtp::formatAddress($from, $fromName),
            'Reply-To: ' . (self::replyTo() ?? Smtp::formatAddress($from, $fromName)),
            'MIME-Version: 1.0',
            $isHtml
                ? 'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
                : 'Content-Type: text/plain; charset=UTF-8',
        ]);

        $body = !$isHtml ? $html : "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . Smtp::htmlToText($html) . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $html . "\r\n\r\n"
            . "--{$boundary}--";

        $ok = mail(
            Smtp::formatAddress($toEmail, $toName),
            Smtp::encodeHeader($subject),
            $body,
            $headers,
            $from !== '' ? '-f' . $from : ''
        );

        if (!$ok) {
            throw new \RuntimeException('Fungsi mail() PHP mengembalikan false.');
        }
    }

    /**
     * Transport development: simpan email sebagai file HTML.
     *
     * @return string path relatif terhadap folder storage (dicatat di mail_log)
     */
    private static function viaFile(string $toEmail, string $subject, string $view, string $html, bool $isHtml = true): string
    {
        $dir = storage_path('logs/mail');

        if (!ensure_dir($dir)) {
            throw new \RuntimeException('Folder storage/logs/mail tidak writable.');
        }

        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $view . '-' . $toEmail));
        $file = sprintf('%s/%s-%s.%s', $dir, gmdate('Ymd-His'), trim($slug, '-'), $isHtml ? 'html' : 'txt');

        $header = $isHtml
            ? sprintf("<!-- To: %s | Subject: %s | Template: %s | %s UTC -->\n", $toEmail, $subject, $view, now())
            : sprintf("To: %s\nSubject: %s\nTemplate: %s\nDate: %s UTC\n\n", $toEmail, $subject, $view, now());

        if (file_put_contents($file, $header . $html) === false) {
            throw new \RuntimeException('Gagal menulis file email.');
        }

        return 'logs/mail/' . basename($file);
    }

    private static function log(
        ?int $editionId,
        string $toEmail,
        string $subject,
        string $view,
        string $status,
        ?string $error,
        ?string $filePath = null
    ): void {
        try {
            if (!Db::tableExists('mail_log')) {
                return;
            }

            Db::insert('mail_log', [
                'edition_id' => $editionId,
                'to_email'   => mb_substr($toEmail, 0, 190),
                'subject'    => mb_substr($subject, 0, 255),
                'template'   => $view,
                'status'     => $status,
                'error'      => $error === null ? null : mb_substr($error, 0, 500),
                'created_at' => now(),
                'sent_at'    => $status === 'sent' ? now() : null,
                'file_path'  => $filePath,
            ]);
        } catch (Throwable $e) {
            Logger::error('Mailer: gagal menulis mail_log', ['error' => $e->getMessage()]);
        }
    }
}
