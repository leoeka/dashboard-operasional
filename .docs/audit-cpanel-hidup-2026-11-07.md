# Audit cPanel hidup, 7 November 2026

Semua angka di bawah dibaca langsung dari cPanel yang sedang login di browser
(`cpanel.exitobalidigital.com`, akun `exib8656`, sesi Jupiter). Tidak ada satu pun
setelan yang diubah, tidak ada berkas yang diunggah atau dihapus, tidak ada
panggilan API berbayar. Nilai rahasia (kata sandi, kunci API, APP_KEY) sengaja
tidak disalin ke dokumen ini.

## 1. Jawaban singkat

Dasbornya **sudah ada di cPanel itu dan sudah hidup**. Bukan rencana lagi.

- Alamat: `https://tools.exitobalidigital.com`
- `/` menjawab 302 ke `/login`, `/login` menjawab 200 (5.383 byte).
- Document root: `/home/exib8656/public_html/tools.exitobalidigital.com/public`
- Aplikasi dibaca dari induknya: `public/index.php` memanggil `../vendor/autoload.php`
  dan `../bootstrap/app.php`. Susunan ini benar (repo di luar root web).
- Salinan induk cocok dengan repo lokal HEAD `8aaf745`: `composer.json` 3.068 byte,
  `artisan` 425 byte, `public/index.php` 543 byte, `.env.example` 2.845 byte.
  Keempatnya sama persis dengan ukuran di komputer ini.
- Tiga cron aktif setiap menit: `schedule:run`, `queue:work --stop-when-empty
  --tries=3 --timeout=300`, dan `billing:process-renewals` (memakai `ea-php83`,
  keluaran ke `storage/logs/cron-billing.txt`). Perintah terakhir itu alias dari
  `finance:process-renewals` di repo ini, jadi yang deployed memang aplikasi ini.

Yang tidak bisa dijalankan di akun itu: **pembuatan mockup jalur baru** (panggilan
OpenAI panjang + Chromium). Alasannya terukur, ada di bagian 4 dan 5.

## 2. Identitas server dan akun

| Butir | Nilai |
|---|---|
| Nama server | kilimanjaro |
| cPanel | 138.0 (build 21) |
| Apache | 2.4.69 |
| Database | 11.4.13-MariaDB-cll-lve (CloudLinux) |
| Sistem operasi | linux, kernel 5.14.0-611.49.2.el9 (turunan RHEL 9) |
| Arsitektur | x86_64, 80 CPU, load saat dibaca 9,84 |
| IP bersama | 202.10.43.154 |
| Paket hosting | Build |
| Pemilik akun di mata reseller | `admrwn2` |
| Rumah akun | `/home/exib8656` |
| Shell akun | `/usr/local/cpanel/bin/noshell` |
| Disk terpakai | 5.049,89 MB (batas megabyte 0 = tidak dipatok) |
| Inode | 174.740 dari 250.000, sisa 75.260 |
| Domain | exitobalidigital.com + 7 subdomain (app, business, instan, invoice, personal, professional, tools) |

`owner: admrwn2` menegaskan ucapan pemilik: akun ini di bawah reseller, jadi
provider tidak bisa dihubungi langsung. Semua perbaikan harus lewat apa yang
tersedia di layar cPanel.

## 3. Fitur yang nyala dan yang dimatikan

Dibaca dari daftar fitur akun, bukan ditebak.

Nyala dan berguna:
- `cron`, `filemanager`, `version_control` (Git Version Control), `backup`.
- `lvephpsel` (Select PHP Version) dan `multiphp`; paket PHP tersedia dari
  `ea-php56` sampai `ea-php84`. Pilihan akun saat ini: **PHP 8.3**.
  Handler untuk subdomain tools: **ea-php82**.
- `lveresusage` (Resource Usage), `imunify360`, `wp-toolkit`, `softaculous`.
- Ekstensi PHP 8.3 yang aktif mencakup semua yang aplikasi butuhkan: bcmath, dom,
  fileinfo, gd, intl, mbstring, mysqlnd, pdo_mysql, pdo_sqlite, phar, sockets,
  tidy, xmlreader, xmlwriter, zip.

