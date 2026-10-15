<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;
use Cenim\Storage\StorageManager;
use InvalidArgumentException;

/**
 * Identitas visual per edisi:
 *
 *   Logo edisi  — satu berkas (unggahan admin, atau hasil generator yang
 *                 dipasang). Dipakai di landing, panel, favicon, kop PDF, dan
 *                 kartu Previous Conference begitu edisinya sudah lewat.
 *   Logo kop    — daftar logo pada kop LoA/invoice/receipt (kiri/kanan,
 *                 urutan, tinggi). Bawaan: logo edisi di kiri, ITS + Computer
 *                 Engineering + BME di kanan.
 */
final class BrandingRepo
{
    /** Syarat logo edisi (ditampilkan di halaman admin). */
    public const LOGO_MIN_PX     = 512;
    public const LOGO_MAX_BYTES  = 2 * 1048576;
    public const KOP_LOGO_MIN_PX = 150;

    /** Logo kop bawaan (berkas di reference/, ikut repo). */
    private const DEFAULT_KOP = [
        ['kind' => 'edition_logo', 'ref_path' => null,                         'label' => 'Edition logo',          'side' => 'left',  'height_mm' => 22],
        ['kind' => 'ref',          'ref_path' => 'reference/kop-logo-its.png', 'label' => 'ITS',                   'side' => 'right', 'height_mm' => 12],
        ['kind' => 'ref',          'ref_path' => 'reference/kop-logo-ce.png',  'label' => 'Computer Engineering',  'side' => 'right', 'height_mm' => 12],
        ['kind' => 'ref',          'ref_path' => 'reference/kop-logo-bme.png', 'label' => 'Biomedical Engineering', 'side' => 'right', 'height_mm' => 13],
    ];

    // ── Logo edisi ──────────────────────────────────────────────────────────

    public static function logoFile(int $editionId): ?array
    {
        $fileId = (int) SettingRepo::get($editionId, 'logo_file_id', 0);
        $file   = $fileId > 0 ? FileRepo::find($fileId) : null;

        return $file !== null && ($file['deleted_at'] ?? null) === null ? $file : null;
    }

    /** URL logo edisi untuk halaman web. */
    public static function logoUrl(?array $edition = null): string
    {
        $edition ??= EditionRepo::context() ?? [];
        $file      = isset($edition['id']) ? self::logoFile((int) $edition['id']) : null;

        return $file !== null ? public_file_url($file) : asset('image/logo-cenim-2026.png');
    }

