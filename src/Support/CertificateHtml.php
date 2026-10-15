<?php

declare(strict_types=1);

namespace Cenim\Support;

use Cenim\Domain\CertificateRepo;
use Cenim\Domain\CertificateTemplateRepo;
use Cenim\Domain\EditionRepo;
use Cenim\Domain\SettingRepo;
use Cenim\Pdf\PdfBase;

/**
 * Menyiapkan data satu sertifikat untuk dirender sebagai HTML
 * (views/certificate/document.php). PDF dibuat di browser dari HTML yang sama,
 * jadi tampilan layar, cetak, dan PDF selalu identik.
 *
 * Koordinat mengikuti editor template: x/y/width dalam persen halaman, ukuran
 * font dalam pt, tinggi baris = size × 0.42 + 1.5 mm, QR & tanda tangan diukur
 * lebarnya dalam mm dengan titik acuan tengah-atas.
 */
final class CertificateHtml
{
    /** Pilihan font per field → keluarga font Google Fonts. */
    public const FONTS = [
        'sans'   => "'Poppins', 'Segoe UI', Arial, sans-serif",
        'serif'  => "'Playfair Display', Georgia, 'Times New Roman', serif",
        'script' => "'Great Vibes', 'Brush Script MT', cursive",
    ];

    public const GOOGLE_FONTS = 'https://fonts.googleapis.com/css2?family=Great+Vibes&family=Playfair+Display:wght@400;700&family=Poppins:wght@400;600;700&display=swap';

    /** Ukuran halaman (mm) potret. */
    private const PAGE_SIZES = ['A4' => [210.0, 297.0], 'LETTER' => [215.9, 279.4]];

    /** @var array<int,string> cache data URI per edisi (kop) */
    private static array $letterheads = [];

