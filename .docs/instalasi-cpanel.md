# Panduan instalasi cPanel

Panduan ini untuk deploy Laravel pada cPanel dengan Terminal dan PHP CLI. Shared hosting tidak selalu memenuhi kebutuhan proses latar dan Chromium. Konfirmasi dukungan provider sebelum memindahkan aplikasi produksi.

## 1. Pastikan paket hosting memenuhi kebutuhan

Minta provider memastikan:

- PHP 8.2 atau lebih baru, Composer, dan ekstensi `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `xml`, serta `zip`.
- MySQL/MariaDB dan database yang dapat diakses dari aplikasi.
- Worker queue database yang aktif, dan PHP CLI dengan `proc_open` aktif untuk menjalankan Node.js/Chromium melalui Browsershot.
- Node.js 22.12 atau lebih baru, npm, paket Puppeteer, dan Chrome/Chromium headless yang dapat dijalankan oleh akun cPanel.
- Cron setiap menit dan worker queue yang boleh berjalan sampai job selesai. Beberapa job aplikasi dapat berjalan sampai 10 menit; job mockup OpenAI dapat berjalan lebih lama.
- Koneksi HTTPS keluar untuk layanan yang diaktifkan, terutama OpenAI dan Google APIs.
- Ruang disk, memori, batas upload, serta izin tulis yang cukup untuk dokumen, gambar, PDF, cache, dan berkas tema.

Pembuatan mockup dimasukkan ke antrean seperti job SEO, jadi request web tidak lagi menjalankan `proc_open`. Namun worker tetap membutuhkan `proc_open` untuk menjalankan Node.js/Chromium saat membuat preview. Jika worker queue tidak aktif, job menumpuk; jika Node.js atau Chromium tidak tersedia, pembuatan dan pratinjau mockup serta potret URL tidak berfungsi. Untuk batasan tersebut, gunakan VPS/worker terpisah atau minta provider memberi solusi yang didukung. Jangan membuka seluruh source code ke web sebagai jalan pintas.

## 2. Tempatkan aplikasi di luar `public_html`

Contoh struktur:

```text
/home/CPANEL_USER/apps/dashboard-operasional/     # seluruh repository Laravel
/home/CPANEL_USER/apps/dashboard-operasional/public/  # document root domain
```

Gunakan Git Version Control cPanel atau Terminal untuk menyalin repository ke direktori aplikasi. Di cPanel, atur document root domain/subdomain ke folder `public` tersebut. Hanya isi `public` yang boleh dapat diakses langsung dari web.

Jangan unggah seluruh repository ke `public_html`, dan jangan menyalin `index.php` ke root web tanpa menyesuaikan jalur bootstrap secara aman. Jika cPanel tidak mengizinkan document root mengarah ke folder `public`, tanyakan metode deploy Laravel resmi kepada provider sebelum lanjut.

## 3. Pasang dependency dan aset

Dari Terminal cPanel:

```sh
cd /home/CPANEL_USER/apps/dashboard-operasional
php -v
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Gunakan PHP CLI yang sama versinya dengan PHP domain. Jika perintah `php` menunjuk ke versi yang salah, pilih path PHP dari MultiPHP Manager/provider dan gunakan path itu untuk semua perintah Artisan dan cron.

`npm ci` memasang Puppeteer serta dependency build. Jika provider memasang Chromium sendiri, konfirmasi cara mematikan unduhan browser Puppeteer agar tidak menghabiskan kuota disk. Jangan hapus `node_modules/puppeteer` setelah build karena Browsershot memerlukannya saat runtime.

## 4. Siapkan `.env` dan database

Buat `.env` baru di root repository pada server, bukan di `public`:

```sh
if [ ! -f .env ]; then cp .env.example .env; fi
```

Isi nilai server yang sebenarnya:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://domain-anda`
- `APP_KEY` baru, dibuat sekali dengan `php artisan key:generate`
- `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` dari menu MySQL Databases cPanel. Nama database/user sering mendapat prefix akun.
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, dan `QUEUE_CONNECTION=database`
- `OPENAI_API_KEY` hanya bila fitur mockup OpenAI akan digunakan
- kredensial mail dan layanan eksternal lain hanya bila fitur terkait dipakai

