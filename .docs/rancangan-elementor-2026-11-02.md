# Rancangan Elementor: halaman bisa diedit visual, bukan HTML — 2026-11-02

Status: rancangan. Nol kode diubah. Menunggu persetujuan pemilik.

Dasar: permintaan pemilik 2026-11-02 ("WordPress bisa langsung diedit lewat
Elementor, editannya bukan bentuk HTML"); Elementor gratis 4.3.5 terpasang di
situs uji Laragon 2026-11-02; rancangan bentuk tema 2026-10-30; `DESIGN.md`.

## 1. Arah

Isi halaman berhenti disimpan sebagai satu keping HTML. Setiap halaman ditulis
sebagai susunan elemen Elementor: kotak pembungkus, judul, teks, gambar, dan
tombol. Klien membuka halaman, memilih "Edit with Elementor", lalu mengklik
judul atau foto dan mengubahnya langsung. Yang disimpan adalah data elemen,
bukan kode HTML yang harus dibaca klien.

Yang berubah:

- isi halaman (beranda dan semua halaman isi) menjadi data Elementor;
- beranda berhenti jadi templat tema, pindah ke daftar Pages supaya bisa diedit;
- tema tetap membawa HTML cadangan untuk berjaga kalau plugin Elementor mati.

Yang tetap:

- header dan footer milik tema (menu + logo), karena Elementor gratis tidak
  menyediakan Theme Builder;
- stylesheet tema sebagai penjaga rupa; tidak ada desain ulang;
- alur kandidat mockup → gerbang tim → ZIP tema;
- menu WordPress, pustaka media, dan tautan halaman yang sudah terbukti jalan.

Batas yang harus disebut terang-terangan: janji "satu ZIP, nol plugin" berubah
menjadi dua langkah. Tema tetap satu ZIP, tetapi situs klien perlu memasang
plugin Elementor dari direktori WordPress. Tema tetap hidup tanpa plugin itu,
hanya saja halaman tampil sebagai HTML cadangan dan tidak bisa diedit visual.

## 2. Selisih hari ini vs yang dituju

| Bagian | Hari ini | Yang dituju |
| --- | --- | --- |
| Beranda | `front-page.php` di dalam tema | halaman "Beranda" di Pages, ditetapkan sebagai front page |
| Isi halaman | HTML di `post_content` | data Elementor + HTML cadangan di `post_content` |
| Editor | Block Editor / HTML | Elementor: judul, teks, gambar, tombol |
| Header/footer | tema | tetap tema (Elementor gratis) |
| Menu, media | importer tema | tetap, tidak berubah |
| Langkah pasang | aktifkan tema | aktifkan tema + pasang plugin Elementor |

## 3. Fakta terukur 2026-11-02

- Elementor 4.3.5 aktif di `C:/laragon/www/wp-exito-test`; Default Kit #148 ada;
  elemen `container` tersedia dan dipakai Elementor versi ini.
- Tujuh halaman hasil impor (#117–#123) hanya punya meta
  `_siteflow_page_key`. Nol halaman punya `_elementor_edit_mode` atau
  `_elementor_data`, jadi Elementor belum memegang isi halaman mana pun.
- `style.css` tema 11.588 byte. Hanya **2** selector memakai anak langsung (`>`),
  dua-duanya `.hero-copy > p`; ada **5** deklarasi `display: grid|flex`.
  Artinya pembungkus tambahan Elementor hanya berpotensi mengganggu dua aturan,
  bukan seluruh lembar.
- `page.php` membungkus isi dengan `<main class="siteflow-page">`. `style.css`
  memakai selector `main` polos dan tidak punya aturan `.siteflow-page`, jadi
  kelas tambahan itu tidak mengubah rupa.
- Situs uji memakai permalink polos (`?page_id=`). Tautan halaman tetap sah.
- Elementor 4.3.5 di PHP 8.4 menulis satu deprecation notice ke log
  (`Implicitly marking parameter $key as nullable`). Tidak tampil di halaman dan
  tidak menghalangi; dicatat supaya tidak dikira kerusakan tema.

## 4. Bentuk data Elementor

Dibaca dari sumber plugin yang terpasang, bukan dari ingatan:

| Tempat | Isi | Arti |
| --- | --- | --- |
| `_elementor_data` | JSON larik elemen | susunan halaman: pembungkus dan widget |
| `_elementor_edit_mode` | `builder` | penanda "halaman ini dipegang Elementor" |
| `_elementor_template_type` | `wp-page` | jenis dokumen |
| `_elementor_version` | `4.3.5` | versi yang menulis data |

Satu elemen berbentuk:

```
{ "id": "7char", "elType": "container|widget", "widgetType": "...",
  "settings": { ... }, "elements": [ ... ] }
```

Widget yang dipakai hanya yang ada di Elementor gratis dan cocok dengan isi
mockup: `heading`, `text-editor`, `image`, `button`. Pembungkus memakai
`container` dengan `html_tag` (`section`, `div`, `header`, `footer`).

Widget `html`, `shortcode`, `sidebar`, dan `wordpress` **dilarang** oleh meja
periksa. Kalau salah satu muncul, build gagal: itu berarti kita diam-diam
mengembalikan HTML yang justru mau dibuang.

## 5. Aturan konversi HTML → elemen

| Markah asli | Menjadi | Catatan |
| --- | --- | --- |
| `section`, `article`, `div` pembungkus | `container` | kelas asli dipindah ke `_css_classes`; lebar penuh; padding dan jarak 0 supaya CSS tema yang mengatur |
| `h1`–`h6` tanpa markah dalam | `heading` | ukuran judul dipertahankan |
| `h1`–`h6` yang berisi tautan/penekanan | `text-editor` | supaya tautan di dalam judul tidak hilang |
| `p`, `ul`, `ol`, `blockquote`, teks kecil | `text-editor` | editor kaya; tautan, tebal, miring tetap ada dan diedit sebagai teks, bukan kode |
| `img` dari slot foto | `image` | memakai id lampiran hasil impor media + teks alternatif asli |
| `a` bergaya tombol (kelas `button`, `btn`, `cta`) | `button` | tautan halaman/tel/WhatsApp jadi URL nyata; tidak ada `#` |
| `a` di dalam paragraf | tetap di dalam `text-editor` | tautan teks biasa |
| deretan kartu berulang | `container` bersarang | struktur dan kelas asli dipertahankan |
| foto latar lewat CSS | tetap di stylesheet | tidak bisa diganti dari Elementor gratis |
| tabel, formulir, iframe | tidak didukung | mockup sekarang tidak memakainya; kalau muncul, build gagal dan itu keputusan terpisah |

Aturan tambahan:

- id elemen dibuat deterministik dari slug halaman + urutan, supaya build ulang
  menghasilkan data yang bisa dibandingkan.
- token `[[siteflow:gambar:...]]` dan `[[siteflow:halaman:...]]` tetap dipakai
  di berkas JSON tema, lalu diganti importer dengan id lampiran dan permalink.
- teks di dalam `text-editor` memang rich text (bisa mengandung `<strong>` dan
  `<a>`). Yang klien lihat dan klik adalah teks berformat, bukan kode HTML.

## 6. Beranda

- `front-page.php` dicabut dari tema. Beranda lahir sebagai halaman "Beranda"
  dari isi `index.html` (tanpa header/footer), sama seperti halaman isi lain.
- Importer menetapkan `show_on_front = page` dan `page_on_front` ke halaman itu.
  Item menu "Beranda" tetap tautan kustom ke `home_url('/')`.
- `page.php` tetap menjadi pembungkus; pengukuran di bagian 3 menunjukkan kelas
  `siteflow-page` tidak mengubah rupa.
- Kalau Elementor mati atau belum dipasang, `post_content` cadangan tampil lewat
  `page.php`. Tidak ada halaman kosong.

## 7. Importer

Urutan satu kali jalan:

1. unggah semua `images/` ke pustaka media (sama seperti sekarang);
2. buat/perbarui halaman: tulis `post_content` cadangan dan data Elementor;
3. ganti token di data Elementor: gambar → id + URL lampiran, halaman → permalink;
4. tulis meta `_elementor_edit_mode`, `_elementor_template_type`,
   `_elementor_version`, `_elementor_data`;
5. buat menu dan pasang ke lokasi `primary` (tidak berubah);
6. tetapkan beranda statis;
7. simpan versi isi.

Penjaga editan klien:

- Saat impor, sidik data Elementor yang ditulis disimpan di
  `_siteflow_elementor_hash`.
- Saat impor ulang, kalau `_elementor_data` yang ada **berbeda** dari sidik itu,
  halaman dianggap sudah diedit klien. Halaman itu **dilewati**, tidak ditimpa,
  dan jumlah halaman yang dilewati muncul di notifikasi wp-admin.
- Halaman yang belum disentuh klien tetap diperbarui saat tema dibangun ulang.
- Meta Elementor ditulis walaupun plugin sedang mati, supaya begitu plugin
  dipasang halaman langsung dikenali tanpa impor ulang.

## 8. Rupa: tetap sama, bukan desain ulang

Ini pekerjaan penerjemahan bentuk, bukan kesempatan mendesain ulang. Palet,
tipografi, jarak, dan komposisi tetap dari `DESIGN.md` dan mockup yang sudah
disetujui.

- Nol warna, gradien, bayangan, atau ikon baru.
- Nol emoji dan nol penanda hias.
- CSS tema tetap sumber rupa. Penyesuaian hanya kalau pembungkus Elementor
  memutus selector; dari pengukuran, kandidatnya dua aturan `.hero-copy > p`.
- Tombol Elementor yang tidak terjangkau CSS tema diberi warna dan bentuk dari
  nilai CSS yang sudah ada, bukan nilai karangan.
- Bukti rupa: potret sebelum vs sesudah di lebar 1440 dan 375, halaman beranda
  dan satu halaman isi. Bedanya harus bisa dijelaskan satu per satu; kalau tidak,
  perbaiki jembatan CSS, bukan desainnya.

## 9. Meja periksa: tambahan

| Kunci | Gagal kalau |
| --- | --- |
| `elementor_payload` | JSON tidak sah; ada widget di luar daftar; ada widget `html`/`shortcode`; token gambar/halaman tidak dikenal; gambar tanpa lampiran padanan; tautan tanpa halaman tujuan |
| `front_page` | tema masih membawa `front-page.php`, atau tidak ada halaman beranda di `halaman.json` |

Perubahan lain:

- `front-page.php` keluar dari daftar berkas wajib; `content/<slug>.json`
  masuk daftar berkas wajib per halaman.
- Batas ukuran M-11 ikut menghitung berkas JSON.
- Banner gerbang menampilkan jumlah halaman yang punya data Elementor.

## 10. Tes

- Pembangun: `index.html` + `pages/<slug>.html` → `content/<slug>.html` dan
  `content/<slug>.json`; pemetaan judul/teks/gambar/tombol benar; nol widget
  `html`; token hanya yang dikenal; `front-page.php` tidak dibuat; beranda
  masuk `halaman.json`.
- Importer (tes potongan teks functions.php): memuat kunci meta Elementor,
  kunci sidik, penjaga lewati, penetapan beranda statis.
- Meja periksa: gagal pada JSON rusak, widget terlarang, token asing; lolos pada
  kandidat satu halaman.
- Suite sqlite penuh hijau sebelum laporan.
- Bukti nyata di Laragon: pasang ulang tema, impor, buka satu halaman di
  Elementor, ubah satu judul, simpan, lalu pastikan halaman depan ikut berubah
  dan editor menampilkan widget (bukan satu blok HTML). Potret sebelum/sesudah.
- CodeRabbit tetap dilewati sesuai keputusan pemilik; pemeriksaan di browser
  dilakukan tangan.

## 11. Urutan kerja

1. Pembangun: penerjemah HTML → elemen, bentuk tema tanpa `front-page.php`,
   berkas JSON per halaman, tes pembangun.
2. Importer: tulis meta Elementor, ganti token, beranda statis, penjaga editan,
   tes potongan.
3. Meja periksa + layar + README (langkah plugin Elementor ditulis jujur).
4. Suite penuh, build ZIP, pasang di Laragon, uji edit, potret pembanding.
5. Laporan ke pemilik dengan angka sebelum/sesudah.

Cadangan sebelum menyentuh jalur baru:

- tema yang sekarang sudah terpasang tetap ada; ZIP-nya bisa dibuat ulang dari
  build #8;
- database situs uji sudah dicadangkan ke
  `C:/laragon/backup/wp-exito-test-2026-11-02.sql`;
- jalan pulang: aktifkan lagi `exito-client-theme` atau pulihkan cadangan itu.

## 12. Risiko dan batas

- Rupa bisa bergeser karena pembungkus tambahan Elementor. Ditekan dengan kelas
  asli yang dipertahankan, pengukuran selector, dan potret pembanding.
- Elementor gratis: header/footer tidak bisa diedit, foto latar CSS tidak bisa
  diganti, dan tidak ada Theme Builder.
- Skema data Elementor bisa berubah di versi besar. Data lama biasanya
  dimigrasikan plugin; uji setelah pembaruan besar tetap perlu.
- Impor ulang tidak boleh menimpa editan klien; penjaga sidik menanganinya,
  dengan konsekuensi halaman yang diedit tidak ikut pembaruan tema.
- Elementor menambah satu langkah pasang dan satu ketergantungan plugin.
- Permalink polos di situs uji bukan masalah, tetapi situs klien sebaiknya
  memakai permalink cantik; tidak diubah oleh rancangan ini.

## 13. Tidak termasuk

- Elementor Pro, Theme Builder, dan header/footer yang bisa diedit.
- Mengedit CSS dari Elementor; stylesheet tema tetap sumber rupa.
- Membuat foto latar CSS bisa diganti dari Elementor.
- Desain ulang halaman.
- Memasang atau membundel plugin Elementor dari dalam tema.
- Memindahkan menu ke Elementor.

## 14. Keputusan yang diminta

Satu: setujui rancangan ini untuk mulai ngoding.

Tiga hal yang saya pilih sebagai bawaan dan boleh Anda tolak:

1. HTML cadangan tetap disimpan di `post_content`; yang diedit klien adalah data
   Elementor. Tujuannya supaya situs tidak kosong kalau plugin mati.
2. Halaman yang sudah diedit klien dilewati saat impor ulang, tidak ditimpa.
3. Meta Elementor ditulis walaupun plugin belum terpasang, supaya pemasangan
   plugin belakangan langsung dikenali.
