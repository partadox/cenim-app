-- Pertanyaan tambahan Payment form (Form Builder → Payment form) sebelumnya
-- belum ditampilkan di form author. Jawabannya kini disimpan di sini.
ALTER TABLE payments ADD COLUMN extra_json TEXT;

-- Contoh pertanyaan (bisa diubah/dihapus di Form Builder → Payment form).
INSERT INTO form_fields (edition_id, form_key, field_key, label, type, options_json, placeholder, help_text, is_required, sort, is_visible, created_at)
SELECT e.id, 'pay_step2', 'paid_by', 'Who paid the registration fee?', 'radio',
       '["The author personally","The author''s institution / university","A sponsor or research grant"]',
       NULL, 'Helps the committee match the transfer.', 0, 10, 1, datetime('now')
FROM editions e
WHERE NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = e.id AND f.form_key = 'pay_step2' AND f.field_key = 'paid_by');

INSERT INTO form_fields (edition_id, form_key, field_key, label, type, options_json, placeholder, help_text, is_required, sort, is_visible, created_at)
SELECT e.id, 'pay_step2', 'receipt_name', 'Name / institution to print on the receipt (if different)', 'text',
       NULL, 'e.g. Faculty of Engineering, Universitas Contoh', 'Leave empty to use the first author''s name.', 0, 20, 1, datetime('now')
FROM editions e
WHERE NOT EXISTS (SELECT 1 FROM form_fields f WHERE f.edition_id = e.id AND f.form_key = 'pay_step2' AND f.field_key = 'receipt_name');
