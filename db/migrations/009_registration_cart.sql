-- Registrasi per paper diperluas:
--   * presenter dipilih di registrasi (dulu di form attendance) + mode offline/online
--   * paper tambahan dari first author yang sama (biaya registrasi 50%), tetap
--     dengan registrasi/invoice/LoA/receipt sendiri; main_author_id = paper utamanya
--   * peserta tambahan non-presenter ("keranjang"): co-author atau orang luar
--   * ukuran baju (opsional, Settings → Registration) per orang yang datang
-- Form attendance menjadi "Video presentation form" (tabel attendances tetap dipakai).

ALTER TABLE registrations ADD COLUMN presenter_id INTEGER REFERENCES paper_authors(id) ON DELETE SET NULL;
ALTER TABLE registrations ADD COLUMN presentation_mode TEXT NOT NULL DEFAULT 'offline';   -- offline | online
ALTER TABLE registrations ADD COLUMN presenter_shirt TEXT;
ALTER TABLE registrations ADD COLUMN main_author_id INTEGER REFERENCES authors(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS registration_attendees (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id      INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    registration_id INTEGER NOT NULL REFERENCES registrations(id) ON DELETE CASCADE,
    paper_author_id INTEGER REFERENCES paper_authors(id) ON DELETE SET NULL,  -- NULL = bukan author paper ini
    name            TEXT    NOT NULL,
    affiliation     TEXT,
    email           TEXT,
    shirt_size      TEXT,
    sort            INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_registration_attendees ON registration_attendees (registration_id, sort);

-- Presenter & mode untuk registrasi yang sudah ada: ambil dari form attendance lama
-- bila ada, kalau tidak dari penanda presenter di daftar author.
UPDATE registrations SET
    presenter_id = COALESCE(
        (SELECT t.presenter_id FROM attendances t WHERE t.author_id = registrations.author_id),
        (SELECT pa.id FROM paper_authors pa WHERE pa.author_id = registrations.author_id AND pa.is_presenter = 1 ORDER BY pa.position LIMIT 1)
    ),
    presentation_mode = COALESCE((SELECT t.mode FROM attendances t WHERE t.author_id = registrations.author_id), 'offline')
WHERE presenter_id IS NULL;

-- Contoh pertanyaan tambahan Registration form (bisa diubah/dihapus di Form Builder).
INSERT INTO form_fields (edition_id, form_key, field_key, label, type, options_json, placeholder, help_text, is_required, sort, is_visible, created_at)
SELECT e.id, 'reg_step1', 'dietary', 'Dietary requirement of the attending people', 'select',
       '["No restriction","Vegetarian","Vegan","Halal only","Other (write it in the remarks)"]',
       NULL, 'Used for the lunch and the conference dinner.', 0, 10, 1, datetime('now')
FROM editions e
WHERE NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = e.id AND f.form_key = 'reg_step1' AND f.field_key = 'dietary');

INSERT INTO form_fields (edition_id, form_key, field_key, label, type, options_json, placeholder, help_text, is_required, sort, is_visible, created_at)
SELECT e.id, 'reg_step1', 'invitation_letter', 'Do you need an invitation letter (visa / travel permit)?', 'radio',
       '["No","Yes"]', NULL, 'The secretariat sends it to the presenter''s email after the payment is confirmed.', 0, 20, 1, datetime('now')
FROM editions e
WHERE NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = e.id AND f.form_key = 'reg_step1' AND f.field_key = 'invitation_letter');

INSERT INTO form_fields (edition_id, form_key, field_key, label, type, options_json, placeholder, help_text, is_required, sort, is_visible, created_at)
SELECT e.id, 'reg_step1', 'remarks', 'Remarks for the committee', 'textarea',
       NULL, 'e.g. accessibility needs, arrival date, other dietary needs', NULL, 0, 30, 1, datetime('now')
FROM editions e
WHERE NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = e.id AND f.form_key = 'reg_step1' AND f.field_key = 'remarks');

-- Contoh pertanyaan form attendance lama lebih cocok di Registration form
-- (form attendance kini hanya untuk video). Dipindah bila belum ada jawabannya.
UPDATE form_fields SET form_key = 'reg_step1', sort = 15, label = 'Will the attending people join the conference dinner?',
       help_text = 'Offline attendance only.', updated_at = datetime('now')
WHERE form_key = 'attend_step3' AND field_key = 'conference_dinner'
  AND NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = form_fields.edition_id AND f.form_key = 'reg_step1' AND f.field_key = 'conference_dinner')
  AND NOT EXISTS (SELECT 1 FROM attendances t WHERE t.edition_id = form_fields.edition_id AND t.extra_json LIKE '%conference_dinner%');

UPDATE form_fields SET form_key = 'reg_step1', sort = 5, updated_at = datetime('now')
WHERE form_key = 'attend_step3' AND field_key = 'session_preference'
  AND NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = form_fields.edition_id AND f.form_key = 'reg_step1' AND f.field_key = 'session_preference')
  AND NOT EXISTS (SELECT 1 FROM attendances t WHERE t.edition_id = form_fields.edition_id AND t.extra_json LIKE '%session_preference%');

INSERT INTO form_fields (edition_id, form_key, field_key, label, type, options_json, placeholder, help_text, is_required, sort, is_visible, created_at)
SELECT e.id, 'attend_step3', 'video_minutes', 'Video duration (minutes)', 'number',
       NULL, 'e.g. 12', 'Keep it within the presentation time given by the committee.', 0, 10, 1, datetime('now')
FROM editions e
WHERE NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = e.id AND f.form_key = 'attend_step3' AND f.field_key = 'video_minutes');
