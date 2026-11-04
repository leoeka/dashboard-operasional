# Analisis tahap 8-9: bangun tema WordPress + meja periksa — 2026-10-30

Perintah pemilik: "melanjutkan project ini analisis dulu jangan tambah atau ubah kode catat
semua yang di analisis lalu laporkan kepada saya". Dokumen ini catatan baca-saja; tidak ada
kode yang diubah. Satu-satunya perintah yang dijalankan adalah dua suite tes yang sudah ada
(19 tes / 90 asersi, semuanya hijau, sqlite dari `phpunit.xml`).

## 1. Temuan pokok: tahap 8 dan 9 ternyata sudah ada di repo

Bukan sisa builder lama. Commit `13e1fe2` (2026-10-22 14:15, penulis pemilik) **menambahkan**
berkas-berkas ini, bersamaan dengan seluruh kerja tahap 0-7:

| Berkas | Isi |
| --- | --- |
| `app/Services/WpThemeBuilder.php` (353 baris) | baca `index.html` kandidat terpilih, ganti penanda, tulis berkas tema |
| `app/Services/WpThemeInspector.php` (444 baris) | meja periksa + kemas ZIP + status `ready` |
| `app/Jobs/BuildWpThemeJob.php` | antrean: building → build → inspect, gagal dicatat |
| `tests/Feature/WpThemeBuilderTest.php` (8 tes) | struktur tema, kontak, gambar, nol penanda, klien kosong, header style, job, gagal |
| `tests/Feature/WpThemeInspectorTest.php` (11 tes) | berkas wajib, header, php -l, penanda, K-9, M-11, unduh ZIP, lintas proyek |

Sudah tersambung juga di luar dua service itu:

- Rute `pages.projects.builds.store` dan `pages.projects.builds.download` (`routes/web.php:134-137`).
- Tombol "Mulai build WordPress", status "sedang diproses", "Unduh ZIP WordPress", dan
  "Coba lagi build" di gerbang (`resources/views/workspace/mockup-gate.blade.php:71-99`).
- `WpBuildController::store()` memanggil `BuildWpThemeJob::dispatch()` (`WpBuildController.php:64`).

Artinya: kriteria tes tahap 8 ("penanda kontak terganti semua") **sudah lulus** di atas kertas,
dan bagian besar tahap 9 (periksa + ZIP + unduh) juga sudah berdiri. Yang belum: kesesuaiannya
dengan pasal rancangan dan ketahanannya terhadap keluaran GPT yang sebenarnya.

Catatan riwayat kerja: sejak commit itu masih ada perubahan yang belum di-commit (daftar `M` di
`git status`), plus berkas baru bertanggal 2026-10-23 milik pemilik (`MockupAsset`, `ProjectSeo`,
`ProjectBacklink`, `ClientViewTest`, `MockupAssetsUploadTest`) yang **tidak disentuh** analisis ini.

## 2. Selisih terhadap rancangan (yang sudah benar tidak dicatat di sini)

Rancangan: pasal 5 (CTA), pasal 6 (meja periksa), pasal 8 (format tema), berkas aturan
`resources/ai/aturan-mockup.md`.

### G1. Menu WordPress hanya didaftarkan, tidak dibuat dan tidak pernah dipanggil
`functions.php` memuat `register_nav_menus()` (`WpThemeBuilder.php:237-240`), tetapi tema tidak
pernah memanggil `wp_nav_menu()` — grep atas builder, inspektur, dan tes: nol hasil. Header
menyalin tautan statis dari kandidat, bukan menu WordPress. Pasal 5/8 meminta "menu dibuat dan
ditetapkan"; pasal 6 memeriksa "lokasi menu terdaftar **dan terisi**", sedangkan inspektur hanya
mencari string `register_nav_menus` (`WpThemeInspector.php:252-268`). Jadi menu kosong tetap lolos.

