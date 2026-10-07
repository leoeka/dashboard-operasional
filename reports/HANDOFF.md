# Catatan Serah-Terima — Design Generator V2 & Live Demo

### Kualitas asset dan parity WordPress — 7 Oktober 2026

- Asset foto baru diperiksa lewat OpenAI Responses API sebelum disimpan. Reviewer menolak foto berteks, watermark/logo, poster, kolase, distorsi berat, atau blur yang tidak layak. Sistem mencoba membuat ulang satu kali; setelah dua penolakan slot tetap gagal dan kandidat tidak dianggap lengkap. Prompt version dinaikkan agar retry tidak memakai foto lama.
- Build WordPress menggunakan partial Blade `mockup.body` dan CSS yang sama dengan preview, serta memakai file foto/logo yang sama. Theme shell/header/footer diganti dengan renderer deterministik, screenshot theme memakai screenshot mockup terpilih jika file tersedia, dan URL navigasi diselesaikan memakai permalink WordPress. Metadata Elementor lama dibersihkan supaya tidak mengaktifkan versi halaman yang berbeda. Isi halaman disimpan sebagai satu blok HTML yang dapat diedit lewat Block Editor.
- File utama: `MockupAssetService`, `BundleBuilderService`, `BundleExporterService`, `resources/views/mockup/site.blade.php`, `resources/views/mockup/body.blade.php`, `config/services.php`, `.env.example`, `README` instalasi theme, `MockupAssetLifecycleTest`, `BlueprintParityTest`, `GutenbergBlockValidityTest`.
- Keputusan: visual WordPress memakai markup mockup penuh di blok HTML untuk menjaga CSS dan komposisi tetap identik; pengeditan visual dilakukan pada markup blok itu, bukan tiap elemen sebagai blok native terpisah. Reviewer memakai `gpt-4.1-mini` secara default di luar environment testing. Total ada satu review vision per foto yang dihasilkan, ditambah kemungkinan biaya satu regenerasi.
- Pemeriksaan terarah: `php artisan test --compact tests/Feature/MockupAssetLifecycleTest.php tests/Feature/BlueprintParityTest.php tests/Unit/GutenbergBlockValidityTest.php` menghasilkan **32 passed, 147 assertions**. Suite penuh `php artisan test --compact` menghasilkan **519 passed, 2.027 assertions**.
- Sintaks: `php -l` lulus untuk tiga service yang diubah serta tiga file tes terkait. `git diff --check` bersih.
- Batasan: panggilan OpenAI nyata dan instalasi theme pada situs WordPress belum diuji. Markup, CSS, dan asset sama pada build, tetapi hasil pixel tetap dapat berbeda karena font gagal dimuat, plugin WordPress, konten eksternal, viewport, dan browser.

### Perbaikan dan verifikasi kode — 6 Oktober 2026

- Polling proposal kini memperlakukan status `completed` yang dinormalisasi sebagai selesai, sehingga workspace memuat ulang hasil akhir.
- Job pembuatan proposal dibuat unik per proyek dan progres `queued`/`processing` tidak ditimpa oleh request duplikat.
- Bundle lama tidak lagi dapat diunduh sebagai hasil terbaru setelah rebuild mulai atau gagal. Status `building`, `failed`, dan `exported` ditampilkan konsisten di halaman bundle.
- Preview dan workspace mendukung proposal lama yang hanya memiliki `mockup`, termasuk saat `mockup_candidates` kosong.
- Area utama: `BundleController`, `MockupPreviewController`, `WebsiteBuilderController`, `GenerateProposalJob`, `project-workspace.blade.php`, `bundles/index.blade.php`.
- Regresi ditambahkan untuk proposal legacy, progres job duplikat, sukses rebuild, kegagalan builder/ZIP export, serta unduhan bundle lama.
- Pemeriksaan: tes terarah **38 lulus, 186 assertion** sebelum kasus workspace legacy ditambahkan; tes terarah sesudahnya **19 lulus, 107 assertion**; suite lengkap terakhir **516 lulus, 2.004 assertion**. `git diff --check` bersih.
- Build nyata yang menghubungi API GPT/OpenAI dan instalasi ZIP di WordPress belum dijalankan; tes builder memakai HTTP fake.

