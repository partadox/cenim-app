<?php

declare(strict_types=1);

namespace Cenim\Domain;

/**
 * Nilai BAWAAN setiap blok konten halaman publik.
 *
 * Dulu nilai ini ditulis langsung di dalam template (argumen kedua cms_text()),
 * sehingga editor di panel tampil kosong dan admin tidak bisa melihat teks yang
 * sedang aktif. Sekarang nilainya tinggal di satu tempat: template hanya
 * memanggil key-nya, editor mengisi kolomnya dengan nilai yang berlaku, dan
 * database hanya menyimpan blok yang benar-benar diubah admin.
 *
 * Isi awalnya = teks landing statis CENIM 2026, jadi halaman tetap tampil sama
 * seperti sebelum ada CMS ketika belum ada yang diedit.
 *
 * Token berikut diganti nilai hidup saat ditampilkan (lihat ContentRepo::render),
 * dan tetap berfungsi walau admin sudah mengedit teksnya:
 *   {included_pages}      jumlah halaman yang tercakup biaya (dari Fees)
 *   {extra_paper_percent} persen biaya paper tambahan
 *   {edition_name}        nama edisi aktif, mis. "CENIM 2026"
 *   {edition_year}        tahun edisi aktif
 *   {conference_dates}    tanggal konferensi edisi aktif, mis. "24-25 Nov 2026" (Settings)
 */
