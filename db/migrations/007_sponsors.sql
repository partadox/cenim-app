-- Sponsor & institusi pendukung di landing (dulu markup tetap di home.php).

CREATE TABLE IF NOT EXISTS sponsors (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id   INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    name         TEXT    NOT NULL,
    role         TEXT,                  -- mis. Host, Co-Host, Sponsor, Supported by
    logo_path    TEXT,                  -- aset tema, mis. image/sponsor/1.png
    logo_file_id INTEGER REFERENCES files(id) ON DELETE SET NULL,  -- logo unggahan admin
    url          TEXT,
    sort         INTEGER NOT NULL DEFAULT 0,
    is_visible   INTEGER NOT NULL DEFAULT 1,
    created_at   TEXT    NOT NULL,
    updated_at   TEXT
);
CREATE INDEX IF NOT EXISTS idx_sponsors_edition ON sponsors (edition_id, sort);

-- Isi awal = sponsor yang tertulis di landing sebelumnya (4 & 5 dulu dikomentari).
INSERT INTO sponsors (edition_id, name, role, logo_path, url, sort, is_visible, created_at)
SELECT e.id, s.name, s.role, s.logo_path, s.url, s.sort, s.visible, datetime('now')
FROM editions e
JOIN (
    SELECT 'Institut Teknologi Sepuluh Nopember' AS name, 'Sponsor' AS role, 'image/sponsor/1.png' AS logo_path, 'https://www.its.ac.id' AS url, 10 AS sort, 1 AS visible
    UNION ALL SELECT 'Computer Engineering and Biomedical Engineering', 'Host', 'image/sponsor/2.png', NULL, 20, 1
    UNION ALL SELECT 'Politeknik Negeri Banyuwangi', 'Co-Host', 'image/sponsor/3.png', 'https://www.poliwangi.ac.id', 30, 1
    UNION ALL SELECT 'IEEE Industrial Electronics Society', 'Co-Sponsor', 'image/sponsor/4.png', NULL, 40, 0
    UNION ALL SELECT 'IEEE Indonesia Section', 'Technical Co-Sponsor', 'image/sponsor/5.png', NULL, 50, 0
) s
WHERE NOT EXISTS (SELECT 1 FROM sponsors x WHERE x.edition_id = e.id);