Dimatikan (tidak bisa dinyalakan sendiri dari layar cPanel):
- `ssh` dan `api_shell` → **tidak ada Terminal, tidak ada SSH**. Diperkuat oleh
  `shell = noshell`.
- `lvenodejssel` → **tidak ada Setup Node.js App**. `lvepythonsel` dan
  `lverubysel` juga mati. `passengerapps` mati walaupun paket menyebut maksimum 4.
- `apitokens` → tidak bisa membuat token API untuk otomasi dari luar.
- `multiphp_ini_editor` → tidak bisa menyunting php.ini per domain.
- `modsecurity` mati (Imunify360 yang jaga), `password` mati (tidak bisa ganti
  kata sandi cPanel sendiri).

Akibat praktis: perintah apa pun hanya bisa dijalankan lewat **cron** atau lewat
**Git Version Control** (berkas `.cpanel.yml` menjalankan perintah sebagai
pengguna). Composer, Node, dan Chromium tidak bisa dipasang dengan cara biasa.

## 4. Batas daya akun (CloudLinux LVE) dan pelanggaran hari ini

Dibaca dari Resource Usage, periode 7 November 2026 00:00 sampai 11:00 Asia/Jakarta.

| Sumber | Batas | Rata-rata per jam | Pelanggaran hari ini |
|---|---|---|---|
| CPU (SPEED) | 100% (setara satu inti) | 8% sampai 22% | 8 |
| Memori fisik (PMEM) | **1 GiB** | 4 MB sampai 37 MB | **25** |
| Entry processes (EP) | 15 | 0 | 0 |
| Jumlah proses (NPROC) | **30** | 0,1 sampai 2 | **8** |
| IO | tidak dipatok | di bawah 1,1 KB/s | 0 |
| IOPS | tidak dipatok | 0 | 0 |

Ringkasan layar: "You have reached the limits within the past 24 hours. CPU
resources limit was reached for your site. Physical memory resources limit was
reached for your site. You have reached the processes number limit 8 times."

Jam 02:00 sampai 03:00 tercatat 25 pelanggaran memori dan 8 pelanggaran jumlah
proses, padahal rata-rata memori hanya 32 MB. Artinya ada lonjakan singkat yang
menyentuh 1 GB, bukan pemakaian yang stabil tinggi.

Ini angka yang menjawab pertanyaan "boleh tidak job 35 menit di sini":
- Chromium headless umumnya perlu 300 sampai 500 MB per proses, belum termasuk Node.
  Dengan plafon 1 GB yang dibagi dengan situs WordPress lain di akun yang sama,
  peluang prosesnya dibunuh besar.
- Chromium juga memecah diri jadi banyak proses (renderer, gpu, utility). Plafon
  NPROC cuma 30 dan hari ini sudah 8 kali tersentuh tanpa Chromium sama sekali.
- CPU dipatok 100% setara satu inti, jadi render dan pengolahan gambar melambat
  justru pada saat job sudah berjalan lama.

## 5. Kemampuan per fitur, dengan bukti

| Fitur aplikasi | Vonis di akun ini | Bukti |
|---|---|---|
| Dasbor, keuangan, invoice, PDF proposal | Jalan, sudah hidup | `/login` 200; cron billing menulis log sampai 03:01 hari ini |
| Antrean database + cron tiap menit | Jalan | tiga cron aktif; `laravel.log` dan `cron-billing.txt` diperbarui 03:01 |
| PHP CLI | Ada, 8.3 | `include_path` di log menyebut `/opt/alt/php83`; pilihan akun php = 8.3 |
| Penulis konten SEO (panggilan keluar) | Belum diukur di server ini | tidak ada jejak hasil di `storage/app/private` (kosong) |
| Pembuatan mockup (OpenAI, job panjang) | Secara teknis mungkin lewat cron, tapi rapuh | plafon 1 GB / 30 proses sudah dilanggar hari ini; cron antrean sekarang `--timeout=300` sedangkan job minta sampai 2.400 detik |
| Pratinjau PNG (Chromium) | **Tidak bisa** | tidak ada `node_modules` di folder aplikasi; Node selector mati; tanpa shell tidak bisa memasang Puppeteer |
| Potret tautan referensi | **Tidak bisa** | sama, butuh Chromium, dan dipanggil dari proses web |
| Pemeriksa sintaks tema (`exec`) | Belum diukur | `disable_functions` tidak terlihat dari layar cPanel; butuh satu uji nyata |
| Codex CLI | Tidak diperlukan lagi | jalur Codex sudah dicabut dari kode; sisa foldernya masih ada di server (lihat bagian 7) |

