-- Galeri foto landing (tombol "Gallery" di bagian About) — dulu 2 foto tetap
-- di site/partials/footer.php. Poster CFP disimpan di setting cfp_poster_file_id.

CREATE TABLE IF NOT EXISTS gallery_photos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id  INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    file_id     INTEGER REFERENCES files(id) ON DELETE SET NULL,   -- foto unggahan admin
    image_path  TEXT,                                               -- aset tema (isi awal)
    caption     TEXT,
    sort        INTEGER NOT NULL DEFAULT 0,
    is_visible  INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT    NOT NULL,
    updated_at  TEXT
);
CREATE INDEX IF NOT EXISTS idx_gallery_edition ON gallery_photos (edition_id, sort);

INSERT INTO gallery_photos (edition_id, image_path, caption, sort, created_at)
SELECT e.id, p.path, NULL, p.sort, datetime('now')
FROM editions e
JOIN (SELECT 'image/about-1.jpg' AS path, 10 AS sort UNION ALL SELECT 'image/about-2.jpg', 20) p
WHERE NOT EXISTS (SELECT 1 FROM gallery_photos g WHERE g.edition_id = e.id);
