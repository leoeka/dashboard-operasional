<?php

/*
|--------------------------------------------------------------------------
| Bobot skor laporan SEO / SXO / GEO
|--------------------------------------------------------------------------
|
| Tiap lapis diberi skor 0-100 = rata-rata tertimbang dari sub-sinyal yang
| DATANYA TERSEDIA. Sub-sinyal yang belum dianalisis otomatis dikeluarkan
| dari perhitungan (tidak dihitung 0), dan ditandai "belum dianalisis" di
| laporan. Ubah angka di sini untuk menyetel penekanan tiap sinyal.
|
*/

return [
    'weights' => [
        'seo' => [
            'lighthouse_seo' => 0.5,   // skor kategori SEO dari Lighthouse (PageSpeed)
            'structured_data' => 0.5,  // cakupan schema.org di halaman-halaman kunci
        ],
        'sxo' => [
            'core_web_vitals' => 0.35, // LCP / CLS / INP
            'accessibility' => 0.15,   // skor aksesibilitas Lighthouse
            'ctr' => 0.20,             // banyaknya query halaman-1 dengan CTR di bawah normal
            'engagement' => 0.30,      // engagement rate rata-rata landing page organik (GA4)
        ],
        'geo' => [
            'ai_crawler_access' => 0.5, // crawler "penjawab" (OAI-SearchBot, Perplexity, Google-Extended, ...) diizinkan?
            'structured_data' => 0.35,  // ada schema yang bisa "dibaca" mesin AI?
            'llms_txt' => 0.15,         // ada /llms.txt?
        ],
    ],

    // Ambang label status untuk nilai akhir tiap lapis.
    'thresholds' => [
        'good' => 80,
        'needs_improvement' => 50,
    ],
];