## 6. Masalah keamanan yang ditemukan (perlu tindakan, belum disentuh)

### 6.1 Ada salinan repo kedua DI DALAM root web, dan `.env`-nya bisa diunduh

Root web adalah `.../tools.exitobalidigital.com/public`. Di dalamnya ternyata ada
pohon Laravel lengkap lain: `app`, `bootstrap`, `config`, `database`, `resources`,
`routes`, `storage`, `tests`, `vendor`, `public`, ditambah `build`, `cache`,
`images`. Ukuran berkasnya beda dari induk, jadi ini salinan yang berbeda
(`composer.json` 2.487 byte dan `artisan` 350 byte, lawan induk 3.068 dan 425).
Basis datanya pun lain: `exib8656_lara355`, sedangkan aplikasi hidup memakai
`exib8656_finance`. Pola nama basis data itu khas pemasang otomatis.

Diuji dari luar tanpa login, hanya kode status (isi tidak diunduh ke komputer ini):

| URL | Hasil |
|---|---|
| `/.env` | **200, 1.188 byte** (bisa diunduh siapa pun) |
| `/composer.json` | 200, 2.487 byte |
| `/composer.lock` | ada di root web, 310.473 byte |
| `/artisan` | 200, 350 byte (kode sumber polos) |
| `/vendor/autoload.php` | 200, 0 byte (dijalankan, bukan bocor) |
| `/storage/logs/laravel.log` | 403 (tertutup, bagus) |
| `/.git/config` | 404 (tidak ada repo Git di server) |
| `/../.env` | 403 (lintas folder diblokir) |
| `/app/Services/MockupOpenAiRunner.php` | 404 |

Jadi yang bocor ke internet adalah `.env` salinan kedua (kata sandi basis data
`exib8656_lara355` dan APP_KEY), bukan `.env` aplikasi hidup. `.env` aplikasi
hidup berada di induk, di luar root web, dan tidak terjangkau dari URL.

Tindakan yang disarankan, menunggu izin:
1. Arsipkan dulu salinan kedua itu bertanggal, lalu keluarkan dari root web.
   Yang wajib tinggal di `public` hanya `index.php`, `.htaccess`, `build`,
   `images`, `favicon.ico`, `robots.txt`, dan tautan `storage`.
2. Ganti kata sandi basis data `exib8656_lara355` karena sudah terbuka ke publik.
3. Kalau basis data itu tidak dipakai, tutup sekalian lewat Manage My Databases.

### 6.2 Setelan aplikasi hidup masih setelan lokal

Dari `.env` induk (nilai rahasia tidak disalin):
- `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug`
- `APP_URL=http://localhost` padahal alamatnya `https://tools.exitobalidigital.com`
- `MAIL_MAILER=smtp` ke Gmail dengan kata sandi aplikasi tersimpan di berkas
- `PENAI_MOCKUP_MODEL` kurang huruf O di depan (salah ketik; nilainya kebetulan
  sama dengan bawaan, jadi belum menimbulkan akibat)
- Tidak ada satu pun setelan `BROWSERSHOT_*` atau `MOCKUP_CODEX_*`

`APP_DEBUG=true` di produksi berarti halaman error bisa membuka isi setelan.
`APP_URL=http://localhost` membuat tautan yang dibangkitkan aplikasi (PDF, surel,
pengalihan) menunjuk ke alamat yang salah.

### 6.3 Kunci API tersimpan di akun milik reseller

`.env` induk memuat kunci OpenAI, Gemini, Google Ads, PageSpeed, Search Console,
Analytics, Places, dan Fonnte. Pemilik reseller (`admrwn2`) dan skrip apa pun di
akun yang sama (termasuk situs WordPress dan pemasang otomatis) bisa membaca
berkas itu. Ini risiko yang tidak bisa ditutup dari layar cPanel; pilihannya
hanya memindahkan kerja yang memakai kunci itu ke mesin yang dikendalikan sendiri.

