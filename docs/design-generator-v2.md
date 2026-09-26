# Design Generator V2 & Live Website Demo

Branch: `feature/design-generator-v2-live-demo`

Prinsip utama: **LIVE PREVIEW = APPROVED BLUEPRINT = WORDPRESS RESULT.**
Tidak ada tiga desain berbeda untuk tiga tahap itu — ketiganya membaca data yang sama
(`mockup.pages`, `mockup.design`, `mockup.assets`) dan keputusan struktur yang sama
(`ElementorPageBuilderService::describeSections()`).

## 1. Flow aktual (hasil audit, sebelum V2)

```text
Project + Client
  → CompetitorDiscovery / CompetitorContentFetcher      (checkpoint: competitor_research)
  → AnalisisGeminiService::analyzeProject()             (checkpoint: gemini_analysis)
      menulis SELURUH konten: sitemap.pages[].sections[] (type, headline, description, cta, items)
  → GenerateMockupGptService::generateMockupBlueprints() (checkpoint: design_blueprint)
      3× generateMockup(): GPT memilih warna, font, token, dan composition
      per ROLE (hero / icon_band / card_grid) — bukan konten.
      enforceDistinctDesigns(): tiga kandidat wajib beda hero & fingerprint.
      → blueprint FROZEN
  → GenerateMockupGptService::renderCandidates()         (checkpoint: mockup_assets)
      MockupAssetService::generateForCandidate(): foto hero + ≤4 foto item card_grid,
        dipersist ke storage/app/public/mockup-assets/{code}/candidate-{n}/
      generateMockupImage(): render pdf/mockup-render.blade.php → PNG (Browsershot 1440px)
  → PDF proposal + Proposal.ai_reasoning {analysis, mockup, mockup_candidates, selected_mockup_index}
  → selectMockup / approveProposal
      BlueprintManifestService::build() → implementation_manifest (deterministik, bukan GPT vision)
  → BundleBuilderService::build()
      MockupAssetService::loadApproved()  (byte foto yang di-approve, tanpa generate ulang)
      ElementorPageBuilderService::buildPages() → Gutenberg HTML per halaman
      ClaudeWordPressBuilderService::build() → header/footer/style.css/functions.php
  → BundleExporterService → ZIP theme + importer halaman
```

### Masalah yang ditemukan

| # | Lokasi | Masalah |
|---|--------|---------|
| 1 | `describeSections()` | Hanya hero + section ber-item pertama (`icon_band`) + kedua (`card_grid`) yang dirender; sisanya `skipped`. |
| 2 | `BundleBuilderService` | `array_slice($mockupPages, 0, 1)` → hanya Home yang dibangun. |
| 3 | `pdf/mockup-screenshot.blade.php` | Renderer lama hard-coded; hanya dipakai sebagai fallback bila kandidat tidak punya screenshot. |
| 4 | `pdf/mockup-render.blade.php` | Hanya bisa merender hero + 1 icon band + 1 card grid, lebar tetap 1440px, tidak responsif. |
| 5 | `buildPages()` | Slug halaman bisa bertabrakan (`$pages[$slug]` saling menimpa); Home = index 0 walau halaman "Home" ada di posisi lain. |
| 6 | `MockupAssetService::loadApproved()` | Nama file `home-item-N` hanya muat satu section berfoto per halaman. |

## 2. Konflik dengan asumsi awal & keputusan

1. **Proposal lama yang sudah di-approve** hanya menampilkan 3 section di PNG. Merender semua
   section untuk blueprint lama akan membuat WordPress berbeda dari yang di-approve client.
   → Blueprint baru ditandai `design.renderer_version = 2`. Blueprint tanpa penanda tetap
   memakai aturan legacy (3 role) di preview, PNG, manifest, dan WordPress — konsisten dengan
   apa yang dulu di-approve. Untuk mendapat layout V2, proposal di-generate ulang.
2. **Nama role `icon_band` dan `card_grid`** sudah tersimpan di blueprint lama, manifest aset
   (`sections[n].role = card_grid`), dan skema JSON designer. Nama itu dipertahankan
   (setara dengan `feature_grid` dan `services`/showcase). Role baru ditambahkan untuk section lain.
   Keputusan *bagaimana* section digambar dipisah ke field `renderer` pada plan.
3. **Konten section berasal dari Gemini**, bukan GPT. Agar ritme visual bisa bervariasi, prompt
   Gemini diperluas sedikit (bentuk item untuk FAQ/testimoni/statistik/harga/tim) — tanpa
   mengubah alur. GPT tetap tidak menulis konten.
4. **Tidak ada policy per-project** di aplikasi ini; semua route project cukup `auth`. Preview
   mengikuti pola yang sama.

## 3. Arsitektur V2

### Section plan (satu sumber keputusan)

`ElementorPageBuilderService::describeSections($sections, $design)` mengembalikan per section:
`role`, `renderer`, `composition` (hasil `CompositionSpec::resolve()`), alignment, `rendered`.

| Role | Renderer | Composition | Konten |
|------|----------|-------------|--------|
| `hero` | `hero` | `HERO_COMPOSITIONS` | section index 0 |
| `icon_band` | `features` | `feature_grid` | fitur / alasan |
| `card_grid` | `cards` | `standard_cards`, `asymmetric_cards` | layanan / produk (berfoto) |
| `editorial_media` | `editorial` / `alternating` | `editorial_text_image`, `alternating_media` | cerita, about, split teks-gambar |
| `stats` | `stats` | `stats_band` | angka |
| `testimonials` | `testimonials` | `testimonial_grid` | kutipan |
| `gallery` | `gallery` | `gallery` | portofolio / galeri |
| `logos` | `logos` | `logo_showcase` | klien / partner |
| `faq` | `faq` | `faq` | tanya-jawab |
| `cta` | `cta` | `cta` | ajakan / kontak |
| `team` | `team` | `team` | tim |
| `pricing` | `pricing` | `pricing` | paket harga |

Legacy (tanpa `renderer_version`): hanya `hero`, `icon_band`, `card_grid`; sisanya `skipped`.

### Renderer bersama

```text
App\Support\MockupSite::build()        view-model: nav, halaman aktif, section + plan + foto
resources/views/mockup/site.blade.php  dokumen HTML lengkap
resources/views/mockup/styles.blade.php, header, footer, hero, section, sections/*.blade.php

pdf/mockup-render.blade.php     → @include('mockup.site', fixed width 1440, data-URL foto)
projects/mockup-live.blade.php  → @include('mockup.site', responsif, URL foto dari storage)
```

### Live preview

- `GET /projects/{project}/mockup/{candidate}/preview` → `pages.projects.mockup.preview`
  (halaman website murni, `?page=slug` untuk berpindah halaman)
- `GET /projects/{project}/mockup/{candidate}/demo` → `pages.projects.mockup.demo`
  (wrapper Desktop / Tablet / Mobile / Fullscreen dengan iframe)
- `candidate` = index 0-based `mockup_candidates`; di luar jangkauan → 404.

### WordPress

`renderGutenbergBlocks()` mengikuti plan yang sama dan memakai core blocks
(`group`, `columns`, `heading`, `paragraph`, `list`, `quote`, `details`, `image`, `buttons`)
sehingga tetap bisa diedit di Block Editor. Seluruh sitemap dibangun; Home selalu halaman
pertama & front page; slug dijamin unik.
