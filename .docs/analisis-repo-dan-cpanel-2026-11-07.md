# Analisis repo dan kesiapan cPanel (2026-11-07)

Status: catatan analisis, nol suntingan kode. Semua angka di bawah diukur hari ini di mesin lokal.

## 1. Keadaan repo

- Working tree bersih, tidak ada perubahan yang belum di-commit.
- Commit terakhir: `8aaf745` (2026-11-07 08:52) perbaikan redirect analisis SEO.
- Suite penuh dengan `DB_CONNECTION=sqlite`: **472 lulus, 1858 assertion, 121,61 detik, exit 0**. Kegagalan `SeoUrlAnalysisTest` yang tercatat 2026-11-06 sudah tertutup oleh commit pagi ini.
- Enam commit terakhir mengubah arah sistem, bukan hanya menambal: `9d25066` (brief + gerbang tim), `67ab98a` (fitur AEO/SEO milik pemilik), `c9312b8` (migrasi mockup ke OpenAI API), `49d8662` (mockup masuk antrean untuk cPanel), `8ef8d39` (dist keluar dari git), `8aaf745` (redirect SEO).

## 2. Alur mockup sekarang berbeda dari catatan sebelumnya

Alur lama yang dianalisis 2026-11-06: tombol web melepas proses Codex CLI lewat `proc_open`, Codex menulis berkas dan foto sendiri, aplikasi memulihkan foto dari manifest dan cache Codex.

Alur yang berlaku sekarang:

1. Tombol web memanggil `MockupOpenAiLauncher::launch()` ([app/Support/MockupOpenAiLauncher.php:18](../app/Support/MockupOpenAiLauncher.php)).
2. Launcher menolak lebih dulu bila `OPENAI_API_KEY` kosong atau queue masih `sync`/`null` ([:89-102](../app/Support/MockupOpenAiLauncher.php)), lalu memasukkan satu job per kandidat ke antrean database.
3. `GenerateMockupCandidate` ([app/Jobs/GenerateMockupCandidate.php](../app/Jobs/GenerateMockupCandidate.php)) berjalan di worker: `timeout 2400`, `tries 100`, `maxExceptions 1`, kunci `WithoutOverlapping` per proyek dengan `expireAfter 2460`. `config/queue.php` menaikkan `retry_after` bawaan ke 2460.
4. `MockupOpenAiRunner` mengirim satu request Responses API per kandidat dengan alat `image_generation` ([app/Services/MockupOpenAiClient.php:41-46](../app/Services/MockupOpenAiClient.php)), lalu Laravel yang menulis berkas, memetakan gambar ke slot, dan memvalidasi.
5. Brief tetap `MockupCodexBrief::build()`, ditambah kontrak eksekusi API di `apiOutputInstructions()` ([app/Services/MockupOpenAiRunner.php:284-312](../app/Services/MockupOpenAiRunner.php)). Badan brief tidak diubah.
6. Pratinjau tetap Chromium lewat `PageSnapshot`, kini bisa diarahkan lewat `BROWSERSHOT_NODE_BINARY`, `BROWSERSHOT_NODE_MODULE_PATH`, `BROWSERSHOT_CHROME_PATH`.

Konsekuensi penting: `MockupCodexLauncher` dan command `mockup:codex` sudah dihapus. `MockupCodexRunner` dan `MockupCodexPhotos` masih ada di repo tetapi tidak dirujuk controller maupun launcher mana pun; keduanya hanya hidup lewat tes dan utilitas bersama. Jadi temuan tentang cache foto Codex tidak lagi berada di jalur yang dipakai tombol web.

## 3. Tinjauan Claude 2026-11-06: mana yang masih berlaku

Claude meninjau kode jalur Codex dan mengusulkan empat perbaikan aplikasi. Setelah alur pindah ke API, sebagian besar sudah diselesaikan dengan cara lain di runner baru:

