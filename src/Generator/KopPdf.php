<?php

declare(strict_types=1);

namespace Cenim\Generator;

require_once CENIM_ROOT . '/vendor-lite/tfpdf/tfpdf.php';
require_once CENIM_ROOT . '/vendor-lite/tfpdf/font/unifont/ttfonts.php';

/**
 * Halaman A4 kosong berkop — template surat.
 *
 * Sengaja tidak memakai PdfBase agar tidak ikut menggambar kop otomatis
 * (kop-nya justru gambar yang sedang kita tempel).
 */
final class KopPdf extends \tFPDF
{
    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');

        $this->SetMargins(15, 14, 15);
        $this->SetAutoPageBreak(true, 20);
        $this->SetCreator('CENIM App');
        $this->AddFont('dejavu', '', 'DejaVuSansCondensed.ttf', true);
        $this->SetFont('dejavu', '', 10);
    }
}
