-- Fase 7 (landing CMS): kolom tambahan yang dibutuhkan tampilan publik.

-- Panitia ditampilkan di landing sebagai "Nama (Afiliasi)", jadi afiliasinya
-- perlu kolom sendiri (sebelumnya hanya ada role & division).
ALTER TABLE committee ADD COLUMN affiliation TEXT;

-- Keynote speaker punya beberapa tautan profil (Scholar, Scopus, situs pribadi).
ALTER TABLE speakers ADD COLUMN links_json TEXT;
ALTER TABLE speakers ADD COLUMN profile_url TEXT;

-- Blok konten landing: catat siapa yang terakhir mengubah, untuk jejak audit.
ALTER TABLE content_blocks ADD COLUMN updated_by TEXT;
