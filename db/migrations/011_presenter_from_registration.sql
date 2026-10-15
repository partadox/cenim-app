-- Presenter dipilih author di Registration form, bukan diset saat import/create.
-- Flag lama (first author otomatis presenter) dibersihkan: hanya presenter yang
-- dipilih pada registrasi yang sudah dikirim yang tetap ditandai.

UPDATE paper_authors
SET is_presenter = CASE
        WHEN id IN (
            SELECT presenter_id FROM registrations
            WHERE presenter_id IS NOT NULL AND status != 'draft'
        ) THEN 1
        ELSE 0
    END;
