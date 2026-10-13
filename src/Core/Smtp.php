<?php

declare(strict_types=1);

namespace Cenim\Core;

use RuntimeException;

/**
 * Klien SMTP minimal (tanpa composer / PHPMailer).
 *
 * Mendukung: koneksi biasa, implicit TLS (port 465, skema ssl://), STARTTLS,
 * dan AUTH LOGIN / AUTH PLAIN. Cukup untuk mengirim satu pesan HTML lewat
 * akun email cPanel.
 */
final class Smtp
{
    private mixed $socket = null;
    private string $log   = '';

    public function __construct(
        private string $host,
        private int $port = 587,
        private string $secure = 'tls',      // tls (STARTTLS) | ssl | ''
        private string $username = '',
        private string $password = '',
        private int $timeout = 15
    ) {
    }

    /**
     * @param array{email:string,name?:string} $from
     * @param bool $isHtml false = kirim $body apa adanya sebagai text/plain saja
     * @throws RuntimeException
     */
    public function send(array $from, string $toEmail, string $toName, string $subject, string $body, bool $isHtml = true, ?string $replyTo = null): void
    {
        $this->connect();

        try {
            $this->expect($this->read(), 220);

            $hostname = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $this->command('EHLO ' . $hostname, 250);

            if ($this->secure === 'tls') {
                $this->command('STARTTLS', 220);

                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS gagal dinegosiasikan.');
                }

                // Wajib EHLO ulang setelah kanal dienkripsi.
                $this->command('EHLO ' . $hostname, 250);
            }

            if ($this->username !== '') {
                $this->authenticate();
            }

            $this->command('MAIL FROM:<' . $from['email'] . '>', 250);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', 354);

            $this->write($this->buildMessage($from, $toEmail, $toName, $subject, $body, $isHtml, $replyTo) . "\r\n.\r\n");
            $this->expect($this->read(), 250);

            $this->command('QUIT', [221, 250]);
        } finally {
            $this->close();
        }
    }

    public function transcript(): string
    {
        return $this->log;
    }

    private function connect(): void
    {
        $prefix = $this->secure === 'ssl' ? 'ssl://' : '';
        $errno  = 0;
        $error  = '';

        $socket = @stream_socket_client(
            $prefix . $this->host . ':' . $this->port,
            $errno,
            $error,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            throw new RuntimeException(sprintf('Tidak bisa terhubung ke %s:%d (%s)', $this->host, $this->port, $error !== '' ? $error : 'timeout'));
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    private function authenticate(): void
    {
        // AUTH PLAIN lebih ringkas dan didukung luas; AUTH LOGIN sebagai cadangan.
        $plain = base64_encode("\0" . $this->username . "\0" . $this->password);

        $response = $this->send_('AUTH PLAIN ' . $plain);
        if ($this->code($response) === 235) {
            return;
        }

        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    private function buildMessage(array $from, string $toEmail, string $toName, string $subject, string $html, bool $isHtml = true, ?string $replyTo = null): string
    {
        $fromName = (string) ($from['name'] ?? '');
        $boundary = 'cenim-' . random_token(8);
        $text     = $isHtml ? self::htmlToText($html) : $html;

        // Domain Message-ID mengikuti domain pengirim (bukan "localhost" dari CLI)
        // — Message-ID yang tidak cocok dengan domain From menaikkan skor spam.
        $domain = substr((string) strrchr($from['email'], '@'), 1) ?: 'cenim.local';

        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . self::formatAddress($from['email'], $fromName),
            'To: ' . self::formatAddress($toEmail, $toName),
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . random_token(12) . '@' . $domain . '>',
            'MIME-Version: 1.0',
        ];

        // Balasan penerima diarahkan ke alamat panitia (mis. info.cenim@its.ac.id),
        // sementara pengirim teknis tetap domain yang terverifikasi di ZeptoMail.
        if ($replyTo !== null && $replyTo !== '') {
            array_splice($headers, 3, 0, ['Reply-To: ' . self::formatAddress($replyTo)]);
        }

        if (!$isHtml) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $body = str_replace(["\r\n", "\r"], "\n", $text);
            $body = str_replace("\n", "\r\n", $body);
            $body = preg_replace('/^\./m', '..', $body) ?? $body;

            return implode("\r\n", $headers) . "\r\n\r\n" . $body;
        }

        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $text . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n\r\n"
            . "--{$boundary}--";

        // Titik di awal baris harus di-escape (dot-stuffing) agar tidak mengakhiri DATA.
        $body = preg_replace('/^\./m', '..', $body) ?? $body;

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    public static function formatAddress(string $email, string $name = ''): string
    {
        return $name === '' ? '<' . $email . '>' : self::encodeHeader($name) . ' <' . $email . '>';
    }

    public static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) === 1
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    public static function htmlToText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</(p|div|tr|h1|h2|h3|li)>#i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** @param int|array<int,int> $expected */
    private function command(string $command, int|array $expected): string
    {
        $response = $this->send_($command);
        $this->expect($response, $expected);
        return $response;
    }

    private function send_(string $command): string
    {
        $this->write($command . "\r\n");
        return $this->read();
    }

    private function write(string $data): void
    {
        if (@fwrite($this->socket, $data) === false) {
            throw new RuntimeException('Koneksi SMTP terputus saat menulis.');
        }
        // Jangan pernah menuliskan kredensial ke transcript.
        $this->log .= '> ' . (str_starts_with($data, 'AUTH') ? "AUTH …\r\n" : $data);
    }

    private function read(): string
    {
        $response = '';

        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            // Baris terakhir memakai spasi setelah kode, baris lanjutan memakai '-'.
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        $meta = stream_get_meta_data($this->socket);
        if (($meta['timed_out'] ?? false) === true) {
            throw new RuntimeException('Server SMTP tidak merespons (timeout).');
        }

        $this->log .= '< ' . $response;

        return $response;
    }

    private function code(string $response): int
    {
        return (int) substr(trim($response), 0, 3);
    }

    /** @param int|array<int,int> $expected */
    private function expect(string $response, int|array $expected): void
    {
        $code     = $this->code($response);
        $expected = is_array($expected) ? $expected : [$expected];

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException(sprintf(
                'Balasan SMTP tidak sesuai: %s (diharapkan %s)',
                trim($response) !== '' ? trim($response) : '(kosong)',
                implode('/', $expected)
            ));
        }
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }
}
