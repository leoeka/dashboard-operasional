# Rancangan: alur "AI Mockup Builder" dijalankan lewat API OpenAI (2026-10-21)

**Status: rancangan untuk dicoret pemilik. Nol berkas kode disunting.**
Sumber: dokumen rancangan dari ChatGPT yang dilampirkan pemilik (6 tahap, 9 bagian),
dibaca ulang terhadap isi repo hari ini. Perintah pemilik: *"aku mau tetap pake rancangan ini
tapi tetap memakai API"* - jadi setiap tempat yang di dokumen asal menyebut Codex diganti
jalur API OpenAI yang sudah ada, dan sisanya dinilai satu per satu terhadap kode nyata.

Cara membaca tabel di bawah: **SUDAH ADA** = jalannya sudah ada dan sudah punya tes;
**PERLU DIBANGUN** = belum ada sama sekali; **PERLU DICORET** = bertentangan dengan keputusan
pemilik yang sudah terkunci, jadi tidak boleh dikerjakan sebelum dia mencoret keputusan lama.

---

## 1. Enam tahap dokumen asal vs enam tahap yang sudah jalan

| Tahap dokumen asal | Isi di repo hari ini | Status |
| --- | --- | --- |
| 01 Brief klien | `WebsiteBuilderController::runPipeline()` menggabungkan data proyek, kebutuhan website, dan aset klien; dua tahap pertama menyaring pasar sasaran dan meneliti pesaing nyata | SUDAH ADA — [app/Http/Controllers/WebsiteBuilderController.php:189](file:///C:/Program%20Codding/dashboard-operasional/app/Http/Controllers/WebsiteBuilderController.php#L189) |
| 02 Generate 3 konsep | Tiga kandidat dibuat dari tiga arah visual yang berbeda (editorial / modern & confident / calm & approachable), tiap kandidat generasi penuh, bukan satu desain yang diwarnai ulang; jumlah bisa diatur 2-3 | SUDAH ADA — [app/Services/GenerateMockupGptService.php:678](file:///C:/Program%20Codding/dashboard-operasional/app/Services/GenerateMockupGptService.php#L678) |
| 03 Pilih satu konsep | Pilihan disimpan sebagai indeks kandidat saat persetujuan; konsep lain tetap tersimpan untuk riwayat | SUDAH ADA — [app/Http/Controllers/WebsiteBuilderController.php:79](file:///C:/Program%20Codding/dashboard-operasional/app/Http/Controllers/WebsiteBuilderController.php#L79) |
| 04 Build WordPress | Tema dirakit **tanpa AI**: blok Gutenberg sungguhan disusun dari data yang sama dengan mockup yang disetujui | SUDAH ADA, tapi bentuknya berbeda — lihat bagian 3 |
| 05 Test dan packaging | Perakitan ZIP di antrean + endpoint progres; berkas ZIP disimpan dan proyek pindah ke selesai | SEBAGIAN — lihat bagian 4 |
| 06 WordPress Install ZIP | Satu `theme-install.zip`: theme + foto + logo + perakit halaman yang disuntik ke `functions.php` + README | SUDAH ADA — [app/Services/BundleExporterService.php:19](file:///C:/Program%20Codding/dashboard-operasional/app/Services/BundleExporterService.php#L19) |

Aturan utama dokumen asal ("build tidak boleh mulai sebelum satu konsep disetujui") **sudah
dijaga dua kali**: saat tombol ditekan dan saat pekerja antrean menjalankan
[app/Models/Project.php:151](file:///C:/Program%20Codding/dashboard-operasional/app/Models/Project.php#L151),
[app/Jobs/BuildProjectBundleJob.php:87](file:///C:/Program%20Codding/dashboard-operasional/app/Jobs/BuildProjectBundleJob.php#L87).

## 2. Nama komponen di dokumen asal: mana yang nyata

Enam dari tujuh nama benar-benar ada (`WebsiteBuilderController`, `GenerateMockupGptService`,
`ScreenshotService`, `BundleBuilderService`, `BundleExporterService`, `BundleController`).
Yang tidak ada: **`ClaudeWordPressBuilderService`**. Bukan kehilangan - bentuk kerjanya sudah
pernah dipakai lalu **dilepas sengaja**: perakit halaman sekarang mencatat alasannya di
dokumentasinya sendiri, yaitu bahwa menulis tema lewat Claude/GPT berulang kali menghasilkan
keluaran yang tidak cocok dengan bentuk yang dibutuhkan pemakainya
([app/Services/ElementorPageBuilderService.php:13](file:///C:/Program%20Codding/dashboard-operasional/app/Services/ElementorPageBuilderService.php#L13)).
Ini temuan paling penting dari pencocokan: dokumen asal ditulis dari ingatan proyek lama, dan
bagian yang paling ingin dia ubah (04) adalah bagian yang sudah pernah dicoba dan sudah pernah
dibuang karena sebab yang tercatat di kode.

## 3. "Codex membangun website" kalau diganti API: ada dua bentuk, dan salah satunya sudah ada drafnya

Dokumen asal memisahkan **spesifikasi desain** dari **implementasi WordPress**, dan meminta
preview memakai perakit desain yang sama supaya struktur yang dilihat klien menjadi kontrak
pembangunan. Permintaan itu persis bentuk yang sudah jalan: satu resolusi desain dipakai
bersama oleh mockup, demo, dan ZIP
([app/Support/MockupSite.php](file:///C:/Program%20Codding/dashboard-operasional/app/Support/MockupSite.php),
[app/Support/CompositionSpec.php](file:///C:/Program%20Codding/dashboard-operasional/app/Support/CompositionSpec.php)).

Jadi "tetap pakai API" punya dua tafsir, dan ini keputusan pemilik, bukan yang bisa saya tebak:

**Bentuk pertama (yang sekarang jalan, namanya Jalur A di dokumen lain).** API OpenAI memutuskan
*apa* dan *di mana* (palet, tipografi, urutan bagian, komposisi hero, header/footer, foto); kode
PHP yang merakit tema. Tidak ada token untuk tema: terukur nol
([.docs/hemat-biaya-proposal.md:23](file:///C:/Program%20Codding/dashboard-operasional/.docs/hemat-biaya-proposal.md#L23)).
Menjalankan rancangan ini dalam bentuk pertama = tidak mengubah apa pun di tahap 04.

**Bentuk kedua (Jalur B, sudah didraf, belum dicoret pemilik).** API OpenAI menulis sendiri
CSS dan markup tema, masih lewat panggilan API yang sama, lalu perakit yang sama memastikan apa
yang disetujui = apa yang dikirim. Drafnya ada di
[.docs/rancangan-jalur-b-terjaga.md](file:///C:/Program%20Codding/dashboard-operasional/.docs/rancangan-jalur-b-terjaga.md).
Ini yang mengisi tahap 04 dokumen asal tanpa Codex, dan ini juga yang harus melewati empat dari
enam penjaga yang tidak boleh dikorbankan.

Yang **tidak** berubah di kedua bentuk: tidak ada CLI, tidak ada PATH mesin, tidak ada proses
login, tidak ada pekerja antrean khusus. Semua alasan Codex dulu macet ada di bagian 5 dokumen
asal; API memotong bagian itu seluruhnya, karena kuncinya sudah dipakai jalan sejak awal lewat
`OPENAI_API_KEY` ([config/services.php:39](file:///C:/Program%20Codding/dashboard-operasional/config/services.php#L39)).

## 4. Yang benar-benar baru: meja periksa tahap 05

Ini bagian dokumen asal yang paling berharga, dan isinya bukan Codex. Tabel sembilan pemeriksaan
belum ada padannya di repo. Yang ada sekarang cuma empat: ZIP harus bisa dibuka dan berisi yang
dibutuhkan (dijaga tes), isi halaman tidak boleh kosong atau terpotong, tiga konsep harus lengkap
semua sebelum boleh ditampilkan, dan unduhan menolak kalau berkasnya hilang.

Rinci, jujur soal bisa-tidaknya di mesin ini:

| Pemeriksaan | Nilai sekarang | Bisa dikerjakan tanpa AI & tanpa Codex? |
| --- | --- | --- |
| Struktur paket: berkas wajib ada, ZIP terbaca | Sebagian (hanya saat unduh) | Ya |
| Checksum + versi build tercatat | Tidak ada | Ya, murah |
| Konten: semua halaman terjadwal terbentuk | Ya, tidak ada | Ya |
| Aset: logo dan foto yang dirujuk tersedia | Sebagian (daftar aset hilang dicatat) | Ya |
| Navigasi: menu + homepage menunjuk benar | Tidak diperiksa | Ya |
| Tampilan desktop/mobile lolos | Demo iframe 1440/768/390, tidak dinilai | Perlu aturan penilaian |
| PHP tidak ada syntax error | Tidak ada | Ya, asal `php` tersedia di mesin pembangunan |
| Instalasi di WordPress staging | Tidak ada, dan tidak ada staging di mesin ini | Tidak - perlu lingkungan |
| Fungsi: tanpa fatal error, tautan diuji | Tidak ada | Sebagian besar tidak |

Artinya: dari sembilan, **tujuh bisa dikejar sekarang**, dua sisanya menuntut WordPress staging
yang di mesin ini belum ada - itu bukan pekerjaan satu berkas dan tidak boleh dijanjikan.

## 5. Tiga tabel baru: jangan ditelan mentah

Dokumen asal mengusulkan `mockup_generations`, `mockup_concepts`, `website_builds`. Repo ini
menyimpan hal yang sama di tempat lain: isi konsep dan pilihan tersimpan sebagai satu berkas JSON
di kolom teks panjang pada tabel proposal
([database/migrations/2026_09_02_000002_widen_proposals_ai_reasoning_column.php](file:///C:/Program%20Codding/dashboard-operasional/database/migrations/2026_09_02_000002_widen_proposals_ai_reasoning_column.php)),
jejak tiap tahap di tabel pemeriksaan antrean, dan hasil ZIP di tabel berkas proyek. Kolom yang
diminta dokumen asal sudah ada, hanya bentuknya berbeda: `selected_concept_id` = indeks kandidat
terpilih, `status` = status proposal + status berkas proyek, `brief_snapshot` = isi JSON itu,
`artifact_path` = jalur ZIP.

Yang nyata-nyata belum ada dan tidak bisa ditambal dengan pemetaan: **nomor versi build** dan
**kunci anti-duplikat untuk permintaan berulang**. Kalau yang diinginkan pemilik bisa dihitung
("build mana yang menghasilkan ZIP ini, berapa kali dia diulang"), itu butuh kolom atau tabel
sendiri - bukan tiga tabel penuh. Rekomendasi saya: **satu** penambahan saja (versi + checksum
pada berkas proyek yang sudah ada), bukan normalisasi tiga tabel, karena memindahkan isi JSON ke
tiga tabel berarti menulis ulang semua pemakai yang sekarang membaca JSON itu, termasuk penjaga
paritas yang menjaga "yang disetujui = yang dikirim".

## 6. state machine: sudah ada, jangan dibuat lagi di atasnya

Enam status usulan (queued, generating_concepts, awaiting_selection, building, validating,
ready) sudah terpecah di repo jadi tiga tempat yang berbeda: status proyek
(`request` / `in_progress` / `completed`), status proposal (`pending` / `approved` / `rejected`),
status berkas proyek (`draft` / `building` / `exported` / `failed`), plus pemulihan per tahap di
tabel pemeriksaan antrean. Menambah lapisan status baru di atas ketiganya pernah menjadi sumber
cacat di repo ini (laporan sempat menanyakan status yang salah nama dan angkanya selalu nol).
Yang layak diambil dari dokumen asal cuma satu: status **siap** tidak boleh ditulis oleh tahap
build, tapi oleh meja periksa bagian 4.

## 7. Bentuk paket: ini keputusan yang sudah pernah diambil

Dokumen asal menyarankan satu ZIP berisi theme + plugin pendamping berisi wizard instalasi. Repo
ini **sudah sengaja membuang** format dua-komponen itu: tabel dan sisa-sisa paket model lama
dijatuuhkan lewat migrasi
([database/migrations/2026_09_09_000000_purge_zipwp_remnants.php:36](file:///C:/Program%20Codding/dashboard-operasional/database/migrations/2026_09_09_000000_purge_zipwp_remnants.php#L36)),
dan yang dipakai sekarang satu ZIP theme yang bisa diunggah dari menu Tema, dengan perakit halaman
dititipkan di dalam tema. Kalau pemilik ingin kembali ke format plugin pendamping, itu bukan
"menerapkan rancangan" tapi **membalik keputusan lama**, dan dia perlu mengatakannya terang-terangan.

Catatan yang justru layak diambil dari bagian ini: dokumen asal benar bahwa ZIP theme biasa tidak
mengisi database WordPress. Jawaban repo sudah ada dan sudah di jalur yang benar: importer di
dalam tema membuat halaman, menu, homepage, dan media saat tema diaktifkan, dan pembuatannya
idempoten supaya wizard yang dijalankan dua kali tidak menggandakan konten.

## 8. Biaya, karena ini bagian yang dulu menguras saldo

Angka yang terukur di dokumen hemat: foto **$0,063 per gambar**, tema **nol token**. Jadi tahap
02-03 (yang memakai API) adalah biaya teks, dan tahap 04 tidak menambah biaya sama sekali dalam
bentuk pertama. Dalam bentuk kedua, tahap 04 menambah panggilan teks per build - belum pernah
diukur, jangan ditulis sebagai angka. Pencatat pemakaian per panggilan (task #66) dan plafon dolar
per proposal (task #76) sempat dibangun 21 Oktober lalu **dicabut pemilik malamnya** ("ini nggak
usah") - lihat bagian 19. Dokumen asal menyebut "batasi izin" untuk agen; untuk API, batas yang
benar-benar ada sekarang adalah plafon keluaran 16.384 token, laju foto per menit, dan batas foto
per bagian - tidak ada plafon dolar.

## 9. Urutan pengerjaan yang saya sarankan (kalau rancangan ini disetujui)

1. Meja periksa bagian 4: checksum + versi build + periksa berkas wajib + periksa menu/homepage.
   Tidak menyentuh AI, tidak menyentuh tema, dan tiap item bisa dites sebelum item berikutnya.
2. Satu pertanyaan bentuk tahap 04 dijawab pemilik (bagian 3): tetap perakitan tanpa AI, atau
   Jalur B yang menulis CSS lewat API. Semua langkah setelah ini tergantung jawaban itu.
3. Kalau Jalur B: kerjakan draf yang sudah ada
   ([.docs/rancangan-jalur-b-terjaga.md](file:///C:/Program%20Codding/dashboard-operasional/.docs/rancangan-jalur-b-terjaga.md)),
   jangan menulis rancangan baru; aturan desain sudah ada di
   [`.docs/aturan-desain-sisi-klien.md`](file:///C:/Program%20Codding/dashboard-operasional/.docs/aturan-desain-sisi-klien.md).
4. Penambahan kolom versi/checksum saja (bagian 5), bukan tiga tabel.
5. Status "siap" dipindahkan dari tahap build ke meja periksa (bagian 6).
6. Dua pemeriksaan yang butuh WordPress staging diletakkan di daftar tunggu, dan tidak diklaim
   selesai sampai lingkungannya ada.

Urutan ini sengaja membalik dokumen asal: dia membangun adapter agen lebih dulu (langkah 4 di
dokumennya); saya menaruh meja periksa lebih dulu, karena meja periksa berguna untuk kedua bentuk
tahap 04 dan tidak bisa dirusak oleh pilihan itu.

## 10. Empat hal yang tidak boleh dikorbankan siapa pun yang mengerjakan ini

Enam penjaga yang tercatat di
[.docs/catatan-tahan-website-2026-10-20.md:70](file:///C:/Program%20Codding/dashboard-operasional/.docs/catatan-tahan-website-2026-10-20.md#L70)
masih berlaku penuh. Empat yang paling sering tersenggol oleh rancangan model ini:

- paritas PNG = demo = ZIP (yang disetujui klien adalah yang dikirim),
- persetujuan bisa berjalan tanpa penyedia AI (foto sisa boleh gagal dibeli, build tidak boleh),
- blueprint dibekukan setelah dibuat (retry tidak membayar ulang desain),
- `reusePersisted()` (foto yang sudah ada tidak dibeli dua kali).

## 11. Pertanyaan lama: yang sudah dijawab dan yang masih terbuka

21 Oktober, pemilik menjawab pertanyaan nomor 1 dengan uraian bentuk yang dia inginkan
("GPT menghasilkan tiga implementasi HTML/CSS atau spesifikasi layout ... Setelah dipilih, GPT
membangun versi WordPress final"). Jawabannya: **bentuk kedua (GPT ikut menulis tampilan), Jalur B**.
Bagian 12 dan 13 adalah consequences dari jawaban itu, dibaca terhadap kode hari ini.

Masih terbuka:

1. ZIP: tetap satu `theme-install.zip`, atau kembali ke theme + plugin pendamping? Kalau kembali,
   itu membalik keputusan 2026-09-09 dan harus dinyatakan.
2. Meja periksa: boleh saya kerjakan tujuh pemeriksaan yang tidak butuh staging, dimulai dari
   checksum + versi build?
3. Tiga tabel baru: saya tolak dan ganti satu kolom versi + checksum. Setuju, atau tetap mau
   tabel terpisah?
4. Tahan umum ("DITAHAN 2026-10-20") dibuka sebagian untuk pekerjaan ini, atau menunggu?
5. Gambar placeholder: sumber gambarnya mana (bagian 13), dan apakah placeholder boleh ikut ke
   situs final klien?

## 12. Alur yang pemilik mau, dicocokkan baris per baris

| Langkah yang pemilik tulis | Hari ini | Yang berubah |
| --- | --- | --- |
| GPT menghasilkan tiga implementasi HTML/CSS **atau spesifikasi layout** | spesifikasi layout: tiga kandidat = tiga arah visual, keluaran GPT adalah rencana desain, bukan HTML | **Ini masih dua jalan.** Lihat bagian 12.1 - yang ini memblokir semua sisanya |
| Laravel menyimpan setiap konsep secara terpisah | sudah: tiap kandidat utuh tersimpan di berkas JSON proposal, dengan rencana desain + halaman + asetnya | tidak ada |
| Renderer menjalankan ketiga halaman di lingkungan preview | sudah: demo langsung memakai perakit yang sama dengan PNG ([app/Http/Controllers/MockupPreviewController.php:39](file:///C:/Program%20Codding/dashboard-operasional/app/Http/Controllers/MockupPreviewController.php#L39)) | kalau GPT yang menulis HTML, perakit bersama ini tidak lagi jadi satu-satunya sumber - lihat 12.2 |
| `ScreenshotService` mengambil screenshot desktop **dan mobile** | **desktop saja**, 1440x900 tertulis tetap di kedua method ([app/Services/ScreenshotService.php:22](file:///C:/Program%20Codding/dashboard-operasional/app/Services/ScreenshotService.php#L22), [:54](file:///C:/Program%20Codding/dashboard-operasional/app/Services/ScreenshotService.php#L54)); mobile hari ini hanya iframe demo yang dilebarkan/di-sempitkan lewat CSS | tambah lebar + satu potret kedua per konsep (12.3) |
| Dashboard menampilkan ketiga preview agar tim memilih satu | sudah: tiga kartu, **satu gambar per kartu** ([resources/views/workspace/web-mockup.blade.php:37](file:///C:/Program%20Codding/dashboard-operasional/resources/views/workspace/web-mockup.blade.php#L37)) | kartu perlu slot gambar kedua (desktop + mobile berdampingan) |
| Setelah dipilih, GPT membangun versi WordPress final | **tidak ada AI di tahap ini**, nol token, dan alasannya tertulis di kode perakit halaman ([app/Services/ElementorPageBuilderService.php:13](file:///C:/Program%20Codding/dashboard-operasional/app/Services/ElementorPageBuilderService.php#L13)) | ini perubahan paling mahal dan paling riskan - lihat 12.4 |

### 12.1 Yang harus dipilih lebih dulu: HTML/CSS atau spesifikasi layout

Kata "atau" di kalimat pemilik adalah sisa keputusan yang belum dia tutup, dan dua-duanya punya
konsekuensi berbeda jauh:

- **Spesifikasi layout** (bentuk sekarang, hanya ditambah GPT menulis CSS tema di tahap akhir).
  Perakit bersama tetap satu, jadi jaminan "yang disetujui klien = yang dikirim ke klien" tetap
  bisa dites seperti sekarang.
- **HTML/CSS utuh per konsep.** Maka preview, PNG, dan ZIP harus dibangun dari berkas yang GPT
  tulis, bukan dari perakit bersama. Jaminan paritas yang selama ini dijaga tes (PNG = demo = ZIP)
  kehilangan dasarnya, karena tidak ada lagi satu perakit; yang tersisa hanya "kami berharap GPT
  menulis hal yang sama tiga kali".

Rekomendasi saya tetap jalan tengah: GPT menulis **CSS** (palet, tipografi, jarak, gaya header/footer)
dan markup bagian per bagian, tapi struktur besarnya tetap dari spesifikasi layout, sehingga ada
kerangka yang bisa dibandingkan ketika keluaran GPT cacat. Bentuk inilah yang sudah didraf di
[.docs/rancangan-jalur-b-terjaga.md](file:///C:/Program%20Codding/dashboard-operasional/.docs/rancangan-jalur-b-terjaga.md)
(aturan dulu, jatuh ke susunan lama kalau gagal, tetap tiga opsi).

### 12.2 Biaya nyata dari "GPT menulis HTML/CSS": tiga permukaan, bukan satu

Kalau GPT menulis tampilan, tiga tempat harus ikut sumber yang sama: PNG untuk kartu pilihan,
demo langsung untuk klien, dan ZIP untuk WordPress. Sekarang ketiganya sudah satu sumber; kalau
satu saja diganti jadi berkas GPT, muncul risiko baru: **dua permukaan bisa berbeda dan tidak ada
yang menyalakan alarm**. Untuk menutupnya, tiap konsep perlu menyimpan berkas HTML/CSS-nya sendiri,
dan perbandingan antar permukaan harus jadi bagian meja periksa (bagian 4), bukan jadi tes terpisah.

### 12.3 Screenshot mobile: angka, bukan tebakan

Satu potret = satu jalan Chromium, batas 90 detik per potret. Hitungannya:

- sekarang: 1 potret acuan desain (maksimal 2 URL pesaing) + 3 potret kandidat + 1 potret ulang
  saat konsep disetujui = **6 jalan Chromium**
- setelah: **10 jalan Chromium** (3 kandidat jadi 6 potret, potret ulang jadi 2)

Tidak ada biaya API di sini, hanya waktu: tiap potret memakan detik sampai belasan detik, dan
potret mobile setinggi halaman penuh bisa jauh lebih panjang daripada yang desktop. Alternatif
murah yang sudah ada: iframe demo bisa dilebarkan ke 390 hari ini juga, tanpa potret apa pun -
tapi itu bukan gambar yang bisa dibandingkan berdampingan di satu kartu.

Saya sarankan yang mahal dipakai sekali saja: **potret mobile hanya untuk kandidat yang sedang
dilihat/dipilih**,
bukan tiga-tiganya sekaligus, kecuali pemilik memang ingin ketiga konsep tampil dengan dua gambar
sejak awal.

### 12.4 "GPT membangun versi WordPress final": apa yang harus ikut dibangun

Ini yang harus dikerjakan kalau jawaban 12.1 jatuh ke GPT menulis tema, dan semuanya wajib ada
**sebelum** tahap ini dipakai pada proyek klien sungguhan:

1. **Meja periksa** (bagian 4) naik derajat dari bagus-punya jadi wajib: kalau yang menulis kode
   tema adalah GPT, tidak ada lagi jaminan bahwa blok yang dihasilkan sah. Yang sudah ada: tes
   memvalidasi markup blok dan paritas PNG↔ZIP; itu tetap dipakai, tapi tidak cukup.
2. **Nomor versi build + checksum + kunci anti-duplikat** - persis yang belum ada (bagian 5). Kalau
   build bisa gagal dan diulang, tanpa tiga hal itu yang terjadi adalah banyak ZIP untuk satu
   proyek dan tidak ada yang tahu yang mana yang benar.
3. **"Persetujuan bisa berjalan tanpa penyedia AI"** harus tetap hidup: kalau GPT mati saat build,
   sistem harus jatuh ke susunan lama (yang sekarang), bukan membuat proyek macet.
4. **Biaya per build** sudah punya taksiran, tapi satu asumsinya belum pernah diukur. Angka yang
   dihitung 20 Oktober dari byte nyata hasil build: satu kandidat = ±56 kB artefak = 16–23 ribu
   token keluaran, **tiga kandidat = 49–67 ribu token**, jadi **$0,10–0,16 per proposal** kalau
   memakai `gpt-5-mini`, atau **$0,60–2,80** kalau memakai `gpt-5.6` (harga resmi hari itu:
   mini $0,25/$2,00 per juta; gpt-5.6 $2–8/$12–30 per juta). Satu kandidat muat dalam SATU
   panggilan karena plafon keluarannya 16.384. Yang belum terukur: rasio karakter→token (asumsi
   2,5–3,5) dan pemakaian nyata per panggilan - itu tugas #66. Sebagai pembanding, biaya hari ini
   di tahap yang sama **nol**, dan penguasa tagihan tetap foto ($0,063 per gambar).
   Sumber angkanya: [.docs/hemat-biaya-proposal.md](file:///C:/Program%20Codding/dashboard-operasional/.docs/hemat-biaya-proposal.md)
   dan catatan arah desain yang pemilik buat pada 20 Oktober 2026; jangan mengulang perhitungan ini
   dari nol.

## 13. Gambar: pemilik minta placeholder otomatis yang cocok dengan brief

Yang benar dari permintaan ini dan sudah ada: **gambar memang ikut ke dalam paket**. Foto yang
dipakai konsep ditanam sebagai berkas di dalam theme (`generated-images/…`), lalu importer yang
dititipkan di `functions.php` mendaftarkannya ke pustaka media WordPress saat tema diaktifkan
([app/Services/BundleExporterService.php:442](file:///C:/Program%20Codding/dashboard-operasional/app/Services/BundleExporterService.php#L442),
[:508](file:///C:/Program%20Codding/dashboard-operasional/app/Services/BundleExporterService.php#L508)).
Jadi masalah "hasil impor cuma tulisan dan warna" sudah tidak ada.

Yang belum ada: **gambar yang bukan hasil AI**. Sumber gambar saat ini persis tiga: pakai ulang
berkas subjek yang sama dari proyek sebelumnya, unggahan klien yang dicocokkan per peran, atau
 dibuat oleh OpenAI ([app/Services/MockupAssetService.php:499](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L499),
[:628](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L628),
[:708](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L708)).
Kalau slot wajib kosong, kandidatnya dibuang; kalau slot tambahan kosong, renderernya menggambar
form tanpa foto (monogram, keping warna) - bukan menempatkan gambar cadangan.

Cara kerjanya tidak perlu dirombak: **slot gambar (hero, gallery, kartu layanan, gambar pendukung)
sudah ada dan sudah dibatasi jumlahnya** (maksimal 4-6 gambar per bagian, 9 per halaman,
[:37-43](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L37)).
Yang berubah cuma satu: sumber yang mengisi slot. Karena paket menyalin **berkas gambar sungguhan**
(importer tidak mengunduh tautan dari internet saat instalasi), gambar placeholder harus diunduh
dan disimpan lebih dulu oleh Laravel - sumbernya tidak boleh berupa URL tempel.

Tiga sumber yang masuk akal, dengan konsekuensinya:

| Sumber | Biaya | Izin pakai | Variasi | Catatan |
| --- | --- | --- | --- | --- |
| Pustaka gambar lokal milik agency, dikelompokkan per kategori (villa, kamar, kolam, kuliner, produk, kantor) | nol | aman kalau gambar milik sendiri atau bebas-izin | terbatas, cepat terasa berulang | perlu Anda kumpulkan isinya; saya bisa menyiapkan pencocok kata kunci dari brief |
| Penyedia gambar stok lewat API (Unsplash/Pexels) | gratis untuk kebanyakan paket, butuh kunci | **umumnya wajib mencantumkan sumber** - dan repo ini hari ini tidak punya satu pun teks atribusi | besar | perlu satu kolom sumber per berkas + teks kredit yang ikut ke ZIP |
| Tetap AI, tapi model foto yang lebih murah (task #65) | hemat 76% dari angka terukur | aman, gambar dibuat untuk klien itu | sedang | tidak menjawab "tim tidak usah cari gambar", hanya memurahkan |

**Satu risiko yang harus Anda putuskan sendiri.** *[DIPUTUSKAN 2026-10-21: "iya, gunakan hanya untuk
mockup" - lihat bagian 17 untuk apa yang keputusan ini tambahkan ke pipeline.]* Placeholder di
**mockup** itu masuk akal dan
bahkan lebih jujur: klien melihat susunan, bukan foto orang lain yang menyamar jadi hotelnya.
Tapi kalau placeholder yang sama ikut ke **paket final**, situs klien Anda menampilkan foto vila
milik orang lain - persis tipe temuan yang bulan ini kita cabut dari renderer (deretan "pelanggan"
palsu). Saran saya: placeholder untuk mockup, dan gambar final tetap dari unggahan klien atau AI,
dengan daftar gambar yang harus diganti ditampilkan di halaman unduhan supaya tim tahu apa yang
masih palsu.

## 14. Kalau dua permintaan ini disetujui, urutan pengerjaan yang saya sarankan

1. **Ukur dulu, jangan langsung rombak.** Satu proyek nyata, tiga konsep, jalur lama: catat biaya
   dan waktu per tahap. Tanpa angka ini, "GPT menulis tema" tidak bisa dibandingkan dengan yang
   sekarang (nol token).
2. Meja periksa paling murah: checksum + nomor versi build + periksa berkas wajib + periksa menu
   dan homepage. Berlaku untuk kedua bentuk 12.1, tidak menyentuh AI.
3. Sumber gambar placeholder **di tahap mockup saja**, dengan pustaka yang bisa Anda isi sendiri,
   dan pencocok kata kunci dari brief (villa Bali -> vila, kamar, kolam, taman). Foto final tetap
   seperti sekarang.
4. Potret mobile untuk kartu pilihan (12.3), dengan aturan mahal-sekali-saja.
5. Baru setelah itu: GPT menulis CSS/markup tema, dikerjakan di draf Jalur B yang sudah ada, dengan
   penjaga 12.4 lengkap. Ini item paling besar; tidak boleh jadi item pertama.

## 15. Angka yang saya pegang di dokumen ini

- Tiga kandidat: memang tiga, masing-masing generasi penuh, bisa diatur 2-3
  ([app/Services/GenerateMockupGptService.php:697](file:///C:/Program%20Codding/dashboard-operasional/app/Services/GenerateMockupGptService.php#L697)).
- Tema hari ini nol token; foto $0,063 per gambar
  ([.docs/hemat-biaya-proposal.md:23](file:///C:/Program%20Codding/dashboard-operasional/.docs/hemat-biaya-proposal.md#L23)).
- Chromium: 6 jalan sekarang, 10 kalau tiap konsep dua potret (batas 90 detik per potret).
- Suite penuh sesudah Codex dicabut: 3 merah / 584 hijau / 2405 assertion, 106 detik.
- Taksiran biaya GPT menulis CSS/markup tema: $0,10–0,16 per proposal di `gpt-5-mini` (49–67 ribu
  token keluaran untuk tiga kandidat); satu asumsinya (karakter→token 2,5–3,5) belum pernah diukur.
- Yang belum terukur dan sengaja tidak saya tulis sebagai angka: waktu render halaman mobile
  setinggi penuh, dan kecepatan unduh dari penyedia gambar stok.

## 16. Temporary Asset Resolver: wujudnya di Laravel ini

Permintaannya: "soal gambar, rekomendasikan fitur Temporary Asset Resolver di Laravel." Wujudnya
satu pintu yang menjawab pertanyaan "slot gambar ini butuh apa" dengan **bytes berkas di disk**,
bukan dengan tautan; dan karena jawabannya bisa berupa gambar pinjaman, pintu itu juga mencatat
**dari mana gambar itu berasal dan sampai kapan ia boleh disimpan**. Bukan pipeline baru, dan bukan
pengganti yang sudah ada - ia sumber keempat di antara tiga sumber yang sekarang bekerja.

### 16.1 Kenapa kata "temporary" bukan hiasan

Dua kenyataan di kode ini yang membuat fitur wajib punya masa pakai, bukan sekadar folder:

- **Importer WordPress menyalin bytes, tidak pernah mengunduh tautan.** Saat instalasi, gambar masuk
  lewat `wp_upload_bits()` dengan isi berkas yang sudah ada di ZIP
  ([app/Services/BundleExporterService.php:532](file:///C:/Program%20Codding/dashboard-operasional/app/Services/BundleExporterService.php#L532)).
  Jadi gambar placeholder **harus sudah terunduh** sebelum paket dibuat. Kalau resolver hanya
  menempel URL, paketnya berisi situs yang gambarnya hilang begitu host klien tidak bisa mencapai
  server penyedia gambar.
- **Gambar pinjaman punya izin pakai.** Penyedia stok umumnya mewajibkan kredit, dan repo ini hari
  ini tidak menyimpan satu pun teks atribusi. Kalau berkas pinjaman dibiarkan menumpuk tanpa catatan,
  dua bulan dari sekarang tidak ada yang tahu foto mana di situs klien yang masih punya utang kredit.
  Folder sementara + catatan asal-usul adalah cara supaya itu bisa dijawab.

### 16.2 Urutan resolusi (yang dipakai, diganti cuma satu baris)

Slot gambar, batas jumlahnya, dan tiga pengisinya sudah ada. Resolver tinggal disisipkan di tengah:

| Urutan | Pengisi hari ini | Isinya | Sudah ada di kode |
| --- | --- | --- | --- |
| 1 | unggahan klien | foto asli yang dia serahkan, dicocokkan per peran | [MockupAssetService.php:628](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L628) |
| 2 | pakai ulang | berkas yang sudah tersimpan untuk kandidat ini / proyek sebelumnya dengan subjek sama | [:499](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L499) |
| 3 | **placeholder (baru)** | gambar pinjaman yang cocok dengan brief: vila Bali -> vila, kamar, kolam, taman | belum ada |
| 4 | bikin oleh AI | `gpt-image-1`, $0,063 per gambar | [:708](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L708) |

Posisi 3 menyalakan alasan dia mengajukan ini - "tidak perlu membuat semua gambar dengan AI atau
meminta tim mencari gambar satu per satu" - tanpa menutup posisi 4: kalau placeholder tidak cocok
atau dilarang oleh konfigurasi, slot itu tetap bisa jatuh ke AI. Batas jumlah tidak berubah
(maksimal 4-6 gambar per bagian, 9 per halaman,
[:37-43](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L37));
yang berubah hanya dari mana berkasnya datang. Cara cocok kata-kuncinya juga sudah ada bentuknya:
pencocokan per peran (`hero` / `gallery` / `product` / `team` / `editorial`) sudah dipakai untuk
mengurutkan unggahan klien, jadi placeholder cukup ikut kamus yang sama, tidak perlu sistem baru.

### 16.3 Dua tahap umur: pinjaman, lalu dibekukan

Ini bagian yang paling menentukan aman atau tidaknya.

1. **Tahap pinjaman (saat mockup dibuat).** Berkas hasil unduh masuk ke
   `storage/app/temp-assets/{kode-proyek}/` bersama satu sidecar JSON yang mencatat per berkas:
   penyedia, URL asal, nama penulis, jenis izin, kapan diunduh, sampai kapan disimpan, dan slot mana
   yang memakainya. Polanya meniru sidecar subjek gambar yang sudah ada
   ([:46](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L46)),
   jadi tidak ada bentuk penyimpanan baru yang harus dipelajari.
2. **Tahap beku (saat satu konsep disetujui).** Pada titik yang sama sistem sekarang memutuskan
   konsep mana yang dibangun ([app/Models/Project.php:151](file:///C:/Program%20Codding/dashboard-operasional/app/Models/Project.php#L151)),
   berkas yang benar-benar terpakai **disalin keluar dari folder sementara** ke
   `mockup-assets/{kode}/candidate-{n}/`
   ([:1031](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L1031)).
   Setelah itu tidak ada satu pun langkah hilir - render PNG, demo hidup, ZIP - yang boleh membaca
   folder sementara. Akibatnya: unduhan ulang tidak mungkin terjadi saat build, PNG yang dilihat
   klien dan ZIP yang diinstal tetap dari berkas yang sama, dan pembersih umur tidak bisa menghapus
   gambar yang sudah jadi bagian karya resmi.

Yang dibuang pembersih adalah sisa: konsep yang tidak dipilih, dan slot yang belakangan diganti
unggahan klien. Cara menjadwalkannya sudah ada contoh di repo ini - satu entri penjadwal di
[routes/console.php:17](file:///C:/Program%20Codding/dashboard-operasional/routes/console.php#L17)
- jadi satu perintah `assets:buang-sementara` tinggal ditambahkan di sana, bukan infrastruktur baru.

### 16.4 Tiga titik sentuh, bukan tujuh

| Titik | Perubahan | Kenapa cuma itu |
| --- | --- | --- |
| Pengisi slot gambar | tambah satu sumber di urutan 3 | slot, peran, batas, penyimpanan sudah ada |
| README paket | satu daftar "gambar contoh dan sumbernya" | kredit harus ikut sampai ke klien, dan tempatnya sudah ada (README sudah dipakai untuk peringatan ukuran unggahan, [:294-370](file:///C:/Program%20Codding/dashboard-operasional/app/Services/BundleExporterService.php#L294)) |
| Kartu pilihan mockup | tanda "gambar contoh" di tiap kandidat | supaya tim tidak menyangka foto itu milik calon kliennya |

### 16.5 Penjaga yang saya pasang kalau ini dikerjakan

1. **Selalu bytes, tidak pernah URL** untuk berkas yang dikirim ke hilir.
2. **Gagal unduh bukan gagal kandidat.** Slot placeholder yang tidak terisi harus jatuh ke renderer
   tanpa foto (monogram, keping warna) - perilaku yang sudah ada hari ini - bukan membuat
   kandidatnya dibuang. Tanpa penjaga ini, penyedia gambar yang mati satu jam menghentikan seluruh
   generation.
3. **Ukungan tes tidak boleh keluar jaringan.** Panggilan OpenAI saja sudah dipalsukan di suite;
   penyedia gambar stok wajib ikut dipalsukan, dan tes tetap jalan di sqlite.
4. **Ukuran dan tipe berkas diperiksa sebelum dipercaya** (mime image, batas byte), karena isinya
   datang dari internet, bukan dari klien.
5. **Sidecar wajib dibaca sebelum ZIP dibuat.** Kalau sebuah gambar final tercatat berasal dari
   folder pinjaman, build menandai paketnya "berisi gambar contoh" alih-alih diam-diam
   mengirimkannya.
6. **Paket final menolak gambar berlabel pinjaman** - aturan ini sudah diputuskan 2026-10-21,
   bentuknya penjaga 5 dan rinciannya di bagian 17. Konfigurasi tetap menyimpan sakunya, supaya
   keputusan bisa dibalik tanpa mengubah satu baris pun.

### 16.6 Yang ia hemat, dan yang ia tidak selesaikan

Yang jelas: satu proposal hari ini bisa memakai sampai 27 foto AI = **$1,70**, dan gelombang foto itu
terbatas pada 5 gambar per menit sehingga sering tidak selesai dalam anggaran waktu 300 detik
(bagian 13 dan `.docs/hemat-biaya-proposal.md`). Kalau bagian mockup dibiayai placeholder, biaya
token gambarnya mendekati nol dan antrean foto yang jadi penyebab utama "kandidat degraded" hilang.
Yang **belum** saya ukur: berapa lama mengunduh 27 berkas dari penyedia gambar stok - itu sebabnya
angka "hemat waktu" tidak saya tulis sebagai ukuran di bagian 15.

Yang tidak ia selesaikan, dan harus jujur disebut: **placeholder bukan foto klien.** Villa di
halaman mockup itu villa orang lain, bagus untuk menunjukkan susunan dan sama sekali tidak membuktikan
foto hotel calon klien akan tampak baik di sana. Karena itu juga saran saya di bagian 13 tetap
berlaku: placeholder untuk mockup, gambar final tetap dari unggahan klien atau AI, dengan daftar
gambar yang masih harus diganti tampil di halaman unduhan.

Keputusan yang masih menunggu coretan dia (butir 2 dan 3 di task #88): sumber placeholder mana yang
dipakai (pustaka sendiri / penyedia stok ber-API / AI murah), dan boleh tidaknya gambar contoh ikut
ke situs final klien.

**SUDAH DIJAWAB 2026-10-21, sore:** butir ketiga closed - **"iya, gunakan hanya untuk mockup."**
Butir kedua (sumber mana) masih terbuka, dan sekarang jadi satu-satunya yang menghalangi langkah ini.

## 17. Isi keputusan "hanya untuk mockup", dan apa yang ia tambahkan ke pipeline

*[DIGANTIKAN 2026-10-21 malam. Bagian ini tetap dibiarkan terbaca karena isinya analisis, tapi
keputusannya sudah dibalik pemilik ("nggak papa sih kan web siap jadi itu nanti juga di ganti
assetnya"): gambar contoh BOLEH ikut ke paket final, dengan syarat ada label dan daftar yang harus
ditukar. Arah yang berlaku sekarang ada di bagian 18.]*

Ini bukan sakelar yang tinggal dipasang, dan bagian itu harus jelas lebih dulu.

**Hari ini satu sumber gambar melayani dua hasil.** Paket final tidak mencari gambarnya sendiri:
ia membaca
berkas yang sama persis dengan yang difoto jadi PNG kandidat terpilih
([app/Services/BundleBuilderService.php:43](file:///C:/Program%20Codding/dashboard-operasional/app/Services/BundleBuilderService.php#L43)
→ [MockupAssetService.php:313](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L313)).
Selama semua gambar adalah unggahan klien atau buatan AI, "yang disetujui = yang dikirim" berlaku
untuk foto juga. Begitu slot boleh diisi gambar pinjaman, satu berkas tidak bisa lagi melayani dua
permintaan - yang dikirim harus berkas lain.

Jadi keputusan dia menambah **satu langkah tukar** di titik persetujuan, tiga bagian wajibnya:

1. **Setiap slot ditandai sejak pembekuan**: `asli` (unggahan klien / dipakai ulang / buatan AI) atau
   `pinjaman`. Sidecar yang sudah direncanakan di 16.3 adalah tempat tanda itu hidup; tanpa tanda,
   langkah tukar buta.
2. **Saat satu konsep disetujui**, setiap slot `pinjaman` diisi ulang: coba unggahan klien dulu
   (sumber 1 tetap yang paling di depan), lalu AI kalau memang perlu. Slot yang tidak berhasil diganti
   mengikuti jalur yang sudah ada untuk gambar tambahan - dirender tanpa foto (monogram, keping
   warna) - bukan membuat kandidatnya batal, karena pada titik ini kandidat sudah disetujui orangnya.
3. **Pembuat ZIP menolak berkas berlabel `pinjaman`.** Bukan memperingatkan: menolak. Slot wajib yang
   hari ini sudah melempar error kalau berkasnya hilang
   ([BundleBuilderService.php:40-42](file:///C:/Program%20Codding/dashboard-operasional/app/Services/BundleBuilderService.php#L40))
   adalah preseden bahwa kegagalan yang kelihatan lebih baik daripada paket yang diam-diam berisi
   foto orang lain.

**Yang dia terima lewat keputusan ini, ditulis supaya tidak jadi kejutan:** gambar besar yang dilihat
klien saat menyetujui tidak lagi identik dengan gambar di situs yang dia instal. Yang tetap identik:
susunan, porsi dan ukuran tiap gambar, warna, huruf, teks, menu, dan halaman - karena slot dan
rasio gambarnya tidak berubah, hanya isinya. Ini celah baru di jaminan yang sejak awal dijaga Jalur A,
dan dia baru saja membukanya khusus untuk foto. Kalau nanti celah itu tidak dia inginkan, jalurnya
sudah ada dan tidak perlu kode baru: klien mengirim foto sebelum generate, karena sumber pertama selalu
unggahan klien.

**Efeknya ke biaya, dan kenapa angkanya sekarang lebih kecil:** tagihan AI tidak hilang, ia pindah
hanya ke slot yang benar-benar harus tampil di situs klien. Dengan aturan yang sudah berlaku sejak
task #68 (foto penuh hanya untuk kandidat terpilih) ditambah placeholder di sisi mockup, gelombang
gambar AI per proposal turun dari sampai 27 ke hitungan jari - dan antrean 5 gambar per menit yang
selama ini menjadi penyebab utama kandidat pulang dengan foto bolong hampir tidak lagi tersentuh.
Yang tetap belum terukur: kecepatan unduh gambar pinjaman, jadi klaim hemat waktu tidak saya tulis
sebagai angka.

**Satu sisa yang keputusan ini tidak menghapus:** mockup dikirim ke klien sebagai PNG dan PDF
proposal. Kalau sumber gambar pinjaman adalah penyedia stok yang mewajibkan kredit, teks "gambar
contoh" tetap perlu tercatat - bukan untuk situs klien, tapi supaya kita bisa menjawab gambar siapa
yang muncul di dokumen yang kita kirim. Pustaka gambar sendiri milik agency tidak punya utang ini;
karena itu pilihan butir 2 bukan soal selera.

## 18. Arah yang berlaku sekarang: Laravel workflow, model yang mengatur

Dasarnya bukan tafsir saya lagi, melainkan isi ekspor percakapan Anda dengan ChatGPT
(`Cara Membuat Tools ChatGPT — 2026-10-21.docx`, baris 786-895; teksnya saya ekstrak ke
`C:\Users\dimas\.qoder\tmp\C--Program-Codding\chatgpt-export-2026-10-21.txt`). Di sana tertulis
persis: *"Rekomendasi saya: buat fitur Temporary Asset Resolver di Laravel. Codex cukup menentukan
kebutuhan gambar, sedangkan layanan tersebut bertugas mencari, mengunduh, mengoptimalkan, dan mencatat
aset"*, dan *"Kita perlu membuat agent runner sendiri di Laravel"*, dengan enam alat
(`read_project_spec`, `create_mockup_spec`, `search_images`, `download_asset`,
`build_wordpress_package`, `run_validation`).

**Koreksi atas dua klaim saya sebelumnya, karena keduanya salah kutip.** Pertama, saya bilang GPT
"mengirim kata kunci untuk tiap gambar" - tidak. Subjek gambar hari ini diambil PHP dari judul bagian
([app/Services/MockupAssetService.php:124](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L124),
[:251](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L251));
model tidak ikut menentukan gambar apa yang dicari. Kedua, saya menunjuk baris yang salah untuk tempat
model memilih tata letak; yang benar adalah ini.

### 18.1 Beda yang sebenarnya, diukur dari kode

| | Hari ini | Yang Anda minta |
| --- | --- | --- |
| Tata letak | GPT memilih **nama komposisi** dari daftar tertutup yang PHP sediakan per bagian ([GenerateMockupGptService.php:298](file:///C:/Program%20Codding/dashboard-operasional/app/Services/GenerateMockupGptService.php#L298)), dan nama yang tidak ada di daftar dibuang ([ :483](file:///C:/Program%20Codding/dashboard-operasional/app/Services/GenerateMockupGptService.php#L483)). PHP yang menyusun dan merender. | GPT menulis sendiri HTML + CSS tiap konsep, termasuk ukuran kartu dan gaya nav. |
| Gambar | PHP yang menurunkan subjek, PHP yang memilih dari tiga pengisi, AI yang menggambar. | Model yang menyatakan kebutuhan, memilih hasil pencarian, dan memasangnya. |
| Umpan balik visual | Ada, tapi hanya untuk foto AI: hasil render dikirim ke model pemeriksa dan bisa ditolak dua kali ([MockupAssetService.php:779](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupAssetService.php#L779)). Model tidak pernah melihat kandidatnya sebagai satu halaman. | Model melihat renderannya sendiri dan memperbaiki. |

Otot yang diperlukan untuk baris ketiga sudah ada dan sudah jalan: itulah yang menolak foto AI yang
jelek. Yang belum: otot itu dipakai menilai **halaman utuh**, bukan satu gambar.

### 18.2 Loop yang dimaksud, dalam istilah Laravel

Satu jalan per konsep, di dalam satu job antrean (pola yang sudah dipakai build ZIP,
[app/Jobs/BuildProjectBundleJob.php:35](file:///C:/Program%20Codding/dashboard-operasional/app/Jobs/BuildProjectBundleJob.php#L35)):

1. Laravel menyiapkan folder kerja berisi brief, konten yang sudah final, dan aturan desain.
2. Laravel memanggil API dengan daftar alat. Model memanggil alat, Laravel yang menjalankan, hasilnya
   dikirim balik ke model.
3. Model menulis `index.html` + `gaya.css` per konsep ke folder kerja.
4. Model meminta `cari_gambar("villa ubud kolam")` dan `unduh(tautan)`; Laravel yang menyentuh
   internet, menyimpan bytes, mencatat asal-usul, mengembalikan path.
5. Model meminta render, melihatnya, memperbaiki. Ini langkah paling mahal dan paling menentukan mutu,
   jadi jumlah ulangnya harus dibatasi **sebelum** mulai, bukan di tengah.
6. Laravel memotret hasilnya dengan alat yang sudah ada
   ([app/Services/ScreenshotService.php:21](file:///C:/Program%20Codding/dashboard-operasional/app/Services/ScreenshotService.php#L21)),
   menaruh kartunya di dashboard, dan menunggu pilihan seperti sekarang
   ([app/Models/Project.php:151](file:///C:/Program%20Codding/dashboard-operasional/app/Models/Project.php#L151)).

Setelah dipilih, fase kedua berjalan dengan alat yang sama: model menulis theme dari konsep yang menang,
Laravel mengemas ZIP, validator memeriksa, baru unduhan dibuka.

### 18.3 Resolver gambar turun jabatan, dan itu memang benar

Di bagian 16 saya menaruh resolver sebagai **pengisi slot keempat** di dalam mesin PHP. Di arah ini dia
jadi **alat** yang dipanggil model: tetap mencari, mengunduh, mengoptimalkan, mencatat - tapi tidak lagi
memutuskan slot mana dapat apa. Isi 16.3 (lemari pinjaman + catatan asal-usul + dibekukan saat
persetujuan) dipakai apa adanya; yang berubah cuma siapa yang memanggilnya. Batas jumlah yang lama
(4-6 gambar per bagian, 9 per halaman) berhenti jadi pagar, karena yang menentukan banyaknya gambar
sekarang desainernya; gantinya pagar anggaran: batas langkah, batas dolar, batas byte per konsep.

### 18.4 Empat penahan yang dibangun bersamaan, bukan nanti

1. **Satu artefak jadi sumber.** HTML+CSS yang disimpan model adalah satu-satunya yang difoto, yang
   ditampilkan di demo, dan yang dijadikan ZIP. Begitu ada dua jalur render, jaminan
   "yang disetujui = yang dikirim" mati - dan itu nilai jual sistem ini.
2. **Batas langkah dan batas dolar per jalan**, dicek Laravel sebelum tiap panggilan. Ini pelajaran
   20 Oktober saat $20 habis sebelum satu generate selesai; jalan agen mengulang panggilan, jadi tanpa
   plafon ia mengulang kesalahan yang sama dengan cara yang lebih mahal. *(Bagian plafon dolar sempat
   dibangun 21 Oktober, dicabut pemilik malamnya - bagian 19. Batas langkah memang belum pernah
   dibangun, ia menunggu loop Jalur B yang juga belum ada.)*
3. **Pencatat pemakaian tiap panggilan** (task #66). Semua angka biaya di dokumen ini masih aritmetika;
   di jalan agen, tanpa pencatat tidak ada yang bisa menjelaskan kenapa tagihannya begitu.
   *(Sempat dibangun 21 Oktober, dicabut pemilik hari itu juga - bagian 19.)*
4. **Meja periksa sebelum hasil boleh dikirim**: berkas ada, tidak ada gambar rusak, kontras lolos,
   tidak meluber di empat lebar layar, tidak ada kontrol mati. Tujuh dari sembilan pemeriksaan di
   rancangan luar itu bisa dikerjakan tanpa WordPress staging; dua sisanya butuh staging yang belum ada
   di mesin ini.

### 18.5 Yang belum ada tandingannya di kode hari ini

- **Pencarian gambar.** Tidak ada satu pun jalur di repo ini yang mencari gambar ke internet; yang ada
  hanya menggambar (OpenAI images) dan menerima unggahan. Layanan pencarian mana yang dipakai tidak
  bisa saya jawab dari kode, dan harganya belum saya cek.
- **Melihat renderan sendiri.** Sudah ada untuk satu foto, belum untuk satu halaman; dan tabel harga
  resmi tidak mencantumkan harga image input untuk model teks, jadi biaya bagian ini belum bisa saya
  kutip sebagai angka.

### 18.6 Urutan yang saya sarankan

1. ~~Pencatat pemakaian + plafon dolar per jalan~~ - DICABUT pemilik 21 Oktober malam ("ini nggak
   usah"), sesaat setelah selesai dibangun. Konsekuensinya langkah 3 kehilangan alat ukurnya:
   angka biaya di dokumen ini tetap aritmetika, dan task #89 jadi manual (baca tagihan dari
   dasbor OpenAI, bukan dari sistem).
2. Meja periksa tujuh pemeriksaan (penahan 4). Nol AI, nol risiko.
3. Satu konsep Jalur B tanpa gambar pinjaman: HTML+CSS ditulis model, dirender, difoto, dibandingkan
   dengan hasil hari ini. Tujuannya satu: membuktikan mutunya naik - dan tanpa pencatat, pembanding
   dolarnya tidak ada; yang bisa dibandingkan cuma hasil visual.
4. Alat `cari_gambar` + `unduh` + lemari pinjaman + label aset sementara, disambungkan ke artefak itu.
5. Ronde "melihat dan memperbaiki" untuk halaman, dengan batas langkah eksplisit.
6. Baru fase dua: model menulis theme final dari konsep yang menang, ZIP, unduhan.

### 18.7 Tiga hal yang masih milik Anda

1. **Layanan pencarian gambar mana** yang dipakai. Ini yang memblokir langkah 4; saya perlu mengecek
   harga dan syaratnya sebelum menyarankan satu nama.
2. **Boleh dibangun tidak meja periksa (penahan 4)** di atas sistem yang sekarang, sebelum satu baris
   Jalur B ditulis. Pencatat dan plafon sudah terjawab: tidak ("ini nggak usah", 21 Oktober malam).
3. **Apakah penahanan fitur website + WordPress dibuka sebagian** untuk langkah 3 - karena langkah 3
   menyentuh generator, dan generator ini ditahan sejak 20 Oktober.



## 19. Pencatat pemakaian + plafon dolar: dibangun, dicabut hari itu juga (21 Oktober)

Sore 21 Oktober kedua penjaga biaya ini selesai dibangun di atas sistem yang ada - belum satu baris
Jalur B, belum satu kemampuan AI baru:

- **Buku harian pemakaian.** Kelima tempat yang membayar OpenAI menulis satu baris ke tabel baru
  `ai_usage_logs`: tahap, model, saluran, token masuk/keluar, jumlah foto, biaya dolar, lama
  panggilan, milik proyek mana. Menulis buku tidak pernah membatalkan proposal - tabel hilang,
  panggilan tetap jalan, kegagalannya hanya peringatan di log.
- **Plafon dolar satu proposal**, satu angka di `.env` (`OPENAI_PROPOSAL_BUDGET_USD`, bawaan 0 =
  tidak ada plafon), dicek PHP sebelum membayar: gelombang foto dikecilkan ke yang masih terbayar,
  tahap wajib (konten website, desainer tata letak) berhenti dengan pesan "plafon habis", tahap
  sukarela (pembaca gambar referensi) berhenti tanpa membuat proposal gagal.

Delapan tes baru hijau; suite penuh saat itu 3 gagal / 592 lulus - tiga-tiganya merah billing lama.

**Malamnya pemilik berpikir ulang: "ini nggak usah."** Keduanya dicabut total hari itu juga: enam
berkas dikembalikan persis ke keadaan HEAD `0de969c` (diverifikasi berkas per berkas lewat diff;
suite pasca-pembongkaran tidak dijalankan karena dihentikan pemilik), empat berkas baru dihapus.
Tak satu pun dari pekerjaan ini pernah di-commit.

Yang tersisa dari kejadian ini:

- **Kode diparkir, tidak hilang.** `storage/app/backup/penahan-biaya-dibatalkan-20261021/` berisi
  catatan seluruh suntingan 6 berkas dalam satu patch (16,7 KB) plus 4 berkas utuh: rekorder,
  model, migrasi, dan 8 tes. Pasang ulang = terapkan patch itu, tidak ada yang perlu dikarang ulang.
- **Sisa di database dev:** tabel `ai_usage_logs` masih ada (kosong, 0 baris) beserta satu baris di
  daftar migrasi, padahal berkas migrasinya sudah tidak ada. Tidak mengganggu apa pun; menunggu izin
  pemilik untuk di-drop.
- **Konsekuensi ke depan:** task #89 (ukur biaya satu proposal nyata) kehilangan alat ukurnya.
  Selama tidak ada pencatat, semua angka biaya di dokumen ini tetap aritmetika (bagian 14), dan
  pembanding mutu Jalur B di langkah 18.6 hanya bisa dari hasil visual, bukan dolar.
- **Tiga aturan akuntansi yang layak diingat kalau suatu hari dipasang ulang** (detailnya ada di
  berkas parkiran): harga yang dipublikasikan sebagai rentang (gpt-5.6) disimpan kosong, bukan
  ditebak; jawaban yang tidak melaporkan token disimpan kosong, bukan nol - kosong dan nol harus
  terbaca berbeda, kalau tidak satu panggilan yang gagal lapor terhitung "murah"; foto hanya
  ditagih kalau jawabannya benar-benar jadi - yang ditolak batas per menit tidak, yang ditolak
  pemeriksaan visual tetap ya karena uangnya sudah keluar.
