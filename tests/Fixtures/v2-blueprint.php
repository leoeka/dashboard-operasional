<?php

/**
 * A full V2 blueprint — every section shape the content stage can write — used
 * by the renderer, preview and WordPress parity tests. Returned as a fresh
 * array each time so a test can mutate its copy freely.
 */
return [
    'website_concept' => 'Operator tur petualangan Nusantara untuk pelancong urban.',
    'global_cta' => 'Pesan Tur',
    'language' => 'id',
    'seo' => ['meta_description' => 'Tur petualangan Nusantara'],
    'design' => [
        'renderer_version' => 2,
        'style' => 'Editorial, fotografis, lapang',
        'visual_direction' => 'editorial travel',
        'primary_color' => '#12343B',
        'secondary_color' => '#F4F1EA',
        'accent_color' => '#E07A3F',
        'font_heading' => 'Playfair Display',
        'font_body' => 'Inter',
        'container' => 'wide',
        'section_spacing' => 'generous',
        'radius' => 'small',
        'shadow' => 'none',
    ],
    'pages' => [
        [
            'name' => 'Home',
            'sections' => [
                ['type' => 'hero', 'name' => 'Hero', 'headline' => 'Jelajahi Nusantara Tanpa Ribet', 'description' => 'Tur kecil, pemandu lokal, rute yang tidak ada di brosur.', 'cta' => 'Lihat Tur', 'composition' => 'background_image'],
                ['type' => 'features', 'name' => 'Kenapa Kami', 'headline' => 'Perjalanan yang dirancang, bukan dijual', 'items' => [
                    ['title' => 'Grup kecil', 'description' => 'Maksimal 10 orang per tur.'],
                    ['title' => 'Pemandu lokal', 'description' => 'Dipandu warga setempat.'],
                    ['title' => 'Harga jujur', 'description' => 'Tanpa biaya tersembunyi.'],
                    ['title' => 'Fleksibel', 'description' => 'Reschedule gratis H-7.'],
                ], 'composition' => 'feature_grid'],
                ['type' => 'services', 'name' => 'Destinasi', 'headline' => 'Destinasi Unggulan', 'items' => [
                    ['title' => 'Labuan Bajo', 'description' => '3 hari berlayar.', 'price' => 'Rp 6,5 jt'],
                    ['title' => 'Raja Ampat', 'description' => 'Snorkeling & pulau.', 'price' => 'Rp 12 jt'],
                    ['title' => 'Bromo', 'description' => 'Sunrise & kawah.', 'price' => 'Rp 2,4 jt'],
                ], 'composition' => 'asymmetric_cards'],
                ['type' => 'about', 'name' => 'Cerita Kami', 'headline' => 'Dimulai dari satu perahu kayu', 'description' => 'Sejak 2014 kami membawa tamu ke tempat yang kami cintai, dengan cara yang menghormati warga setempat.', 'composition' => 'editorial_text_image'],
                ['type' => 'stats', 'name' => 'Angka', 'headline' => 'Dalam angka', 'items' => [
                    ['value' => '12.000+', 'label' => 'tamu'],
                    ['value' => '48', 'label' => 'rute'],
                    ['value' => '4,9', 'label' => 'rating'],
                ]],
                ['type' => 'testimonial', 'name' => 'Testimoni', 'headline' => 'Kata para tamu', 'items' => [
                    ['quote' => 'Tur terbaik yang pernah saya ikuti.', 'author' => 'Rina', 'role' => 'Jakarta'],
                    ['quote' => 'Pemandunya luar biasa.', 'author' => 'Budi', 'role' => 'Bandung'],
                ]],
                ['type' => 'gallery', 'name' => 'Galeri', 'headline' => 'Galeri perjalanan', 'items' => [
                    ['title' => 'Padar'], ['title' => 'Wayag'], ['title' => 'Penanjakan'], ['title' => 'Kelimutu'],
                ]],
                ['type' => 'faq', 'name' => 'FAQ', 'headline' => 'Pertanyaan umum', 'items' => [
                    ['question' => 'Apakah termasuk tiket pesawat?', 'answer' => 'Tidak, hanya tur darat dan laut.'],
                    ['question' => 'Bisa private tour?', 'answer' => 'Bisa, minimal 4 orang.'],
                ]],
                ['type' => 'cta', 'name' => 'Ajakan', 'headline' => 'Siap berangkat bulan ini?', 'description' => 'Kursi terbatas setiap keberangkatan.', 'cta' => 'Pesan Sekarang'],
                ['type' => 'footer', 'name' => 'Footer', 'headline' => 'Footer'],
            ],
        ],
        ['name' => 'Tentang', 'sections' => [
            ['type' => 'hero', 'name' => 'Hero', 'headline' => 'Tentang Kami', 'description' => 'Tim kecil pencinta laut dan gunung.'],
            ['type' => 'team', 'name' => 'Tim', 'headline' => 'Tim kami', 'items' => [
                ['name' => 'Andi', 'role' => 'Founder'], ['name' => 'Sari', 'role' => 'Operasional'],
            ]],
        ]],
        ['name' => 'Paket', 'sections' => [
            ['type' => 'hero', 'name' => 'Hero', 'headline' => 'Paket Tur'],
            ['type' => 'pricing', 'name' => 'Harga', 'headline' => 'Pilih paket', 'items' => [
                ['title' => 'Hemat', 'price' => 'Rp 2 jt', 'features' => ['Transport', 'Pemandu']],
                ['title' => 'Favorit', 'price' => 'Rp 4 jt', 'features' => ['Transport', 'Pemandu', 'Hotel'], 'featured' => true],
                ['title' => 'Premium', 'price' => 'Rp 8 jt', 'features' => ['Semua', 'Private']],
            ]],
            ['type' => 'partners', 'name' => 'Partner', 'headline' => 'Mitra kami', 'items' => [['title' => 'Garuda'], ['title' => 'Traveloka'], ['title' => 'Kemenparekraf']]],
        ]],
        ['name' => 'Home', 'sections' => [['type' => 'hero', 'headline' => 'Duplikat nama Home']]],
    ],
];
