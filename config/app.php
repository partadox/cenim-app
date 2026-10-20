<?php
/**
 * Konfigurasi non-rahasia. Aman untuk di-commit.
 * Diakses lewat helper cfg('key.subkey').
 */

declare(strict_types=1);

return [
    'app_name'      => 'CENIM',
    'app_long_name' => 'International Conference on Computer Engineering, Network and Intelligent Multimedia',

    // Path relatif dari web root menuju folder aplikasi & aset tema.
    // Aset tema TIDAK ada di repo: berkasnya di object storage Tandikat
    // (public/theme/) dan URL ini dilayani public/asset.php — lihat asset().
    'app_dir'    => '/cenim-app',
    'assets_dir' => '/cenim-app/assets/theme',

    // Segmen URL panel admin. HARUS sama dengan nama folder front controller-nya
    // (cenim-app/4dm1n/), jadi kalau diganti, foldernya ikut di-rename — dan
    // nama segmen di deploy/root.htaccess + cenim-app/.htaccess juga diganti.
    // Ini sekadar menyulitkan penebakan alamat — bukan pengganti autentikasi;
    // yang mengamankan panel tetap key admin + rate limit + Turnstile.
    'admin_dir'  => '4dm1n',
    'author_dir' => 'author',

    'session' => [
        'name'              => 'cenim_sess',
        'idle_timeout'      => 7200, // detik tanpa aktivitas
        'admin_max_hours'   => 12,   // usia session absolut, walau tetap aktif
        'author_max_hours'  => 12,
    ],

    'rate_limit' => [
        'max_attempts'   => 5,
        'window_minutes' => 15,
        'lockout_minutes' => 15,
    ],

    'upload' => [
        'max_bytes'    => 5 * 1024 * 1024,
        'allowed_ext'  => ['pdf', 'jpg', 'jpeg', 'png'],
        'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],

    // Format nomor dokumen. {yy} 2 digit tahun, {year} 4 digit, {seq} nomor urut, {type} kode jenis.
    'numbering' => [
        'invoice'     => 'CENIM{yy}/INV/{seq}',
        'receipt'     => 'CENIM{yy}/RCP/{seq}',
        'certificate' => 'CENIM{year}-{type}-{seq}',
        'seq_pad'     => 4,
    ],

    'certificate_types' => [
        'author'      => ['label' => 'Author',      'code' => 'AUT'],
        'presenter'   => ['label' => 'Presenter',   'code' => 'PRS'],
        'participant' => ['label' => 'Participant', 'code' => 'PAR'],
        'committee'   => ['label' => 'Committee',   'code' => 'COM'],
        'workshop'    => ['label' => 'Workshop',    'code' => 'WSH'],
        'award'       => ['label' => 'Award',       'code' => 'AWD'],
    ],

    // Semua library front-end dari CDN dengan versi terpin.
    'cdn' => [
        'bootstrap_css'   => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        'bootstrap_js'    => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        'fontawesome_css' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
        'jquery_js'       => 'https://code.jquery.com/jquery-3.7.1.min.js',
        'datatables_css'  => 'https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css',
        'datatables_js'   => 'https://cdn.datatables.net/2.0.8/js/dataTables.min.js',
        'datatables_bs5'  => 'https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js',
        'sweetalert_js'   => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.11.0/dist/sweetalert2.all.min.js',
        'toastify_css'    => 'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css',
        'toastify_js'     => 'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.js',
        'turnstile_js'    => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        // HTML → PDF di browser (sertifikat): tangkap lembar → susun halaman PDF.
        'html2canvas_js'  => 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
        'jspdf_js'        => 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
        // Editor WYSIWYG untuk blok HTML Landing Content (TinyMCE 6, lisensi MIT; plugin & skin dimuat dari folder yang sama).
        'tinymce_js'      => 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js',
    ],

    // Host non-CDN yang memang di-embed halaman publik (dipakai menyusun CSP).
    'security' => [
        'frame_src' => [
            'https://challenges.cloudflare.com',  // widget Turnstile
            'https://www.google.com',             // peta lokasi venue
            'https://www.youtube.com',            // video hero & highlight
            'https://www.youtube-nocookie.com',
        ],
        // Dipakai tema landing (Google Fonts) — stylesheet + berkas fontnya.
        'extra_hosts' => [
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
        ],
    ],

    'brand' => [
        'navy' => '#003981',
        'gold' => '#FEBD06',
    ],
];
