# Rancangan: unggah bahan klien lewat ZIP + batas aset

Tanggal: 2026-11-06. Status: **menunggu persetujuan pemilik. Nol suntingan kode.**

## 0. Arah satu kalimat

Satu ZIP berisi semua bahan klien dibuka menjadi aset per berkas seperti yang
sudah ada sekarang, dan batas "boleh berapa banyak" dipindah tempatnya: gudang
tanpa batas, meja kerja Codex pakai satu angka yang bisa diubah sendiri.

## 1. Keadaan yang diukur hari ini

| bagian | kenyataan | tempat |
|---|---|---|
| batas dari server PHP | 2.048 MB per berkas, total kiriman 2 GB, maksimal 20 berkas sekaligus, memori 512 MB | `php.ini` Laragon (php-8.4.15) |
| batas yang dipakai aplikasi | 20 MB per berkas, dan **satu berkas per klik** | `app/Http/Controllers/ProjectController.php:265` |
| bentuk formulirnya | satu tombol pilih berkas + satu dropdown kategori | `resources/views/workspace/mockup-gate.blade.php:505` |
| batas bahan yang dikirim ke Codex | maksimal 8 gambar, 4 MB per gambar, hanya png/jpg/jpeg/webp/svg, hanya kategori logo/foto/referensi | `app/Support/MockupCodexAssets.php:22` |
| kemampuan membuka ZIP | ekstensi `zip` terpasang dan sudah dipakai kelas lain di proyek ini | `app/Services/WpThemeInspector.php` |

Bukti jalurnya belum berat: uji nyata hari ini menyalin 4 berkas (5,16 MB) ke
folder kerja Codex. Kandidatnya selesai 6,7 menit dengan 499.231 token, dan dua
dari tiga fotonya tidak perlu dibuat AI. Tidak ada tanda tambahan beban.

Yang **belum** terukur: berapa token yang dibayar Codex untuk setiap gambar
klien yang dia lihat. Jadi klaim "makin banyak aset makin mahal" sampai sekarang
belum punya angka, dan tidak boleh saya katakan sebagai fakta.

## 2. Jawaban atas dua pertanyaan pemilik

**Bisa unggah lewat ZIP?** Bisa, tanpa pasang apa pun. Yang menahan sekarang
bukan server (server sudah 2 GB), tapi aturan aplikasi sendiri: satu berkas per
klik, maksimal 20 MB.

**Boleh tanpa batas?** Perlu dipisah dua tempat, karena akibatnya beda:

- **Gudang** (daftar aset proyek). Di sini tanpa batas itu aman. Akibatnya cuma
  pemakaian disk. Usulan: lepas batas per berkas jadi satu angka besar di config,
  dan form bisa menerima banyak berkas sekaligus.
- **Meja kerja Codex** (berkas yang benar-benar disalin ke folder tempat Codex
  bekerja). Di sini "tanpa batas" punya konsekuensi nyata: Codex dibatasi 35
  menit dan setiap gambar yang dia buka memakan token. Kalau satu ZIP berisi
  200 foto ikut semua, yang berisiko bukan biayanya, tapi kandidat yang mati
  sebelum halaman terakhir tertulis. Sudah terbukti di ronde-ronde sebelumnya
  bahwa waktu adalah pengikatnya, bukan kemauan model.

Usulan saya: gudang tanpa batas, meja kerja pakai satu angka longgar di config
(mis. 20 gambar), dan `aset.txt` mencatat berapa berkas yang dilewati supaya
Anda tahu bahan mana yang belum terpakai.

## 3. Tahap kerja (kalau disetujui)

1. **Tahap 1 - ZIP masuk.** Form aset menerima berkas `.zip`. ZIP dibuka ke
   folder sementara, setiap gambar di dalamnya jadi baris aset biasa
   (`mockup-assets/{proyek}/...`), ZIP-nya dibuang. Penjaga yang wajib ikut:
   nama berkas di dalam ZIP tidak boleh menunjuk keluar folder (zip slip),
   hanya ekstensi yang dikenal, jumlah berkas dan total ukuran setelah dibuka
   dibatasi (anti zip bomb), nama berkas dibuat aman, dan hasilnya dilaporkan
   sebagai "X berkas masuk, Y dilewati karena apa".
