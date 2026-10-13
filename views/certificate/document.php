<?php
/**
 * Dokumen sertifikat HTML (satu atau banyak lembar). Dipakai author/panitia
 * (author/certificate.php) dan admin (certificate-view.php, certificates-print.php).
 *
 * Tombol "Download PDF" menangkap tiap lembar dengan html2canvas lalu menyusunnya
 * dengan jsPDF (satu lembar = satu halaman, ukuran mm yang sama) di browser;
 * "Print" memakai dialog cetak browser (bisa juga "Save as PDF", hasil vektor).
 *
 * @var array<int,array<string,mixed>> $sheets   hasil CertificateHtml::prepare()
 * @var string                         $title
 * @var string                         $backUrl
 * @var string                         $filename nama berkas PDF unduhan
 */

use Cenim\Support\CertificateHtml;

$first = $sheets[0] ?? ['width' => 297.0, 'height' => 210.0];
$fmt   = static fn (float $mm): string => rtrim(rtrim(number_format($mm, 3, '.', ''), '0'), '.') . 'mm';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> &middot; <?= e(cfg('app_name')) ?></title>
    <link rel="icon" type="image/png" href="<?= e(Cenim\Domain\BrandingRepo::logoUrl()) ?>">
    <link rel="stylesheet" href="<?= e(cfg('cdn.fontawesome_css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?= e(CertificateHtml::GOOGLE_FONTS) ?>">
    <style>
        @page { size: <?= $fmt((float) $first['width']) ?> <?= $fmt((float) $first['height']) ?>; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9edf4; font-family: 'Poppins', system-ui, sans-serif; color: #101e41; }
        .cn-toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
            padding: 10px 16px; background: #003981; color: #fff; box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
        }
        .cn-toolbar .cn-title { font-size: 14px; font-weight: 600; margin-right: auto; }
        .cn-toolbar a, .cn-toolbar button {
            display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(255, 255, 255, .5);
            background: transparent; color: #fff; border-radius: 6px; padding: 6px 12px; font: 500 13px 'Poppins', sans-serif;
            text-decoration: none; cursor: pointer;
        }
        .cn-toolbar .cn-primary { background: #d4a537; border-color: #d4a537; color: #101e41; }
        .cn-toolbar button:disabled { opacity: .6; cursor: wait; }
        .cn-stage { padding: 20px 16px 40px; display: flex; flex-direction: column; align-items: center; gap: 20px; }
        .cn-frame { position: relative; box-shadow: 0 4px 18px rgba(16, 30, 65, .18); background: #fff; }
        .cn-sheet { position: relative; overflow: hidden; background: #fff; transform-origin: top left; }
        .cn-sheet .cn-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: fill; }
        .cn-sheet .cn-border1 { position: absolute; inset: 8mm; border: 1.2mm solid #003981; }
        .cn-sheet .cn-border2 { position: absolute; inset: 11mm; border: .4mm solid #d4a537; }
        .cn-sheet .cn-kop { position: absolute; top: 14mm; left: 50%; transform: translateX(-50%); }
        .cn-sheet .cn-text { position: absolute; white-space: pre-line; word-wrap: break-word; margin: 0; }
        .cn-sheet .cn-img { position: absolute; display: block; }
        .cn-sheet .cn-revoked {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 5;
            font: 700 60pt 'Poppins', sans-serif; color: rgba(200, 40, 40, .28); transform: rotate(-18deg); letter-spacing: 6px;
        }
        @media print {
            body { background: #fff; }
            .cn-toolbar { display: none; }
            .cn-stage { padding: 0; gap: 0; display: block; }
            .cn-frame { box-shadow: none; width: auto !important; height: auto !important; }
            .cn-sheet { transform: none !important; page-break-after: always; break-after: page; }
            .cn-frame:last-child .cn-sheet { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>
<div class="cn-toolbar">
    <span class="cn-title"><i class="fa-solid fa-certificate me-1"></i> <?= e($title) ?></span>
    <a href="<?= e($backUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <button type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    <button type="button" class="cn-primary" id="cnDownload"><i class="fa-solid fa-file-pdf"></i> Download PDF</button>
</div>

<div class="cn-stage" id="cnStage">
    <?php foreach ($sheets as $sheet): ?>
        <div class="cn-frame">
            <div class="cn-sheet" data-wmm="<?= e((string) $sheet['width']) ?>" data-hmm="<?= e((string) $sheet['height']) ?>"
                 style="width:<?= $fmt((float) $sheet['width']) ?>;height:<?= $fmt((float) $sheet['height']) ?>">
                <?php if ($sheet['background'] !== null): ?>
                    <img class="cn-bg" src="<?= e((string) $sheet['background']) ?>" alt="">
                <?php else: ?>
                    <div class="cn-border1"></div>
                    <div class="cn-border2"></div>
                    <?php if ($sheet['letterhead'] !== null): ?>
                        <img class="cn-kop" src="<?= e((string) $sheet['letterhead']) ?>" alt=""
                             style="width:<?= $fmt(min(105.0, (float) $sheet['width'] * 0.36)) ?>">
                    <?php endif; ?>
                <?php endif; ?>

                <?php foreach ($sheet['items'] as $item): ?>
                    <?php if ($item['type'] === 'image'): ?>
                        <img class="cn-img" src="<?= e((string) $item['src']) ?>" alt=""
                             style="left:<?= $fmt((float) $item['left']) ?>;top:<?= $fmt((float) $item['top']) ?>;width:<?= $fmt((float) $item['width']) ?>">
                    <?php else: ?>
                        <p class="cn-text" style="left:<?= $fmt((float) $item['left']) ?>;top:<?= $fmt((float) $item['top']) ?>;width:<?= $fmt((float) $item['width']) ?>;font-size:<?= (int) $item['size'] ?>pt;line-height:<?= $fmt((float) $item['lineHeight']) ?>;font-weight:<?= $item['bold'] ? 700 : 400 ?>;text-align:<?= e((string) $item['align']) ?>;color:<?= e((string) $item['color']) ?>;font-family:<?= e((string) $item['font']) ?>"><?= e((string) $item['text']) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (!empty($sheet['revoked'])): ?>
                    <div class="cn-revoked">REVOKED</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="<?= e(cfg('cdn.html2canvas_js')) ?>"></script>
<script src="<?= e(cfg('cdn.jspdf_js')) ?>"></script>
<script>
(function () {
    var stage  = document.getElementById('cnStage');
    var frames = Array.prototype.slice.call(stage.querySelectorAll('.cn-frame'));

    // Layar: perkecil lembar agar muat lebar jendela (cetak & PDF tetap ukuran asli).
    function fit() {
        var room = Math.min(stage.clientWidth - 32, 1200);
        frames.forEach(function (frame) {
            var sheet = frame.querySelector('.cn-sheet');
            sheet.style.transform = 'none';
            var w = sheet.offsetWidth, h = sheet.offsetHeight;
            var scale = Math.min(1, room / w);
            sheet.style.transform = 'scale(' + scale + ')';
            frame.style.width = (w * scale) + 'px';
            frame.style.height = (h * scale) + 'px';
        });
    }
    window.addEventListener('resize', fit);
    (document.fonts ? document.fonts.ready : Promise.resolve()).then(fit);
    fit();

    /**
     * PDF: setiap lembar ditangkap SENDIRI dengan html2canvas lalu ditempel
     * memenuhi satu halaman jsPDF berukuran sama persis (mm). Tidak memakai
     * html2pdf: pustaka itu menggeser hasil mengikuti posisi gulir halaman dan
     * memotong bagian bawah lembar.
     */
    function captureSheet(sheet) {
        var clone  = sheet.cloneNode(true);
        var holder = document.createElement('div');
        clone.style.transform = 'none';
        holder.style.cssText = 'position:absolute;left:-30000px;top:0;';
        holder.appendChild(clone);
        document.body.appendChild(holder);

        return html2canvas(clone, {
            scale: 3,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            scrollX: 0,
            scrollY: 0,
            width: clone.offsetWidth,
            height: clone.offsetHeight
        }).then(function (canvas) {
            holder.remove();
            return canvas;
        }, function (error) {
            holder.remove();
            throw error;
        });
    }

    var button = document.getElementById('cnDownload');
    button.addEventListener('click', function () {
        var JsPdf = window.jspdf && window.jspdf.jsPDF;
        if (typeof html2canvas === 'undefined' || !JsPdf) {
            alert('The PDF tool could not be loaded. Please use Print and choose "Save as PDF".');
            return;
        }

        button.disabled = true;
        var label = button.innerHTML;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparing PDF...';

        var pdf = null;
        var savedScroll = [window.scrollX, window.scrollY];
        window.scrollTo(0, 0);   // pengaman tambahan: html2canvas memperhitungkan posisi gulir

        var chain = (document.fonts ? document.fonts.ready : Promise.resolve());
        frames.forEach(function (frame) {
            var sheet = frame.querySelector('.cn-sheet');
            var w = parseFloat(sheet.dataset.wmm), h = parseFloat(sheet.dataset.hmm);
            var orientation = w > h ? 'landscape' : 'portrait';

            chain = chain.then(function () { return captureSheet(sheet); }).then(function (canvas) {
                if (pdf === null) {
                    pdf = new JsPdf({ unit: 'mm', format: [w, h], orientation: orientation, compress: true });
                } else {
                    pdf.addPage([w, h], orientation);
                }
                pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, w, h);
            });
        });

        chain.then(function () {
            pdf.save(<?= json_encode($filename) ?>);
        }).catch(function () {
            alert('The PDF could not be created. Please use Print and choose "Save as PDF".');
        }).then(function () {
            window.scrollTo(savedScroll[0], savedScroll[1]);
            button.disabled = false;
            button.innerHTML = label;
        });
    });
})();
</script>
</body>
</html>