    /**
     * @return array{
     *   id:int, code:string, number:?string, title:string, revoked:bool,
     *   width:float, height:float, background:?string, letterhead:?string,
     *   items:array<int,array<string,mixed>>
     * }
     */
    public static function prepare(array $certificate): array
    {
        $editionId = (int) $certificate['edition_id'];
        $edition   = EditionRepo::find($editionId) ?? [];
        $template  = $certificate['template_id'] !== null ? CertificateTemplateRepo::find((int) $certificate['template_id']) : null;
        $template ??= CertificateTemplateRepo::ensure($editionId, (string) $certificate['type']);

        [$w, $h] = self::PAGE_SIZES[(string) ($template['page_size'] ?? 'A4')] ?? self::PAGE_SIZES['A4'];
        if ((string) ($template['orientation'] ?? 'L') === 'L') {
            [$w, $h] = [$h, $w];
        }

        $background = self::fileUrl((int) ($template['background_file_id'] ?? 0));
        $signature  = self::fileUrl((int) ($template['signature_file_id'] ?? 0));
        $signatory  = array_values(SettingRepo::chair($editionId));

        $items = [];
        foreach (CertificateTemplateRepo::fields($template) as $field) {
            if (!empty($field['hidden'])) {
                continue;
            }

            $x     = (float) $field['x'] / 100 * $w;
            $y     = (float) $field['y'] / 100 * $h;
            $width = (float) $field['width'] / 100 * $w;
            $kind  = (string) $field['kind'];

            if ($kind === 'qr' || $kind === 'signature') {
                $src = $kind === 'qr'
                    ? Qr::dataUri(CertificateRepo::verifyUrl((string) $certificate['code']), 8)
                    : $signature;

                if ($src !== null) {
                    $size    = (float) $field['size'];
                    $items[] = ['type' => 'image', 'src' => $src, 'left' => $x - $size / 2, 'top' => $y, 'width' => $size];
                }
                continue;
            }

            $text = match ($kind) {
                'name'        => (string) $certificate['recipient_name'],
                'affiliation' => (string) ($certificate['recipient_affiliation'] ?? ''),
                'number'      => trim((string) ($certificate['number'] ?? '')) === ''
                    ? ''
                    : trim((string) $field['value'] . ' ' . $certificate['number']),
                'subject'     => (string) ($certificate['subject_title'] ?? ''),
                'date'        => self::issuedLine($certificate, $edition),
                'code'        => 'Verification code: ' . $certificate['code'],
                default       => self::staticText((string) $field['key'], (string) $field['value'], ...$signatory),
            };

            if (trim($text) === '') {
                continue;
            }

            $left = match ((string) $field['align']) {
                'L'     => $x,
                'R'     => $x - $width,
                default => $x - $width / 2,
            };

            $items[] = [
                'type'       => 'text',
                'text'       => $text,
                'left'       => $left,
                'top'        => $y,
                'width'      => $width,
                'size'       => (int) $field['size'],
                'lineHeight' => (int) $field['size'] * 0.42 + 1.5,
                'bold'       => !empty($field['bold']),
                'align'      => match ((string) $field['align']) { 'L' => 'left', 'R' => 'right', default => 'center' },
                'color'      => (string) $field['color'],
                'font'       => self::FONTS[(string) ($field['font'] ?? 'sans')] ?? self::FONTS['sans'],
            ];
        }

        return [
            'id'         => (int) $certificate['id'],
            'code'       => (string) $certificate['code'],
            'number'     => $certificate['number'] ?? null,
            'title'      => CertificateRepo::label((string) $certificate['type']) . ' certificate — ' . $certificate['recipient_name'],
            'filename'   => 'Certificate-' . CertificateRepo::label((string) $certificate['type']) . '-'
                . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $certificate['recipient_name']) . '.pdf',
            'revoked'    => CertificateRepo::isRevoked($certificate),
            'width'      => $w,
            'height'     => $h,
            'background' => $background,
            'letterhead' => $background === null ? self::letterhead($editionId) : null,
            'items'      => $items,
        ];
    }

    /** URL same-origin untuk berkas template (dimuat lewat public/file.php). */
    private static function fileUrl(int $fileId): ?string
    {
        return $fileId > 0 ? app_url('public/file.php?id=' . $fileId) : null;
    }

    /** Kop surat sebagai data URI untuk kanvas bawaan (template tanpa gambar latar). */
    private static function letterhead(int $editionId): ?string
    {
        if (!array_key_exists($editionId, self::$letterheads)) {
            $path = PdfBase::letterheadPath();
            self::$letterheads[$editionId] = $path !== null && is_file($path)
                ? 'data:' . (mime_content_type($path) ?: 'image/png') . ';base64,' . base64_encode((string) file_get_contents($path))
                : '';
        }

        return self::$letterheads[$editionId] !== '' ? self::$letterheads[$editionId] : null;
    }

    /** Baris tanggal: tanggal konferensi bila ada, kalau tidak tanggal terbit. */
    public static function issuedLine(array $certificate, array $edition): string
    {
        $place = 'Surabaya, Indonesia';
        $start = (string) ($edition['conference_start'] ?? '');
        $end   = (string) ($edition['conference_end'] ?? '');

        if ($start !== '') {
            $dates = $end !== '' && $end !== $start
                ? fmt_date($start . ' 00:00:00') . ' – ' . fmt_date($end . ' 00:00:00')
                : fmt_date($start . ' 00:00:00');

            return $place . ', ' . $dates;
        }

        return $place . ', ' . fmt_date((string) $certificate['issued_at']);
    }

    /** Field teks statis; key "signatory" diisi dari Settings bila kosong. */
    private static function staticText(string $key, string $value, string $signatoryName, string $signatoryTitle): string
    {
        if ($key !== 'signatory' || $value !== '') {
            return $value;
        }

        if ($signatoryName === '') {
            return '';
        }

        return $signatoryTitle !== '' ? $signatoryName . "\n" . $signatoryTitle : $signatoryName;
    }
}
