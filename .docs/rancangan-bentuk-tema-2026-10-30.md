# Rancangan bentuk tema: halaman isi, menu sungguhan, pustaka media — 2026-10-30

Status: rancangan. Nol kode diubah. Menunggu keputusan pemilik.

Dasar: rancangan builder 2026-10-21 bagian 5, 6, 8; pola installer lama yang kini
ada di arsip (`storage/app/backup/web-builder-dihapus-20261021.tar.gz` →
`app/Services/BundleExporterService.php`); uji instal tema lama di Laragon (tugas #74).

## 1. Arah

Tema hari ini: satu ZIP berisi satu halaman depan. Klien mengaktifkan tema, dapat
halaman depan saja — tidak ada menu, pustaka media kosong, halaman isi tidak ada.
Tautan nav internal dilepas (K-7), jadi tidak ada tautan mati, tapi juga tidak ada
halaman tujuan.

Arah yang dituju tetap janji yang sama: **satu ZIP, satu langkah (aktifkan), tanpa
jaringan, tanpa lisensi**. Bedanya, begitu tema aktif, tema sendiri yang membuat
halaman isi, membuat menu, dan mengisi pustaka media. Nol plugin, nol langkah kedua.

Ini bukan pola baru. Installer lama melakukan persis ini: fungsi impor ditulis tangan
dan ditanam ke `functions.php` saat build, lalu berjalan saat admin pertama membuka
wp-admin. Pola itu sudah pernah lolos uji instal di Laragon; rancangan ini
memindahkannya ke pembangun baru, dengan tiga perbaikan yang dicatat di bagian 6.

## 2. Selisih hari ini vs yang dituju

| Bagian | Hari ini | Yang dituju |
| --- | --- | --- |
| Halaman depan | `front-page.php` dari `index.html` | tetap |
| Halaman isi | tidak dibuat; tautan internal dilepas | tiap tautan internal yang punya berkas lahir sebagai halaman WP sungguhan |
| Menu | lokasi menu didaftarkan, menu tidak ada | "Menu Utama" dibuat, itemnya halaman nyata, dipasang ke lokasi `primary` |
| Pustaka media | kosong | semua foto slot diunggah jadi lampiran |
| Isi halaman | — | blok HTML di Block Editor, klien bisa mengedit |

## 3. Dari mana halaman isi lahir

Sumber tunggal: berkas `pages/<slug>.html` di folder kandidat, ditulis GPT di ronde 3.
Aturan mockup §5 sudah membolehkan subfolder (`pages/tentang.html`); yang ditambahkan
adalah kewajibannya: **setiap tautan internal di `index.html` wajib punya berkas
padanan.**

- Kandidat satu halaman (tidak ada `pages/`): tautan internal tetap dilepas seperti
  hari ini, tema tetap sah. Meja periksa hanya gagal kalau `index.html` menjanjikan
  halaman (ada tautan internal) tetapi berkasnya tidak ada — itu janji yang tidak
  ditepati, bukan pilihan bentuk.
- Slug = nama berkas tanpa ekstensi. Judul halaman = `<title>` berkas itu, cadangan
  `ucfirst(slug)`. Label menu = teks tautan di `index.html`.
- Chrome tiap berkas halaman (`<header>`, `<footer>`) dibuang: tema sudah punya chrome
  global dari `index.html`. Yang masuk halaman hanya isi badan.
- CSS semua halaman digabung ke `style.css` (satu lembar, blok identik dibuang
  sekali). M-11 (60KB) tetap penjaga: kalau bengkak, build gagal periksa, bukan
  dibiarkan.

## 4. Bentuk tema setelah perubahan

```
style.css            CSS index.html + semua halaman isi
functions.php        register_nav_menus + helper + importer (ditulis tangan)
header.php           chrome + wp_nav_menu lokasi primary
footer.php           chrome
front-page.php       isi index.html
page.php             the_content() di antara chrome
index.php            fallback WordPress
screenshot.png
images/              foto slot (sumber untuk pustaka media)
content/<slug>.html  isi halaman isi, mentah sebelum impor
README.txt           petunjuk aktifkan, Pages, Menus, Media
```

Isi halaman sengaja berkas terpisah, bukan `var_export` raksasa di `functions.php`
seperti pola lama: `functions.php` tetap bisa dibaca manusia, dan meja periksa bisa
memindai isi halaman sebelum dikemas.

## 5. Dua jenis penanda

| Tempat | Tautan halaman | Gambar |
| --- | --- | --- |
| Berkas tema (PHP) | `<?php echo esc_url(siteflow_page_url('layanan')); ?>` | `get_template_directory_uri() . '/images/...'` (seperti sekarang) |
| Isi halaman (basis data) | `[[siteflow:halaman:layanan]]` | `[[siteflow:gambar:slot-hero]]` |

Basis data WordPress tidak menjalankan PHP, jadi isi halaman memakai token yang
diganti importer saat halaman dibuat. Penanda `__EXITO_` tetap nol di berkas tema
(kontrak lama tidak berubah); meja periksa menolak token `[[siteflow:...]]` yang
tidak dikenal dan token gambar/halaman yang tidak punya berkas padanan.

Helper `siteflow_page_url('layanan')` mencari halaman lewat kunci meta
`_siteflow_page_key`; kalau belum ada (impor belum jalan) jatuh ke beranda.
Tidak pernah ada tautan mati (K-7). Gambar yang gagal diunggah: `<img>`-nya dibuang
utuh oleh importer, tidak pernah gambar rusak.

## 6. Importer: apa yang jalan saat tema aktif

Ditulis tangan, deterministik, bukan keluaran GPT; ditanam ke `functions.php` saat
build. Pemicu: admin pertama membuka wp-admin (`admin_init`, hanya pengguna
`manage_options`), dibandingkan dengan versi isi (md5 dari daftar halaman + foto + nav).
Tema hasil build ulang membawa versi baru, jadi halaman ikut diperbarui — bukan
tertahan selamanya.

Urutan satu kali jalan:

1. unggah semua `images/` ke pustaka media;
2. buat/perbarui halaman (kunci meta `_siteflow_page_key`, bukan judul);
3. isi token `[[siteflow:halaman:...]]` dan `[[siteflow:gambar:...]]` di isi halaman;
4. buat "Menu Utama" dan itemnya, pasang ke lokasi `primary`;
5. simpan versi isi.

Tiga perbaikan atas pola lama:

- foto memakai peta nama berkas → id lampiran; impor ulang tidak menumpuk duplikat;
- item menu buatan tema dilacak lewat option dan diganti saat impor ulang; item
  yang dibuat klien sendiri tidak pernah disentuh;
- berkas isi dibaca dari `content/`, bukan blok PHP raksasa.

Pintu manual: notifikasi wp-admin "Buat/perbarui halaman sekarang"
(nonce + `manage_options`), untuk situs uji yang sempat memakai build lama.

## 7. Menu

- Nama "Menu Utama". Item: Beranda (tautan kustom ke beranda) + satu item per halaman
  isi, urut kemunculan tautan di `index.html`.
- Dipasang ke lokasi `primary` tanpa menghapus lokasi lain yang mungkin sudah diisi.
- Sebelum impor berjalan: `wp_nav_menu` jatuh ke `wp_list_pages`, jadi tidak ada bilah
  navigasi kosong.
- Lokasi `footer` tetap terdaftar, tetapi footer memakai markah mockup dengan tautan
  halaman yang sudah nyata. Alasan: footer mockup biasanya berisi kolom alamat dan
  kontak, bukan navigasi halaman; menggantinya dengan menu akan mengubah tampilan
  yang sudah disetujui.
- Tidak ada halaman "Home" kosong di daftar Pages: beranda tetap `front-page.php`,
  jadi tidak ada halaman yang isinya diabaikan tema dan membingungkan klien.

## 8. Pustaka media

- Semua berkas `images/` diunggah lewat `wp_upload_bits` → `wp_insert_attachment` →
  `wp_generate_attachment_metadata`.
- Peta nama berkas → id lampiran disimpan di option; impor ulang memakai lampiran yang
  sama selama masih ada.
- Judul lampiran = nama berkas yang dirapikan; tanpa alt karangan.

## 9. Halaman depan & setelan Reading

Front page tetap berkas tema (`front-page.php`). Setelan Reading tidak diubah.
README menulis terang-terangan: beranda hasilnya sudah otomatis; mengubah
"Homepage displays" tidak memindahkan beranda.

## 10. Meja periksa: tambahan

| Kunci | Arti di layar | Gagal kalau |
| --- | --- | --- |
| `page_payloads` | Halaman isi | ada tautan internal tanpa berkas; isi halaman kosong; token tak dikenal; token gambar tanpa berkas |
| `menu_filled` | Menu | fungsi importer tidak ada; ada item nav tanpa halaman + berkas padanan |
| `media_payload` | Pustaka media | `images/` kosong padahal `index.html` memakai gambar; ada berkas yang bukan gambar sah (`getimagesize`) |

Pemeriksaan lama diperluas: nol penanda `__EXITO_` dan K-9 ikut memindai
`content/*.html`; M-11 juga mengukur `content/*.html` (120KB). Ketiga kunci baru
masuk daftar label gerbang, plus jumlah halaman isi di banner.

Batas jujur: meja periksa memeriksa **bahan** importer, bukan hasil di WordPress.
Hasil nyata tetap butuh uji instal; itu tetap langkah manual di Laragon.

## 11. Tes

- Pembangun: `pages/<slug>.html` → `content/<slug>.html` tanpa chrome; CSS halaman
  masuk `style.css` sekali; tautan internal jadi `siteflow_page_url(...)` saat berkas
  ada, tetap dilepas saat tidak ada; `page.php` dan `README.txt` ada; `functions.php`
  memuat importer + daftar halaman/foto/nav; token `[[siteflow:...]]` hanya yang
  dikenal.
- Meja periksa: gagal saat tautan internal tanpa berkas; gagal saat token gambar tanpa
  berkas; gagal saat berkas `images/` rusak; lolos untuk kandidat satu halaman;
  `content/` ikut dipindai K-9, ukuran, dan penanda.
- Suite sqlite penuh hijau (sekarang 381 tes) sebelum laporan.
- Uji instal manual di Laragon setelah hijau (butuh waktu pemilik).

Urutan kerja: pembangun dulu, lalu importer/menu/media, lalu meja periksa + layar,
lalu tes, lalu CodeRabbit.

## 12. Keamanan & risiko

- PHP di dalam tema ditulis tangan, tanpa jaringan, tanpa `eval`; nama berkas
  dirapikan; isi halaman diperiksa K-9 sebelum dikemas; impor manual butuh nonce +
  `manage_options`.
- Risiko: halaman isi tidak pernah dilihat tim/klien saat tinjau — pratinjau hanya
  `index.html`. Usul (keputusan terpisah): kartu tinjau menampilkan daftar halaman isi
  (nama + tautan pratinjau), tanpa PNG baru.
- Risiko: `style.css` membengkak → M-11 menahan, dan pesan gagal menyebut sebabnya.
- Risiko: admin tidak pernah membuka wp-admin → halaman belum dibuat; notifikasi dan
  README menjelaskan.
- Risiko: klien menghapus menu → fallback `wp_list_pages` tetap memberi navigasi.

## 13. Tidak termasuk

- G9: ulang otomatis sekali setelah gagal periksa.
- PNG pratinjau halaman isi.
- Menu footer sungguhan, featured image, tema blok (tetap klasik: titik awal yang
  terbukti).
- Uji instal otomatis (butuh WordPress sungguhan).

## 14. Keputusan yang diminta

Satu: setujui rancangan ini untuk mulai ngoding.
