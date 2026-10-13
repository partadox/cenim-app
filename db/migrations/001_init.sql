-- CENIM App — skema awal (SQLite)
--
-- Konvensi:
--   * Semua timestamp TEXT dalam UTC 'YYYY-MM-DD HH:MM:SS' (lihat helper now()).
--   * Nilai uang REAL; mata uang disimpan eksplisit di kolom currency.
--   * Boolean INTEGER 0/1.
--   * Setiap tabel data bereferensi edition_id agar satu instalasi bisa
--     melayani banyak edisi konferensi (2026, 2027, ...).

-- ── Edisi & pengaturan ──────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS editions (
    id                     INTEGER PRIMARY KEY AUTOINCREMENT,
    year                   INTEGER NOT NULL UNIQUE,
    name                   TEXT    NOT NULL,
    slug                   TEXT    NOT NULL UNIQUE,
    is_active              INTEGER NOT NULL DEFAULT 0,
    timezone               TEXT    NOT NULL DEFAULT 'Asia/Jakarta',
    conference_start       TEXT,
    conference_end         TEXT,
    certificate_release_at TEXT,
    created_at             TEXT    NOT NULL,
    updated_at             TEXT
);

CREATE TABLE IF NOT EXISTS settings (
    edition_id INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    "key"      TEXT    NOT NULL,
    value_json TEXT    NOT NULL,
    updated_at TEXT    NOT NULL,
    PRIMARY KEY (edition_id, "key")
);

-- ── Author (peserta utama) ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS authors (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id        INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    paper_id          TEXT    NOT NULL,
    paper_title       TEXT,
    track             TEXT,
    first_author_name TEXT    NOT NULL,
    email             TEXT    NOT NULL,
    affiliation       TEXT,
    country           TEXT,
    phone             TEXT,
    total_pages       INTEGER NOT NULL DEFAULT 0,
    is_presenter      INTEGER NOT NULL DEFAULT 1,
    paper_status      TEXT    NOT NULL DEFAULT 'accepted',
    note              TEXT,
    imported_batch    TEXT,
    created_at        TEXT    NOT NULL,
    updated_at        TEXT,
    UNIQUE (edition_id, paper_id)
);

CREATE INDEX IF NOT EXISTS idx_authors_email ON authors (edition_id, email);

-- ── Biaya & paket ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS fee_categories (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id   INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    code         TEXT    NOT NULL,
    label        TEXT    NOT NULL,
    currency     TEXT    NOT NULL,
    amount       REAL    NOT NULL DEFAULT 0,
    is_student   INTEGER NOT NULL DEFAULT 0,
    is_ieee      INTEGER NOT NULL DEFAULT 0,
    is_presenter INTEGER NOT NULL DEFAULT 1,
    sort         INTEGER NOT NULL DEFAULT 0,
    is_active    INTEGER NOT NULL DEFAULT 1,
    created_at   TEXT    NOT NULL,
    updated_at   TEXT,
    UNIQUE (edition_id, code)
);

CREATE TABLE IF NOT EXISTS packages (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id   INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    code         TEXT    NOT NULL,
    name         TEXT    NOT NULL,
    description  TEXT,
    currency     TEXT    NOT NULL,
    amount       REAL    NOT NULL DEFAULT 0,
    includes_json TEXT,
    sort         INTEGER NOT NULL DEFAULT 0,
    is_active    INTEGER NOT NULL DEFAULT 1,
    created_at   TEXT    NOT NULL,
    updated_at   TEXT,
    UNIQUE (edition_id, code)
);

-- ── Registrasi (form 1) ─────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS registrations (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id       INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    author_id        INTEGER NOT NULL REFERENCES authors(id) ON DELETE CASCADE,
    fee_category_id  INTEGER REFERENCES fee_categories(id),
    package_id       INTEGER REFERENCES packages(id),
    participant_type TEXT    NOT NULL DEFAULT 'presenter',  -- presenter | non_presenter
    is_student       INTEGER NOT NULL DEFAULT 0,
    is_ieee_member   INTEGER NOT NULL DEFAULT 0,
    ieee_number      TEXT,
    extra_pages      INTEGER NOT NULL DEFAULT 0,
    extra_papers     INTEGER NOT NULL DEFAULT 0,
    currency         TEXT    NOT NULL DEFAULT 'IDR',
    subtotal         REAL    NOT NULL DEFAULT 0,
    discount         REAL    NOT NULL DEFAULT 0,
    total            REAL    NOT NULL DEFAULT 0,
    extra_json       TEXT,                                  -- jawaban field dinamis form builder
    status           TEXT    NOT NULL DEFAULT 'draft',      -- draft|submitted|verified|rejected
    reject_reason    TEXT,
    submitted_at     TEXT,
    verified_at      TEXT,
    verified_by      TEXT,
    created_at       TEXT    NOT NULL,
    updated_at       TEXT,
    UNIQUE (author_id)
);