### G2. Pustaka media tidak diisi
Pasal 8: "pustaka media diisi unduhan slot foto". Builder hanya menyalin gambar ke folder
`images/` tema (`WpThemeBuilder.php:154-171`). Tidak ada unggahan ke pustaka media, sehingga
janji "foto bisa diganti klien nanti di pustaka media" (pasal 9b/aturan baris 14-15) belum punya
jalurnya. Berkas `MockupAsset` yang baru (belum di-commit) tampak menuju ke arah ini, tetapi
belum tersambung ke builder.

### G3. Halaman isi kandidat dibuang
Aturan mengizinkan kandidat menulis halaman lain di subfolder (`aturan-mockup.md:73-74`), tetapi
builder hanya membaca `index.html` (`WpThemeBuilder.php:40-47`), tidak membuat halaman WordPress,
dan menulis semua `__EXITO_PAGE_URL:<slug>` menjadi `home_url('/<slug>/')`
(`WpThemeBuilder.php:124-135`). Untuk halaman yang tidak pernah dibuat, tautan itu menuju 404.
Pasal 8 menyebut "halaman-halaman isi".

### G4. Kontak tanpa data tetap dirender sebagai tautan mati (melanggar K-7)
Bila klien tidak punya nomor/email, penanda diganti `'#'` (`WpThemeBuilder.php:96-113`). Aturan:
"target yang tidak punya data tidak ditulis sama sekali" (`aturan-mockup.md:18`, rancangan baris
128-129). Lebih jauh, tes yang ada **mengunci** perilaku ini: `WpThemeBuilderTest.php:290-294`
mengharapkan `href="#"`. Selama tes itu berbunyi begitu, memperbaiki K-7 akan memerahkan tesnya —
jadi perbaikannya harus sekalian mengubah tes.

### G5. Pemeriksaan K-9 hanya separuh, dan `<head>` kandidat dibuang tanpa jejak
`checkNoExternalEmbeds()` hanya memindai `*.php` di akar tema (`WpThemeInspector.php:273-295`);
`style.css` tidak dipindai, sehingga `@import` atau `url(https://...)` di CSS lolos. Sebaliknya,
`header.php` ditulis dengan kepala tetap (`WpThemeBuilder.php:282-294`): seluruh isi `<head>`
kandidat — `link` huruf, `meta` — hilang. Kalau kandidat memakai huruf dari luar, di WordPress
hurufnya hilang tanpa peringatan (paritas visual pecah, K-9 tidak menangkapnya).

### G6. Meja periksa kontras hampir tidak mengukur apa pun
`checkColorContrast()` hanya membaca pasangan variabel yang namanya persis `--text`/`--bg`
(`WpThemeInspector.php:352-359`). Nama variabel tidak diwajibkan di berkas aturan, dan tes
memakai `body { color: #333 }` tanpa `:root`, sehingga lolos **tanpa diukur**. M-1 di aturan
berbunyi "tiap pasangan warna teks dan latar yang benar-benar terpakai"; M-2 (pilih teks dengan
rasio tertinggi di atas warna merek) tidak diimplementasikan sama sekali.

### G7. Ekstensi gambar: tebakan `.jpg` bentrok dengan berkas nyata
`unduh_gambar` menyimpan berkas sebagai `<slot>.<ekstensi asli>` dan menjawab "slot: hero.png"
(`MockupToolBox.php:195-207`). Aturan menyuruh GPT menulis penanda tanpa ekstensi
(`__EXITO_IMAGE:slot`). Builder lalu menebak `.jpg` kalau penanda tanpa ekstensi
(`WpThemeBuilder.php:137-149`). Akibatnya gambar `.png`/`.webp` menjadi tautan rusak di tema.
Tes hanya menguji `<slot>.jpg` (`WpThemeBuilderTest.php:206-208`), jadi cacat ini tidak terlihat.