2. **Tahap 2 - kategori isi ZIP.** Diambil dari nama folder di dalam ZIP
   (`logo/`, `foto/`, `referensi/`); kalau tidak ada, pakai dropdown yang
   dipilih saat unggah; dokumen (pdf/docx) tetap masuk gudang tapi tidak dikirim
   ke Codex seperti sekarang.
3. **Tahap 3 - batas pindah ke config.** Angka 8 gambar dan 4 MB tidak lagi
   tertulis di kode, tapi dibaca dari config supaya bisa Anda ubah sendiri tanpa
   menyentuh kode. Gudang boleh tanpa batas.
4. **Tahap 4 - uji nyata.** Satu ZIP berisi sekitar 20 berkas campuran (logo,
   foto, pdf, satu nama aneh) diunggah, lalu satu kandidat dijalankan.
   Dibandingkan dengan kandidat 56 hari ini: 6,7 menit, 499.231 token, 2 gambar
   AI, 2 foto klien terpasang.

## 4. Yang harus pemilik putuskan

**A. Batas meja kerja.** (1) angka longgar di config, mis. 20 gambar, sisanya
dilewati dan dicatat, atau (2) benar-benar tanpa batas. Rekomendasi: (1).

**B. Kategori isi ZIP.** (1) dari nama folder, dropdown jadi cadangan, atau
(2) satu kategori untuk seluruh ZIP. Rekomendasi: (1), karena paling sedikit
menyuruh Anda mengklik.

**C. Batas per berkas di gudang.** Usulan awal 100 MB per berkas, dan form boleh
memilih banyak berkas sekaligus (bukan cuma ZIP).

## 5. Yang tidak diubah

Teks brief, aturan desain, alur kandidat, cara foto klien dipulihkan, dan
jalur proyek tanpa aset (tetap persis seperti sekarang).

## 6. Keputusan pemilik (2026-11-06, sesudah rancangan ini dibaca)

1. **Panel "Aset & Materi Desain Klien" jadi tempat unggah ZIP.** Satu ZIP berisi
   semua bahan klien masuk sekali jalan; tidak lagi satu berkas per klik.
2. **Desain referensi keluar dari panel itu.** Panel itu khusus bahan klien
   (logo, foto, dokumen). Referensi dapat **kartu sendiri**.
3. **Aset milik klien sendiri tidak dibatasi.** Batas 8 gambar / 4 MB yang saya
   pasang di tahap 1 dilepas untuk berkas asli klien.

Konsekuensi yang perlu dia tahu sebelum kode ditulis:

- Batas lama (8 gambar, 4 MB) bukan penjaga biaya, tapi penjaga waktu: Codex
  cuma punya 35 menit. Tanpa batas, ZIP berisi 200 foto akan dikirim semua ke
  folder kerjanya. Belum ada angka berapa token per gambar yang dia buka, jadi
  ini akan diukur, bukan ditebak: satu ZIP besar dijalankan nyata, lalu waktu,
  token, dan jumlah halaman yang sempat ditulis dibandingkan dengan kandidat 56
  hari ini (6,7 menit, 499.231 token, 3 halaman).
- Kartu referensi baru menyentuh bagian brief yang sampai sekarang mati: slot
  `$referensiPath` di `MockupCodexBrief::build()` tidak pernah diisi pemanggilnya,
  jadi bagian "03 - Desain referensi (acuan mengikat)" tidak pernah muncul. Kartu
  ini mengisi nilai itu; teks brief tidak diubah.

## 7. Dua hal yang masih perlu dia jawab

- **Kategori isi ZIP:** dibaca dari nama folder di dalam ZIP (`logo/`, `foto/`,
  `dokumen/`) dengan dropdown sebagai cadangan, atau satu kategori untuk seluruh
  ZIP, atau ditebak dari nama berkasnya.
- **Isi kartu referensi:** pilih dari 6 potret gaya yang sudah ada di disk,
  unggah gambar acuan sendiri, atau keduanya (enam potret jadi pilihan cepat,
  unggah jadi jalan lain).

## 8. Yang terpasang (2026-11-06, sesudah dua pertanyaan itu dijawab)

Jawabannya: kategori dibaca dari nama folder, dan kartu referensi berisi acuan
milik klien sendiri (tautan situs, gambar, atau ZIP template).

**ZIP masuk sekali jalan**

- Berkas baru `app/Support/MockupAssetZip.php`: satu ZIP dibuka menjadi satu
  baris aset per berkas, sama seperti hasil unggah satu per satu.