CREATE INDEX IF NOT EXISTS idx_registrations_status ON registrations (edition_id, status);

-- ── File (bukti upload, tanda tangan, logo, kop) ────────────────────────────

CREATE TABLE IF NOT EXISTS files (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id    INTEGER REFERENCES editions(id) ON DELETE CASCADE,
    owner_type    TEXT,                     -- registration | payment | committee | media | edition
    owner_id      INTEGER,
    purpose       TEXT    NOT NULL,         -- student_proof|ieee_proof|payment_proof|signature|logo|kop|other
    driver        TEXT    NOT NULL DEFAULT 'local',  -- local | gdrive
    path          TEXT,                     -- relatif terhadap STORAGE_PATH (driver local)
    remote_id     TEXT,                     -- file id Google Drive (driver gdrive)
    remote_url    TEXT,
    original_name TEXT,
    mime          TEXT,
    size          INTEGER NOT NULL DEFAULT 0,
    sha256        TEXT,
    uploaded_at   TEXT    NOT NULL,
    deleted_at    TEXT
);

CREATE INDEX IF NOT EXISTS idx_files_owner ON files (owner_type, owner_id, purpose);

-- ── Invoice ─────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS invoices (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id      INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    registration_id INTEGER NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
    number          TEXT    NOT NULL UNIQUE,
    issued_at       TEXT    NOT NULL,
    due_date        TEXT,
    currency        TEXT    NOT NULL,
    subtotal        REAL    NOT NULL DEFAULT 0,
    discount        REAL    NOT NULL DEFAULT 0,
    total           REAL    NOT NULL DEFAULT 0,
    status          TEXT    NOT NULL DEFAULT 'unpaid',  -- unpaid | paid | void
    note            TEXT,
    pdf_cache       TEXT,
    created_at      TEXT    NOT NULL,
    updated_at      TEXT
);

CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices (edition_id, status);

CREATE TABLE IF NOT EXISTS invoice_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id  INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    description TEXT    NOT NULL,
    qty         REAL    NOT NULL DEFAULT 1,
    unit_price  REAL    NOT NULL DEFAULT 0,
    amount      REAL    NOT NULL DEFAULT 0,
    sort        INTEGER NOT NULL DEFAULT 0
);

-- ── Pembayaran (form 2) & receipt ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS payments (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id     INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    invoice_id     INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    method         TEXT    NOT NULL DEFAULT 'bank_transfer',
    amount_paid    REAL    NOT NULL DEFAULT 0,
    currency       TEXT    NOT NULL,
    paid_at        TEXT,
    bank_name      TEXT,
    sender_name    TEXT,
    reference      TEXT,
    proof_file_id  INTEGER REFERENCES files(id),
    status         TEXT    NOT NULL DEFAULT 'submitted',  -- submitted | confirmed | rejected
    note           TEXT,
    reject_reason  TEXT,
    submitted_at   TEXT    NOT NULL,
    confirmed_at   TEXT,
    confirmed_by   TEXT,
    created_at     TEXT    NOT NULL,
    updated_at     TEXT
);

CREATE INDEX IF NOT EXISTS idx_payments_status ON payments (edition_id, status);

CREATE TABLE IF NOT EXISTS receipts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    payment_id INTEGER NOT NULL REFERENCES payments(id) ON DELETE CASCADE,
    number     TEXT    NOT NULL UNIQUE,
    issued_at  TEXT    NOT NULL,
    amount     REAL    NOT NULL DEFAULT 0,
    currency   TEXT    NOT NULL,
    pdf_cache  TEXT,
    created_at TEXT    NOT NULL
);

-- ── Panitia & sertifikat ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS committee (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id       INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    name             TEXT    NOT NULL,
    email            TEXT,
    role             TEXT,
    division         TEXT,
    photo_file_id    INTEGER REFERENCES files(id),
    sort             INTEGER NOT NULL DEFAULT 0,
    show_on_landing  INTEGER NOT NULL DEFAULT 1,
    created_at       TEXT    NOT NULL,
    updated_at       TEXT
);

CREATE TABLE IF NOT EXISTS certificate_templates (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id         INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    type               TEXT    NOT NULL,   -- author|presenter|participant|committee|award
    name               TEXT    NOT NULL,
    page_size          TEXT    NOT NULL DEFAULT 'A4',
    orientation        TEXT    NOT NULL DEFAULT 'L',
    background_file_id INTEGER REFERENCES files(id),
    signature_file_id  INTEGER REFERENCES files(id),
    fields_json        TEXT,               -- [{key,label,x,y,size,font,align,color,max_width}]
    is_active          INTEGER NOT NULL DEFAULT 1,
    created_at         TEXT    NOT NULL,
    updated_at         TEXT
);

