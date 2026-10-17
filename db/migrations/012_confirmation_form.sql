-- Confirmation form (form 0) sebelum Registration form, dan jumlah halaman final:
--   * paper_confirmations: satu per paper — rencana presentasi offline/online,
--     perkiraan jumlah orang yang hadir (offline: 1 presenter + sisanya
--     non-presenter), jumlah halaman manuskrip review saat konfirmasi beserta
--     perkiraan biaya halaman tambahan, dan catatan untuk panitia.
--   * authors.final_pages: jumlah halaman final manuscript (diimpor ulang dari
--     EDAS, hanya kolom ini yang berubah). Kosong = belum ada, biaya memakai
--     total_pages (halaman manuskrip review).

ALTER TABLE authors ADD COLUMN final_pages INTEGER;

CREATE TABLE IF NOT EXISTS paper_confirmations (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id        INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    author_id         INTEGER NOT NULL REFERENCES authors(id) ON DELETE CASCADE,
    presentation_mode TEXT    NOT NULL DEFAULT 'offline',  -- offline | online
    attendees         INTEGER NOT NULL DEFAULT 1,          -- orang yang datang (offline), termasuk presenter
    pages             INTEGER NOT NULL DEFAULT 0,          -- halaman manuskrip review saat dikonfirmasi
    extra_pages       INTEGER NOT NULL DEFAULT 0,
    extra_page_fee    REAL    NOT NULL DEFAULT 0,          -- total biaya halaman tambahan (perkiraan)
    currency          TEXT    NOT NULL DEFAULT 'IDR',
    note              TEXT,
    filled_by_id      INTEGER REFERENCES paper_authors(id) ON DELETE SET NULL,
    filled_by_name    TEXT,
    submitted_at      TEXT    NOT NULL,
    created_at        TEXT    NOT NULL,
    updated_at        TEXT,
    UNIQUE (author_id)
);
CREATE INDEX IF NOT EXISTS idx_paper_confirmations_edition ON paper_confirmations (edition_id);

-- CENIM 2026: batas confirmation form 20 Oktober 2026, tampil di timeline
-- sebelum Final Manuscript. Bisa diubah di Settings dan Timeline.
INSERT OR IGNORE INTO settings (edition_id, "key", value_json, updated_at)
SELECT id, 'confirmation_deadline', '"2026-10-20"', datetime('now') FROM editions WHERE year = 2026;

INSERT INTO timeline_items (edition_id, sort, title, current_date_text, history_json, is_visible, created_at)
SELECT e.id,
       COALESCE((SELECT MAX(t.sort) FROM timeline_items t WHERE t.edition_id = e.id AND t.title LIKE '%Acceptance%'), 30) + 5,
       'Confirmation Form', '20 October 2026', '[]', 1, datetime('now')
FROM editions e
WHERE e.year = 2026
  AND NOT EXISTS (SELECT 1 FROM timeline_items t WHERE t.edition_id = e.id AND t.title LIKE '%Confirmation%');