- Kategori per berkas diambil dari folder paling luar: `logo/`, `foto/`,
  `dokumen/`, `company-profile/`, `pendukung/`. Folder yang tidak dikenali
  memakai kategori yang dipilih di dropdown.
- Folder `referensi/` di dalam ZIP tidak dijadikan aset; pesannya mengarahkan ke
  kartu Referensi Desain.
- Penjaga yang tinggal cuma penjaga mesin: nama berkas yang menunjuk keluar
  folder tidak dibuka, sampah ZIP (`__MACOSX`, `Thumbs.db`) dilewati, berkas
  yang bukan gambar/dokumen dilewati, maksimum 300 berkas dan 1 GB sesudah
  dibuka. Ini bukan batas bahan klien; ini batas supaya satu arsip rusak tidak
  menghabiskan disk server.
- `ProjectController::storeFile` bercabang: ekstensi `.zip` lewat kelas itu,
  berkas biasa lewat jalan lama. Aturan ukuran dinaikkan dari 20 MB ke 2 GB,
  sebatas yang server izinkan (`upload_max_filesize` dan `post_max_size` sudah
  terukur 2 GB, `max_file_uploads` 20).
- Layar: tombol "Unggah Aset Tambahan" menerima `.zip`; opsi "Referensi Desain"
  dihapus dari dropdown; balasan "N berkas masuk (Logo Klien 3, ...). 2
  dilewati: ..." sekarang benar-benar tampil karena panel itu dulu tidak
  menampilkan pesan apa pun.

**Batas bahan klien dilepas**

- `MockupCodexAssets` tidak lagi punya `MAKS_GAMBAR`/`MAKS_MB`: semua logo dan
  foto klien disalin ke folder kerja Codex. Yang tetap disaring hanya jenis
  berkas (dokumen tidak bisa dipakai Codex sebagai gambar).
- Kategori `referensi` keluar dari kelas itu: acuan tidak dikirim sebagai bahan.

**Kartu Referensi Desain**

- Panel "Aset & Materi Desain Klien" berganti nama jadi "Aset & Materi Klien"
  dan hanya menampilkan bahan (baris lama berkategori `referensi` tidak dihitung
  lagi).
- Kartu baru di bawahnya: bentuk acuan = tautan situs / gambar / ZIP template /
  kosongkan. Tersimpan di kolom proyek yang sudah ada (`design_reference_type`,
  `design_reference_url`, `design_reference_path`), bukan di tabel aset.
- Rute baru `pages.projects.reference.store` (`POST /projects/{project}/design-reference`).
- Form Request dan Edit Project tidak lagi menyalin berkas referensi ke tabel
  aset; penyalinan itulah yang membuat acuan klien muncul di panel bahan.

**Terukur**

- Tes baru: `tests/Feature/MockupAssetZipTest.php` (10 tes) dan
  `tests/Feature/DesignReferenceCardTest.php` (6 tes, termasuk layar meja kerja
  yang benar-benar dirender).
- Suite penuh `DB_CONNECTION=sqlite`: 442 tes lolos, 1 gagal. Yang gagal adalah
  `SeoUrlAnalysisTest`, sudah gagal sebelum pekerjaan ini dan menunggu kata dia.
- Review CodeRabbit belum bisa dijalankan: `coderabbit` tidak ada di mesin ini.

**Belum dikerjakan**

- Acuan dari kartu ini belum diisi ke slot `$referensiPath` di brief, jadi
  bagian "03 - Desain referensi" masih belum muncul di kandidat. Ini tahap
  berikutnya dan butuh satu keputusan: untuk tautan, aplikasi harus mengambil
  gambarnya sendiri (Codex tidak punya akses internet), potret situs lewat
  Chromium yang sudah ada, atau dia cukup unggah gambar/ZIP.
- ZIP besar belum dijalankan nyata. Batas waktu Codex 35 menit tetap jadi plafon
  yang mengikat; itu yang akan diukur, bukan ditebak.

## 9. Acuan masuk ke Codex + tautan dipotret (2026-11-06, sesudah jawabannya)

Jawabannya: "kalo refrensinya link webnya potret aja situsnya". Jadi tidak ada
pilihan baru di layar; tautan diperlakukan sama seperti gambar.

**Yang berubah**

- `PageSnapshot::captureUrl()` memotret satu situs ukuran penuh lewat Chromium
  yang sama dengan potret kandidat.
