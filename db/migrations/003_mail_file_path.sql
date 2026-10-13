-- Jejak berkas HTML email yang disimpan transport "log" (dev / MAIL_ENABLED=false),
-- supaya halaman Logs bisa membuka isi email yang persis terkirim tanpa menebak
-- nama berkas dari timestamp.
ALTER TABLE mail_log ADD COLUMN file_path TEXT;