CREATE TABLE IF NOT EXISTS certificates (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id         INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    type               TEXT    NOT NULL,
    template_id        INTEGER REFERENCES certificate_templates(id),
    recipient_name     TEXT    NOT NULL,
    recipient_email    TEXT,
    recipient_ref_type TEXT,               -- author | committee | manual
    recipient_ref_id   INTEGER,
    subject_title      TEXT,               -- judul paper / nama penghargaan
    code               TEXT    NOT NULL UNIQUE,
    issued_at          TEXT    NOT NULL,
    revoked_at         TEXT,
    revoke_reason      TEXT,
    pdf_cache          TEXT,
    download_count     INTEGER NOT NULL DEFAULT 0,
    last_downloaded_at TEXT,
    created_at         TEXT    NOT NULL,
    updated_at         TEXT
);

CREATE INDEX IF NOT EXISTS idx_certificates_ref ON certificates (edition_id, type, recipient_ref_type, recipient_ref_id);

-- ── Konten website ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS content_blocks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id  INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    page        TEXT    NOT NULL DEFAULT 'home',   -- home|registration|submission|workshop
    section_key TEXT    NOT NULL,
    sort        INTEGER NOT NULL DEFAULT 0,
    type        TEXT    NOT NULL DEFAULT 'html',   -- html|text|list|slide|table
    data_json   TEXT    NOT NULL,
    is_visible  INTEGER NOT NULL DEFAULT 1,
    updated_at  TEXT    NOT NULL,
    UNIQUE (edition_id, page, section_key)
);

CREATE TABLE IF NOT EXISTS timeline_items (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id        INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    sort              INTEGER NOT NULL DEFAULT 0,
    title             TEXT    NOT NULL,
    current_date_text TEXT    NOT NULL,
    -- Riwayat tanggal yang sudah di-extend, JSON array of string.
    -- Dirender ber-strikethrough dan TIDAK PERNAH dihapus.
    history_json      TEXT    NOT NULL DEFAULT '[]',
    note              TEXT,
    is_visible        INTEGER NOT NULL DEFAULT 1,
    created_at        TEXT    NOT NULL,
    updated_at        TEXT
);

CREATE TABLE IF NOT EXISTS speakers (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id    INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    name          TEXT    NOT NULL,
    title         TEXT,
    affiliation   TEXT,
    photo_file_id INTEGER REFERENCES files(id),
    photo_url     TEXT,
    bio           TEXT,
    sort          INTEGER NOT NULL DEFAULT 0,
    is_visible    INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT    NOT NULL,
    updated_at    TEXT
);

CREATE TABLE IF NOT EXISTS form_fields (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id   INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    form_key     TEXT    NOT NULL,          -- reg_step1 | pay_step2
    field_key    TEXT    NOT NULL,
    label        TEXT    NOT NULL,
    type         TEXT    NOT NULL DEFAULT 'text',  -- text|textarea|select|radio|checkbox|file|number|date
    options_json TEXT,
    placeholder  TEXT,
    help_text    TEXT,
    is_required  INTEGER NOT NULL DEFAULT 0,
    sort         INTEGER NOT NULL DEFAULT 0,
    is_visible   INTEGER NOT NULL DEFAULT 1,
    created_at   TEXT    NOT NULL,
    updated_at   TEXT,
    UNIQUE (edition_id, form_key, field_key)
);

CREATE TABLE IF NOT EXISTS media (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id INTEGER REFERENCES editions(id) ON DELETE CASCADE,
    kind       TEXT    NOT NULL,            -- logo|kop|banner|signature|sponsor|gallery
    file_id    INTEGER REFERENCES files(id),
    label      TEXT,
    meta_json  TEXT,
    sort       INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL
);

-- ── Sistem ──────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS number_sequences (
    edition_id INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    "key"      TEXT    NOT NULL,            -- invoice | receipt | certificate_AUT | ...
    current    INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT    NOT NULL,
    PRIMARY KEY (edition_id, "key")
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    kind       TEXT    NOT NULL,            -- admin | author
    identifier TEXT    NOT NULL,
    ip         TEXT    NOT NULL,
    success    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts (kind, identifier, ip, created_at);

CREATE TABLE IF NOT EXISTS audit_logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id  INTEGER REFERENCES editions(id) ON DELETE SET NULL,
    actor_type  TEXT    NOT NULL,           -- admin | author | system
    actor_id    TEXT,
    action      TEXT    NOT NULL,
    target_type TEXT,
    target_id   INTEGER,
    meta_json   TEXT,
    ip          TEXT,
    ua          TEXT,
    created_at  TEXT    NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs (created_at);

CREATE TABLE IF NOT EXISTS mail_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id INTEGER REFERENCES editions(id) ON DELETE SET NULL,
    to_email   TEXT    NOT NULL,
    subject    TEXT    NOT NULL,
    template   TEXT,
    status     TEXT    NOT NULL DEFAULT 'queued',  -- queued | sent | failed
    error      TEXT,
    created_at TEXT    NOT NULL,
    sent_at    TEXT
);
