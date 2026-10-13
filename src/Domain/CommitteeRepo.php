<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Daftar panitia. Dipakai untuk sertifikat panitia (Fase 6) dan nanti untuk
 * bagian Committee di landing page (Fase 7).
 */
final class CommitteeRepo
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM committee WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(int $editionId, bool $onlyVisible = false): array
    {
        $sql = 'SELECT * FROM committee WHERE edition_id = ?';
        if ($onlyVisible) {
            $sql .= ' AND show_on_landing = 1';
        }

        return Db::all($sql . ' ORDER BY sort, name', [$editionId]);
    }

    /** Baris panitia/reviewer dengan email ini (satu orang bisa punya beberapa peran). */
    public static function forEmail(int $editionId, string $email): array
    {
        $email = mb_strtolower(trim($email));

        return $email === '' ? [] : Db::all(
            'SELECT * FROM committee WHERE edition_id = ? AND LOWER(TRIM(email)) = ? ORDER BY sort, id',
            [$editionId, $email]
        );
    }

    /** Baris panitia untuk email ini di semua edisi (edisi aktif dulu). */
    public static function forEmailAllEditions(string $email): array
    {
        $email = mb_strtolower(trim($email));

        return $email === '' ? [] : Db::all(
            'SELECT c.*, e.year AS edition_year, e.name AS edition_name, e.is_active AS edition_active
             FROM committee c JOIN editions e ON e.id = c.edition_id
             WHERE LOWER(TRIM(c.email)) = ?
             ORDER BY e.is_active DESC, e.year DESC, c.sort, c.id',
            [$email]
        );
    }

    public static function countByEdition(int $editionId): int
    {
        return Db::count('committee', 'edition_id = ?', [$editionId]);
    }

    /** @return array<int,string> */
    public static function divisions(int $editionId): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['division'],
            Db::all(
                "SELECT DISTINCT division FROM committee
                 WHERE edition_id = ? AND division IS NOT NULL AND division != ''
                 ORDER BY division",
                [$editionId]
            )
        );
    }

    public static function create(int $editionId, array $data): int
    {
        $row = self::normalize($data);
        $row['edition_id'] = $editionId;
        $row['created_at'] = now();

        return Db::insert('committee', $row);
    }

    public static function update(int $id, array $data): void
    {
        $row = self::normalize($data);
        $row['updated_at'] = now();

        Db::update('committee', $row, 'id = :target_id', ['target_id' => $id]);
    }

    public static function delete(int $id): void
    {
        Db::delete('committee', 'id = ?', [$id]);
    }

    /** Sertifikat panitia yang sudah pernah diterbitkan untuk baris ini. */
    public static function hasCertificate(int $id): bool
    {
        return Db::value(
            "SELECT 1 FROM certificates
             WHERE recipient_ref_type = 'committee' AND recipient_ref_id = ? AND revoked_at IS NULL LIMIT 1",
            [$id]
        ) !== null;
    }

    /** @return array<string,string> field => pesan error */
    public static function validate(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen((string) $data['name']) > 190) {
            $errors['name'] = 'Name must not exceed 190 characters.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email is not a valid address.';
        }

        return $errors;
    }

    private static function normalize(array $data): array
    {
        return [
            'name'            => trim((string) ($data['name'] ?? '')),
            'email'           => self::nullable($data['email'] ?? null, 190),
            'role'            => self::nullable($data['role'] ?? null, 190),
            'affiliation'     => self::nullable($data['affiliation'] ?? null, 255),
            'division'        => self::nullable($data['division'] ?? null, 190),
            'sort'            => (int) ($data['sort'] ?? 0),
            'show_on_landing' => !empty($data['show_on_landing']) ? 1 : 0,
        ];
    }

    private static function nullable(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
