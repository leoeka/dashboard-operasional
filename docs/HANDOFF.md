# Catatan Serah-Terima — Design Generator V2 & Live Demo

Terakhir diperbarui: **26 September 2026**
Branch: **`feature/design-generator-v2-live-demo`** (lokal, **belum di-push**, belum ada PR)
Repo: `github.com/leoeka/dashboard-operasional` — branch utama `master` (jangan commit langsung ke master)

Dokumen ini untuk siapa pun (manusia atau AI lain) yang melanjutkan pekerjaan ini tanpa
riwayat chat. Baca juga [design-generator-v2.md](design-generator-v2.md) untuk arsitektur detail.

---

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
