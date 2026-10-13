-- Form 3 — Attendance: siapa yang hadir, offline/online, presenter, dan link
-- video presentasi (Google Drive / YouTube) untuk presentasi online.

CREATE TABLE IF NOT EXISTS attendances (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    edition_id        INTEGER NOT NULL REFERENCES editions(id) ON DELETE CASCADE,
    author_id         INTEGER NOT NULL UNIQUE REFERENCES authors(id) ON DELETE CASCADE,  -- satu per paper
    mode              TEXT    NOT NULL,              -- offline | online
    presenter_id      INTEGER REFERENCES paper_authors(id) ON DELETE SET NULL,
    attendee_ids_json TEXT    NOT NULL DEFAULT '[]', -- id paper_authors yang hadir
    video_url         TEXT,                          -- wajib untuk presenter online
    video_host        TEXT,                          -- drive | youtube
    note              TEXT,
    extra_json        TEXT,                          -- jawaban pertanyaan Form Builder (attend_step3)
    filled_by_id      INTEGER REFERENCES paper_authors(id) ON DELETE SET NULL,
    filled_by_name    TEXT,
    submitted_at      TEXT    NOT NULL,
    created_at        TEXT    NOT NULL,
    updated_at        TEXT
);
CREATE INDEX IF NOT EXISTS idx_attendances_edition ON attendances (edition_id, mode);

-- Contoh pertanyaan tambahan (bisa diubah/dihapus di Form Builder → Attendance form).
INSERT INTO form_fields (edition_id, form_key, field_key, label, type, options_json, placeholder, help_text, is_required, sort, is_visible, created_at)
SELECT e.id, 'attend_step3', 'session_preference', 'Preferred presentation session', 'select',
       '["Day 1 - morning","Day 1 - afternoon","Day 2 - morning","Day 2 - afternoon","No preference"]',
       NULL, 'The committee tries to follow it when building the schedule; it is not guaranteed.', 0, 10, 1, datetime('now')
FROM editions e
WHERE NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = e.id AND f.form_key = 'attend_step3' AND f.field_key = 'session_preference');

INSERT INTO form_fields (edition_id, form_key, field_key, label, type, options_json, placeholder, help_text, is_required, sort, is_visible, created_at)
SELECT e.id, 'attend_step3', 'conference_dinner', 'Will the attending author(s) join the conference dinner?', 'radio',
       '["Yes","No"]', NULL, 'Offline attendance only.', 0, 20, 1, datetime('now')
FROM editions e
WHERE NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = e.id AND f.form_key = 'attend_step3' AND f.field_key = 'conference_dinner');
