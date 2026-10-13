# vendor-lite

Library PHP pihak ketiga yang di-*vendor* manual (tanpa composer, karena hosting cPanel
tidak menjalankan composer). Setiap library dicatat di sini: versi, sumber unduhan,
dan lisensinya.

| Folder | Library | Versi | Dipakai untuk | Fase | Lisensi |
|---|---|---|---|---|---|
| `tfpdf/` | tFPDF — `Setasign/tFPDF` branch `master`, diunduh 12 Agu 2026 (`tfpdf.php`, `font/*.php`, `font/unifont/ttfonts.php`) | 1.33-setasign | Invoice, receipt, sertifikat PDF | 4 | FPDF license (bebas pakai) |
| `tfpdf/font/unifont/` | DejaVu Sans Condensed (Regular + Bold) | 2.37 | Font ber-UTF-8 untuk PDF | 4 | Bitstream Vera / DejaVu (bebas) |
| `phpqrcode/` | phpqrcode — `t0k4rt/phpqrcode` branch `master`, diunduh 12 Agu 2026 (`phpqrcode.php` gabungan) | 1.1.4 | QR verifikasi sertifikat | 6 | LGPL-3 |
| ~~`phpmailer/`~~ | **tidak dipakai** — pengiriman email memakai `src/Core/Smtp.php` (klien SMTP sendiri: STARTTLS, AUTH PLAIN/LOGIN, multipart) | — | — | — |

Catatan tFPDF:

* Kodenya **tidak diubah sama sekali**. Upstream mengandalkan autoload composer untuk
  kelas `TTFontFile` (baris `require_once` di `tfpdf.php` sengaja dikomentari di sana),
  jadi `src/Pdf/PdfBase.php` memuat `font/unifont/ttfonts.php` secara manual.
* File cache metrik font (`*.mtx.php`, `*.cw*.dat` di `font/unifont/`) **ikut di-commit**
  supaya di production tidak perlu menulis apa pun ke folder ini. Hapus saja bila
  fontnya diganti — akan dibuat ulang otomatis saat PDF pertama dibuat.
* Hanya dua varian font yang dibawa (Regular + Bold) agar repo tetap ringan. Aksara
  Latin (termasuk aksen dan Vietnam), Yunani, dan Kiril tercakup; CJK dan Arab tidak.

Catatan phpqrcode:

* Ditulis untuk PHP 5 dan memunculkan notice `E_DEPRECATED` di PHP 8.4 (urutan
  parameter opsional). Kodenya **tidak diubah**; `src/Support/Qr.php` membungkam
  notice itu hanya selama library dimuat/dipakai dan menangkap keluaran tak
  terduga dengan output buffer, supaya tidak pernah menyusup ke dalam PDF.
* Cache internal library dimatikan (`QR_CACHEABLE` default false), jadi tidak ada
  folder yang perlu writable.

Aturan:

1. Jangan mengubah kode library. Kalau perlu perilaku khusus, buat wrapper di `src/`
   (`src/Pdf/PdfBase.php`, `src/Core/Mailer.php`).
2. Catat versi persis di tabel di atas saat menambahkan library, agar upgrade bisa dilacak.
3. Folder ini ditolak akses browser oleh `.htaccess`.
4. Library front-end tidak pernah masuk sini — semuanya dari CDN (lihat `config/app.php`).
