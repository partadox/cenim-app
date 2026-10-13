-- Previous Conference di landing (data, bukan markup) + logo kop PDF per edisi.

-- ── Previous Conference ─────────────────────────────────────────────────────
-- Edisi lama (2018–2025, situs statis di cenim-old/) diisi di sini; edisi yang
-- dikelola sistem ini (2026, 2027, …) otomatis ditambahkan begitu sudah lewat
-- (lihat PreviousConferenceRepo::sync). site_url relatif = di bawah web root.
CREATE TABLE IF NOT EXISTS previous_conferences (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    year          INTEGER NOT NULL UNIQUE,
    title         TEXT    NOT NULL,
    site_url      TEXT,                 -- "2024/" (relatif web root) atau URL penuh
    xplore_url    TEXT,                 -- prosiding IEEE Xplore
    image_path    TEXT,                 -- aset tema, mis. image/previous-conf/Cenim 2024.png
    image_file_id INTEGER REFERENCES files(id) ON DELETE SET NULL,  -- gambar unggahan admin
    is_visible    INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT    NOT NULL,
    updated_at    TEXT
);

INSERT OR IGNORE INTO previous_conferences (year, title, site_url, xplore_url, image_path, created_at) VALUES
    (2018, 'CENIM 2018', '2018/', 'https://ieeexplore.ieee.org/xpl/conhome/8703705/proceeding',  'image/previous-conf/Cenim 2018.png', datetime('now')),
    (2019, 'CENIM 2019', '2019/', 'https://ieeexplore.ieee.org/xpl/conhome/8966552/proceeding',  'image/previous-conf/Cenim 2019.png', datetime('now')),
    (2020, 'CENIM 2020', '2020/', 'https://ieeexplore.ieee.org/xpl/conhome/9297829/proceeding',  'image/previous-conf/Cenim 2020.png', datetime('now')),
    (2022, 'CENIM 2022', '2022/', 'https://ieeexplore.ieee.org/xpl/conhome/10037245/proceeding', 'image/previous-conf/Cenim 2022.png', datetime('now')),
    (2024, 'CENIM 2024', '2024/', 'https://ieeexplore.ieee.org/xpl/conhome/10882159/proceeding', 'image/previous-conf/Cenim 2024.png', datetime('now')),
    (2025, 'CENIM 2025', '2025/', 'https://ieeexplore.ieee.org/xpl/conhome/11325747/proceeding', 'image/previous-conf/Cenim 2025.png', datetime('now'));

-- ── Logo pada kop PDF (LoA, invoice, receipt), per edisi ────────────────────
-- kind: edition_logo (logo edisi aktif, ikut berubah bila logo diganti)
--       ref          (logo bawaan di reference/, mis. ITS/CE/BME)
--       file         (logo unggahan admin, tabel files)
CREATE TABLE IF NOT EXISTS kop_logos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id  INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    kind        TEXT    NOT NULL,
    ref_path    TEXT,
    file_id     INTEGER REFERENCES files(id) ON DELETE SET NULL,
    label       TEXT,
    side        TEXT    NOT NULL DEFAULT 'right',   -- left | right
    sort        INTEGER NOT NULL DEFAULT 0,          -- urutan dari kiri ke kanan dalam sisinya
    height_mm   REAL    NOT NULL DEFAULT 13,
    created_at  TEXT    NOT NULL,
    updated_at  TEXT
);
CREATE INDEX IF NOT EXISTS idx_kop_logos_edition ON kop_logos (edition_id, side, sort);
