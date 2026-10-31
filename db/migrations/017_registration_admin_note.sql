-- Keterangan internal admin per registrasi (tidak terlihat author), diisi dari
-- tombol "Note" di daftar Registrations, mis. "transfer lewat panitia", "host paper".

ALTER TABLE registrations ADD COLUMN admin_note TEXT;
ALTER TABLE registrations ADD COLUMN admin_note_at TEXT;