Jangan unggah `.env` lokal, `.env copy.example`, API key, atau password ke GitHub. Jangan membuat ulang `APP_KEY` saat update karena dapat membatalkan sesi dan data terenkripsi.

Lanjutkan:

```sh
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:clear
php artisan config:cache
php artisan view:cache
```

Pastikan folder berikut dapat ditulis oleh akun cPanel: `storage/`, subfolder `storage/framework/`, `storage/logs/`, `storage/app/`, dan `bootstrap/cache/`. Gunakan permission pemilik/grup yang minimum dan sesuai provider; jangan gunakan `777`.

Jika `storage:link` ditolak, minta provider mengaktifkan symlink atau memberi cara aman untuk melayani file publik. Jangan memindahkan seluruh `storage` ke `public_html`.

## 5. Atur Node.js dan Chromium

Cari path executable dari Terminal cPanel, lalu isi jika tidak tersedia melalui `PATH` proses web/cron:

```dotenv
BROWSERSHOT_NODE_BINARY=/path/absolut/node
BROWSERSHOT_NODE_MODULE_PATH=/home/CPANEL_USER/apps/dashboard-operasional/node_modules
BROWSERSHOT_CHROME_PATH=/path/absolut/chromium
```

`BROWSERSHOT_NODE_MODULE_PATH` dan `BROWSERSHOT_CHROME_PATH` boleh dikosongkan hanya jika Puppeteer dapat menemukan dependency dan browser dengan konfigurasi default hosting. Pastikan Chromium bisa dijalankan oleh user cPanel dan temporary directory dapat ditulis.

## 6. Jalankan queue worker dan scheduler

Aplikasi memakai database queue untuk analisis, build tema, dan pengiriman pengingat. Tanpa worker, job akan menumpuk dan status proses tidak selesai. Worker persisten yang dikelola Supervisor atau fitur process manager cPanel adalah pilihan utama:

```sh
cd /home/CPANEL_USER/apps/dashboard-operasional
/path/php /home/CPANEL_USER/apps/dashboard-operasional/artisan queue:work database --sleep=3 --tries=1 --timeout=2400
```

Job mockup dapat berjalan sampai sekitar 35 menit untuk satu kandidat. Karena itu worker perlu `--timeout=2400`, sedangkan `DB_QUEUE_RETRY_AFTER` harus lebih besar, misalnya `2460`. Jangan gunakan mode queue `sync` untuk mockup karena pekerjaan akan berjalan di dalam request web.

Scheduler Laravel memerlukan cron setiap menit. Ganti path PHP dan user sesuai akun:

```cron
* * * * * cd /home/CPANEL_USER/apps/dashboard-operasional && /path/php artisan schedule:run >> /dev/null 2>&1
```

Jika provider hanya mengizinkan cron untuk worker, gunakan worker `--stop-when-empty` dengan penguncian agar tidak ada worker tumpang tindih, misalnya `flock` bila tersedia. Pastikan batas waktu cron provider cukup untuk menuntaskan job yang sedang berjalan. Jika tidak ada process manager, cron yang aman, atau izin proses panjang, shared hosting tersebut tidak cocok untuk seluruh fitur aplikasi.

## 7. Pemeriksaan sebelum dibuka

Jalankan:

```sh
composer check-platform-reqs --no-dev
php artisan migrate:status
php artisan deployment:check-cpanel
```

Perbaiki setiap hasil `GAGAL`. Hasil `PERINGATAN` mengingatkan hal yang tidak dapat diketahui aplikasi secara otomatis, seperti apakah worker queue dan cron benar-benar sudah didaftarkan pada cPanel.

Setelah itu, uji login, upload dan akses file, email, satu job queue, cron scheduler, pembuatan satu mockup, potret URL, serta build tema. Tes mockup akan memanggil OpenAI dan dapat menimbulkan biaya. Jangan lakukan tes itu sebelum API key dan saldo disiapkan.

## 8. Update versi aplikasi

Sebelum update, backup database dan berkas unggahan. Setelah menarik versi baru:

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Jangan menimpa `.env`, folder upload, atau hasil build/berkas kerja pengguna dengan salinan repository. Jika proses update gagal, simpan log dan pulihkan backup yang sesuai.
