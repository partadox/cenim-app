-- Koreksi nominal pembayaran oleh admin (setelah klarifikasi dengan author
-- untuk kasus underpaid/overpaid). amount_paid berisi nominal yang benar;
-- nominal asli dari author disimpan di amount_reported.

ALTER TABLE payments ADD COLUMN amount_reported REAL;
ALTER TABLE payments ADD COLUMN amount_note TEXT;
ALTER TABLE payments ADD COLUMN amount_corrected_at TEXT;