    /** Path lokal logo edisi untuk PDF (unggahan → generator → aset tema → dasar). */
    public static function logoPath(int $editionId, int $year): ?string
    {
        $file = self::logoFile($editionId);
        if ($file !== null) {
            try {
                $path = StorageManager::forFile($file)->localPath($file);
                if ($path !== null && is_file($path)) {
                    return $path;
                }
            } catch (\Throwable) {
                // object storage sedang tidak bisa dihubungi → cadangan di bawah
            }
        }

        foreach ([
            storage_path('generated/logo/' . $year . '/logo-cenim-' . $year . '-512.png'),
            storage_path('generated/logo/' . $year . '/logo-cenim-' . $year . '-1024.png'),
            storage_path('cache/s3/public/theme/image/logo-cenim-' . $year . '.png'),
            // Aset tema yang disalin ke disk (server tanpa akses object storage).
            CENIM_ROOT . '/assets/theme/image/logo-cenim-' . $year . '.png',
            storage_path('cache/s3/public/theme/image/logo-cenim-2026.png'),
            CENIM_ROOT . '/assets/theme/image/logo-cenim-2026.png',
            CENIM_ROOT . '/reference/logo-base.png',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Pasang logo edisi dari unggahan admin.
     *
     * @throws InvalidArgumentException bila berkas tidak memenuhi syarat
     */
    public static function setLogoFromUpload(int $editionId, int $year, array $upload): int
    {
        self::checkImage($upload, self::LOGO_MIN_PX, self::LOGO_MAX_BYTES);

        $fileId = FileRepo::replace($upload, 'edition_logo', 'edition', $editionId, $editionId, $year);
        SettingRepo::set($editionId, 'logo_file_id', $fileId);

        return $fileId;
    }

    /** Pasang logo hasil generator (PNG 1024) sebagai logo edisi. */
    public static function setLogoFromGenerated(int $editionId, int $year): int
    {
        $path = storage_path('generated/logo/' . $year . '/logo-cenim-' . $year . '-1024.png');
        if (!is_file($path)) {
            throw new InvalidArgumentException('Generate the logo for ' . $year . ' first.');
        }

        $fileId = FileRepo::storeLocalCopy($path, basename($path), 'edition_logo', 'edition', $editionId, $editionId, $year);

        foreach (FileRepo::forOwner('edition', $editionId, 'edition_logo') as $old) {
            if ((int) $old['id'] !== $fileId) {
                FileRepo::softDelete((int) $old['id']);
            }
        }
        SettingRepo::set($editionId, 'logo_file_id', $fileId);

        return $fileId;
    }

    /** Kembali ke logo bawaan (tidak ada logo edisi terpasang). */
    public static function clearLogo(int $editionId): void
    {
        SettingRepo::set($editionId, 'logo_file_id', 0);
    }

    // ── Logo kop PDF ────────────────────────────────────────────────────────

    /**
     * Logo kop satu edisi, urut kiri→kanan per sisi. Edisi yang belum pernah
     * diatur otomatis mendapat susunan bawaan.
     *
     * @return array<int,array<string,mixed>> tiap baris + 'path' (lokal) & 'url' (pratinjau admin)
     */
    public static function kopLogos(int $editionId): array
    {
        if ($editionId > 0 && Db::count('kop_logos', 'edition_id = ?', [$editionId]) === 0 && !SettingRepo::getBool($editionId, 'kop_logos_customized')) {
            foreach (self::DEFAULT_KOP as $i => $row) {
                Db::insert('kop_logos', $row + ['edition_id' => $editionId, 'sort' => $i + 1, 'created_at' => now()]);
            }
        }

        $edition = EditionRepo::find($editionId) ?? [];
        $rows    = Db::all(
            "SELECT * FROM kop_logos WHERE edition_id = ? ORDER BY CASE side WHEN 'left' THEN 0 ELSE 1 END, sort, id",
            [$editionId]
        );

        foreach ($rows as &$row) {
            $row['path'] = self::kopLogoPath($row, $editionId, (int) ($edition['year'] ?? date('Y')));
            $row['url']  = admin_url('kop-logo.php?id=' . (int) $row['id']);
        }

        return $rows;
    }

    public static function kopLogoPath(array $row, int $editionId, int $year): ?string
    {
        return match ((string) $row['kind']) {
            'edition_logo' => self::logoPath($editionId, $year),
            'ref'          => is_file(CENIM_ROOT . '/' . $row['ref_path']) ? CENIM_ROOT . '/' . $row['ref_path'] : null,
            default        => self::filePath((int) ($row['file_id'] ?? 0)),
        };
    }

    /** @throws InvalidArgumentException */
    public static function addKopLogo(int $editionId, int $year, array $upload, string $side, float $heightMm, string $label): int
    {
        self::checkImage($upload, self::KOP_LOGO_MIN_PX, self::LOGO_MAX_BYTES);

        $fileId = FileRepo::store($upload, 'kop_logo', 'edition', $editionId, $editionId, $year);
        $side   = $side === 'left' ? 'left' : 'right';

        SettingRepo::set($editionId, 'kop_logos_customized', true);

        return Db::insert('kop_logos', [
            'edition_id' => $editionId,
            'kind'       => 'file',
            'file_id'    => $fileId,
            'label'      => mb_substr(trim($label), 0, 120) ?: pathinfo((string) ($upload['name'] ?? 'Logo'), PATHINFO_FILENAME),
            'side'       => $side,
            'sort'       => (int) Db::value('SELECT COALESCE(MAX(sort), 0) + 1 FROM kop_logos WHERE edition_id = ? AND side = ?', [$editionId, $side]),
            'height_mm'  => self::clampHeight($heightMm),
            'created_at' => now(),
        ]);
    }

    public static function updateKopLogo(int $id, string $side, float $heightMm, string $label): void
    {
        $row = Db::one('SELECT * FROM kop_logos WHERE id = ?', [$id]);
        if ($row === null) {
            return;
        }

        $side = $side === 'left' ? 'left' : 'right';
        $data = [
            'side'       => $side,
            'height_mm'  => self::clampHeight($heightMm),
            'label'      => mb_substr(trim($label), 0, 120) ?: $row['label'],
            'updated_at' => now(),
        ];
        if ($side !== $row['side']) {
            $data['sort'] = (int) Db::value('SELECT COALESCE(MAX(sort), 0) + 1 FROM kop_logos WHERE edition_id = ? AND side = ?', [(int) $row['edition_id'], $side]);
        }

        Db::update('kop_logos', $data, 'id = :target_id', ['target_id' => $id]);
        SettingRepo::set((int) $row['edition_id'], 'kop_logos_customized', true);
    }

    /** Geser satu posisi ke kiri (-1) atau kanan (+1) dalam sisinya. */
    public static function moveKopLogo(int $id, int $direction): void
    {
        $row = Db::one('SELECT * FROM kop_logos WHERE id = ?', [$id]);
        if ($row === null) {
            return;
        }

        $siblings = Db::all('SELECT id FROM kop_logos WHERE edition_id = ? AND side = ? ORDER BY sort, id', [(int) $row['edition_id'], $row['side']]);
        $ids      = array_map('intval', array_column($siblings, 'id'));
        $index    = array_search($id, $ids, true);
        $target   = $index === false ? false : $index + ($direction < 0 ? -1 : 1);

        if ($target === false || $target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        foreach ($ids as $i => $siblingId) {
            Db::update('kop_logos', ['sort' => $i + 1], 'id = :target_id', ['target_id' => $siblingId]);
        }
        SettingRepo::set((int) $row['edition_id'], 'kop_logos_customized', true);
    }

    public static function deleteKopLogo(int $id): void
    {
        $row = Db::one('SELECT * FROM kop_logos WHERE id = ?', [$id]);
        if ($row === null) {
            return;
        }

        Db::delete('kop_logos', 'id = ?', [$id]);
        self::releaseFile((int) ($row['file_id'] ?? 0));
        SettingRepo::set((int) $row['edition_id'], 'kop_logos_customized', true);
    }

    /** Kembalikan susunan logo kop bawaan. */
    public static function resetKopLogos(int $editionId): void
    {
        $files = array_column(Db::all('SELECT file_id FROM kop_logos WHERE edition_id = ? AND file_id IS NOT NULL', [$editionId]), 'file_id');
        Db::delete('kop_logos', 'edition_id = ?', [$editionId]);
        foreach ($files as $fileId) {
            self::releaseFile((int) $fileId);
        }
        SettingRepo::set($editionId, 'kop_logos_customized', false);
    }

    // ── Bantuan ─────────────────────────────────────────────────────────────

    /** Hapus berkas logo kop hanya bila tidak dipakai edisi lain (hasil salin edisi). */
    private static function releaseFile(int $fileId): void
    {
        if ($fileId > 0 && Db::count('kop_logos', 'file_id = ?', [$fileId]) === 0) {
            FileRepo::softDelete($fileId);
        }
    }

    private static function filePath(int $fileId): ?string
    {
        $file = $fileId > 0 ? FileRepo::find($fileId) : null;
        if ($file === null) {
            return null;
        }

        try {
            $path = StorageManager::forFile($file)->localPath($file);
        } catch (\Throwable) {
            return null;
        }

        return $path !== null && is_file($path) ? $path : null;
    }

    private static function clampHeight(float $mm): float
    {
        return round(max(6.0, min(30.0, $mm > 0 ? $mm : 13.0)), 1);
    }

    /**
     * Logo harus PNG/JPG (format yang bisa dipakai di PDF), sisi terpendek
     * minimal $minPx, dan ukuran berkas maksimal $maxBytes.
     *
     * @throws InvalidArgumentException
     */
    /**
     * Gambar pengesahan dokumen: tanda tangan chair (LoA), tanda tangan
     * treasurer (invoice & receipt), dan stempel konferensi yang ditumpuk di
     * atas tanda tangan mana pun (posisi & ukuran: SettingRepo::stamp()).
     *
     * @var array<string,array{kind:string,purpose:string,label:string}>
     */
    public const SIGNERS = [
        'chair'     => ['kind' => 'signature',           'purpose' => 'chair_signature',     'label' => 'Chair signature'],
        'treasurer' => ['kind' => 'signature_treasurer', 'purpose' => 'treasurer_signature', 'label' => 'Treasurer signature'],
        'stamp'     => ['kind' => 'stamp',               'purpose' => 'conference_stamp',    'label' => 'Conference stamp'],
    ];

    /**
     * Pasang gambar tanda tangan atau stempel (PNG transparan dianjurkan).
     *
     * @throws InvalidArgumentException
     */
    public static function setSignature(int $editionId, int $year, string $role, array $upload): int
    {
        $signer = self::SIGNERS[$role] ?? throw new InvalidArgumentException('Unknown signer.');

        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Please choose an image file.');
        }

        $info = @getimagesize((string) $upload['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw new InvalidArgumentException('The image must be a PNG or JPG file.');
        }
        if ((int) $upload['size'] > upload_max_bytes(self::LOGO_MAX_BYTES)) {
            throw new InvalidArgumentException('The image must not exceed ' . upload_max_label(self::LOGO_MAX_BYTES) . '.');
        }
        if (min($info[0], $info[1]) < 150) {
            throw new InvalidArgumentException(sprintf('The image is %d×%d px; the shortest side must be at least 150 px.', $info[0], $info[1]));
        }

        $fileId = FileRepo::replace($upload, $signer['purpose'], 'edition', $editionId, $editionId, $year);

        Db::delete('media', 'edition_id = ? AND kind = ?', [$editionId, $signer['kind']]);
        Db::insert('media', [
            'edition_id' => $editionId,
            'kind'       => $signer['kind'],
            'file_id'    => $fileId,
            'label'      => $signer['label'],
            'created_at' => now(),
        ]);

        return $fileId;
    }

    public static function clearSignature(int $editionId, string $role): void
    {
        $kind = self::SIGNERS[$role]['kind'] ?? null;
        if ($kind === null) {
            return;
        }

        foreach (Db::all('SELECT file_id FROM media WHERE edition_id = ? AND kind = ?', [$editionId, $kind]) as $row) {
            if ($row['file_id'] !== null) {
                FileRepo::softDelete((int) $row['file_id']);
            }
        }
        Db::delete('media', 'edition_id = ? AND kind = ?', [$editionId, $kind]);
    }

    /** Baris files gambar yang terpasang untuk peran ini (chair/treasurer/stamp), atau null. */
    public static function signatureFile(int $editionId, string $role): ?array
    {
        $kind = self::SIGNERS[$role]['kind'] ?? null;
        if ($kind === null || !Db::tableExists('media')) {
            return null;
        }

        return Db::one(
            'SELECT f.* FROM media m JOIN files f ON f.id = m.file_id
             WHERE m.edition_id = ? AND m.kind = ? AND f.deleted_at IS NULL
             ORDER BY m.id DESC LIMIT 1',
            [$editionId, $kind]
        );
    }

    /** Path lokal gambar tanda tangan / stempel untuk PDF, atau null. */
    public static function signaturePath(int $editionId, string $role): ?string
    {
        $row = self::signatureFile($editionId, $role);
        if ($row === null) {
            return null;
        }

        try {
            $path = StorageManager::forFile($row)->localPath($row);
        } catch (\Throwable) {
            return null; // object storage tidak bisa dihubungi → PDF tanpa gambar
        }

        return $path !== null && is_file($path) ? $path : null;
    }

    private static function checkImage(array $upload, int $minPx, int $maxBytes): void
    {
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Please choose an image file.');
        }

        $info = @getimagesize((string) $upload['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw new InvalidArgumentException('The logo must be a PNG or JPG image.');
        }

        if ((int) $upload['size'] > upload_max_bytes($maxBytes)) {
            throw new InvalidArgumentException('The logo must not exceed ' . upload_max_label($maxBytes) . '.');
        }

        if (min($info[0], $info[1]) < $minPx) {
            throw new InvalidArgumentException(sprintf(
                'The image is %d×%d px; the shortest side must be at least %d px.',
                $info[0],
                $info[1],
                $minPx
            ));
        }
    }
}
