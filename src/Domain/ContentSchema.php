<?php

declare(strict_types=1);

namespace Cenim\Domain;

/**
 * Daftar blok konten yang bisa diedit admin, per halaman publik.
 *
 * Ini "peta" antara editor di panel dan pemanggilan cms_text()/cms_html()/
 * cms_list() di dalam template. Menambah blok baru = tambahkan di sini lalu
 * pakai key-nya di template.
 *
 * kind: text (satu baris) | html (banyak baris, boleh tag) | list (JSON)
 */
final class ContentSchema
{
    /** @return array<string,array{label:string,groups:array<string,array<string,array{kind:string,label:string,hint?:string}>>}> */
    public static function pages(): array
    {
        return [
            'home' => [
                'label'  => 'Landing page',
                'groups' => [
                    'Hero' => [
                        'hero.title'        => ['kind' => 'text', 'label' => 'Main title'],
                        'hero.text'         => ['kind' => 'text', 'label' => 'Tagline'],
                        'hero.button_label' => ['kind' => 'text', 'label' => 'Button label'],
                        'hero.button_url'   => ['kind' => 'text', 'label' => 'Button link', 'hint' => 'e.g. submission.php'],
                        'hero.slides'       => ['kind' => 'list', 'label' => 'Slides', 'hint' => 'Each item: bg (path inside /assets), video (optional YouTube URL)'],
                    ],
                    'Key information' => [
                        'info.cards' => ['kind' => 'list', 'label' => 'Info cards', 'hint' => 'Each item: value, label, theme (text-theme or text-theme2)'],
                    ],
                    'About' => [
                        'about.title'          => ['kind' => 'text', 'label' => 'Section title'],
                        'about.body'           => ['kind' => 'html', 'label' => 'Body', 'hint' => 'Simple HTML allowed, e.g. <br> between paragraphs'],
                        'about.image'          => ['kind' => 'text', 'label' => 'Image', 'hint' => 'Path inside /assets, e.g. image/banner-cenim-2026.png'],
                        'about.edition_number' => ['kind' => 'text', 'label' => 'Edition number badge'],
                        'about.poster_label'   => ['kind' => 'text', 'label' => 'Poster button label', 'hint' => 'The poster image is uploaded in Website → Poster & Gallery'],
                        'about.gallery_label'  => ['kind' => 'text', 'label' => 'Gallery button label', 'hint' => 'Photos are managed in Website → Poster & Gallery'],
                        'about.gallery_title'  => ['kind' => 'text', 'label' => 'Gallery window title'],
                    ],
                    'Highlights' => [
                        'highlight.title' => ['kind' => 'text', 'label' => 'Title'],
                        'highlight.body'  => ['kind' => 'html', 'label' => 'Body'],
                        'highlight.video' => ['kind' => 'text', 'label' => 'Video URL'],
                        'highlight.image' => ['kind' => 'text', 'label' => 'Background image', 'hint' => 'Path inside /assets'],
                    ],
                    'Topics' => [
                        'topics.title'  => ['kind' => 'text', 'label' => 'Section title'],
                        'topics.groups' => ['kind' => 'list', 'label' => 'Topic groups', 'hint' => 'Each item: title, items (array of topic names)'],
                    ],
                    'Section titles' => [
                        'schedule.title'  => ['kind' => 'text', 'label' => 'Schedule title'],
                        'keynote.title'   => ['kind' => 'text', 'label' => 'Keynote title'],
                        'committee.title' => ['kind' => 'text', 'label' => 'Committee title'],
                    ],
                    'Venue' => [
                        'venue.title'     => ['kind' => 'text', 'label' => 'Section title'],
                        'venue.name'      => ['kind' => 'text', 'label' => 'Venue name'],
                        'venue.address'   => ['kind' => 'text', 'label' => 'Address'],
                        'venue.map_embed' => ['kind' => 'text', 'label' => 'Google Maps embed URL'],
                    ],
                ],
            ],

            'registration' => [
                'label'  => 'Registration page',
                'groups' => [
                    'Fee' => [
                        'fee.title' => ['kind' => 'text', 'label' => 'Fee section title', 'hint' => 'The fee table itself comes from Fees & Packages'],
                        'fee.notes' => ['kind' => 'list', 'label' => 'Notes below the table', 'hint' => 'Each item: text (simple HTML allowed)'],
                    ],
                    'Procedure' => [
                        'procedure.title' => ['kind' => 'text', 'label' => 'Title'],
                        'procedure.body'  => ['kind' => 'html', 'label' => 'Body'],
                        'bank.title'      => ['kind' => 'text', 'label' => 'Payment account title', 'hint' => 'Account numbers come from Settings'],
                    ],
                ],
            ],

            'submission' => [
                'label'  => 'Submission page',
                'groups' => [
                    'Content' => [
                        'page.body' => ['kind' => 'html', 'label' => 'Page content', 'hint' => 'Full HTML of the page body'],
                    ],
                ],
            ],

            'workshop' => [
                'label'  => 'Workshop page',
                'groups' => [
                    'Content' => [
                        'page.body' => ['kind' => 'html', 'label' => 'Page content', 'hint' => 'Full HTML of the page body'],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string,array{kind:string,label:string,hint?:string}> key => definisi */
    public static function blocks(string $page): array
    {
        $blocks = [];

        foreach (self::pages()[$page]['groups'] ?? [] as $group) {
            foreach ($group as $key => $definition) {
                $blocks[$key] = $definition;
            }
        }

        return $blocks;
    }

    public static function isValidPage(string $page): bool
    {
        return array_key_exists($page, self::pages());
    }
}