### G8. Pemisahan header/footer bergantung pada tag `<header>`/`<footer>`
Builder memisahkan chrome dengan regex tag itu (`WpThemeBuilder.php:265-279`). Aturan tidak
mewajibkan kandidat memakainya. Kalau GPT menulis `<div class="site-header">`, `header.php`
lahir tanpa navigasi — padahal inspektur tidak memeriksa keberadaan navigasi.

### G9. "Ulang sekali otomatis" belum ada
Pasal 6: gagal periksa → "ulang sekali otomatis". Kode sekarang: job `tries = 1`, dan ulang
hanya lewat tombol "Coba lagi build" (`WpBuildController.php:40-52`). Tidak salah, tetapi
berbeda dari bunyi rancangan.

### G10. Keluaran AI ditulis apa adanya menjadi templat PHP
`front-page.php` diisi markah kandidat tanpa penyaringan (`WpThemeBuilder.php:307-316`). Kalau
keluaran model mengandung `<?php ... ?>`, ia akan **dijalankan** di situs WordPress klien.
Kemungkinannya kecil (aturan melarang skrip), tetapi pagar K-9 hanya memeriksa iframe dan
`script src` — bukan PHP.

### G11. Operasional antrean
`.env` pengembangan memakai `QUEUE_CONNECTION=database`. Tanpa `php artisan queue:work` yang
hidup, build berhenti di status `menunggu` tanpa pesan. Sama seperti job mockup, tetapi layak
ditulis di layar supaya tim tidak menunggu kosong.

### G12. Tampilan kegagalan belum menampilkan daftar periksa
Pasal 6: tim melihat daftar pelanggaran. Gerbang hanya menampilkan satu kalimat
`inspection_results['error']` (`mockup-gate.blade.php:78`); `checks` dan `errors` tersimpan di
JSON tetapi tidak dirender.

### G13. Komentar basi (bukan cacat perilaku)
`WpBuildController` masih menulis "pembangunan temanya ... menyusul di tahap berikutnya"
(`WpBuildController.php:16-18`), padahal jobnya sudah dipanggil di baris 64.

## 3. Yang sudah benar dan tidak perlu diutak-atik

- Satu artefak: tema lahir dari `index.html` kandidat yang dicatat sebagai pilihan klien.
- Kontak dari CRM: `tel:`, `mailto:`, `wa.me` dengan normalisasi `0` → `62` (pola sama dengan
  `WhatsAppService.php:207-212`), termasuk cadangan `whatsapp` ↔ `phone` dan `billingEmail()`.
- `style.css` dengan kepala tema sah; seluruh `<style>` kandidat dipindah ke sana; penanda
  gambar di dalam CSS dikonversi ke jalur relatif (langkah yang tepat untuk berkas statis).
- `screenshot.png` dari `preview.png` kandidat; gambar kandidat disalin ke `images/`.
- Nol penanda diperiksa dua kali: builder (`WpThemeBuilder.php:338-352`) dan inspektur.
- `php -l`, ambang M-11, tolak iframe/skrip luar di PHP, ZIP berfolder slug, unduh dicek status
  `ready` + kepemilikan proyek.
- Pola job konsisten dengan job mockup: `tries = 1`, `timeout = 600`, jejak kegagalan di baris.

## 4. Dampak bisnis kalau dibiarkan

Tema yang diunduh bisa sampai ke tangan klien dalam keadaan: tanpa menu, gambar hero rusak,
tombol kontak `#` yang tidak melakukan apa-apa, tautan halaman 404, dan huruf yang berubah — dan
meja periksa tetap mengatakan "Lolos meja periksa". Itu justru kebalikan dari tujuan pasal 6
("rules yang bisa membuktikan dirinya").

## 5. Usul arah (belum dikerjakan)

Dua langkah, dipisah karena bobotnya beda:

