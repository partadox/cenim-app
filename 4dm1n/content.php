<?php
/**
 * Editor konten landing page.
 *
 * Setiap kolom diisi dengan nilai yang SEDANG tampil di halaman publik — baik
 * itu hasil edit admin maupun teks bawaan (src/Domain/ContentDefaults.php).
 * Jadi admin selalu melihat isi yang aktif dan bisa langsung mengubahnya.
 *
 * Yang tersimpan di database hanya blok yang benar-benar berbeda dari bawaan:
 *   - menyimpan nilai yang sama dengan bawaan → baris dihapus (tetap "bawaan"),
 *   - mengosongkan kolom → kembali ke bawaan,
 *   - tombol "Reset" → sama, sekaligus menampilkan kembali teks bawaan di kolom.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\ContentRepo;
use Cenim\Domain\ContentSchema;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);
$page      = (string) input('page', 'home');

if (!ContentSchema::isValidPage($page)) {
    Response::abort(404, 'Unknown page.');
}

if (is_post()) {
    Csrf::verify();

    // ── Reset satu blok ke teks bawaan template ─────────────────────────────
    if (input('action') === 'reset') {
        $key = (string) input('key', '');

        if (array_key_exists($key, ContentSchema::blocks($page))) {
            ContentRepo::forget($editionId, $key, $page);
            Audit::log('content.reset', ['target_type' => 'content_block', 'meta' => ['page' => $page, 'key' => $key]]);
            Session::flash('success', 'Block "' . $key . '" reset to the template default.');
        }

        Response::redirect(admin_url('content.php?page=' . $page));
    }

    // ── Simpan semua blok pada satu halaman ─────────────────────────────────
    $values   = is_array($_POST['block'] ?? null) ? $_POST['block'] : [];
    $saved    = 0;
    $reverted = 0;
    $errors   = [];

    foreach (ContentSchema::blocks($page) as $key => $definition) {
        if (!array_key_exists($key, $values)) {
            continue;
        }

        $raw = (string) $values[$key];

        // Kosongkan = kembali ke bawaan (lebih aman daripada menyimpan string kosong).
        if (trim($raw) === '') {
            ContentRepo::forget($editionId, $key, $page);
            continue;
        }

        if ($definition['kind'] === 'list') {
            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                $errors[$key] = 'Not valid JSON: ' . json_last_error_msg();
                continue;
            }
        }

        // Sama dengan bawaan → tidak perlu disimpan, biar blok ini tetap ikut
        // kalau teks bawaannya diperbarui di rilis berikutnya.
        if (ContentRepo::matchesDefault($key, (string) $definition['kind'], $raw, $page)) {
            if (ContentRepo::isCustomised($editionId, $key, $page)) {
                ContentRepo::forget($editionId, $key, $page);
                $reverted++;
            }
            continue;
        }

        if ($definition['kind'] === 'list') {
            ContentRepo::set($editionId, $key, ['items' => array_values(json_decode($raw, true))], $page);
            $saved++;
            continue;
        }

        ContentRepo::setText($editionId, $key, $raw, $page);
        $saved++;
    }

    if ($errors !== []) {
        foreach ($errors as $key => $message) {
            Session::flash('error', $key . ': ' . $message);
        }
    }

    if ($saved > 0) {
        Audit::log('content.updated', ['target_type' => 'content_block', 'meta' => ['page' => $page, 'blocks' => $saved]]);
        Session::flash(
            'success',
            $saved . ' block(s) saved'
                . ($reverted > 0 ? ', ' . $reverted . ' back to default' : '')
                . '. Open the preview to check the result.'
        );
    } elseif ($reverted > 0) {
        Session::flash('success', $reverted . ' block(s) back to the default text.');
    }

    Response::redirect(admin_url('content.php?page=' . $page));
}

// ── Nilai untuk form: SELALU nilai yang sedang berlaku ──────────────────────
// (hasil edit admin, atau teks bawaan bila belum pernah diedit)
$stored = [];
foreach (ContentSchema::blocks($page) as $key => $definition) {
    $custom = ContentRepo::isCustomised($editionId, $key, $page);

    $stored[$key] = [
        'value'      => ContentRepo::editorValue($editionId, $key, (string) $definition['kind'], $page),
        'custom'     => $custom,
        'updated_at' => $custom ? ContentRepo::updatedAt($editionId, $key, $page) : null,
    ];
}

View::render('admin/content', [
    'title'    => 'Landing content',
    'subtitle' => ContentSchema::pages()[$page]['label'] . ' — ' . ($edition['name'] ?? ''),
    'active'   => 'content',
    'edition'  => $edition,
    'page'     => $page,
    'pages'    => ContentSchema::pages(),
    'groups'   => ContentSchema::pages()[$page]['groups'],
    'stored'   => $stored,
], 'admin');