| Usulan | Keadaan sekarang |
| --- | --- |
| `recover()` mengenali bahan klien dan mengunci slot dari cache | Tidak relevan di jalur aktif. Tidak ada cache fallback sama sekali; gambar disimpan dari hasil alat API ([saveImages:437](../app/Services/MockupOpenAiRunner.php)). |
| Path `aset/` ditulis ulang jadi token | Tidak diperlukan. Aset klien disalin ke folder kandidat dengan label sebagai slot dan ekstensi asli ([copyClientAssets:506-519](../app/Services/MockupOpenAiRunner.php)), lalu manifest ditulis oleh Laravel, bukan oleh model ([writeAssetManifests:525-562](../app/Services/MockupOpenAiRunner.php)). |
| Cek kontrak dua arah sebelum pratinjau | Sudah ada di [validateCandidate:568-616](../app/Services/MockupOpenAiRunner.php): halaman wajib, slot yang dirujuk harus tersedia, gambar yang dibuat harus dipakai, `hero` wajib. JSON rencana dan token divalidasi di [writeFiles:410-416](../app/Services/MockupOpenAiRunner.php). |
| Perbaiki pembaca `ide.txt` yang mengambil baris pertama | **Masih berlaku.** [readIdea:630](../app/Services/MockupOpenAiRunner.php) tetap `strtok(..., "\n")`. Bila model menulis judul `# Ide` di baris pertama, kartu kandidat menampilkan `# Ide`. Bug yang sama ada di `MockupCodexRunner::bacaIde()` (kode mati). |

Sisa celah yang belum dijaga di jalur baru, semuanya belum diuji dengan API live:

- Tidak ada pemeriksaan bahwa `src`/`url()` benar-benar memakai token. Model yang menulis `src="logo.png"` atau URL luar lolos `validateCandidate`, lalu rusak di pratinjau dan di tema. Instruksi teks sudah melarangnya ([:291](../app/Services/MockupOpenAiRunner.php)), tetapi tidak ada penjaga kode.
- Logo SVG ditolak lebih awal dengan pesan jelas ([mimeForExtension:618-626](../app/Services/MockupOpenAiRunner.php) hanya png/jpg/webp). Ini konsisten dengan `WpThemeBuilder::imageFileIndex()` yang melempar RuntimeException bila ada `.svg` di folder kandidat ([:325-327](../app/Services/WpThemeBuilder.php)). Artinya klien yang mengirim logo SVG akan ditolak, bukan dirasterisasi otomatis. Perlu keputusan: rasterisasi saat unggah, atau tetap tolak dengan pesan.
- Aset klien berlabel sama dengan slot gambar API ditolak ([:511-513](../app/Services/MockupOpenAiRunner.php)). Aman, tetapi nama label berasal dari nama berkas klien, jadi dua klien berbeda bisa memberi nama yang mudah bertabrakan (`hero`, `logo`).

## 4. Jawaban cPanel, per fitur

Yang sudah tersedia untuk deploy:

- `.docs/instalasi-cpanel.md` (146 baris, delapan langkah, termasuk update versi).
- `php artisan deployment:check-cpanel` ([app/Console/Commands/CheckCpanelReadiness.php](../app/Console/Commands/CheckCpanelReadiness.php)).
- `dist/dashboard-operasional-cpanel.zip`, 5.295.778 byte, 314 entri. Isinya kode sumber saja: `app`, `resources`, `database`, `config`, `public`, `routes`, `bootstrap`, `artisan`, `composer.json/lock`, `package.json/lock`, `.docs`, `scripts`. **Tidak ada `vendor`, tidak ada `node_modules`, tidak ada `.env`, tidak ada data klien** (`storage` hanya berisi `.gitignore`). Dua berkas SQL pendamping ada di `dist/`: `database-cpanel.sql` dan `upgrade-db-lama-ke-versi-baru.sql`. Folder `dist` sudah di-gitignore.

Hasil `deployment:check-cpanel` di mesin lokal hari ini: 3 GAGAL (`APP_ENV`, `APP_DEBUG`, `APP_URL` masih setelan lokal, wajar), 3 PERINGATAN (`storage:link`, worker queue, cron), sisanya OK termasuk **"Node.js dan Chromium bisa merender"**. Perintah ini bisa dijalankan ulang di server setelah deploy; hasilnya yang menentukan, bukan dugaan.

Kebutuhan yang tetap mengikat di server:

