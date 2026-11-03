<?php

/*
 * The public website in Malay (the default). The same keys, in English, are in lang/en/landing.php: change both together.
 * Facts only: no made-up numbers, names or logos. What is a placeholder is listed in config/landing.php (placeholder_blocks).
 */
return [
    'meta' => [
        'title' => 'MyKaunsel — Sokongan kaunseling yang disahkan',
        'description' => 'Cari kaunselor berdaftar dan tempah sesi dalam talian atau bersemuka. Untuk individu, organisasi dan kaunselor di Malaysia.',
        'soon_title' => 'Tidak lama lagi — MyKaunsel',
    ],

    'a11y' => ['skip' => 'Langkau ke kandungan', 'menu' => 'Menu', 'open_menu' => 'Buka menu', 'close_menu' => 'Tutup menu', 'main_nav' => 'Navigasi utama'],

    'nav' => [
        'platform' => 'Platform', 'solutions' => 'Penyelesaian', 'how_it_works' => 'Cara ia berfungsi', 'pricing' => 'Harga', 'resources' => 'Sumber',
        'login' => 'Log masuk', 'dashboard' => 'Papan pemuka', 'signup' => 'Daftar', 'signup_org' => 'Sebagai organisasi', 'signup_counselor' => 'Sebagai kaunselor',
        'waitlist' => 'Sertai senarai menunggu', 'get_app' => 'Dapatkan aplikasi', 'soon' => 'Tidak lama lagi', 'see_all' => 'Lihat semua', 'more' => 'Lagi',
    ],

    'menu' => [
        'platform' => [
            'columns' => [
                ['title' => 'Untuk individu', 'items' => [
                    ['find-a-counselor', 'Cari kaunselor', 'Semak profil kaunselor berdaftar'],
                    ['book-a-session', 'Tempah sesi', 'Pilih masa yang kosong dan sahkan'],
                    ['online-in-person', 'Dalam talian dan bersemuka', 'Pilih cara yang anda selesa'],
                    ['invite-code', 'Sertai dengan kod jemputan', 'Gunakan kod dari organisasi anda'],
                ]],
                ['title' => 'Untuk kaunselor', 'items' => [
                    ['shared-calendar', 'Kalendar dikongsi', 'Satu jadual untuk semua tempat kerja'],
                    ['booking-modes', 'Mod tempahan', 'Tempahan terus atau permintaan masa'],
                    ['meeting-links', 'Pautan mesyuarat', 'Sediakan pautan untuk sesi dalam talian'],
                    ['profile-credentials', 'Profil dan kelayakan', 'Tunjukkan bidang dan bahasa anda'],
                ]],
                ['title' => 'Untuk organisasi', 'items' => [
                    ['member-management', 'Urus ahli', 'Terima dan urus ahli organisasi'],
                    ['domain-verification', 'Pengesahan domain', 'Sahkan organisasi anda dengan DNS'],
                    ['counselor-roster', 'Senarai kaunselor', 'Jemput dan urus kaunselor anda'],
                    ['reports-analytics', 'Laporan dan analitik', 'Kiraan sahaja, tanpa nama'],
                ]],
                ['title' => 'Keselamatan', 'items' => [
                    ['counselor-verification', 'Pengesahan kaunselor', 'Semakan rekod dan semakan manusia'],
                    ['privacy-data', 'Privasi dan data', 'Apa yang kami simpan dan siapa nampak'],
                    ['session-policies', 'Dasar sesi', 'Peraturan tempahan dan pembatalan'],
                    ['ai-assistant', 'Pembantu AI', 'Teman bual, bukan kaunselor'],
                ]],
            ],
            'more' => [
                'items' => [['get-the-app', 'Dapatkan aplikasi', 'iOS dan Android'], ['wellness-feed', 'Suapan kesejahteraan', 'Kongsi dan sokong']],
                'card' => ['title' => 'Muat turun aplikasi', 'text' => 'Tempah sesi terus dari telefon anda.', 'cta' => 'Dapatkan aplikasi', 'slug' => 'get-the-app'],
            ],
            'see_all' => ['features', 'Lihat semua ciri'],
        ],
        'solutions' => [
            'columns' => [
                ['title' => 'Universiti', 'items' => [
                    ['universities', 'Kaunseling kampus', 'Sokongan untuk pelajar anda'],
                    ['student-codes', 'Kod jemputan pelajar', 'Pelajar menyertai dengan satu kod'],
                    ['alumni-access', 'Akses alumni', 'Kekalkan sokongan selepas bergraduat'],
                ]],
                ['title' => 'Korporat', 'items' => [
                    ['corporate', 'Kesejahteraan pekerja', 'Sokongan untuk pasukan anda'],
                    ['closed-access', 'Akses tertutup', 'Hanya pekerja anda boleh menempah'],
                    ['aggregate-reports', 'Laporan agregat', 'Kiraan sahaja, tanpa nama'],
                ]],
                ['title' => 'Klinik', 'items' => [
                    ['clinics', 'Penyenaraian terbuka', 'Klinik anda boleh ditemui orang ramai'],
                    ['clinic-counselors', 'Kaunselor klinik', 'Urus kaunselor di satu tempat'],
                    ['clinic-commission', 'Model komisen', 'Cara kos dikira untuk klinik'],
                ]],
                ['title' => 'Kaunselor bebas', 'items' => [
                    ['independent-counselors', 'Amalan di platform', 'Terima tempahan tanpa sistem sendiri'],
                    ['own-schedule', 'Jadual sendiri', 'Anda tetapkan masa yang dibuka'],
                    ['build-a-profile', 'Bina profil', 'Tunjukkan bidang dan bahasa anda'],
                ]],
            ],
            'more' => [
                'items' => [['how-it-works', 'Cara ia berfungsi', 'Tiga laluan, langkah demi langkah'], ['pricing', 'Harga', 'Pelan dan komisen']],
                'card' => ['title' => 'Bukan pasti mana sesuai?', 'text' => 'Beritahu kami tentang organisasi anda.', 'cta' => 'Hubungi kami', 'slug' => 'contact'],
            ],
            'see_all' => ['solutions', 'Lihat semua penyelesaian'],
        ],
        'how' => [
            'columns' => [
                ['title' => 'Individu', 'items' => [
                    ['how-choose', 'Pilih', 'Cari kaunselor yang sesuai'],
                    ['how-book', 'Tempah', 'Pilih masa dan sahkan'],
                    ['how-meet', 'Berjumpa', 'Dalam talian atau bersemuka'],
                ]],
                ['title' => 'Organisasi', 'items' => [
                    ['how-apply-org', 'Mohon', 'Daftar organisasi anda'],
                    ['how-verify-domain', 'Sahkan domain', 'Buktikan anda memiliki domain'],
                    ['how-add-counselors', 'Tambah kaunselor', 'Jemput pasukan anda'],
                ]],
                ['title' => 'Kaunselor', 'items' => [
                    ['how-apply-counselor', 'Mohon', 'Hantar maklumat dan lesen amalan'],
                    ['how-get-verified', 'Disahkan', 'Semakan rekod dan semakan manusia'],
                    ['how-open-slots', 'Buka masa', 'Tetapkan masa untuk ditempah'],
                ]],
            ],
            'more' => null,
            'see_all' => ['how-it-works', 'Lihat cara ia berfungsi'],
        ],
        'pricing' => [
            'columns' => [
                ['title' => 'Pelan', 'items' => [
                    ['plan-universities', 'Universiti', 'Pelan tahunan mengikut saiz'],
                    ['plan-corporate', 'Korporat', 'Pelan tahunan mengikut saiz'],
                    ['compare-plans', 'Bandingkan pelan', 'Jadual perbandingan'],
                ]],
                ['title' => 'Komisen', 'items' => [
                    ['commission-clinics', 'Klinik', 'Komisen bagi setiap sesi'],
                    ['commission-independent', 'Kaunselor bebas', 'Komisen bagi setiap sesi'],
                    ['how-commission-works', 'Cara komisen dikira', 'Penerangan ringkas'],
                ]],
                ['title' => 'Untuk individu', 'items' => [
                    ['pricing-individuals', 'Tiada yuran platform', 'Individu tidak membayar yuran platform'],
                ]],
            ],
            'more' => [
                'items' => [['promo-codes', 'Kod promosi', 'Cara menggunakan kod'], ['request-a-quote', 'Minta sebut harga', 'Untuk organisasi besar'], ['pricing-faq', 'Soalan lazim harga', 'Soalan tentang bayaran']],
                'card' => ['title' => 'Bukan pasti pelan mana?', 'text' => 'Bercakap dengan kami.', 'cta' => 'Hubungi kami', 'slug' => 'contact'],
            ],
            'see_all' => ['pricing', 'Lihat semua harga'],
        ],
        'resources' => [
            'columns' => [
                ['title' => 'Belajar', 'items' => [
                    ['faq-help', 'Soalan lazim dan bantuan', 'Jawapan kepada soalan biasa'],
                    ['mental-health-articles', 'Artikel kesihatan mental', 'Bacaan ringkas'],
                    ['glossary', 'Glosari', 'Istilah dalam bahasa mudah'],
                ]],
                ['title' => 'Sokongan', 'items' => [
                    ['contact', 'Hubungi kami', 'Kami sedia membantu'],
                    ['safety-crisis', 'Keselamatan dan sokongan krisis', 'Talian bantuan segera'],
                    ['status', 'Status', 'Keadaan sistem'],
                ]],
                ['title' => 'Syarikat', 'items' => [
                    ['about', 'Tentang kami', 'Misi dan projek'],
                    ['terms', 'Terma Perkhidmatan', 'Peraturan menggunakan MyKaunsel'],
                    ['privacy', 'Privasi dan PDPA', 'Cara data anda dijaga'],
                ]],
            ],
            'more' => null,
            'see_all' => ['faq-help', 'Lihat pusat bantuan'],
        ],
    ],

    'hero' => [
        'strong' => 'Sokongan kaunseling yang disahkan.',
        'soft' => 'Cari kaunselor berdaftar dan tempah sesi dalam talian atau bersemuka, terus dari telefon anda.',
        'app_store' => 'App Store', 'google_play' => 'Google Play', 'badge_soon' => 'Akan datang',
        'media_alt' => 'Paparan aplikasi MyKaunsel',
    ],

    'paths' => [
        'individual' => ['label' => 'Individu', 'title' => 'Saya mencari sokongan', 'text' => 'Cari kaunselor berdaftar dan tempah sesi dalam aplikasi.', 'cta' => 'Dapatkan aplikasi'],
        'organization' => ['label' => 'Organisasi', 'title' => 'Kami sebuah organisasi', 'text' => 'Urus kaunselor, ahli dan tempahan di satu portal.', 'cta' => 'Daftar organisasi', 'cta_closed' => 'Sertai senarai menunggu'],
        'counselor' => ['label' => 'Kaunselor', 'title' => 'Saya seorang kaunselor', 'text' => 'Terima tempahan tanpa perlu membina sistem sendiri.', 'cta' => 'Daftar sebagai kaunselor', 'cta_closed' => 'Sertai senarai menunggu'],
    ],

    'proof' => [
        'strong' => 'Dibina atas kepercayaan.',
        'soft' => 'Setiap bahagian MyKaunsel direka untuk menjaga keselamatan dan privasi anda.',
        'assurances' => [
            ['title' => 'Kelayakan disemak', 'text' => 'Kaunselor disemak berdasarkan rekod pendaftaran dan semakan manusia sebelum profil dipaparkan.'],
            ['title' => 'Tempah dengan mudah', 'text' => 'Pilih masa yang kosong dan tempah dalam beberapa ketukan.'],
            ['title' => 'Sesi tidak dirakam', 'text' => 'MyKaunsel tidak merakam sesi anda dengan kaunselor.'],
        ],
        'logos_label' => 'Dipercayai oleh', 'logo' => 'Logo',
        'quotes' => [
            ['text' => 'Kami dapat menawarkan sokongan kaunseling kepada pelajar dengan lebih teratur.', 'name' => 'Nama Contoh', 'role' => 'Pegawai kaunseling, universiti'],
            ['text' => 'Saya tidak perlu lagi menguruskan jadual dan tempahan secara manual.', 'name' => 'Nama Contoh', 'role' => 'Kaunselor berdaftar'],
        ],
        'figures' => [['00+', 'kaunselor disahkan'], ['00+', 'organisasi'], ['00', 'sesi bulan ini']],
        'placeholder_note' => 'Kandungan contoh',
    ],

    'problem' => [
        'strong' => 'Mencari sokongan tidak sepatutnya sukar.',
        'soft' => 'Terlalu ramai orang berhenti sebelum sempat bermula.',
        'cards' => [
            ['title' => 'Sukar tahu siapa yang layak', 'text' => 'Tidak mudah memastikan seseorang kaunselor berdaftar dan berkelayakan.'],
            ['title' => 'Sukar mencari dan menempah', 'text' => 'Mencari masa yang sesuai selalunya memerlukan banyak langkah.'],
            ['title' => 'Bimbang tentang privasi', 'text' => 'Kebimbangan tentang siapa yang akan tahu sering menghentikan langkah pertama.'],
        ],
        'learn_more' => 'Ketahui lebih lanjut',
    ],

    'solution' => [
        'strong' => 'Satu platform yang anda boleh percayai.',
        'soft' => 'Kaunselor disahkan, tempahan yang mudah, dan privasi yang dijaga.',
        'pillars' => [
            ['slug' => 'counselor-verification', 'media' => 'solution-verified', 'title' => 'Kaunselor disahkan.', 'text' => 'Setiap kaunselor disemak terhadap rekod pendaftaran dan disemak oleh manusia.', 'cta' => 'Ketahui tentang pengesahan'],
            ['slug' => 'book-a-session', 'media' => 'solution-booking', 'title' => 'Tempahan yang mudah.', 'text' => 'Tapis mengikut bidang, bahasa dan lokasi, kemudian pilih masa yang kosong.', 'cta' => 'Lihat cara menempah'],
            ['slug' => 'privacy-data', 'media' => 'solution-private', 'title' => 'Peribadi dan selamat.', 'text' => 'Anda mengawal apa yang dikongsi. Organisasi tidak membaca perbualan anda.', 'cta' => 'Baca tentang privasi'],
        ],
    ],

    'how' => [
        'strong' => 'Tiga langkah untuk bermula.',
        'soft' => 'Dari mencari kaunselor hingga berjumpa, semuanya dalam satu aplikasi.',
        'steps' => [
            ['title' => 'Pilih', 'text' => 'Tapis kaunselor mengikut bidang, bahasa dan lokasi.'],
            ['title' => 'Tempah', 'text' => 'Pilih masa yang kosong dan sahkan tempahan anda.'],
            ['title' => 'Berjumpa', 'text' => 'Sertai sesi dalam talian atau hadir secara bersemuka.'],
        ],
        'cta' => 'Lihat cara ia berfungsi',
    ],

    'audiences' => [
        'org' => [
            'label' => 'Untuk organisasi', 'strong' => 'Satu portal untuk seluruh unit kaunseling.', 'soft' => 'Universiti, syarikat dan klinik.',
            'benefits' => ['Jemput dan urus kaunselor anda', 'Terima ahli dengan kod jemputan', 'Lihat laporan kiraan tanpa nama', 'Sahkan organisasi dengan DNS'],
            'cta' => 'Daftar organisasi', 'cta_closed' => 'Sertai senarai menunggu', 'learn' => 'Ketahui lebih lanjut',
        ],
        'counselor' => [
            'label' => 'Untuk kaunselor', 'strong' => 'Amalan anda, jadual anda.', 'soft' => 'Terima tempahan tanpa sistem sendiri.',
            'benefits' => ['Satu kalendar untuk semua tempat kerja', 'Tempahan terus atau permintaan masa', 'Pautan mesyuarat untuk sesi dalam talian', 'Profil dengan bidang dan bahasa anda'],
            'cta' => 'Daftar sebagai kaunselor', 'cta_closed' => 'Sertai senarai menunggu', 'learn' => 'Ketahui lebih lanjut',
        ],
    ],

    'app' => [
        'strong' => 'Bawa MyKaunsel bersama anda.',
        'soft' => 'Cari, tempah dan berjumpa kaunselor terus dari telefon anda.',
        'scan' => 'Imbas untuk memuat turun', 'qr_label' => 'Kod QR', 'phone_alt' => 'Skrin aplikasi MyKaunsel',
    ],

    'faq' => [
        'title' => 'Soalan lazim', 'cta' => 'Lihat semua soalan',
        'items' => [
            ['q' => 'Adakah MyKaunsel pengganti bantuan profesional?', 'a' => 'Tidak. MyKaunsel membantu anda menghubungi kaunselor berdaftar. Ia bukan pengganti penjagaan kecemasan atau profesional. Jika anda dalam bahaya, hubungi talian bantuan di bahagian bawah halaman ini.'],
            ['q' => 'Siapa yang boleh melihat perbualan saya?', 'a' => 'Perbualan anda dengan pembantu AI adalah peribadi dan hanya disimpan jika anda bersetuju. Ia tidak dikongsi dengan kaunselor atau organisasi anda.'],
            ['q' => 'Bagaimana kaunselor disahkan?', 'a' => 'Kaunselor disemak berdasarkan rekod pendaftaran dan dokumen mereka, kemudian disemak oleh pasukan kami sebelum profil dipaparkan.'],
            ['q' => 'Siapa boleh menempah kaunselor yang mana?', 'a' => 'Kaunselor bebas dan klinik terbuka boleh ditempah oleh sesiapa. Kaunselor organisasi tertutup, seperti universiti atau syarikat, hanya boleh ditempah oleh ahli organisasi itu.'],
            ['q' => 'Adakah pembantu AI seorang kaunselor?', 'a' => 'Tidak. Ia teman bual yang membantu anda menyusun perasaan dan mencari kaunselor yang sesuai. Ia tidak memberi diagnosis atau rawatan.'],
        ],
    ],

    'cta' => ['strong' => 'Mulakan hari ini.', 'soft' => 'Pilih laluan yang sesuai dengan anda.'],

    'urgent' => [
        'label' => 'Perlukan bantuan segera?',
        'text' => 'MyKaunsel bukan pengganti penjagaan kecemasan atau profesional. Jika anda atau seseorang dalam bahaya, hubungi:',
    ],

    'footer' => [
        'description' => 'Sokongan kaunseling yang disahkan untuk Malaysia.',
        'columns' => [
            ['title' => 'Platform', 'items' => [['for-individuals', 'Untuk individu'], ['for-counselors', 'Untuk kaunselor'], ['for-organizations', 'Untuk organisasi'], ['features', 'Ciri-ciri'], ['find-a-counselor', 'Cari kaunselor'], ['get-the-app', 'Dapatkan aplikasi'], ['wellness-feed', 'Suapan kesejahteraan']]],
            ['title' => 'Penyelesaian', 'items' => [['universities', 'Universiti'], ['corporate', 'Korporat'], ['clinics', 'Klinik'], ['independent-counselors', 'Kaunselor bebas']]],
            ['title' => 'Harga', 'items' => [['pricing', 'Pelan'], ['commission', 'Komisen'], ['promo-codes', 'Kod promosi'], ['pricing-faq', 'Soalan lazim harga']]],
            ['title' => 'Sumber', 'items' => [['how-it-works', 'Cara ia berfungsi'], ['faq-help', 'Soalan lazim dan bantuan'], ['mental-health-articles', 'Artikel kesihatan mental'], ['glossary', 'Glosari'], ['safety-crisis', 'Keselamatan dan sokongan krisis'], ['status', 'Status']]],
            ['title' => 'Syarikat', 'items' => [['about', 'Tentang kami'], ['contact', 'Hubungi kami'], ['join-waitlist', 'Sertai senarai menunggu']]],
            ['title' => 'Undang-undang', 'items' => [['terms', 'Terma Perkhidmatan'], ['privacy', 'Dasar Privasi'], ['pdpa-notice', 'Notis PDPA'], ['verification-policy', 'Dasar pengesahan kaunselor'], ['session-policy', 'Dasar sesi dan pembatalan']]],
        ],
        'copyright' => '© :year MyKaunsel. Hak cipta terpelihara.',
        'contact' => 'Hubungi', 'language' => 'Bahasa', 'sitemap' => 'Peta laman', 'ms' => 'Bahasa Melayu', 'en' => 'English',
    ],

    'soon' => [
        'strong' => 'Halaman ini akan datang tidak lama lagi.', 'soft' => 'Kami sedang menyediakannya.',
        'back' => 'Kembali ke laman utama', 'pages' => [],
    ],
];
