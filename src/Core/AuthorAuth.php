<?php

declare(strict_types=1);

namespace Cenim\Core;

use Cenim\Domain\CommitteeRepo;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\PaperAuthorRepo;

/**
 * Login author & panitia/reviewer (satu pintu di /author): email + kode OTP
 * 6 digit yang dikirim ke email itu. Tidak ada password.
 *
 * Identitas sesi adalah EMAIL, bukan paper. Dari email itu:
 *   - papers()    → semua paper tempat email tercatat sebagai author (first/co-author)
 *   - committee() → baris panitia/reviewer dengan email yang sama
 *   - current()   → paper yang sedang dipilih (baris tabel authors); modul
 *                   registrasi/invoice/pembayaran tetap bekerja per paper
 *   - me()        → baris paper_authors milik orang ini pada paper terpilih
 */
final class AuthorAuth
{
    public const KIND = 'author';
    /** Rate limit email tidak terdaftar, per IP. */
    public const LOOKUP_KIND = 'author_lookup';

    private const EMAIL    = 'author_email';
    private const EDITION  = 'author_edition_id';
    private const PAPER    = 'author_paper_id';
    private const FP       = 'author_fp';
    private const AT       = 'author_login_at';
    private const INTENDED = 'author_intended';
    private const PENDING  = 'author_pending_email';

    private static ?string $email = null;
    private static bool $loaded   = false;
    /** @var array<int,array<string,mixed>>|null */
    private static ?array $papers = null;
    /** @var array<int,array<string,mixed>>|null */
    private static ?array $committee = null;

    // ── Status sesi ─────────────────────────────────────────────────────────

    public static function isLoggedIn(): bool
    {
        return self::email() !== null;
    }

    /** Email orang yang sedang login, atau null. */
    public static function email(): ?string
    {
        if (self::$loaded) {
            return self::$email;
        }
        self::$loaded = true;

        $email = (string) Session::get(self::EMAIL, '');
        if ($email === '') {
            return self::$email = null;
        }

        if (Session::get(self::FP) !== self::fingerprint()) {
            self::clearSession();
            Audit::log('author.session_rejected', ['actor_type' => 'system', 'meta' => ['reason' => 'fingerprint mismatch']]);
            return null;
        }

        $loginAt = (string) Session::get(self::AT, '');
        $maxAge  = max(1, (int) cfg('session.author_max_hours', 12)) * 3600;
        if ($loginAt !== '' && (strtotime($loginAt . ' UTC') + $maxAge) < time()) {
            self::clearSession();
            Session::flash('warning', 'Your session has expired. Please sign in again.');
            return null;
        }

        return self::$email = $email;
    }

    /** @return array<int,array<string,mixed>> paper milik orang ini (baris authors + me_*) */
    public static function papers(): array
    {
        $email = self::email();
        if ($email === null) {
            return [];
        }

        // Semua edisi: paper edisi lama tetap terlihat (arsip, form read-only).
        return self::$papers ??= PaperAuthorRepo::papersForEmailAllEditions($email);
    }

    /** @return array<int,array<string,mixed>> baris committee dengan email orang ini */
    public static function committee(): array
    {
        $email = self::email();
        if ($email === null) {
            return [];
        }

        return self::$committee ??= CommitteeRepo::forEmailAllEditions($email);
    }

    /** Paper yang sedang dipilih (baris tabel authors), atau null bila orang ini bukan author. */
    public static function current(): ?array
    {
        $papers = self::papers();
        if ($papers === []) {
            return null;
        }

        $selected = (int) Session::get(self::PAPER, 0);
        foreach ($papers as $paper) {
            if ((int) $paper['id'] === $selected) {
                return $paper;
            }
        }

        return $papers[0];
    }

    /** Baris paper_authors milik orang yang login pada paper terpilih. */
    public static function me(): ?array
    {
        $paper = self::current();

        return $paper === null ? null : PaperAuthorRepo::find((int) $paper['me_id']);
    }

    /** id paper_authors milik orang ini di semua paper-nya. @return array<int,int> */
    public static function personIds(): array
    {
        return array_map(static fn (array $p): int => (int) $p['me_id'], self::papers());
    }