- Menyimpan acuan bertipe tautan langsung memicu potret. Hasilnya masuk ke
  `design-references/{proyek}/potret-{acak}.png` dan kolom jalur terisi; kalau
  Chromium gagal, tautannya tetap tersimpan, jalurnya tetap kosong, dan
  layarnya memunculkan tombol "Ambil ulang potret" beserta sebab kegagalannya.
- Kartu acuan menampilkan potretnya di samping tautan, jadi dia bisa melihat
  apa yang sebenarnya dilihat Codex nanti.
- `MockupCodexReference::letakan()` menyalin acuan ke folder kerja Codex di
  `acuan/acuan.{ekstensi}`: gambar dipakai apa adanya, ZIP template dibuka
  gambar terbesar di dalamnya, SVG dan berkas yang hilang dianggap tidak ada.
  Letaknya di subfolder supaya tidak ikut terkumpul sebagai foto website oleh
  pembangun tema.
- `MockupCodexRunner::tulisBrief()` akhirnya mengisi slot kelima brief. Bagian
  "03 - Desain referensi (acuan mengikat)" muncul begitu ada acuan; tanpa acuan
  brief tetap seperti kemarin. Teks brief tidak disentuh.
- Acuan yang rusak tidak menggagalkan kandidat: cuma dicatat di log, Codex
  kembali ke arah desain biasa.

**Terukur**

- Potret nyata lewat jalur penyimpanan aplikasi: example.com 5,3 detik,
  38 KB, 1440x900. Sebelum ini baliatvride.online 7,2 detik, 2,9 MB,
  1440x5729 (halaman panjang, potret ukuran penuh).
- Suite penuh `DB_CONNECTION=sqlite`: 450 tes lolos, 1 gagal. Yang gagal tetap
  `SeoUrlAnalysisTest` yang lama, belum disentuh.
- Tes baru: 3 tes potret di kartu acuan + 3 tes `MockupCodexReference` + 2 tes
  brief (dengan acuan dan tanpa acuan). Di DesignReferenceCardTest Chromium
  dipalsukan; yang diuji adalah isi berkas dan kolom, bukan Chromium.
- Review CodeRabbit masih belum bisa: `coderabbit` tetap tidak ada di mesin ini.

**Belum diukur nyata**

- Satu kandidat dengan acuan sungguhan. Yang terpasang baru jalur berkasnya;
  belum dijalankan `mockup:codex` dengan potret di folder kerja, jadi belum
  terbukti Codex benar-benar meniru gambar itu dan bukan membuat mockup sendiri.
- ZIP besar masih menunggu, sama seperti di bagian 8.

## 10. Terukur nyata: 1 kandidat dengan acuan potret situs (2026-11-06)

Proyek uji: 11 (PT SH transport, bahan klien 4 berkas sudah ada). Acuan:
tautan `https://baliatvride.online` dipotret lewat jalur kartu (7,8 detik,
3.044.549 byte, 1440x5729) lalu dipakai sebagai acuan mengikat. Kandidat baru
id 57 di slot 1; slot 2 (terpilih) dan slot 3 (tes aset) tidak disentuh.

**Angka**

| | tanpa acuan (id 56) | dengan acuan (id 57) |
|---|---|---|
| waktu | 6 m 42 dtk | 3 m 45 dtk |
| token | 499.231 | 344.777 (masuk 334.824, keluar 9.953) |
| halaman | 3 | 3 |
| foto AI yang dibuat | ada | nol, dua slot memakai foto klien |

Acuannya benar-benar dibaca: token desainnya keluar oranye `#F36A21`, latar
`#F5F5F3`, tombol radius 2 px, "white cards, square corners, subtle shadow,
orange labels and thin dividers" - itu bahasa visual situs acuannya, bukan
bahasa visual logistik pada umumnya. Bagian 03 brief muncul dan
`desain-referensi.txt` mencatat `design: acuan/acuan.png`.

**Temuan baru (belum diperbaiki, menunggu kata dia)**

Codex di mesin ini sering menulis berkas dengan tanda awal UTF-8 (BOM,
`EF BB BF`). Yang memakai BOM pada run ini: `foto-slot.txt`, `ide.txt`,
`desain-referensi.txt`, `design-tokens.json`, `rencana.json`. Akibatnya:

