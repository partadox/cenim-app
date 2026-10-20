<?php

declare(strict_types=1);

namespace Cenim\Domain;

/**
 * Pop-up pengumuman di halaman publik (home, workshop, registration) — satu per
 * edisi, disimpan di settings key `announcement_popup`. Isi HTML ditulis admin
 * lewat editor WYSIWYG (dipercaya, sama seperti blok konten landing).
 *
 * Frekuensi: 'session' = sekali per kunjungan (sessionStorage browser), muncul
 * lagi bila isinya diubah; 'always' = setiap halaman dibuka.
 */
final class PopupRepo
{
    public const KEY = 'announcement_popup';

    /** Halaman publik yang menampilkan pop-up. */
    public const PAGES = ['home', 'workshop', 'registration'];

    public const FREQUENCIES = [
        'session' => 'Once per visit (shows again after the content changes)',
        'always'  => 'Every time one of the pages is opened',
    ];

    /** @return array{enabled:bool, title:string, body:string, button:string, frequency:string, updated_at:string} */
    public static function get(int $editionId): array
    {
        $data = SettingRepo::getArray($editionId, self::KEY, []);

        return [
            'enabled'    => (bool) ($data['enabled'] ?? false),
            'title'      => (string) ($data['title'] ?? 'Announcement'),
            'body'       => (string) ($data['body'] ?? ''),
            'button'     => (string) ($data['button'] ?? 'Close'),
            'frequency'  => isset(self::FREQUENCIES[$data['frequency'] ?? '']) ? (string) $data['frequency'] : 'session',
            'updated_at' => (string) ($data['updated_at'] ?? ''),
        ];
    }

    public static function save(int $editionId, array $values): void
    {
        SettingRepo::set($editionId, self::KEY, [
            'enabled'    => (bool) $values['enabled'],
            'title'      => mb_substr(trim((string) $values['title']), 0, 150),
            'body'       => (string) $values['body'],
            'button'     => mb_substr(trim((string) $values['button']), 0, 40) ?: 'Close',
            'frequency'  => isset(self::FREQUENCIES[$values['frequency'] ?? '']) ? (string) $values['frequency'] : 'session',
            'updated_at' => now(),
        ]);
    }

    /** Pop-up aktif untuk halaman ini, atau null. */
    public static function forPage(int $editionId, string $page): ?array
    {
        if (!in_array($page, self::PAGES, true)) {
            return null;
        }

        $popup = self::get($editionId);
        $text  = trim(strip_tags($popup['body'], '<img><iframe><video>'));

        return $popup['enabled'] && $text !== '' ? $popup : null;
    }
}
