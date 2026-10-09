# Dashboard Operasional

Aplikasi Laravel untuk mengelola klien dan project, membuat proposal dan mockup website, membangun tema WordPress, serta menangani SEO dan Billing & Finance.

## Melanjutkan pekerjaan

Mulai dari `MANUAL_BOOK.md` di folder manual eksternal. Manual itu memuat panduan project dan menjadi satu-satunya tempat mencatat perubahan, pembuatan, pemeriksaan, keputusan, dan pekerjaan tertunda. Sebelum mengubah kode, baca aturan agent yang disediakan pemilik dan periksa `git status --short` agar perubahan lokal tetap aman.

Manual teknis dan arsip dokumentasi lama berada di luar repo pada `C:\Program Codding\dashboard-operasional-manual\`. Folder itu sengaja tidak dilacak Git. Jika belum tersedia di mesin, minta salinannya kepada pemilik project.

## Menjalankan secara lokal

Lihat `composer.json` dan `.env.example` untuk dependensi dan konfigurasi. Setelah mengatur `.env` dan database development:

```powershell
composer install
npm install
php artisan migrate
composer dev
```

Jalankan pemeriksaan sesuai perubahan yang dibuat dan catat perintah beserta hasil sebenarnya di bagian riwayat kerja dalam manual.
