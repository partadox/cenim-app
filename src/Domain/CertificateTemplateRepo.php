<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;
use Cenim\Storage\StorageManager;

/**
 * Template sertifikat per jenis: ukuran halaman, gambar latar (opsional),
 * dan posisi setiap elemen.
 *
 * Koordinat disimpan dalam PERSEN lebar/tinggi halaman (0–100), bukan mm,
 * supaya tata letak tetap benar walau ukuran halaman diganti dan supaya
 * editor drag-and-drop di browser bisa langsung memakai angka yang sama.
 *
 * Jenis field (kind):
 *   text    → tulisan tetap (isi di field.value)
 *   name    → nama penerima
 *   affiliation → instansi penerima
 *   number  → nomor sertifikat (nomor induk template + "-001")
 *   subject → judul paper / nama penghargaan
 *   date    → tanggal terbit
 *   code    → kode verifikasi
 *   qr        → QR menuju halaman verifikasi (size = lebar dalam mm)
 *   signature → gambar tanda tangan template (size = lebar dalam mm)
 */
final class CertificateTemplateRepo
{
    public const KINDS = ['text', 'name', 'affiliation', 'number', 'subject', 'date', 'code', 'qr', 'signature'];

    public const PAGE_SIZES   = ['A4', 'LETTER'];
    public const ORIENTATIONS = ['L', 'P'];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM certificate_templates WHERE id = ?', [$id]);
    }

    public static function forType(int $editionId, string $type): ?array
    {
        return Db::one(
            'SELECT * FROM certificate_templates WHERE edition_id = ? AND type = ? ORDER BY id DESC LIMIT 1',
            [$editionId, $type]
        );
    }

    /** @return array<int,array<string,mixed>> semua jenis, dibuat bila belum ada */
    public static function allEnsured(int $editionId): array
    {
        $templates = [];

        foreach (array_keys((array) cfg('certificate_types', [])) as $type) {
            $templates[$type] = self::ensure($editionId, (string) $type);
        }

        return $templates;
    }

    /** Ambil template; buat dengan tata letak bawaan bila belum ada. */
    public static function ensure(int $editionId, string $type): array
    {
        $existing = self::forType($editionId, $type);
        if ($existing !== null) {
            return $existing;
        }

        $id = Db::insert('certificate_templates', [
            'edition_id'  => $editionId,
            'type'        => $type,
            'name'        => (string) (cfg('certificate_types.' . $type . '.label') ?? ucfirst($type)) . ' certificate',
            'page_size'   => 'A4',
            'orientation' => 'L',
            'fields_json' => json_encode(self::defaultFields($type), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'is_active'   => 1,
            'created_at'  => now(),
        ]);

        return self::find($id) ?? [];
    }

    public static function save(int $id, array $data): void
    {
        Db::update('certificate_templates', [
            'name'        => mb_substr(trim((string) ($data['name'] ?? '')), 0, 190),
            'page_size'   => in_array($data['page_size'] ?? '', self::PAGE_SIZES, true) ? $data['page_size'] : 'A4',
            'orientation' => in_array($data['orientation'] ?? '', self::ORIENTATIONS, true) ? $data['orientation'] : 'L',
            'is_active'   => !empty($data['is_active']) ? 1 : 0,
            'updated_at'  => now(),
        ], 'id = :target_id', ['target_id' => $id]);
    }

    /** Simpan tata letak hasil editor. */
    public static function saveFields(int $id, array $fields): void
    {
        Db::update('certificate_templates', [
            'fields_json' => json_encode(self::sanitizeFields($fields), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at'  => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        // Tata letak berubah → PDF yang sudah tersimpan tidak valid lagi.
        self::invalidateCertificates($id);
    }

    public static function setBackground(int $id, ?int $fileId): void
    {
        Db::update('certificate_templates', [
            'background_file_id' => $fileId,
            'updated_at'         => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        self::invalidateCertificates($id);
    }

    public static function setSignature(int $id, ?int $fileId): void
    {
        Db::update('certificate_templates', [
            'signature_file_id' => $fileId,
            'updated_at'        => now(),
        ], 'id = :target_id', ['target_id' => $id]);

        self::invalidateCertificates($id);
    }

    /** Buang cache PDF semua sertifikat yang memakai template ini. */
    public static function invalidateCertificates(int $templateId): int
    {
        return Db::update(
            'certificates',
            ['pdf_cache' => null, 'updated_at' => now()],
            'template_id = :template_id',
            ['template_id' => $templateId]
        );
    }

    /**
     * Field template tersimpan, dilengkapi field standar yang belum ada (mis.
     * template lama sebelum ada field instansi & nomor) dengan posisi bawaan —
     * jadi langsung muncul di editor tanpa perlu reset tata letak.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function fields(?array $template): array
    {
        $decoded = json_decode((string) ($template['fields_json'] ?? '[]'), true);
        $fields  = is_array($decoded) ? self::sanitizeFields($decoded) : [];

        if ($fields === []) {
            return $fields;
        }

        $keys = array_column($fields, 'key');
        foreach (self::defaultFields((string) ($template['type'] ?? 'author')) as $default) {
            if (in_array($default['kind'], ['affiliation', 'number'], true) && !in_array($default['key'], $keys, true)) {
                $fields[] = self::sanitizeFields([$default])[0];
            }
        }

        return $fields;
    }

    /** Simpan nomor induk sertifikat untuk template ini. */
    public static function setNumberBase(int $id, string $base): void
    {
        Db::update('certificate_templates', [
            'number_base' => mb_substr(trim($base), 0, 120) ?: null,
            'updated_at'  => now(),
        ], 'id = :target_id', ['target_id' => $id]);
    }

    public static function backgroundPath(?array $template): ?string
    {
        $fileId = (int) ($template['background_file_id'] ?? 0);
        if ($fileId <= 0) {
            return null;
        }

        $file = FileRepo::find($fileId);
        if ($file === null) {
            return null;
        }

        $path = StorageManager::forFile($file)->localPath($file);

        return $path !== null && is_file($path) ? $path : null;
    }

    public static function signaturePath(?array $template): ?string
    {
        $fileId = (int) ($template['signature_file_id'] ?? 0);
        if ($fileId <= 0) {
            return null;
        }

        $file = FileRepo::find($fileId);
        if ($file === null) {
            return null;
        }

        $path = StorageManager::forFile($file)->localPath($file);

        return $path !== null && is_file($path) ? $path : null;
    }

    /**
     * Tata letak bawaan (persen halaman, orientasi landscape).
     * Cukup rapi untuk langsung dipakai tanpa gambar latar.
     */
    public static function defaultFields(string $type): array
    {
        $heading = match ($type) {
            'presenter'   => 'CERTIFICATE OF PRESENTATION',
            'participant' => 'CERTIFICATE OF PARTICIPATION',
            'committee'   => 'CERTIFICATE OF APPRECIATION',
            'award'       => 'CERTIFICATE OF ACHIEVEMENT',
            default       => 'CERTIFICATE OF PUBLICATION',
        };

        $intro = match ($type) {
            'committee' => 'is awarded to',
            'award'     => 'is proudly presented to',
            default     => 'is presented to',
        };

        $forLine = match ($type) {
            'presenter'   => 'for presenting the paper entitled',
            'participant' => 'for participating in the conference',
            'committee'   => 'for the dedication and contribution as',
            'award'       => 'in recognition of',
            default       => 'as the author of the paper entitled',
        };

        return [
            ['key' => 'heading',  'kind' => 'text',    'label' => 'Heading',        'value' => $heading, 'x' => 50, 'y' => 24, 'size' => 24, 'bold' => true,  'align' => 'C', 'color' => '#003981', 'width' => 80],
            ['key' => 'number',   'kind' => 'number',  'label' => 'Certificate number', 'value' => 'No.', 'x' => 50, 'y' => 32, 'size' => 10, 'bold' => false, 'align' => 'C', 'color' => '#6b7893', 'width' => 60],
            ['key' => 'intro',    'kind' => 'text',    'label' => 'Intro line',     'value' => $intro,   'x' => 50, 'y' => 38, 'size' => 12, 'bold' => false, 'align' => 'C', 'color' => '#6b7893', 'width' => 70],
            ['key' => 'name',     'kind' => 'name',    'label' => 'Recipient name', 'value' => '',       'x' => 50, 'y' => 44, 'size' => 26, 'bold' => true,  'align' => 'C', 'color' => '#101e41', 'width' => 80],
            ['key' => 'affiliation', 'kind' => 'affiliation', 'label' => 'Affiliation', 'value' => '', 'x' => 50, 'y' => 52, 'size' => 12, 'bold' => false, 'align' => 'C', 'color' => '#3d4a66', 'width' => 75],
            ['key' => 'for_line', 'kind' => 'text',    'label' => 'Reason line',    'value' => $forLine, 'x' => 50, 'y' => 59, 'size' => 11, 'bold' => false, 'align' => 'C', 'color' => '#6b7893', 'width' => 70],
            ['key' => 'subject',  'kind' => 'subject', 'label' => 'Paper / award',  'value' => '',       'x' => 50, 'y' => 65, 'size' => 13, 'bold' => true,  'align' => 'C', 'color' => '#101e41', 'width' => 72],
            ['key' => 'date',     'kind' => 'date',    'label' => 'Issue date',     'value' => '',       'x' => 50, 'y' => 78, 'size' => 10, 'bold' => false, 'align' => 'C', 'color' => '#6b7893', 'width' => 60],
            ['key' => 'code',     'kind' => 'code',    'label' => 'Verification code', 'value' => '',    'x' => 22, 'y' => 92, 'size' => 8,  'bold' => false, 'align' => 'C', 'color' => '#6b7893', 'width' => 30],
            ['key' => 'qr',       'kind' => 'qr',      'label' => 'QR code',        'value' => '',       'x' => 22, 'y' => 80, 'size' => 20, 'bold' => false, 'align' => 'C', 'color' => '#000000', 'width' => 20],
            ['key' => 'signature', 'kind' => 'signature', 'label' => 'Signature',    'value' => '',       'x' => 78, 'y' => 73, 'size' => 42, 'bold' => false, 'align' => 'C', 'color' => '#000000', 'width' => 42],
            ['key' => 'signatory', 'kind' => 'text',    'label' => 'Signatory',      'value' => '',       'x' => 78, 'y' => 86, 'size' => 10, 'bold' => true,  'align' => 'C', 'color' => '#101e41', 'width' => 40],
        ];
    }

    /** Bersihkan & batasi nilai field dari editor sebelum disimpan. */
    public static function sanitizeFields(array $fields): array
    {
        $clean = [];

        foreach ($fields as $field) {
            if (!is_array($field) || ($field['key'] ?? '') === '') {
                continue;
            }

            $kind = in_array($field['kind'] ?? '', self::KINDS, true) ? (string) $field['kind'] : 'text';

            $clean[] = [
                'key'    => mb_substr(preg_replace('/[^a-z0-9_]/i', '', (string) $field['key']) ?? '', 0, 40),
                'kind'   => $kind,
                'label'  => mb_substr(trim((string) ($field['label'] ?? $field['key'])), 0, 60),
                'value'  => mb_substr((string) ($field['value'] ?? ''), 0, 300),
                'x'      => self::clamp((float) ($field['x'] ?? 50), 0, 100),
                'y'      => self::clamp((float) ($field['y'] ?? 50), 0, 100),
                'size'   => (int) self::clamp((float) ($field['size'] ?? 12), 5, 60),
                'bold'   => !empty($field['bold']),
                'align'  => in_array($field['align'] ?? 'C', ['L', 'C', 'R'], true) ? (string) $field['align'] : 'C',
                'color'  => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($field['color'] ?? '')) === 1
                    ? (string) $field['color']
                    : '#101e41',
                'width'  => self::clamp((float) ($field['width'] ?? 60), 5, 100),
                'hidden' => !empty($field['hidden']),
                'font'   => in_array($field['font'] ?? '', ['sans', 'serif', 'script'], true) ? (string) $field['font'] : 'sans',
            ];
        }

        return $clean;
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return round(max($min, min($max, $value)), 2);
    }
}
