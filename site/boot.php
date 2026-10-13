<?php
/**
 * Bootstrap halaman publik (landing, registration, submission, workshop, verify).
 *
 * Dipanggil oleh stub di web root. Menyediakan helper CMS supaya markup tema
 * tetap seperti aslinya, hanya isinya yang bisa diedit admin — dan bila blok
 * belum pernah diedit, teks bawaan di dalam template yang dipakai.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Cenim\Domain\CommitteeRepo;
use Cenim\Domain\ContentRepo;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\SettingRepo;
use Cenim\Domain\SpeakerRepo;
use Cenim\Domain\TimelineRepo;

/**
 * Sertakan partial tema (site/partials/header.php, footer.php).
 * Path aset relatifnya dibereskan oleh site_page() saat seluruh halaman selesai.
 */
function site_partial(string $name): void
{
    $file = CENIM_ROOT . '/site/partials/' . basename($name) . '.php';

    if (is_file($file)) {
        /** @psalm-suppress UnresolvableInclude */
        include $file;
    }
}

/**
 * Markup tema & konten CMS memakai path aset RELATIF ("assets/css/style.css"),
 * warisan landing statis yang dulu berdiri di web root. Aset tema kini ada di
 * cenim-app/assets/theme, jadi semua path itu diarahkan ke asset() — berlaku
 * sama untuk web root, subpath produksi (/conf/cenim), maupun pratinjau admin.
 */
function site_fix_asset_paths(string $html): string
{
    $prefix = rtrim(asset(''), '/') . '/';

    return str_replace(
        ['"assets/', "'assets/", 'url(assets/'],
        ['"' . $prefix, "'" . $prefix, 'url(' . $prefix],
        $html
    );
}

/** Render satu halaman publik: home | registration | submission | workshop. */
function site_page(string $page): void
{
    $file = CENIM_ROOT . '/site/pages/' . basename($page) . '.php';

    if (!is_file($file)) {
        Cenim\Core\Response::abort(404, 'Page not found.');
    }

    ob_start();
    include $file;
    echo site_fix_asset_paths((string) ob_get_clean());
}

/** Edisi aktif (array kosong bila database belum siap). */
function site_edition(): array
{
    static $edition = null;

    if ($edition === null) {
        $edition = EditionRepo::active() ?? [];
    }

    return $edition;
}

function site_edition_id(): int
{
    return (int) (site_edition()['id'] ?? 0);
}

/** Nama edisi untuk sub-title tema, mis. "CENIM 2026". */
function site_name(): string
{
    return (string) (site_edition()['name'] ?? cfg('app_name'));
}

// ── Blok konten ─────────────────────────────────────────────────────────────

/**
 * Teks polos (di-escape).
 *
 * Nilai bawaannya TIDAK lagi ditulis di sini melainkan di
 * src/Domain/ContentDefaults.php — supaya editor di panel bisa menampilkan teks
 * yang sedang aktif, bukan kolom kosong. Menambah blok baru: daftarkan key-nya
 * di ContentSchema + ContentDefaults, lalu panggil di template.
 */
function cms_text(string $key, string $page = 'home'): string
{
    return e(ContentRepo::text(site_edition_id(), $key, $page));
}

/** Teks yang boleh memuat HTML sederhana (ditulis admin, jadi dipercaya). */
function cms_html(string $key, string $page = 'home'): string
{
    return ContentRepo::text(site_edition_id(), $key, $page);
}

/** @return array<int,array<string,mixed>> daftar (hero slide, topik, dll) */
function cms_list(string $key, string $page = 'home'): array
{
    return ContentRepo::data(site_edition_id(), $key, $page);
}

// ── Data dari tabel ─────────────────────────────────────────────────────────

/** @return array<int,array<string,mixed>> */
function site_timeline(): array
{
    return TimelineRepo::all(site_edition_id(), true);
}

/**
 * Tanggal timeline: yang sudah lewat dicoret, yang berjalan ditampilkan normal.
 * Inilah pola "extend" CENIM — tanggal lama tidak pernah dihapus.
 */