### Migrasi catatan proyek — 6 Oktober 2026

- Handoff kode dipindahkan dari `docs/HANDOFF.md` ke laporan ini. Handoff Billing & Finance dipindahkan ke `reports/BILLING_FINANCE_STATUS.md`.
- Aturan agent kini meminta semua catatan pekerjaan berikutnya ditambahkan di bagian paling atas laporan ini, dengan ringkasan perilaku, file, keputusan, pemeriksaan, dan keterbatasan.
- Folder `docs/` dan isi selain dua handoff tersebut dihapus sesuai permintaan pengguna.

> **Review visual baru, 1 Oktober 2026:** proyek **#34 / `REVIEW-PELLET-20261001`**
> dibuat dari brief Supplier Wood Pellet #30. Tiga kandidat dan proposal #34 berhasil
> dibuat; proposal masih pending. Catatan review dan screenshot lama yang dahulu tersimpan
> di folder `docs/` tidak ikut dipertahankan saat folder tersebut dihapus.
> Codex hanya menjalankan proyek uji dan menilai desain; kode aplikasi tidak diubah.
> Rekomendasi: opsi 2 sebagai dasar revisi, belum layak approval. Run pertama terkena
> batas internal 300 detik; run kedua selesai memakai checkpoint.

### Update lanjutan — 6 Oktober 2026 (build langsung dari workspace)

- Sesudah klien memilih opsi di proposal yang dikirim tim, klik tombol pada kartu mockup
  langsung mengirim build ke `BundleController::build`. Tidak ada pencatatan approval
  terpisah atau halaman Build WordPress tambahan. Hasil sukses menuju halaman unduh ZIP.
- Build belum dijalankan untuk proyek tertentu; menjalankan build memanggil GPT/OpenAI.
- `BundleBuilderService` kini memakai `OpenAiWordPressBuilderService` pada OpenAI Responses API
  (default model `gpt-5.6`, bisa diatur lewat `OPENAI_WORDPRESS_BUILDER_MODEL`). Isi halaman
  tetap Gutenberg yang dirakit deterministik dari mockup; GPT hanya membuat chrome tema.
- Tes baru mencakup stream GPT, lampiran PNG mockup terpilih, klasifikasi kuota, lint PHP,
  penolakan stream tidak lengkap, dan kegagalan bundle tanpa API key. Jalur build Anthropic
  yang duplikat dihapus agar hanya satu implementasi produksi yang aktif.

### Perubahan alur — 6 Oktober 2026 (build langsung dari mockup)

- Ini menggantikan alur sebelumnya yang meminta tim mencatat approval di workspace.
  Proposal tetap dikirim ke klien di luar sistem. Sesudah klien menyebut opsi yang
  dipilih, tim menekan tombol build pada kartu opsi itu; satu POST menyimpan indeks
  mockup, membuat manifest, lalu membangun ZIP. Tidak ada tombol approval terpisah
  dan status proposal `approved` bukan lagi syarat build.
- Tim hanya menekan opsi yang klien pilih setelah mendapat balasan eksternal. Sistem
  tidak mengumpulkan bukti persetujuan.

### Review lanjutan — 6 Oktober 2026 (alur build GPT)

- Tombol build memilih dan menyimpan kandidat dalam request yang sama; status approval
  internal tidak lagi mengatur build.
- Builder sekarang menolak manifest tanpa `exito-client-theme/style.css` atau `index.php`,
  agar hasil GPT yang tidak bisa dipasang tidak disimpan sebagai build sukses.
- Fake HTTP test untuk image generation dipersempit ke `/v1/images/generations`; pola
  domain terlalu luas sebelumnya menangkap request Responses API dan membuat tes build
  mendapat respons gambar, bukan stream GPT.
- Migration desain URL saat rollback mengembalikan kolom ke definisi asal `VARCHAR(255)`.
  Fixture `BillingRollbackGuardTest` menyingkirkan migration desain baru saat mengukur
  tujuh langkah rollback.
- Tes terarah terbaru untuk build langsung, approval legacy, GPT, parity, dan lifecycle:
  **46 passed, 200 assertions**.