1. **Tambal cacat yang bisa dibuktikan** — G7 (ekstensi gambar), G4 (K-7 + ubah tes yang
   mengunci `#`), G5 (pindai CSS + putuskan nasib huruf luar), G6 (ukur pasangan warna nyata,
   tambah M-2), G3 (jangan hasilkan tautan ke halaman yang tidak ada), G8 (wajibkan penanda
   struktur di aturan atau deteksi yang lebih longgar), G12 (tampilkan daftar periksa).
   Semua ini menyentuh dua service + satu tes, tanpa mengubah bentuk tema.
2. **Keputusan bentuk tema** — G1 (menu asli dibuat & ditetapkan), G2 (pustaka media), G3
   (halaman isi sebagai halaman WordPress). Ketiganya mengubah isi ZIP dan perlu keputusan
   pemilik: apakah menu dibuat otomatis oleh tema saat diaktifkan (pola installer lama), dan
   apakah pustaka media diisi saat aktivasi atau lewat unggahan `MockupAsset` yang sedang
   dibangun.

Pertanyaan yang perlu dijawab pemilik sebelum kode disentuh: kerjakan langkah 1 lebih dulu
(tambal, tes diperbaiki, satu putaran CodeRabbit), lalu putuskan langkah 2 sebagai tahap
tersendiri — atau langsung tulis ulang builder dengan kontrak menu/media/halaman sejak awal?

## 6. Status tambalan (2026-10-30, atas izin pemilik)

Pemilik memilih "lanjut menambal cacat yang bisa dibuktikan" — langkah 1 dikerjakan, langkah 2
tetap menunggu keputusannya.

| Cacat | Tambalan |
| --- | --- |
| G7 ekstensi gambar | Penanda gambar dicocokkan ke berkas nyata di folder kandidat (jpg/jpeg/png/webp/gif/svg); slot tanpa berkas menggagalkan build dengan pesan "Unduh dulu lewat unduh_gambar". |
| G4 K-7 kontak `#` | Tautan yang targetnya tidak punya data dibuang utuh (teks tetap); penanda yang tersisa di luar tautan menggagalkan build. |
| G3 tautan 404 | Tautan halaman yang tidak dibuat tema dibuang; `kontak`/`contact` hanya jadi jangkar kalau benar-benar ada bagian dengan `id` itu. Awalan slug (`layanan` vs `layanan-detail`) dan ekstensi (`tentang.html`) tidak lagi salah ditolak. |
| G5 K-9 pindai CSS | Pemindaian rekursif atas `.php` dan `.css`: `<script>`, `<form>`, `<iframe>`, dan host luar (selain wa.me/w3.org) ditolak; `@import` huruf luar tertangkap. |
| G6 kontras | M-1 diukur dari pasangan warna teks/latar yang benar-benar ditulis (var() diurai ke nilai :root, heks 3/6/8 digit, rgb/rgba); M-2 menolak teks di atas warna merek yang bukan rasio tertinggi; latar gradien/rgba transparan/url() dilewati, bukan diukur salah. |
| G12 tampilan gagal | Gerbang menampilkan daftar periksa berlabel Indonesia (lolos/gagal) plus pesan kendala dan rincian galat. |
| G8 struktur | Aturan §5 mewajibkan `<header>`/`<footer>` dan menulis warna teks+latar pada aturan yang sama agar M-1 terukur; larangan skrip/formulir diperjelas. |

Verifikasi: suite sqlite 381 tes / 1397 asersi hijau (sebelumnya 377). Dua putaran review
CodeRabbit: putaran 1 menemukan tabrakan awalan penanda dan latar gabungan yang salah ukur —
keduanya ditambal dan diuji; putaran 2 tidak menemukan temuan Critical.

Sisa untuk keputusan pemilik: G1 menu WordPress dibuat & ditetapkan, G2 pustaka media, G3
halaman isi sebagai halaman WordPress, serta percobaan ulang otomatis sekali saat meja periksa
gagal (G9).