    /** @return array<int,int> */
    public static function committeeIds(): array
    {
        return array_map(static fn (array $c): int => (int) $c['id'], self::committee());
    }

    public static function id(): int
    {
        return (int) (self::current()['id'] ?? 0);
    }

    /** Pilih paper aktif; hanya paper milik orang ini yang diterima. */
    public static function selectPaper(int $authorId): bool
    {
        foreach (self::papers() as $paper) {
            if ((int) $paper['id'] === $authorId) {
                Session::put(self::PAPER, $authorId);
                return true;
            }
        }

        return false;
    }

    // ── Login dua langkah ───────────────────────────────────────────────────

    /** Email yang sedang menunggu kode (langkah 2), atau ''. */
    public static function pendingEmail(): string
    {
        return (string) Session::get(self::PENDING, '');
    }

    public static function cancelPending(): void
    {
        Session::forget(self::PENDING);
    }

    /**
     * Langkah 1: kirim kode ke email. Mengembalikan null bila langkah 2 boleh
     * ditampilkan, atau pesan error.
     *
     * Email yang tidak ada di data author/panitia langsung ditolak dengan pesan
     * jelas. Supaya tidak bisa dipakai menebak daftar peserta secara massal,
     * penolakan itu dihitung per IP (LOOKUP_KIND) dan dikunci setelah 5 kali.
     */
    public static function requestCode(string $email, bool $resend = false): ?string
    {
        $email = mb_strtolower(trim($email));

        // Kirim ulang dari langkah 2 tidak menampilkan Turnstile lagi; batas
        // pengiriman di LoginCode tetap berlaku.
        if (!$resend && ($turnstileError = Turnstile::check())) {
            return $turnstileError;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Please enter a valid email address.';
        }

        $edition = EditionRepo::active();
        if ($edition === null) {
            Logger::error('AuthorAuth: tidak ada edisi aktif');
            return 'Sign in is not open yet.';
        }

        $lookupWait = RateLimit::retryAfter(self::LOOKUP_KIND, client_ip());
        if ($lookupWait > 0) {
            return 'Too many unregistered emails were tried. Please try again in ' . RateLimit::humanRetryAfter($lookupWait) . '.';
        }

        $editionId = (int) $edition['id'];
        // Author/panitia edisi mana pun boleh masuk (edisi lama: arsip dokumen & sertifikat).
        $known     = PaperAuthorRepo::papersForEmailAllEditions($email) !== []
            || CommitteeRepo::forEmailAllEditions($email) !== [];

        if (!$known) {
            RateLimit::record(self::LOOKUP_KIND, client_ip(), false);
            Audit::log('author.code_unknown_email', ['actor_type' => 'system', 'meta' => ['email' => $email]]);

            return 'The email ' . $email . ' is not registered as an author or committee member of '
                . 'any CENIM edition. Please use the email listed in your paper submission or committee data, '
                . 'or contact the secretariat.';
        }

        $wait = LoginCode::waitSeconds($email);
        if ($wait > 0) {
            // Mengirim ulang form email untuk alamat yang sama: kode sebelumnya
            // masih berlaku, jadi langsung ke langkah 2 tanpa kirim ulang.
            if (!$resend && self::pendingEmail() === $email && $wait <= LoginCode::RESEND_SECONDS) {
                return null;
            }
            return 'Please wait ' . RateLimit::humanRetryAfter($wait) . ' before requesting another code.';
        }

        $code = LoginCode::issue($email, $editionId);
        if (!self::sendCode($email, $code, (string) $edition['name'])) {
            return 'We could not send the sign-in code right now. Please try again in a few minutes or contact the secretariat.';
        }
        Audit::log('author.code_sent', ['actor_type' => 'system', 'meta' => ['email' => $email]]);

        Session::put(self::PENDING, $email);

        return null;
    }