Catatan transparansi: saat audit, isi `.env` induk sempat terbaca utuh lewat API
File Manager sebelum penyensoran saya berjalan (sensor saya salah alamat kolom,
jadi isi mentahnya lolos ke keluaran alat). Nilainya tidak disalin ke dokumen ini
dan tidak akan saya ulang. Kalau pemilik ingin aman, putar kunci OpenAI dan
Gemini setelah audit ini.

## 7. Kebersihan dan ruang

- `storage/logs/laravel.log` = **198.158.094 byte (189 MB)**, diperbarui 03:01.
  `LOG_LEVEL=debug` ditambah cron tiap menit membuatnya membengkak.
- `storage/logs/cron-billing.txt` = 15.776.529 byte (15 MB).
- Arsip menganggur di folder aplikasi: `dashboard-operasional-cpanel-old.zip`
  161.102.310 byte, `balitourplaces.zip` 108.009.911 byte,
  `dashboard-operasional-cpanel.zip` 5.295.802 byte, `MANUAL_BOOK.docx` 3.730.144
  byte. Total sekitar 278 MB yang tidak dibutuhkan aplikasi untuk jalan.
- Sisa jalur Codex lama: `storage/app/mockup-work/1/` berisi `candidate-4` sampai
  `candidate-9` dan folder `codex`, bertanggal 6 November 16:03 sampai 16:10.
- `storage/app/private` dan `storage/app/public` kosong (hanya `.gitignore`),
  jadi belum ada dokumen atau kandidat yang selesai dibuat di server ini.
- Ada dua skrip uji di induk: `tes-billing.php` (487 byte) dan `tes-db.php`
  (333 byte). Di luar root web, jadi tidak bocor, tapi tetap sebaiknya diarsipkan.
- Inode sisa 75.260. Memasang `node_modules` Puppeteer akan memakan puluhan ribu
  inode, jadi rencana itu juga mentok di sini, bukan cuma di memori.

## 8. Arah yang disarankan setelah angka ini masuk

1. **Dasbor tetap di cPanel ini.** Sudah hidup, cron dan PHP CLI terbukti jalan,
   dan semua ekstensi PHP yang dibutuhkan tersedia. Tidak perlu pindah.
2. **Kerja berat mockup dipindah ke mesin yang dikendalikan sendiri** (komputer
   pemilik atau VPS murah), dengan cara mesin itu menjemput pekerjaan lewat HTTPS
   keluar. Plafon 1 GB / 30 proses / 1 inti plus matinya Node selector membuat
   Chromium tidak mungkin dipasang dan dijalankan di sini dengan andal.
3. **Tutup kebocoran `.env` lebih dulu**, sebelum menambah fitur apa pun. Ini
   satu-satunya temuan yang tidak boleh menunggu.
4. **Betulkan setelan produksi** (`APP_ENV`, `APP_DEBUG`, `APP_URL`, tingkat log)
   dan cron antrean supaya memakai `ea-php83` secara eksplisit seperti cron
   billing, bukan `php` yang versinya bergantung pilihan akun.
5. Gambar pratinjau jangan menentukan hidup-mati kandidat. Tanpa Chromium di
   server, job yang sudah dibayar akan gagal di 88% dan PDF proposal klien ikut
   kosong. Ini sudah dicatat di `analisis-repo-dan-cpanel-2026-11-07.md`.

Semua tindakan di atas adalah perubahan pada server bersama, jadi menunggu izin
pemilik satu per satu. Dokumen ini hanya catatan hasil baca.

## 9. Keputusan pemilik, 7 November 2026

Pemilik menolak tindakan penutupan kebocoran: "tidak usah aja diganti".
Tidak ada berkas yang dipindahkan, tidak ada kata sandi yang diubah, tidak ada
setelan server yang disentuh. Akibat yang tetap berlaku dan sudah disampaikan:
`https://tools.exitobalidigital.com/.env` masih menjawab 200 dan masih bisa
diunduh tanpa login, beserta `composer.json`, `composer.lock`, dan `artisan`.
Kata sandi basis data `exib8656_lara355` di dalamnya tetap yang lama.

Butir yang masih menunggu keputusan terpisah: setelan produksi (`APP_ENV`,
`APP_DEBUG`, `APP_URL`, tingkat log) dan pemindahan kerja berat mockup ke mesin
yang dikendalikan pemilik.