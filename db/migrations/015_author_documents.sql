-- Dokumen tambahan per paper/peserta yang diminta author (mis. surat undangan,
-- surat visa, surat tugas), diunggah admin dari halaman Edit author ke object
-- storage dan bisa diunduh author di Portal. Disimpan di tabel files
-- (owner_type = 'author', purpose = 'author_document') dengan nama & keterangan.

ALTER TABLE files ADD COLUMN title TEXT;
ALTER TABLE files ADD COLUMN description TEXT;
