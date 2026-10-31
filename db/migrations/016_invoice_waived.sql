-- Biaya registrasi digratiskan (waived), mis. paper dari host. Invoice tetap
-- terbit dan berstatus paid (lunas) dengan receipt bernominal 0, sehingga
-- dokumennya bisa diunduh untuk administrasi tetapi tidak dihitung pendapatan.

ALTER TABLE invoices ADD COLUMN waived_at TEXT;
ALTER TABLE invoices ADD COLUMN waived_note TEXT;