final class ContentDefaults
{
    /** @return array<string,array<string,string|array<int,mixed>>> page => key => bawaan */
    public static function all(): array
    {
        return [
            'home' => [
                'hero.slides' => [
                    [
                        'bg' => 'image/header-3.jpg',
                        'video' => 'https://www.youtube.com/watch?v=HpHJO7uoZs4',
                    ],
                    [
                        'bg' => 'image/header-1.jpg',
                        'video' => 'https://www.youtube.com/watch?v=zzJcADNiNhg',
                    ],
                    [
                        'bg' => 'image/header-2.jpg',
                        'video' => 'https://www.youtube.com/watch?v=0wY6pN9bInE',
                    ],
                ],
                'hero.title' => 'International Conference on Computer Engineering, Network and Intelligent Multimedia (CENIM) {edition_year}',
                'hero.text' => 'AI-Driven Society: Resilient Networks, Human-Centric Technology, Smart Health Care and Sustainable Multimedia for a Global Future',
                'hero.button_label' => 'Submission',
                'hero.button_url' => 'submission.php',
                'info.cards' => [
                    [
                        'value' => 'Hybrid',
                        'label' => 'Conference Participants',
                        'theme' => 'text-theme',
                    ],
                    [
                        'value' => 'Banyuwangi',
                        'label' => 'Conference Venue',
                        'theme' => 'text-theme2',
                    ],
                    [
                        'value' => '{conference_dates}',
                        'label' => 'Conference Dates',
                        'theme' => 'text-theme',
                    ],
                ],
                'about.image' => 'image/banner-cenim-2026.png',
                'about.edition_number' => '7',
                'about.poster_label' => 'CFP Poster',
                'about.gallery_label' => 'Gallery',
                'about.gallery_title' => 'Highlights from Previous CENIM Conferences',
                'about.title' => 'AI-Driven Society: Resilient Networks, Human-Centric Technology, Smart Health Care and Sustainable Multimedia for a Global Future',
                'about.body' => 'The {edition_year} International Conference on Computer Engineering, Network and Intelligent Multimedia (CENIM) will be held in Banyuwangi, Indonesia. The conference is hosted by the Department of Computer Engineering and the Department of Biomedical Engineering of Institut Teknologi Sepuluh Nopember (ITS), in collaboration with Politeknik Negeri Banyuwangi as co-host. All sessions will be conducted in a hybrid mode, with offline participation highly encouraged. <br> <br> The goal of this conference is to facilitate researchers, practitioners and lecturers around the world to publish, explore and share their current research in Computer Engineering and related fields. <br> <br> The papers of {edition_name} will be indexed by IEEExplore with conference record number of -. IEEE reserves the right to exclude a paper from distribution after the conference, including the IEEE Xplore Digital Library. <br> <br> This conference will be sponsored by IEEE Indonesia Section and IEEE Industrial Chapter.',
                'highlight.image' => 'image/about-2.jpg',
                'highlight.title' => 'Relive the Excellence: CENIM Highlights',
                'highlight.video' => 'https://www.youtube.com/watch?v=b3lO1-KfK3E',
                'highlight.body' => 'Take a trip down memory lane and experience the vibrant energy of CENIM. From groundbreaking keynote sessions to the inspiring exchange of ideas among global researchers, last year’s conference was truly unforgettable. We’ve captured all the excitement, the networking, and the best moments in our official after-movie.',
                'topics.groups' => [
                    [
                        'title' => 'Computer Engineering',
                        'items' => [
                            'Computing Algorithms',
                            'Natural Language Processing',
                            'Machine Learning and Artificial Intelligence',
                            'Soft Computing',
                            'Web Intelligence and Big Data',
                            'Sustainable Computing',
                            'Game Technology and Game Engineering',
                            'Computer Vision, Graphics, and Visualization',
                        ],
                    ],
                    [
                        'title' => 'Biomedical Engineering',
                        'items' => [
                            'Biomedical Instrumentation',
                            'Biomedical Sensors and Transducers',
                            'Biomedical Informatics',
                            'Biomedical Imaging and Image Processing',
                            'Rehabilitation Engineering',
                            'Assistive Technology',
                            'Biomedical Signal Processing',
                            'Decision Support System for Medical Diagnosis',
                            'Telemedicine and e-health',
                            'Healthcare Technologies',
                            'Medical Physics',
                        ],
                    ],
                    [
                        'title' => 'Telecommunication System and Engineering',
                        'items' => [
                            'Computer and Communication Networks',
                            'Electromagnetic and Radio Propagation',
                            'Information Security',
                            'Wireless Sensor Networks',
                            'Mobile Communication',
                            'Digital Forensics',
                            'Remote Sensing and GIS',
                            'Multimedia Information Processing',
                            'Information Systems and Management',
                            'ICT in Smart City',
                        ],
                    ],
                    [
                        'title' => 'Electronics and Automation',
                        'items' => [
                            'Electronic Circuit and System',
                            'Microcontroller and Embedded Systems',
                            'IoT and Ubiquitous Computing',
                            'Mechatronics, Robotics and Automation',
                            'Smart Devices',
                            'Edge Computing',
                        ],
                    ],
                    [
                        'title' => 'Energy and Power System',
                        'items' => [
                            'Renewable Energy',
                            'Artificial Intelligence in Power Systems',
                            'Smart Grid',
                        ],
                    ],
                ],
                'topics.title' => 'Conference Topics',
                'schedule.title' => 'Schedules',
                'keynote.title' => 'Keynote Speaker',
                'committee.title' => 'Conference Organizer',
                'venue.title' => 'Conference Location',
                'venue.name' => 'El Royale Hotel Banyuwangi',
                'venue.address' => 'Jl. Raya Jember No.KM.7, Krajan, Dadapan, Kec. Kabat, Kabupaten Banyuwangi, Jawa Timur 68461',
                'venue.map_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3948.4347022724223!2d114.33442977521077!3d-8.25945518298913!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dd15a0c1eec47d7%3A0xfb1ea4e1456dbee0!2seL%20Hotel%20Banyuwangi!5e0!3m2!1sen!2sid!4v1772779815061!5m2!1sen!2sid',
            ],
            'registration' => [
                'fee.title' => 'Registration Fee',
                'fee.notes' => [
                    [
                        'text' => 'The registration fee applies to the first author of the accepted paper. An additional paper by the same first author can be included for {extra_paper_percent}% of the registration fee. Student ID and/or IEEE Membership ID should be uploaded when you register.',
                    ],
                    [
                        'text' => 'Acceptance for presentation does not guarantee inclusion in the IEEE Xplore proceedings. Papers that do not meet IEEE requirements or fail to address required revisions may not be included. Papers that are not presented at the conference (no-show) will not be included in the proceedings.',
                    ],
                    [
                        'text' => '<b>No refund will be provided in such cases.</b>',
                    ],
                    [
                        'text' => 'Payments must be made in full, with any bank charges or transfer fees borne by the registrant.',
                    ],
                ],
                'procedure.title' => 'Registration Procedure',
                'procedure.body' => '<p>Detail announce later</p>',
                'bank.title' => 'Payment Account',
            ],
            'submission' => [
                'page.body' => '<div class="row gy-4">
            <div class="col-lg-12">
                <div class="portfolio-description" data-aos="fade-up" data-aos-delay="300">
                    <p style="color:#444444; margin-bottom:20px; margin-top:50px;">
                        Please submit your paper through the EDAS conference management system through the following link:
                    </p>
                    <a href="https://edas.info/newPaper.php?c=35128" 
                       id="btn-pdf-1"
                       class="btn btn-outline-primary" 
                       style="color:#174793; border-color:#174793; transition: background-color 0.3s ease, color 0.3s ease; margin-bottom: 10px;" 
                       onmouseover="this.style.backgroundColor=\'#174793\'; this.style.color=\'#ffffff\';" 
                       onmouseout="this.style.backgroundColor=\'transparent\'; this.style.color=\'#174793\';">
                        EDAS
                    </a>
                </div>
            </div>
        </div>

        <div class="section-header-container">
            <div class="section-header font-c" id="accessing-the-templates">
                <h2 style="font-weight: bold; font-size:30px; margin-bottom:20px; margin-top:50px;">
                    Accessing the templates
                </h2>
            </div>
            
            <div class="rte text parbase">
                <p><strong>Microsoft Word</strong></p>
                <ul>
                    <li>
                        <a href="https://www.ieee.org/content/dam/ieee-org/ieee/web/org/conferences/conference-template-a4.docx" data-layer-attached="true">A4</a> 
                        (DOC, 30 KB) <strong>Updated 2024</strong>
                    </li>
                </ul>
                
                <p>
                    <a href="http://www.ctan.org/tex-archive/macros/latex/contrib/IEEEtran/IEEEtran_HOWTO.pdf" class="ext" data-extlink="" target="_blank" rel="noopener nofollow noreferrer" title="(opens in a new window)" data-layer-attached="true">
                        <strong>LaTeX Template Instructions</strong>
                    </a>
                    (PDF, 63 KB)
                    <span style="color:rgb(0,0,0); font-family:Arial, Helvetica, sans-serif; font-size:14.0px; font-weight:400;">
                        <strong>[Be sure to use the template\'s conference mode.]</strong>
                    </span>
                </p>
                <ul>
                    <li>
                        <a href="https://www.ieee.org/content/dam/ieee-org/ieee/web/org/conferences/conference-latex-template.zip" target="_blank" data-layer-attached="true">Template</a> 
                        (ZIP, 700 KB) <strong>Updated 2024</strong>
                    </li>
                    <li>
                        <a href="https://www.ieee.org/content/dam/ieee-org/ieee/web/org/conferences/IEEEtranBST2.zip" data-layer-attached="true">LaTeX Bibliography Files</a> 
                        (ZIP, 309 KB)
                    </li>
                </ul>
                
                <p><strong>Overleaf</strong></p>
                <ul>
                    <li>
                        When working in Overleaf, the template is available at 
                        <a href="https://www.overleaf.com/gallery/tagged/ieee-official" class="ext" data-extlink="" target="_blank" rel="noopener nofollow noreferrer" title="(opens in a new window)" data-layer-attached="true">
                            https://www.overleaf.com/gallery/tagged/ieee-official
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <p style="color:#444444; margin-bottom:20px; margin-top:30px;">
            Papers should follow the two-column IEEE conference style, between 4 to 6 pages. However, if the paper exceeds the 6-page limit, there will be an additional fee for each page beyond the 6-page maximum (but no longer than 8 pages). Please consult the following link for manuscript templates:
        </p>
        <a href="https://www.ieee.org/conferences/publishing/templates.html" 
           id="btn-pdf-2"
           class="btn btn-outline-primary" 
           style="color:#174793; border-color:#174793; transition: background-color 0.3s ease, color 0.3s ease; margin-bottom: 10px;" 
           onmouseover="this.style.backgroundColor=\'#174793\'; this.style.color=\'#ffffff\';" 
           onmouseout="this.style.backgroundColor=\'transparent\'; this.style.color=\'#174793\';">
            IEEE Templates
        </a>',
            ],
            'workshop' => [
                'page.body' => '<div class="team-details-wrap mb-80">
                <div class="row gx-60 gy-40">
                    <div class="col-xl-5">
                        <div class="about-card-img">
                            <img src="assets/image/banner-cenim-2026.png" alt="team image">
                        </div>
                    </div>
                    <div class="col-xl-7">
                        <div class="about-card">
                            <div class="about-card-title-wrap">
                                <div class="media-left">
                                    <h2 class="h3 about-card_title mt-n2">To be announced later</h2>
                                    <p class="about-card_desig">-</p>
                                </div>
                            </div>

                            <p class="about-card_text">To be announced later...</p>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="row gy-40">
                <div class="col-xl-6">
                    <h3 class="title mt-n2 mb-25">To be announced later</h3>
                    <p>-</p>
                    <p class="mb-n1 mt-25">-</p>
                </div>
            </div>',
            ],
        ];
    }

    /** @return array<string,string|array<int,mixed>> semua bawaan satu halaman */
    public static function page(string $page): array
    {
        return self::all()[$page] ?? [];
    }

    /** Bawaan satu blok; string untuk text/html, array untuk list. */
    public static function get(string $page, string $key): string|array
    {
        return self::page($page)[$key] ?? '';
    }

    public static function has(string $page, string $key): bool
    {
        return array_key_exists($key, self::page($page));
    }
}