- Kartu tiap opsi mockup sekarang langsung POST `mockup_index` ke build; `/bundle` hanya
  menampilkan hasil unduhan dan tautan kembali ke workspace, tidak menawarkan langkah build kedua.
- Full suite pada perubahan direct-build sebelum cleanup endpoint pilihan lama:
  **507 passed, 3 failed**. Tiga sisanya ada di `BillingEmailDeliveryTest`
  (subject mail dibuat setelah frozen-clock berubah, dan ekspektasi tanggal Inggris
  sementara output lokal berbahasa Indonesia); perlu ditangani terpisah.
- Build kini memakai cache lock per proyek selama proses pemilihan mockup, pembuatan tema,
  dan ekspor ZIP. Permintaan kedua ditolak dengan pesan agar menunggu; pilihan mockup tidak
  berubah dan GPT tidak dipanggil ulang. Lock minimal 900 detik, atau timeout GPT + 300 detik.
  Cache produksi harus memakai store yang mendukung lock dan dibagi antar worker; default
  aplikasi adalah `database`. PHP hasil GPT masih hanya dilint, belum diaudit statis untuk
  perilaku berbahaya; ZIP tetap perlu QA sebelum diserahkan. Build nyata dan install WordPress
  belum diuji.
- Verifikasi setelah lock: tes build terarah **6 lulus, 33 assertions**
  (`BuildSelectedMockupTest` dan `OpenAiWordPressBuilderTest`). Full suite: **507 lulus,
  3 gagal, 1961 assertions**; tiga kegagalan yang sama tetap berada di `BillingEmailDeliveryTest`,
  bukan alur builder GPT atau lock. Build GPT nyata tidak dijalankan karena memanggil API berbayar.

Terakhir diperbarui: **6 Oktober 2026**
Branch yang direncanakan: **`feature/design-generator-v2-live-demo`** (belum di-push, belum ada PR).
Saat verifikasi terakhir checkout aktif `master`; pengguna memilih melanjutkan di branch tersebut
tanpa commit.
Repo: `github.com/leoeka/dashboard-operasional` — branch utama `master` (jangan commit langsung ke master)

Laporan ini untuk siapa pun yang melanjutkan pekerjaan tanpa riwayat chat. Implementasi dan
tes di repositori menjadi acuan untuk detail arsitektur.

---

### Update lanjutan — 1 Oktober 2026 (kualitas visual mockup)

