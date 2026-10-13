-- Login per ORANG (email + OTP), semua author per paper, panitia/reviewer
-- dengan instansi, jejak "diisi oleh", sertifikat per orang bernomor induk, dan LoA.
--
-- Tabel `authors` tetap satu baris per PAPER (first author tercatat di sana
-- supaya modul lama tidak berubah); daftar lengkap author ada di paper_authors.

-- ── Semua author dari setiap paper ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS paper_authors (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id   INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    author_id    INTEGER NOT NULL REFERENCES authors(id) ON DELETE CASCADE,  -- baris paper
    position     INTEGER NOT NULL DEFAULT 1,     -- 1 = first author
    name         TEXT    NOT NULL,
    email        TEXT,                           -- huruf kecil; kosong = tidak bisa login
    affiliation  TEXT,
    country      TEXT,
    is_presenter INTEGER NOT NULL DEFAULT 0,     -- penerima sertifikat presenter
    created_at   TEXT    NOT NULL,
    updated_at   TEXT
);
CREATE INDEX IF NOT EXISTS idx_paper_authors_email ON paper_authors (edition_id, email);
CREATE INDEX IF NOT EXISTS idx_paper_authors_paper ON paper_authors (author_id, position);

-- Setiap paper yang sudah ada otomatis punya first author sebagai presenter.
INSERT INTO paper_authors (edition_id, author_id, position, name, email, affiliation, country, is_presenter, created_at)
SELECT edition_id, id, 1, first_author_name, LOWER(TRIM(email)), affiliation, country, 1, created_at
FROM authors;

-- ── Kode OTP login (6 digit, disimpan sebagai hash) ─────────────────────────
CREATE TABLE IF NOT EXISTS login_codes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id  INTEGER REFERENCES editions(id) ON DELETE CASCADE,
    email       TEXT    NOT NULL,
    code_hash   TEXT    NOT NULL,
    attempts    INTEGER NOT NULL DEFAULT 0,
    expires_at  TEXT    NOT NULL,
    used_at     TEXT,
    ip          TEXT,
    created_at  TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_login_codes_email ON login_codes (email, created_at);

-- Panitia & reviewer: kolom committee.affiliation sudah ada sejak 002.

-- ── Siapa yang mengisi registrasi / pembayaran (semua author boleh mengisi) ─
ALTER TABLE registrations ADD COLUMN filled_by_id   INTEGER REFERENCES paper_authors(id) ON DELETE SET NULL;
ALTER TABLE registrations ADD COLUMN filled_by_name TEXT;
ALTER TABLE payments      ADD COLUMN filled_by_id   INTEGER REFERENCES paper_authors(id) ON DELETE SET NULL;
ALTER TABLE payments      ADD COLUMN filled_by_name TEXT;

-- ── Sertifikat per orang + nomor induk ──────────────────────────────────────
ALTER TABLE certificates          ADD COLUMN number TEXT;                 -- nomor induk + "-001"
ALTER TABLE certificates          ADD COLUMN recipient_affiliation TEXT;
ALTER TABLE certificate_templates ADD COLUMN number_base TEXT;            -- nomor induk per jenis

-- Sertifikat lama yang tercatat per paper dipindah ke first author paper itu.
UPDATE certificates
SET recipient_ref_type = 'paper_author',
    recipient_ref_id   = (SELECT pa.id FROM paper_authors pa
                          WHERE pa.author_id = certificates.recipient_ref_id AND pa.position = 1)
WHERE recipient_ref_type = 'author';

-- ── Letter of Acceptance ────────────────────────────────────────────────────
ALTER TABLE authors ADD COLUMN loa_number    TEXT;
ALTER TABLE authors ADD COLUMN loa_issued_at TEXT;
