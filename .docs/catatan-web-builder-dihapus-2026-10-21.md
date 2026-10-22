# Fitur web builder dihapus total 2026-10-21, menunggu rancangan baru dari pemilik

Catatan penutup. Seluruh rantai pembangkit website — penulis konten, tiga kandidat
mockup, proposal PDF, pilihan + approval klien, sampai ZIP WordPress beserta
pratinjau mockup-nya — dihapus dari kode, basis data, dan tampilan atas perintah
pemilik: **"hapus fitur web builder kita buat ulang dari awal 0 kodekan"**.
Dokumen ini satu-satunya jejaknya, supaya rancangan ulang tidak perlu menebak apa
yang dulu ada.

## Tiga keputusan pemilik yang mengikat

1. **Rancangan bangun ulang diceritakan pemilik sendiri nanti.** Saya tidak
   menulis satu baris fitur pengganti; yang tersisa dari Workspace hanyalah
   cangkang (rel tahap + kartu Informasi Umum + jalur SEO/Backlink).
2. **Seluruh rantai pembangkit ikut dihapus.** Yang dipertahankan: form Request,
   halaman Workspace (cangkangnya), SEO/backlink, Finance/CRM, Reports.
3. **Datanya ikut dihapus.** Tabel proposals ternyata berisi 0 baris, jadi tidak
   ada data yang hilang sama sekali.

## Arsip sebelum dihapus

- Kode rantai: `storage/app/backup/web-builder-dihapus-20261021.tar.gz`
  (101 berkas, 201.204 bita) + daftar isinya
  `storage/app/backup/daftar-berkas-builder-20261021.txt`.
- Penahan biaya yang dicabut sorenya (terpisah, sudah dicatat di dokumen
  rancangan bagian 19): `storage/app/backup/penahan-biaya-dibatalkan-20261021/`.

Semua masih bisa dipulihkan dari arsip itu atau dari git (belum di-commit pada
saat penghapusan, tapi beberapa pekerjaan sebelumnya sudah masuk commit pemilik).

## Yang hilang

- **Kode:** 3 controller (WebsiteBuilderController, BundleController,
  MockupPreviewController), 3 model (Proposal, ProjectBundle, dan model yatim
  ProjectProposalItem), 10 service + 1 trait, 2 job, 9 kelas Support, model
  PipelineCheckpoint yang tertinggal, dan view `mockup/` utuh + 7 view lain
  (termasuk komponen progress-bar tempat modal progres tinggal).
- **Tes:** 17 file Feature + 5 file Unit milik rantai. Dua file tes campuran
  (ProviderSafetyAuditTest, ProviderFailureRecoveryTest) tidak dihapus utuh —
  bagian yang menguji layanan bersama SEO dipertahankan, sisanya dilepas.
- **Basis data:** migrasi pembuat/pengubah tabel rantai dihapus, dan satu
  migrasi penjaga baru (`2026_10_21_140000_drop_web_builder_artifacts`)
  menjatuhkan tabel `proposals`, `project_bundles` (0 baris, tertinggal dari
  purge lama), `pipeline_checkpoints` (3 baris), `mockup_templates` (sudah
  tiup oleh purge ZipWP), plus dua kolom jejak di projects. Semua dijaga
  `hasTable`/`hasColumn`, jadi di basis data yang sudah bersih migrasi ini
  no-op. Sudah dijalankan di MySQL dev.
- **Lain-lain:** folder kosong `storage/app/bundles`, skrip pemeriksa peramban
  `tests/Browser/wp-block-editor-check.cjs`, rute proposal/mockup/bundle, dan
  jalur 'web' di rel Workspace (rel kini hanya SEO/Backlink; project tanpa
  keduanya menampilkan kartu informasi saja).

## Yang hampir bocor dan dibereskan saat penghapusan

1. **`JsonRepair` ternyata bukan milik rantai.** Layanan SEO
   `AnalisisGeminiService` masih memanggilnya. Kelasnya dikembalikan utuh dari
   arsip ke `app/Support/JsonRepair.php`.
2. **Kolom `description` lahir dari pasangan migrasi rantai.** Migrasi penambah
   kolom lama dihapus, jadi migrasi pengganti namanya juga dihapus dan kolom
   `description` kini dibuat langsung di migrasi pembuat tabel projects.
   MySQL dev tidak terdampak (kolomnya sudah ada sejak lama).
3. **Dua metode yatim ikut dikurbulkan** karena hanya rantai yang memakai:
   `AnalisisGeminiService::deriveTargetMarket()` (plus prompt-nya) dan
   `ProjectFile::assetRole()` (plus daftar perannya). Kelas AnalisisGeminiService
   sekarang murni milik SEO.
4. **Seeder** masih mengimpor model Proposal (impor mati saja, tidak pernah
   dipakai) — impor itu dihapus.
5. Komentar yang menunjuk kelas terhapus dirapikan di ProjectController,
   SeoBacklinkController, requestOrderController, ProjectFile, dan
   AnalisisGeminiService.

## Yang sengaja tidak disentuh

- Tiga tes billing yang memang sudah merah sejak sebelum penghapusan
  (2 BillingEmailDelivery + 1 BillingRollbackGuard — yang terakhir menunggu
  keputusan pemindahan penjaga rollback, lihat daftar tugas).
- `PageController` tanpa rute (kode mati lama, bukan milik rantai).
- Kunci konfigurasi OpenAI di `config/services.php` — masih dipakai SEO
  (AnalisisGeminiService) dan nanti mungkin dipakai rancangan baru.
- `.env` tidak disentuh sama sekali.
- Dua skrip lepas lama di `storage/app/`: `create-demo-mockup.php` dan
  `generate-demo-ai.php` (bukan bagian penghapusan ini).
- Dokumen rancangan lama di `.docs/` dibiarkan sebagai rekam sejarah
  (`rancangan-mockup-api-openai-2026-10-21.md`, `rancangan-jalur-b-terjaga.md`,
  `hemat-biaya-proposal.md`, `rancangan-desain-sisi-klien.md`).

## Verifikasi

- MySQL dev sesudah migrasi: keempat tabel rantai hilang; tabel projects utuh
  (22 kolom, 1 baris, tanpa kolom jejak); data klien tidak tersentuh.
- Suite penuh (sqlite, **bukan** `DB_CONNECTION=mysql` — jangan pernah):
  **3 gagal / 366 lolos / 1.237 asersi**. Tiga-tiganya merah billing lama yang
  sama. Pembanding sebelum penghapusan: 3 gagal / 584 lolos / 2.405 asersi —
  selisihnya seluruhnya tes milik rantai yang dihapus.
- `php artisan route:list` sehat: 86 rute, tanpa satu pun rute proposal,
  mockup, atau bundle.

## Langkah berikutnya

Bangun ulang dari nol **menunggu penjelasan rancangan dari pemilik** — itu
kesepakatannya saat memutuskan hapus. Sampai penjelasan itu datang, tidak ada
kode pengganti yang ditulis, dan yang tersisa dari fitur ini hanya dokumen ini
plus arsip di `storage/app/backup/`.