| Fitur | Kebutuhan | cPanel shared biasa |
| --- | --- | --- |
| Login, proyek, keuangan, invoice, PDF | PHP 8.2+, MySQL, ekstensi standar, izin tulis storage | Bisa |
| Analisis SEO/AEO, backlink, PageSpeed | Queue database + cron tiap menit + HTTPS keluar ke Google/OpenAI | Bisa bila worker/cron diizinkan |
| Pembuatan mockup (API OpenAI) | Worker queue yang boleh hidup 35 menit per kandidat (`timeout 2400`), HTTPS keluar ke OpenAI | Bergantung provider; banyak shared hosting membatasi proses panjang |
| Pratinjau kandidat (`preview.png`) | Node.js + Puppeteer + Chromium, `proc_open` di worker | Umumnya tidak tersedia |
| Potret tautan referensi | Chromium **di proses web**, bukan worker ([ProjectController.php:343](../app/Http/Controllers/ProjectController.php)) | Umumnya tidak tersedia |
| Build tema WordPress + pemeriksa | `exec('php -l ...')` di worker ([WpThemeInspector.php:250](../app/Services/WpThemeInspector.php)) | Bila `exec` masuk `disable_functions`, pemeriksaan sintaks gagal |
| Jalur Codex CLI | Tidak dipakai lagi oleh tombol web | Tidak perlu dipasang di server |

Ukuran pendukung: cache Chromium lokal 699 MB, paket Codex CLI lokal 445 MB (tidak perlu ikut ke server), `node_modules/puppeteer` + `puppeteer-core` sekitar 7,6 MB belum termasuk browser, `storage/app/mockup-work` lokal 446 MB (data kerja, jangan ikut diunggah).

Kesimpulan: **dasbor bisa naik ke cPanel; fitur mockup dan potret tidak bisa dijamin di shared hosting biasa.** Penghalangnya bukan Codex lagi, melainkan Chromium dan proses latar berumur panjang. Codex CLI sudah lepas dari jalur web, jadi pertanyaan "apalagi Codex-nya" jawabannya: tidak perlu Codex di server.

Tiga pilihan arah, belum diputuskan:

1. cPanel yang menyediakan Terminal, Node.js, Chromium, dan process manager. Harus dikonfirmasi tertulis ke provider sebelum bayar. Panduan yang ada sudah menulis syarat ini.
2. Dasbor di cPanel, worker mockup + pratinjau di VPS kecil yang menunjuk database yang sama. Satu worker, satu cron; kode tidak berubah.
3. Seluruh aplikasi di VPS. Paling sedikit kejutan, biaya dan tanggung jawab operasional naik.

## 5. Risiko terbuka yang terukur

1. **Belum ada satu pun request API live.** Dokumen migrasi sendiri menyatakan uji berbayar, validasi gambar nyata, build tema dari kandidat API, dan deploy cPanel belum dilakukan ([rancangan-migrasi-mockup-openai-api-2026-11-06.md:4,12](rancangan-migrasi-mockup-openai-api-2026-11-06.md)). Tes yang lulus memakai respons palsu.
2. Nama model sudah diverifikasi hari ini: `GET /v1/models` akun pemilik mengembalikan HTTP 200 dengan 96 model, dan `gpt-6-astra` serta `gpt-image-2.5-sunburst` **ada**. Jadi kekhawatiran nama model tidak terbukti. Biaya dan mutu per kandidat tetap belum terukur.
3. **Zip deploy lebih tua dari commit terakhir.** Zip dibuat 08:47, commit `8aaf745` pukul 08:52. Zip itu belum memuat perbaikan redirect SEO. Perlu dibangun ulang sebelum diunggah.
4. Zip memuat `__pycache__/tapscan.cpython-312.pyc`, sisa alat pembuat zip. Tidak berbahaya, tetapi tidak perlu ikut ke server.
5. Klaim di panduan cPanel bahwa "request web tidak lagi menjalankan `proc_open`" hanya benar untuk pembuatan mockup. Potret tautan referensi masih memanggil Chromium dari request web. Bila provider mematikan `proc_open` untuk PHP web, fitur itu gagal dengan pesan (tautan tetap tersimpan, ada tombol ambil ulang), bukan mati total.
6. `MockupCodexRunner.php:314` masih memuat teks instruksi tambal di dalam docblock ("GANTI method jalankanCodex() ... dengan versi ini"). Kode itu sudah mati; teksnya bisa membingungkan pembaca berikutnya.

## 6. Yang tidak dilakukan

Tidak ada kode yang disunting, tidak ada commit, tidak ada unggahan ke server, tidak ada request API berbayar. Satu-satunya panggilan keluar adalah `GET /v1/models` yang tidak memakai token generasi. Brief `MockupCodexBrief.php` tidak disentuh.