    /** Langkah 2: cocokkan kode. null = berhasil login, atau pesan error. */
    public static function verifyCode(string $code): ?string
    {
        $email = self::pendingEmail();
        if ($email === '') {
            return 'Please enter your email first.';
        }

        $retryAfter = RateLimit::retryAfter(self::KIND, $email);
        if ($retryAfter > 0) {
            return 'Too many failed attempts. Please try again in ' . RateLimit::humanRetryAfter($retryAfter) . '.';
        }

        $result = LoginCode::verify($email, $code);

        if ($result !== 'ok') {
            RateLimit::record(self::KIND, $email, false);
            Audit::log('author.login_failed', ['actor_type' => 'system', 'meta' => ['email' => $email, 'reason' => $result]]);

            if ($result === 'expired') {
                return 'This code has expired or was used too many times. Please request a new code.';
            }

            $left = LoginCode::triesLeft($email);
            return 'The code is not correct. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.';
        }

        $editionId = EditionRepo::activeId();
        $papers    = PaperAuthorRepo::papersForEmailAllEditions($email);
        $committee = CommitteeRepo::forEmailAllEditions($email);

        if ($papers === [] && $committee === []) {
            // Kode benar tapi email tidak (lagi) terdaftar — mis. data dihapus admin.
            return 'This email is not registered as an author or committee member.';
        }

        Session::regenerate();
        Session::forget(self::PENDING);
        Session::put(self::EMAIL, $email);
        Session::put(self::EDITION, $editionId);
        Session::put(self::FP, self::fingerprint());
        Session::put(self::AT, now());
        Session::put(self::PAPER, (int) ($papers[0]['id'] ?? 0));

        self::reset();

        RateLimit::record(self::KIND, $email, true);
        RateLimit::clear(self::KIND, $email);
        Audit::log('author.login', [
            'actor_type' => 'author',
            'actor_id'   => $email,
            'meta'       => ['papers' => count($papers), 'committee' => count($committee)],
        ]);

        return null;
    }

    private static function sendCode(string $email, string $code, string $editionName): bool
    {
        $minutes = LoginCode::TTL_MINUTES;
        $lines   = [
            'Hello,',
            '',
            'Your ' . $editionName . ' sign-in code is:',
            '',
            '    ' . $code,
            '',
            'The code is valid for ' . $minutes . ' minutes and can be used once.',
            'If you did not try to sign in, you can ignore this email.',
            '',
            ...Mailer::signatureLines(),
            '',
        ];

        return Mailer::sendText($email, '', $code . ' is your ' . $editionName . ' sign-in code', implode("\n", $lines), 'login_code');
    }

    // ── Guard & logout ──────────────────────────────────────────────────────

    public static function requireLogin(): void
    {
        if (self::isLoggedIn()) {
            return;
        }

        if (is_ajax()) {
            Response::json(['ok' => false, 'error' => 'Session expired. Please sign in again.'], 401);
        }

        if (!is_post()) {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            if ($uri !== '' && str_starts_with($uri, author_url()) && !str_contains($uri, 'login.php')) {
                Session::put(self::INTENDED, $uri);
            }
        }

        Response::redirect(author_url('login.php'));
    }

    /** Untuk halaman yang butuh paper (registrasi, invoice, ...): panitia murni diarahkan ke tab Committee. */
    public static function requirePaper(): void
    {
        self::requireLogin();

        if (self::current() === null) {
            Response::redirect(author_url('index.php?tab=committee'));
        }
    }

    public static function logout(): void
    {
        $email = self::email();
        if ($email !== null) {
            Audit::log('author.logout', ['actor_type' => 'author', 'actor_id' => $email]);
        }
        self::clearSession();
        Session::destroy();
    }

    public static function takeIntended(): string
    {
        $url = (string) Session::get(self::INTENDED, '');
        Session::forget(self::INTENDED);

        $prefix = author_url();
        return ($url !== '' && str_starts_with($url, $prefix)) ? $url : author_url('index.php');
    }

    private static function clearSession(): void
    {
        foreach ([self::EMAIL, self::EDITION, self::PAPER, self::FP, self::AT] as $key) {
            Session::forget($key);
        }
        self::reset();
        self::$loaded = true;
    }

    private static function reset(): void
    {
        self::$email     = null;
        self::$loaded    = false;
        self::$papers    = null;
        self::$committee = null;
    }

    private static function fingerprint(): string
    {
        return hash('sha256', 'author|' . user_agent());
    }
}
