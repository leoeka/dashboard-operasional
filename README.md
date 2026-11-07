# Dashboard Operasional

Aplikasi operasional berbasis Laravel untuk mengelola proyek, analisis SEO, mockup website, proposal, dan build tema WordPress.

## Kebutuhan

- PHP 8.2 atau lebih baru dan ekstensi yang diminta Composer.
- MySQL/MariaDB untuk data aplikasi dan queue.
- Node.js 22.12 atau lebih baru dan Chromium untuk fitur potret mockup.
- Worker queue database aktif; PHP CLI dengan `proc_open` untuk menjalankan Node.js/Chromium saat membuat preview mockup.
- `composer` dan `npm` untuk memasang dependency dan membangun aset.

## Pengembangan lokal

```powershell
composer install
if (!(Test-Path .env)) {
    Copy-Item .env.example .env
    php artisan key:generate
}
npm ci
npm run build
php artisan migrate
php artisan test
```

Isi konfigurasi database dan layanan eksternal di `.env` lokal. Jangan commit `.env` atau membagikan API key.

## Instalasi cPanel

Ikuti [Panduan instalasi cPanel](.docs/instalasi-cpanel.md). Panduan mencakup lokasi aman di luar `public_html`, konfigurasi database, queue worker, cron, Node.js/Chromium, serta pemeriksaan server melalui:

```sh
php artisan deployment:check-cpanel
```

Shared hosting belum tentu mendukung semua prasyarat fitur mockup. Pastikan hasil pemeriksaan di server target lulus sebelum mengandalkan pembuatan mockup, potret URL, atau build tema.