- **Foto berantakan, penyebab ditemukan dari real run #30/#33:** prompt foto mengirim headline
  dalam tanda kutip (`Subject: "Experience Bali…"`) dan brief LAYOUT kandidat ("expressive serif
  headings, a composed gallery…"). Model gambar mencetak teks itu ke foto → poster/kolase bertulisan.
  `MockupAssetService::photoPrompt()` kini mendeskripsikan adegan, hanya mengirim arah fotografi
  (cahaya/warna/lensa, `photographyDirection()`), dan melarang poster/kolase/teks.
  Kualitas gambar default `medium` (`OPENAI_IMAGE_QUALITY`, dulu hard-coded `low`): biaya per foto naik.
  Foto lama di `storage/app/public/mockup-assets` masih versi lama; perlu generate ulang untuk melihat hasilnya.
- **Kartu listing** (tur/kamar/produk): komposisi baru `listing_cards`, shape `listing`
  (`CompositionSpec::sectionShape()`), partial `mockup/partials/listing-card.blade.php` +
  `ElementorPageBuilderService::gbListingCard()`. Field item: `location, duration, rating, reviews,
  price, price_unit`. Daftar komposisi `listing` adalah superset `card_items` → blueprint lama tidak berubah.
  Rating/durasi/harga tetap lewat integrity gate: hanya tampil kalau ada di brief klien.
- **Anti-slop (V2 / `.full-page` saja, PNG legacy tidak berubah):** navbar mengambang di atas hero,
  orb dekoratif & blok offset di belakang foto dihapus, shadow kartu diringankan, hero overlay
  kini menampilkan foto (dulu opacity .55 + scrim warna primary), mosaic galeri terisi untuk 2–6 item.
  Bug CSS dari commit 106fa15 (`{margin-bottom:0` tak ditutup → media query 860px rusak) diperbaiki.
- **Rate limit foto:** akun OpenAI tier 1 = 5 gambar/menit; semua foto dulu dikirim serentak → 429 →
  "Baru 1 dari 3 opsi mockup yang lengkap". `MockupAssetService::generate()` kini mengirim per gelombang
  (`OPENAI_IMAGES_PER_MINUTE`, default 5), me-retry 429 rate limit, dan berhenti di
  `OPENAI_IMAGE_TIME_BUDGET` (default 300 dtk); sisanya dilengkapi saat retry. Timeout job proposal
  dinaikkan ke 900 dtk (`GenerateProposalJob`, `composer.json` queue:listen, `DB_QUEUE_RETRY_AFTER` 960).
- Verifikasi: `php artisan test` **505 lulus**; `--group=browser` lulus (20 kombinasi). Belum diuji di
  Block Editor WordPress asli untuk kartu listing (hanya memakai block yang sudah tervalidasi).

### Update lanjutan — 29 September 2026

- Perbaikan §6.1 diterapkan: ketika AI aktif, kegagalan Gemini diteruskan sebagai
  `ProviderException`; checkpoint analisis tetap gagal dan bisa di-retry. Fallback
  lokal hanya untuk AI yang sengaja dinonaktifkan. Checkpoint lama project #28
  tidak diubah; instruksi untuk tidak langsung me-retry project tersebut tetap berlaku.
- Perbaikan kode §6.2 diterapkan: body error dan exception Google Places disanitasi
  sebelum masuk log, termasuk pesan error analisis bisnis Gemini. Log lama dan
  rotasi credential belum ditangani.
- Sanitizer existing diperluas untuk query key, API key, Bearer, access token,
  secret, dan credential dalam teks maupun nilai JSON berpetik.
- `tests/Feature/ProviderFailureRecoveryTest.php` mencakup 27 kasus: sukses Gemini
  tersimpan dan dipakai ulang, kegagalan menghentikan pipeline sebelum GPT/foto,
  checkpoint failed tanpa payload fallback, retry memanggil Gemini kembali,
  AI nonaktif, serta 12 format secret pada HTTP error dan transport error.
- Audit lanjutan: contoh teks biasa `secret destination`, `credential management`,
  dan `monkey=value` tetap utuh. ProviderException yang sudah terklasifikasi tetap
  instance yang sama; konstruktor menyamarkan custom message/detail, context
  menyimpan provider, error_code, HTTP status, status SDK Gemini, dan retryability.
- Checkpoint hanya reuse `completed`; tes membuktikan `failed` dengan payload
  lama tetap dieksekusi ulang. Throwable mentah di boundary checkpoint/PDF dan
  Google property discovery dikonversi ke ProviderException tanpa raw previous.
- Audit log mencakup Gemini, Google Places/Analytics/Search Console/PageSpeed,
  GPT/OpenAI, screenshot dan reference fetcher, serta Fonnte. Pesan error/URL
  disanitasi; raw payload Gemini invalid dan raw response Fonnte tidak dicatat.
  Pemakaian body/json untuk parsing data tetap ada. Jalur OpenAI image dan Claude
  sudah memakai ProviderException. `throw $e` controller proposal hanya menerima
  ProviderException. Stage tetap disimpan pada checkpoint dan log pipeline.
- Regresi audit ada di `tests/Feature/ProviderSafetyAuditTest.php`; gabungan kedua
  file regresi provider **56 lulus / 198 assertions**.
- Verifikasi terbaru: `php artisan test` **483 lulus / 1820 assertions**;
  `php artisan test --group=browser` **1 lulus / 98 assertions** (16 kombinasi
  halaman/viewport); `npm.cmd run build` sukses. `npm.cmd` digunakan karena
  execution policy PowerShell memblokir `npm.ps1`.
- Belum melakukan real AI run, push, atau PR pada sesi lanjutan ini.

## 1. Tujuan

Prinsip utama: **LIVE PREVIEW = APPROVED BLUEPRINT = WORDPRESS RESULT.**
Satu blueprint (`mockup.pages` + `mockup.design` + `mockup.assets`) dipakai untuk PNG proposal,
Live Demo, dan halaman WordPress. Tidak boleh ada tiga desain berbeda.

Alur:

```text
Brief → Gemini (konten) → Content Integrity Gate → GPT (desain, 3 kandidat)
      → foto (MockupAssetService) → renderer bersama → PNG + Live Demo
      → approve → Gutenberg (ElementorPageBuilderService) → ZIP tema → WordPress
```

## 2. Status

| Tahap | Status |
|---|---|
| Audit & dokumentasi flow | ✅ selesai |
| Section roles V2 (tidak ada lagi `skipped` untuk section berisi) | ✅ |
| Renderer bersama (PNG + Live Demo) | ✅ |
| Live Preview + device frame (Desktop/Tablet/Mobile/Fullscreen) | ✅ |
| Prompt desain V2 (komposisi per section, anti-generik) | ✅ |
| Renderer Gutenberg per composition + full sitemap | ✅ |
| Uji di Block Editor WordPress asli (lokal, WP 7.1) | ✅ 0 block invalid setelah perbaikan |
| Smoke test mobile otomatis (390/768/1024/1440) | ✅ |
| Content integrity gate (anti fakta karangan) | ✅ |
| **Real AI generation** | ❌ **gagal — provider ditolak** (lihat §5) |
| Push branch + PR | ⏳ belum — menunggu real run berhasil |
| Anti-Slop Design Rule Engine | ⏳ belum dimulai — **branch terpisah** (lihat §7) |

Test: **427 lulus** (`php artisan test`). `npm run build` sukses.

### Commit di branch (terbaru di atas)

```text
75d49ae feat: add a hard content-integrity gate after the Gemini content stage
d55d1c0 test: automate the responsive smoke test for the live demo
feb5206 fix: make generated Gutenberg blocks valid in the real Block Editor
0730b9c refactor: use the shared renderer for the pipeline fallback PNG
b160154 feat: build full approved sitemap into WordPress with composition renderers
9841d52 feat: expand AI design direction to art-direct every section
6f5700a feat: add live mockup website preview with device frames
a8b54f4 refactor: render blueprint sections by composition through one shared renderer
```

## 3. Keputusan penting (jangan dibalik tanpa alasan kuat)

1. **`design.renderer_version = 2`** menandai blueprint baru. Blueprint tanpa penanda (proposal
   lama yang sudah di-approve) tetap memakai aturan legacy 3 section (hero / icon_band /
   card_grid), karena PNG yang dulu disetujui client hanya menampilkan 3 section itu.
   Logikanya ada di `CompositionSpec::isFullPage()` dan `ElementorPageBuilderService::describeSections()`.
2. **Nama role `icon_band` dan `card_grid` dipertahankan**, karena sudah tersimpan di blueprint,
   manifest aset, dan skema designer. Cara menggambar section ditentukan field **`renderer`**
   pada plan: `hero, features, cards, editorial, alternating, stats, testimonials, gallery,
   logos, faq, cta, team, pricing`.
3. **Satu jalur layout saja.** Setiap renderer baru harus lewat `describeSections()` → `renderer`,
   lalu digambar di **dua** tempat yang identik strukturnya:
   `resources/views/mockup/sections/{renderer}.blade.php` dan
   `ElementorPageBuilderService::gbFullPageSection()`. Jangan buat renderer ketiga.
4. **AI tidak boleh menulis HTML/CSS.** GPT hanya memilih nama composition dari daftar di
   `CompositionSpec`, divalidasi terhadap bentuk konten section (`compositionsForShape()`).
   Field dari GPT dibatasi ke field desain saja (`GenerateMockupGptService::designFields()`),
   sehingga GPT tidak bisa menimpa copy dari Gemini.
5. **Konten dari Gemini, desain dari GPT.** Ketiga kandidat punya konten yang sama dan hanya
   berbeda desainnya.
6. **Content integrity gate** (`ContentIntegrityService`) berjalan setelah Gemini. Evidence
   hanya data Project + Client. Data kompetitor/referensi **tidak pernah** menjadi fakta klien.
   Pricing tidak lagi disorot otomatis di tengah; hanya bila klien menyebut paket itu.
7. **Foto hanya untuk halaman Home** (hero + section berfoto, maks. 9 foto section per kandidat,
   dialokasikan per section utuh). Halaman lain memakai bentuk tanpa foto.
8. **Route preview hanya `auth`**, karena aplikasi belum punya policy per-project.

## 4. File kunci

| File | Isi |
|---|---|
| `app/Support/CompositionSpec.php` | Daftar composition, role/renderer, shape, versi |
| `app/Services/ElementorPageBuilderService.php` | `describeSections()` (plan) + renderer Gutenberg |
| `app/Support/MockupSite.php` | View-model renderer bersama (PNG + Live Demo) |
| `app/Support/SectionContent.php` | Normalisasi item (FAQ, testimoni, statistik, harga) |
| `app/Support/SitemapPages.php` | Aturan Home + slug unik |
| `app/Support/Palette.php` | Warna teks di atas band |
| `resources/views/mockup/*` | Renderer Blade bersama |
| `app/Http/Controllers/MockupPreviewController.php` | Route preview & demo |
| `app/Services/GenerateMockupGptService.php` | Prompt desain V2, merge, distinctness |
| `app/Services/MockupAssetService.php` | Slot foto per section, nama file bundle |
| `app/Services/ContentIntegrityService.php` | Gate anti fakta karangan |
| `app/Services/BundleExporterService.php` | ZIP tema + importer halaman WordPress |
| `tests/Fixtures/v2-blueprint.php` | Blueprint V2 lengkap untuk test |

Route: `pages.projects.mockup.preview` → `/projects/{project}/mockup/{candidate}/preview?page=slug`
dan `pages.projects.mockup.demo` → `/projects/{project}/mockup/{candidate}/demo`
(`candidate` = index 0-based). Tombol **Open Demo** ada di workspace per kandidat.

## 5. Real AI run — gagal (26 Sep 2026, project #28, kode `REAL-V2-051609`)

Brief: *Bali Private Tour & Airport Transfer*, referensi `https://demo.goodlayers.com/traveltour/`.

| Provider | Error |
|---|---|
| Gemini | Service account yang terikat ke API key dihapus/dinonaktifkan |
| Google Places (competitor discovery) | 403 `CONSUMER_SUSPENDED` |
| OpenAI | 429 `credit_balance_exhausted` → stage `design_blueprint` gagal (benar) |

Tidak ada foto yang dibuat; biaya praktis nol. Poin laporan tentang konten/desain belum bisa dinilai.

### Sebelum mencoba lagi
1. Aktifkan Gemini (service account / API key baru).
2. Isi saldo OpenAI.
3. (Opsional) Aktifkan kembali Google Places API key.
4. **Jangan retry project #28**: checkpoint `gemini_analysis`-nya berisi analisis *fallback*
   (lihat §6.1). Buat project baru, atau hapus checkpoint project #28 terlebih dahulu.
5. `.env` lokal berisi `PROPOSAL_AI_ENABLED=false`. Dengan setelan itu Gemini tidak pernah
   dipanggil. Untuk run nyata, override di skrip (`config(['services.proposal_ai_enabled' => true])`)
   atau ubah `.env` secara sadar.

Cara run yang dipakai: skrip PHP yang mem-bootstrap Laravel, membuat `Client` + `Project`
dengan brief di atas (tujuan, arah desain, dan catatan referensi dimasukkan ke `description`,
karena field `design_reference_notes` belum ada), lalu memanggil
`WebsiteBuilderController::runProposalGeneration()` secara sinkron. Pipeline berhenti setelah
kandidat + record Proposal dibuat, **tanpa** approve dan **tanpa** build WordPress. Live Demo
dicek lewat route preview untuk kandidat 0, 1, dan 2.

Laporan yang diminta setelah real run berhasil: sitemap Gemini, section per halaman, content
shape, composition 3 kandidat, fingerprint, jumlah foto, aset degraded, testimoni/statistik/harga
karangan, kebocoran konten referensi, perbedaan struktur kandidat, Live Demo terender,
error provider, hasil test.

## 6. Masalah terbuka (belum diperbaiki — menunggu keputusan)

1. **Fallback palsu masuk checkpoint.** `AnalisisGeminiService::analyzeProject()` memakai
   analisis fallback lokal (1 halaman, hero saja) saat Gemini gagal, dan checkpoint
   `gemini_analysis` ditandai *completed*. Retry lalu mendesain di atas fallback dan tidak
   pernah mencoba Gemini lagi. Usul: jika AI aktif, kegagalan Gemini harus menggagalkan stage
   (lempar `ProviderException`) supaya bisa di-retry. **Perlu diperbaiki sebelum real run berikutnya.**
2. **Kebocoran secret di log.** `CompetitorDiscoveryService` menulis body error Google apa adanya
   ke `storage/logs/laravel.log`, termasuk **API key Google Places dalam teks biasa**. Perbaiki
   dengan `ProviderException::sanitise()`, dan rotate/hapus key yang bocor.
3. **Foto duplikat di Media Library.** Setiap import ulang tema meng-upload foto yang sama lagi
   (`-1`, `-5`, …). Perilaku lama, bukan dari V2.
4. **Repo tidak punya CI** (`.github/workflows` tidak ada). Langkah "jalankan CI dari remote"
   belum bisa dilakukan sampai CI dibuat.
5. Retry desain oleh GPT per kandidat belum ada; perbaikan saat ini deterministik (distinctness).

## 7. Langkah berikutnya (urutan yang disepakati)

1. Perbaiki §6.1 (dan sebaiknya §6.2).
2. Aktifkan provider → **satu** real AI run → laporkan sesuai §5.
3. `git push -u origin feature/design-generator-v2-live-demo` → buat PR ke `master` → review.
   Jangan merge langsung.
4. Setelah V2 stabil, buat **branch baru** `feature/design-rule-engine` dari `master` untuk
   **Anti-Slop Design Rule Engine**. Tahap pertama: R-05, R-11, R-12, R-14, R-15, R-20, R-24,
   R-25, R-26, R-29, R-30, R-31, R-35, R-36, R-37, R-38. Aturan Anti-Slop ada di
   `.claude/skills/antislop*` (lisensi MIT, © 2026 Miqdad Badjuber: sertakan notice jika menyalin).
   Pola: GPT blueprint → validator PHP → perbaikan deterministik → (maks. 1) retry GPT → validasi ulang.
5. Setelah itu: form request klien berbentuk wizard, dengan field **"apa yang disukai dari referensi"**
   (`design_reference_notes`) sebagai prioritas.

## 8. Cara verifikasi ulang

```bash
php artisan test                      # seluruh suite (427)
php artisan test --group=browser      # smoke test responsif (butuh Node + Puppeteer)
npm run build
```

**Uji Block Editor WordPress asli** (situs uji lokal di `C:/laragon/www/wp-exito-test`, WP 7.1):

```bash
# 1. jalankan server (auto_prepend mematikan wp-cron/loopback; tanpa ini php -S sangat lambat)
#    isi prepend: define('DISABLE_WP_CRON', true); + filter pre_http_request yang menolak 127.0.0.1:8890
cd C:/laragon/www/wp-exito-test && php -d auto_prepend_file=<prepend.php> -S 127.0.0.1:8890
# 2. pasang ZIP tema hasil BundleExporterService, lalu jalankan importer:
php wp-cli.phar --path=wp-exito-test theme install <theme-install.zip> --force --activate
php wp-cli.phar --path=wp-exito-test eval 'require_once ABSPATH."wp-admin/includes/image.php"; require_once ABSPATH."wp-admin/includes/file.php"; echo exito_client_import_pages();'
# 3. cek setiap halaman di editor
WP_URL=http://127.0.0.1:8890 WP_USER=<admin> WP_PASS=<password> node tests/Browser/wp-block-editor-check.cjs <postId>...
```

Buat user admin uji dengan `wp user create <nama> <email> --role=administrator --user_pass=<password>`
(sudah ada user `claudetest` di situs uji lokal). Tema asli situs uji
dicadangkan di `wp-content/themes/exito-client-theme.bak-v1`.

## 9. Cara kerja dengan tim

- Bahasa: Indonesia. Jawaban ringkas.
- Permintaan sering datang sebagai tempelan chat AI milik Leo. Tugasnya **memverifikasi dan
  mengoreksi**, bukan sekadar merangkum.
- Koreksi disampaikan sebagai "hasil pengecekan lanjutan", bukan "yang tadi salah".
- Aksi berbayar (API) dan aksi keluar (push, PR) hanya dilakukan setelah ada izin eksplisit.
