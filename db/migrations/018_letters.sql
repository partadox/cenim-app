-- Letter Generate: surat bebas dari admin (mis. surat undangan, surat visa,
-- surat keterangan) per edisi, opsional terkait satu paper. Isi ditulis dengan
-- editor WYSIWYG dan boleh memuat variabel {paper_id}, {paper_title}, dst.;
-- PDF ber-kop dengan tanda tangan chair atau treasurer.

CREATE TABLE IF NOT EXISTS letters (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id  INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    author_id   INTEGER REFERENCES authors(id) ON DELETE SET NULL,  -- paper (opsional)
    title       TEXT    NOT NULL,               -- nama surat, juga judul di PDF
    number      TEXT,                           -- nomor surat
    letter_date TEXT,                           -- YYYY-MM-DD
    body        TEXT    NOT NULL DEFAULT '',    -- HTML dari editor
    signer      TEXT    NOT NULL DEFAULT 'chair',  -- chair | treasurer | none
    show_title  INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT    NOT NULL,
    updated_at  TEXT
);
CREATE INDEX IF NOT EXISTS idx_letters_edition ON letters (edition_id, id);
