<?php
/**
 * Seed konten landing dari website CENIM yang berjalan sekarang.
 *
 * Membaca index.php lama (yang masih statis) lalu memindahkan bagian yang
 * berupa DATA ke database: keynote speaker dan susunan panitia. Bagian yang
 * berupa teks tetap punya nilai bawaan di dalam site/pages/*.php, jadi tidak
 * perlu di-seed.
 *
 *   php cenim-app/db/seed_landing.php                 # dari ../index.php
 *   php cenim-app/db/seed_landing.php /path/index.php
 *
 * Idempoten: baris yang namanya sudah ada dilewati.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya bisa dijalankan dari CLI.\n");
}

require __DIR__ . '/../bootstrap.php';

use Cenim\Core\Db;
use Cenim\Domain\CommitteeRepo;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\SpeakerRepo;

/**
 * Sumber HTML landing statis, urut prioritas:
 *   1. argumen CLI (kalau diberikan),
 *   2. salinan di dalam repo — dipakai secara default supaya seeding tidak
 *      bergantung pada isi web root, yang setelah go-live sudah berisi stub CMS
 *      (bukan lagi halaman statis, jadi tidak ada lagi yang bisa dibaca),
 *   3. backup di sebelah web root,
 *   4. index.php web root (kondisi sebelum go-live).
 */
$candidates = array_filter([
    $argv[1] ?? null,
    CENIM_ROOT . '/reference/landing-2026-static.php',
    dirname(CENIM_ROOT) . '/index-landing-backup.php',
    dirname(CENIM_ROOT) . '/index.php',
]);

$source = null;

foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $source = $candidate;
        break;
    }
}

if ($source === null) {
    exit("File sumber tidak ditemukan. Coba: php cenim-app/db/seed_landing.php /path/index.php\n");
}

$edition = EditionRepo::active();

if ($edition === null) {
    exit("Belum ada edisi aktif. Jalankan dulu: php cenim-app/db/seed.php cenim2026\n");
}

$editionId = (int) $edition['id'];
$html      = (string) file_get_contents($source);

echo "Sumber : {$source}\n";
echo "Edisi  : {$edition['name']}\n";
echo str_repeat('-', 60) . PHP_EOL;

// ── Keynote speaker ─────────────────────────────────────────────────────────
// Struktur di tema: .swiper-slide > .th-team > img + h3 a + span.team-desig + .th-social a*
$speakerBlocks = [];
if (preg_match('#id="teamSlider3".*?<div class="swiper-wrapper">(.*?)\n\s*</div>\s*</div>\s*</div>\s*</div>\s*</div>#s', $html, $m) === 1) {
    $speakerBlocks = preg_split('#<!-- Item#', $m[1]) ?: [];
}

$newSpeakers = 0;
$sort        = 0;

foreach ($speakerBlocks as $block) {
    if (preg_match('#<h3 class="box-title"><a href="([^"]*)"[^>]*>(.*?)</a></h3>#s', $block, $name) !== 1) {
        continue;
    }

    $sort += 10;

    $speakerName = trim(strip_tags($name[2]));
    $profileUrl  = trim($name[1]);

    if ($speakerName === '' || SpeakerRepo::findByName($editionId, $speakerName) !== null) {
        continue;
    }

    preg_match('#<span class="team-desig">(.*?)</span>#s', $block, $desig);
    preg_match('#<div class="team-img">\s*<img src="([^"]+)"#s', $block, $photo);

    // Tautan sosial dikenali dari domainnya.
    $links = [];
    if (preg_match_all('#<a[^>]*href="([^"]+)"#s', $block, $anchors) > 0) {
        foreach ($anchors[1] as $url) {
            if (str_contains($url, 'scholar.google')) {
                $links['scholar'] = $url;
            } elseif (str_contains($url, 'scopus.com')) {
                $links['scopus'] = $url;
            } elseif ($url !== $profileUrl && str_starts_with($url, 'http')) {
                $links['website'] = $url;
            }
        }
    }

    SpeakerRepo::create($editionId, [
        'name'        => $speakerName,
        'affiliation' => trim(strip_tags($desig[1] ?? '')),
        'photo_url'   => isset($photo[1]) ? preg_replace('#^assets/#', '', $photo[1]) : null,
        'profile_url' => $profileUrl !== '' ? $profileUrl : null,
        'sort'        => $sort,
        'is_visible'  => true,
    ] + $links);

    $newSpeakers++;
}

echo "  + {$newSpeakers} keynote speaker\n";

// ── Susunan panitia ─────────────────────────────────────────────────────────
// Struktur di tema: .price-card2 dengan judul kartu = divisi, tiap <li> satu orang.
$newMembers = 0;

if (preg_match('#id="comittee-sec"(.*?)<!--==============================#s', $html, $section) === 1) {
    preg_match_all(
        '#<h3 class="price-card_title">\s*(.*?)</h3>.*?<ul>(.*?)</ul>#s',
        $section[1],
        $cards,
        PREG_SET_ORDER
    );

    $groupSort = 0;

    foreach ($cards as $card) {
        $division = trim(preg_replace('#\s+#', ' ', strip_tags(str_replace('<br>', ' ', $card[1]))) ?? '');
        $groupSort += 100;
        $memberSort = $groupSort;

        preg_match_all('#<li>(.*?)</li>#s', $card[2], $items, PREG_SET_ORDER);

        foreach ($items as $item) {
            $text = trim(preg_replace('#\s+#', ' ', strip_tags(str_replace('</b>', '</b>|', $item[1]))) ?? '');

            if ($text === '') {
                continue;
            }

            // Format yang mungkin: "<b>Role</b> : Nama (Afiliasi)" atau "Nama (Afiliasi)"
            $role = null;
            if (str_contains($text, '|')) {
                [$rolePart, $text] = array_map('trim', explode('|', $text, 2));
                $role = trim($rolePart);
                $text = ltrim($text, ': ');
            }

            $affiliation = null;
            if (preg_match('#^(.*?)\s*\((.*)\)\s*$#', $text, $parts) === 1) {
                $text        = trim($parts[1]);
                $affiliation = trim($parts[2]);
            }

            if ($text === '') {
                continue;
            }

            $memberSort += 1;

            $exists = Db::value(
                'SELECT 1 FROM committee WHERE edition_id = ? AND name = ? AND COALESCE(division, \'\') = ?',
                [$editionId, $text, $division]
            );

            if ($exists !== null) {
                continue;
            }

            CommitteeRepo::create($editionId, [
                'name'            => $text,
                'role'            => $role,
                'division'        => $division,
                'affiliation'     => $affiliation,
                'sort'            => $memberSort,
                'show_on_landing' => true,
            ]);

            $newMembers++;
        }
    }
}

echo "  + {$newMembers} anggota panitia\n";
echo "Seed landing selesai.\n";
