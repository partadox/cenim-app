-- Ukuran baju di confirmation form (bila Settings → shirt_size_enabled aktif):
-- satu ukuran per orang yang datang offline, urutan pertama = presenter.
-- Dipakai untuk memesan kaos ke vendor lebih awal. JSON array, NULL bila tidak ditanyakan.
ALTER TABLE paper_confirmations ADD COLUMN shirt_sizes_json TEXT;