- baris pertama `foto-slot.txt` tidak terbaca, dan baris pertama itu slot
  `hero`. Foto hero (bahan klien) hilang, jadi potret pratinjau dan halaman
  akhirnya bolong di pahlawan. Run-run lama juga kena BOM (id 49, 50, 52),
  tetapi slot pertamanya terselamatkan oleh cadangan dari cache gambar;
  cache tidak bisa menyelamatkan berkas milik klien.
- `ide.txt` terbaca sebagai "# Ide" (baris pertama), jadi kalimat ide di kartu
  kandidat jadi sampah.
- `design-tokens.json` dan `rencana.json` tidak bisa diurai sebagai JSON kalau
  ber-BOM. Tidak ada pembaca tetap memakai dua berkas itu hari ini, jadi
  belum ada yang pecah; ini jebakan yang siap aktif.

Perbaikan yang diusulkan: buang BOM saat berkas Codex dibaca (satu tempat di
pembaca), bukan mengubah brief atau perilaku Codex.

## 11. BOM dibuang di satu fungsi baca (2026-11-06, sesudah dia bilang "kerjakan")

**Keputusan akhir** (sudah ditanyakan ke Claude gratis lewat akun dia sendiri):
(a) buang byte penanda di sisi pembaca, dan (d) gagal keras hanya untuk berkas
yang tetap tidak bisa diurai. Opsi (c), menormalkan folder kerja sesudah
Codex selesai, dibatalkan: satu pintu sudah cukup, menulis ulang berkas berarti
logika yang sama hidup di dua tempat. Opsi (b) tetap ditolak: itu mengubah
brief tulisan pemilik untuk urusan teknis.

**Yang terpasang**

Berkas baru `app/Support/MockupCodexText.php`, satu fungsi baca untuk semua
berkas teks hasil Codex:

- berkas tidak ada -> string kosong (pemanggil sudah punya pesan sendiri);
- UTF-16LE / UTF-16BE (keluaran `>` PowerShell 5) dikonversi ke UTF-8;
- semua tanda awal UTF-8 dibuang, termasuk yang nyelip di tengah;
- CRLF dan CR jadi LF, jadi nilai jalur tidak membawa `\r`;
- berkas yang ada tetapi tidak terbaca atau bukan UTF-8 valid ->
  `RuntimeException`. Runner sudah menangkap Throwable dan menulis alasannya
  ke kartu kandidat, jadi kegagalan kelihatan, bukan tertutup cadangan.
- setiap berkas yang dinormalisasi dicatat di log.

Sembilan pembaca dialihkan lewat pintu itu:
`MockupCodexPhotos.php:113` dan `:180` (desain-referensi.txt, foto-slot.txt),
`MockupCodexRunner.php:564` `:591` `:620` `:626` (halaman, slot, ide.txt),
`WpThemeBuilder.php:75` `:358` (index.html dan pages/*.html),
`MockupCandidateController.php:311` (pratinjau hidup).

Cadangan cache Codex sekarang dicatat di log setiap kali dipakai
(`MockupCodexPhotos.php:88`). Jalurnya tidak diubah, hanya tidak lagi diam.

**Bukti**

Pola lama diuji pada baris ber-BOM, hasil nyata: `preg_match` mengembalikan
`0`, dan berkas UTF-16 memang `mb_check_encoding = false`. Jadi temuan di
bagian 10 terukur, bukan dugaan.

Tes baru `tests/Feature/CodexBerkasBomTest.php`: 10 tes, 18 penegasan, semua
lolos (sqlite). Isinya: pintu baca (BOM, UTF-16, CRLF, berkas tiada, UTF-8
cacat), foto hero tetap terpasang dari manifest ber-BOM dan manifest
UTF-16LE, jalur foto bersih dari `\r`, gambar rancangan tetap dikecualikan
walau catatan ber-BOM, kalimat ide bersih, slot foto terbaca di halaman
UTF-16, dan pemulihan cache tercatat di log.

Suite penuh (sqlite): 460 lolos, 1 gagal. Yang gagal tetap
`tests/Feature/SeoUrlAnalysisTest.php:49`, temuan lama yang menunggu dia.

CodeRabbit tidak bisa dijalankan: `coderabbit` dan `ocr` tidak terpasang di
mesin ini. Review tahap ini belum ada.

**Belum terukur**: satu run Codex nyata sesudah tambalan ini. Bukti terakhir
yang dibutuhkan adalah foto bahan klien di slot pertama ikut terpasang tanpa
menyentuh cache.
