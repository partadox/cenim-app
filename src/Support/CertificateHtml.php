<?php

declare(strict_types=1);

namespace Cenim\Support;

use Cenim\Domain\BrandingRepo;
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
        // Tanda tangan: gambar template bila diunggah, kalau tidak tanda tangan chair
        // dari Settings (sama dengan surat/LoA), ditumpuk stempel konferensi.
        $ownSignature = (int) ($template['signature_file_id'] ?? 0) > 0;
        $chairFile    = $ownSignature ? null : BrandingRepo::signatureFile($editionId, 'chair');
        $signature    = $ownSignature
            ? self::fileUrl((int) $template['signature_file_id'])
            : ($chairFile !== null ? self::fileUrl((int) $chairFile['id']) : null);
        $signRatio    = self::imageRatio($ownSignature ? CertificateTemplateRepo::signaturePath($template) : ($chairFile !== null ? BrandingRepo::signaturePath($editionId, 'chair') : null));
        $stampFile    = BrandingRepo::signatureFile($editionId, 'stamp');
        $stampRatio   = $stampFile !== null ? self::imageRatio(BrandingRepo::signaturePath($editionId, 'stamp')) : 1.0;
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

                    // Stempel: titik tengahnya digeser dx/dy mm dari tengah tanda tangan (Settings → stamp).
                    if ($kind === 'signature' && $stampFile !== null) {
                        $stamp   = SettingRepo::stamp($editionId);
                        $stampW  = (float) $stamp['size'];
                        $items[] = [
                            'type'  => 'image',
                            'src'   => self::fileUrl((int) $stampFile['id']),
                            'left'  => $x + (float) $stamp['dx'] - $stampW / 2,
                            'top'   => $y + $size * $signRatio / 2 + (float) $stamp['dy'] - $stampW * $stampRatio / 2,
                            'width' => $stampW,
                        ];
                    }
                }
                continue;
            }

            if ($kind === 'line') {
                $left = match ((string) $field['align']) { 'L' => $x, 'R' => $x - $width, default => $x - $width / 2 };
                $items[] = ['type' => 'line', 'left' => $left, 'top' => $y, 'width' => $width,
                    'height' => max(0.2, (float) $field['size'] / 10), 'color' => (string) $field['color']];
                continue;
            }

            $text = match ($kind) {
                'name'        => (string) $certificate['recipient_name'],
                'affiliation' => trim((string) ($certificate['recipient_affiliation'] ?? '')) === ''
                    ? ''
                    : trim((string) $field['value'] . ' ' . $certificate['recipient_affiliation']),
                'paper'       => self::paperTitle($certificate),
                'number'      => trim((string) ($certificate['number'] ?? '')) === ''
                    ? ''
                    : trim((string) $field['value'] . ' ' . $certificate['number']),
                'subject'     => (string) ($certificate['subject_title'] ?? ''),
                'date'        => self::issuedLine($certificate, $edition, (string) $field['value']),
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

    /** Rasio tinggi/lebar gambar (0,4 bila tidak terbaca). */
    private static function imageRatio(?string $path): float
    {
        $size = $path !== null && is_file($path) ? @getimagesize($path) : false;

        return $size !== false && $size[0] > 0 ? $size[1] / $size[0] : 0.4;
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

    /** Judul paper penerima: dari paper si penerima (Award), kalau tidak subject_title. */
    private static function paperTitle(array $certificate): string
    {
        if ((string) ($certificate['recipient_ref_type'] ?? '') === 'paper_author' && (int) ($certificate['recipient_ref_id'] ?? 0) > 0) {
            $title = \Cenim\Core\Db::value(
                'SELECT a.paper_title FROM paper_authors pa JOIN authors a ON a.id = pa.author_id WHERE pa.id = ?',
                [(int) $certificate['recipient_ref_id']]
            );
            if ($title !== null && trim((string) $title) !== '') {
                return (string) $title;
            }
        }

        return (string) $certificate['type'] === CertificateRepo::TYPE_AWARD ? '' : (string) ($certificate['subject_title'] ?? '');
    }

    /** Baris tanggal: tanggal konferensi bila ada, kalau tidak tanggal terbit. $place dari field.value. */
    public static function issuedLine(array $certificate, array $edition, string $place = ''): string
    {
        $place = trim($place) !== '' ? trim($place) : 'Surabaya, Indonesia';
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