function site_timeline_dates(array $item): string
{
    // Sama dengan situs lama: tiap tanggal di barisnya sendiri, yang lama dicoret
    // (mis. "12 August 2026" / "(1st Extend) 31 August 2026" / "(Final Extend) 14 Sept 2026").
    $lines = [];
    foreach (TimelineRepo::history($item) as $old) {
        $lines[] = '<s>' . e($old) . '</s>';
    }
    $lines[] = e((string) $item['current_date_text']);

    return implode(' <br> ', $lines);
}

/** @return array<int,array<string,mixed>> */
function site_speakers(): array
{
    return SpeakerRepo::all(site_edition_id(), true);
}

function site_speaker_photo(array $speaker): string
{
    return SpeakerRepo::photoUrl($speaker) ?? asset('img/normal/team1-1.jpg');
}

/** @return array<string,array<int,array<string,mixed>>> divisi => anggota */
function site_committee_groups(): array
{
    $groups = [];

    foreach (CommitteeRepo::all(site_edition_id(), true) as $member) {
        $division = trim((string) ($member['division'] ?? '')) !== ''
            ? (string) $member['division']
            : 'Committee';

        $groups[$division][] = $member;
    }

    return $groups;
}

/** Satu baris anggota panitia: "Role : Nama (Afiliasi)". */
function site_committee_line(array $member): string
{
    $line = '';

    if (trim((string) ($member['role'] ?? '')) !== '') {
        $line .= '<b>' . e((string) $member['role']) . '</b> : ';
    }

    $line .= e((string) $member['name']);

    if (trim((string) ($member['affiliation'] ?? '')) !== '') {
        $line .= ' (' . e((string) $member['affiliation']) . ')';
    }

    return $line;
}

/**
 * Tabel biaya untuk halaman registration — sumbernya sama dengan yang dipakai
 * FeeEngine saat menghitung tagihan author, jadi tidak mungkin beda.
 *
 * @return array<string,array{rows:array<int,array{label:string,amount:float}>,extra_page:float}>
 */
function site_fee_table(): array
{
    $editionId = site_edition_id();
    $table     = [];

    foreach (FeeEngine::categories($editionId) as $category) {
        $currency = (string) $category['currency'];

        $table[$currency]['rows'][] = [
            'label'  => (string) $category['label'],
            'amount' => (float) $category['amount'],
        ];
    }

    foreach (array_keys($table) as $currency) {
        $table[$currency]['extra_page'] = FeeEngine::extraPageFee($editionId, (string) $currency);
    }

    return $table;
}

function site_included_pages(): int
{
    return FeeEngine::includedPages(site_edition_id());
}

function site_extra_paper_percent(): int
{
    return (int) FeeEngine::extraPaperPercent(site_edition_id());
}

/** @return array<int,array<string,mixed>> rekening untuk halaman registrasi publik */
function site_bank_accounts(): array
{
    // Rekening tampil di halaman publik hanya bila admin mengaktifkannya
    // (Settings → Bank accounts), supaya rekening contoh tidak pernah terpublikasi.
    if (!SettingRepo::getBool(site_edition_id(), 'bank_public', false)) {
        return [];
    }

    return array_values(array_filter(
        SettingRepo::getArray(site_edition_id(), 'bank_accounts', []),
        static fn ($bank): bool => is_array($bank) && trim((string) ($bank['account_no'] ?? '')) !== ''
    ));
}

/** Format harga di tabel biaya publik, sama dengan situs lama: "$ 350", "Rp. 3.500.000". */
function site_money(float $amount, string $currency): string
{
    if ($currency === 'IDR') {
        return 'Rp. ' . number_format($amount, 0, ',', '.');
    }

    return '$ ' . number_format($amount, floor($amount) == $amount ? 0 : 2, '.', ',');
}

function site_setting(string $key, string $default = ''): string
{
    return SettingRepo::getString(site_edition_id(), $key, $default);
}

function site_registration_open(): bool
{
    return SettingRepo::getBool(site_edition_id(), 'registration_open', true);
}
