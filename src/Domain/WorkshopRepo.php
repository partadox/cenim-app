<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Workshop (dikelola admin) dan pendaftarannya lewat Portal.
 *
 * Yang boleh mendaftar: siapa pun yang login ke Portal dengan paper accepted
 * atau sebagai peserta non-presenter. Bila Settings → workshop_requires_payment
 * aktif (bawaan), form baru terbuka setelah salah satu registrasinya lunas.
 * Satu orang (email) boleh ikut beberapa workshop; kuota per workshop opsional.
 */
final class WorkshopRepo
{
    /** @return array<int,array<string,mixed>> + registered (jumlah pendaftar) */
    public static function all(int $editionId, bool $activeOnly = false): array
    {
        return Db::all(
            'SELECT w.*, (SELECT COUNT(*) FROM workshop_registrations r WHERE r.workshop_id = w.id) AS registered
             FROM workshops w WHERE w.edition_id = ?' . ($activeOnly ? ' AND w.is_active = 1' : '') . '
             ORDER BY w.sort, w.id',
            [$editionId]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM workshops WHERE id = ?', [$id]);
    }

    /** Edisi ini punya workshop? (Admin → Workshops; tiap edisi berbeda) */
    public static function enabled(int $editionId): bool
    {
        return SettingRepo::getBool($editionId, 'workshop_enabled', true);
    }

    public static function isOpen(int $editionId): bool
    {
        return self::enabled($editionId) && SettingRepo::getBool($editionId, 'workshop_open', true);
    }

    /** Sertifikat workshop aktif? (= template sertifikat Workshop aktif) */
    public static function certificatesEnabled(int $editionId): bool
    {
        $template = CertificateTemplateRepo::forType($editionId, CertificateRepo::TYPE_WORKSHOP);

        return $template !== null && (int) $template['is_active'] === 1;
    }

    public static function requiresPayment(int $editionId): bool
    {
        return SettingRepo::getBool($editionId, 'workshop_requires_payment', true);
    }

    /**
     * Sudah memenuhi syarat bayar? Lunas pada salah satu paper/registrasi orang ini.
     *
     * @param array<int,int> $authorIds baris authors milik orang ini (AuthorAuth::papers)
     */
    public static function paymentDone(array $authorIds): bool
    {
        $ids = array_values(array_filter(array_map('intval', $authorIds)));
        if ($ids === []) {
            return false;
        }

        return Db::value(
            "SELECT 1 FROM invoices i JOIN registrations r ON r.id = i.registration_id
             WHERE i.status = 'paid' AND r.author_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ') LIMIT 1',
            $ids
        ) !== null;
    }

    /** @return array<int,int> id workshop yang diikuti email ini */
    public static function joinedIds(int $editionId, string $email): array
    {
        return array_map('intval', array_column(Db::all(
            'SELECT workshop_id FROM workshop_registrations WHERE edition_id = ? AND email = ?',
            [$editionId, mb_strtolower(trim($email))]
        ), 'workshop_id'));
    }

    /** @return array<int,array<string,mixed>> workshop yang diikuti email ini */
    public static function joined(int $editionId, string $email): array
    {
        return Db::all(
            'SELECT w.* FROM workshop_registrations r JOIN workshops w ON w.id = r.workshop_id
             WHERE r.edition_id = ? AND r.email = ? ORDER BY w.sort, w.id',
            [$editionId, mb_strtolower(trim($email))]
        );
    }

    /**
     * Simpan pilihan workshop orang ini (menambah & melepas).
     *
     * @param array<int,int> $workshopIds pilihan baru
     * @return array{added:int, removed:int, full:array<int,string>}
     */
    public static function saveChoice(int $editionId, array $person, ?array $paper, array $workshopIds): array
    {
        $email   = mb_strtolower(trim((string) $person['email']));
        $current = self::joinedIds($editionId, $email);
        $active  = [];
        foreach (self::all($editionId, true) as $w) {
            $active[(int) $w['id']] = $w;
        }
        $wanted = array_values(array_intersect(array_unique(array_map('intval', $workshopIds)), array_keys($active)));
        $out    = ['added' => 0, 'removed' => 0, 'full' => []];

        Db::transaction(static function () use ($editionId, $person, $paper, $email, $current, $active, $wanted, &$out): void {
            foreach (array_diff($current, $wanted) as $id) {
                Db::delete('workshop_registrations', 'workshop_id = ? AND email = ?', [$id, $email]);
                $out['removed']++;
            }
            foreach (array_diff($wanted, $current) as $id) {
                $w     = $active[$id];
                $count = (int) Db::value('SELECT COUNT(*) FROM workshop_registrations WHERE workshop_id = ?', [$id]);
                if ($w['quota'] !== null && $count >= (int) $w['quota']) {
                    $out['full'][] = (string) $w['title'];
                    continue;
                }
                Db::insert('workshop_registrations', [
                    'edition_id'      => $editionId,
                    'workshop_id'     => $id,
                    'author_id'       => $paper !== null ? (int) $paper['id'] : null,
                    'paper_author_id' => (int) $person['id'],
                    'name'            => (string) $person['name'],
                    'email'           => $email,
                    'affiliation'     => $person['affiliation'] ?? null,
                    'created_at'      => now(),
                ]);
                $out['added']++;
            }
        });

        return $out;
    }

    /**
     * Pendaftar (semua workshop atau satu workshop) beserta asalnya.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function registrants(int $editionId, ?int $workshopId = null): array
    {
        return Db::all(
            'SELECT r.*, w.title AS workshop_title, a.paper_id, a.paper_status
             FROM workshop_registrations r
             JOIN workshops w ON w.id = r.workshop_id
             LEFT JOIN authors a ON a.id = r.author_id
             WHERE r.edition_id = ?' . ($workshopId !== null ? ' AND r.workshop_id = ?' : '') . '
             ORDER BY w.sort, w.id, r.created_at',
            $workshopId !== null ? [$editionId, $workshopId] : [$editionId]
        );
    }

    /** @param array<string,mixed> $data title, speaker, description, schedule, quota, sort, is_active */
    public static function save(int $editionId, ?int $id, array $data): int
    {
        $row = [
            'title'       => mb_substr(trim((string) $data['title']), 0, 190),
            'speaker'     => ($s = trim((string) ($data['speaker'] ?? ''))) !== '' ? mb_substr($s, 0, 190) : null,
            'description' => ($d = trim((string) ($data['description'] ?? ''))) !== '' ? mb_substr($d, 0, 2000) : null,
            'schedule'    => ($t = trim((string) ($data['schedule'] ?? ''))) !== '' ? mb_substr($t, 0, 190) : null,
            'quota'       => ($q = (int) ($data['quota'] ?? 0)) > 0 ? $q : null,
            'sort'        => (int) ($data['sort'] ?? 0),
            'is_active'   => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($id !== null) {
            Db::update('workshops', $row + ['updated_at' => now()], 'id = :target_id', ['target_id' => $id]);

            return $id;
        }

        return Db::insert('workshops', $row + ['edition_id' => $editionId, 'created_at' => now()]);
    }

    public static function delete(int $id): void
    {
        Db::run("DELETE FROM certificates WHERE recipient_ref_type = 'workshop_registration'
                 AND recipient_ref_id IN (SELECT id FROM workshop_registrations WHERE workshop_id = ?)", [$id]);
        Db::delete('workshops', 'id = ?', [$id]);
    }

    /** Hapus pendaftaran (mis. tidak hadir) beserta sertifikat workshop-nya. */
    public static function removeRegistration(int $id): void
    {
        Db::run("DELETE FROM certificates WHERE recipient_ref_type = 'workshop_registration' AND recipient_ref_id = ?", [$id]);
        Db::delete('workshop_registrations', 'id = ?', [$id]);
    }
}
