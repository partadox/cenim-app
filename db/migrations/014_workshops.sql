-- Workshop: daftar workshop per edisi (dikelola admin) dan pendaftarnya.
-- Pendaftaran lewat Portal oleh author maupun peserta non-presenter, satu baris
-- per orang per workshop (email unik per workshop).

CREATE TABLE IF NOT EXISTS workshops (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id  INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    title       TEXT    NOT NULL,
    speaker     TEXT,
    description TEXT,
    schedule    TEXT,                               -- teks bebas, mis. "24 Nov 2026, 13:00-16:00"
    quota       INTEGER,                            -- NULL = tanpa batas
    sort        INTEGER NOT NULL DEFAULT 0,
    is_active   INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT    NOT NULL,
    updated_at  TEXT
);
CREATE INDEX IF NOT EXISTS idx_workshops_edition ON workshops (edition_id, sort);

CREATE TABLE IF NOT EXISTS workshop_registrations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id      INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    workshop_id     INTEGER NOT NULL REFERENCES workshops(id) ON DELETE CASCADE,
    author_id       INTEGER REFERENCES authors(id) ON DELETE SET NULL,        -- paper / peserta asal
    paper_author_id INTEGER REFERENCES paper_authors(id) ON DELETE SET NULL,  -- orangnya
    name            TEXT    NOT NULL,
    email           TEXT    NOT NULL,
    affiliation     TEXT,
    created_at      TEXT    NOT NULL,
    UNIQUE (workshop_id, email)
);
CREATE INDEX IF NOT EXISTS idx_workshop_registrations_email ON workshop_registrations (edition_id, email);

-- CENIM 2026: dua workshop (bisa diubah di Admin → Workshops).
INSERT INTO workshops (edition_id, title, speaker, description, sort, is_active, created_at)
SELECT e.id, 'Computer Vision Workshop', 'Dr. Andrew Prasetyo',
       'Lecturer at the Department of Computer Engineering, Institut Teknologi Sepuluh Nopember (ITS).', 10, 1, datetime('now')
FROM editions e
WHERE e.year = 2026 AND NOT EXISTS (SELECT 1 FROM workshops w WHERE w.edition_id = e.id AND w.title = 'Computer Vision Workshop');

INSERT INTO workshops (edition_id, title, speaker, description, sort, is_active, created_at)
SELECT e.id, 'Robot Workshop', 'Dr. Muhtadin',
       'Lecturer at the Department of Computer Engineering, Institut Teknologi Sepuluh Nopember (ITS), and advisor of the ITS Robotics Team.', 20, 1, datetime('now')
FROM editions e
WHERE e.year = 2026 AND NOT EXISTS (SELECT 1 FROM workshops w WHERE w.edition_id = e.id AND w.title = 'Robot Workshop');
