# Fitur website + WordPress: DITAHAN 2026-10-20

**Perintah pemilik:** "fitur web wp tahan dulu sampek sini buat catatan."
Tidak ada suntingan lagi pada alur request → konten → mockup → approval → theme ZIP sampai dia membuka
kembali. Catatan ini titik balik, bukan rencana kerja.

## Angka pada titik henti

- Commit terakhir `e6ca58f`. **Semua pekerjaan sejak itu belum di-commit**: 58 berkas terlacak berubah
  (+3.132 / −3.732) dan 11 entri baru (4 dokumen `.docs`, 2 kelas PHP, 9 parsial `resources/views/workspace/`,
  4 file tes). Kalau ada yang rusak, tidak ada titik balik di dalam repo.
- Suite penuh: **3 failed / 579 passed / 2.383 assertion**. Tiganya merah lama urusan Billing
  (2× `BillingEmailDeliveryTest` template email tidak merender rincian, 1× `BillingRollbackGuardTest`
  hitungan ledger migrasi), bukan akibat pekerjaan website.
- Tidak ada satu pun yang diverifikasi di peramban pada proyek ini (login otomatis diblokir). Klaim
  tampilan berhenti di tes Feature + baca kode. Yang terukur di Chromium asli hanya 12 kombinasi chrome
  nav × footer (Jalur A, 2026-10-19).

## Yang sudah selesai dan terbukti

Rinciannya jangan ditulis ulang di sini; keempat dokumen `.docs` masih sumbernya.
- **Tahap A/B/C alur website** (2026-10-19) - penulis konten pindah ke GPT lewat Responses API wajib
  streaming, pilihan klien + approval jadi satu aksi, build ZIP masuk antrean + endpoint progres, theme
  dibangun deterministik (nol panggilan AI), ZIP membuat project `completed`.
- **Jalur A: chrome nav + footer jadi varian** (2026-10-19) - 4 varian header × 3 footer, kunci pembentuk
  tetap PHP, paritas PNG = demo = ZIP tidak berubah.
- **Nav menu WordPress** (2026-10-19) - chrome bisa dirender tanpa daftar tautan statis, importer membuat
  dan menetapkan menu.
- **Plafon keluaran penulis konten** (2026-10-20) - 32.768 → 16.384, karena pada gpt-5.6 plafon adalah batas
  tagihan satu panggilan, bukan pilihan panjang tulisan.
- **Foto draf hero-only** (2026-10-20) - 27 foto per proposal → 3 draf + maksimal 8 untuk opsi yang dipilih.
  Dua akibat yang paling penting: biaya ± $1,70 → ± $0,69, dan "proposal pertama selalu pulang dengan foto
  bolong" berhenti jadi biasa (27 foto pada 5 gambar/menit = 5,4 menit, anggaran cuma 300 detik).

## Yang belum disentuh sama sekali

| Tuas | Isi | Kenapa ditahan |
|---|---|---|
| #66 | Pencatat pemakaian tiap panggilan AI | Bukan penghematan, tapi syarat: tanpa ini semua angka di `hemat-biaya-proposal.md` tetap aritmetika tabel harga, dan tidak ada cara membuktikan sebuah perubahan berhasil |
| #76 | Plafon dolar per proposal, dicek sebelum tiap gelombang | Bergantung #66 |
| #67 | Screenshot referensi dibayar tiga kali | ± 2 baris, nol risiko; belum dikerjakan karena tidak mendesak |
| #65 | Ganti model foto ke yang murah (hemat 76%) | Tidak bisa dibuktikan tanpa saldo + mata pemilik |

## Tujuh hal yang menunggu keputusan pemilik

Setiap baris: pertanyaannya apa, dan apa yang berubah kalau dia jawab.

1. **Approval saat kuota foto habis.** Sekarang: jalan terus pakai draf (tanpa foto bagian tengah), karena
   jaminan "approval bisa tanpa panggilan AI apa pun" tidak boleh dikorbankan demi foto. Kalau dia mau
   approval ditolak sampai foto penuh, itu pilihan sadar: tombol lagi bisa gagal saat OpenAI bermasalah.
2. **Cacat kontras tombol (M-2, `aturan-desain-sisi-klien.md` baris 104).** Ada di setiap tombol setiap
   klien, hari ini, di jalur lama, tidak ada hubungannya dengan Jalur B. Sudah diminta izinnya, belum dijawab.
3. **Empat keputusan `rancangan-jalur-b-terjaga.md` §8** - tiga opsi atau dua, cadangan ke susunan lama,
   dan konsekuensi "tata letak tidak bisa diedit lewat Gutenberg". Catatan: §8 masih menulis "foto hero saja
   saat draf" sebagai keputusan yang menunggu, padahal tuas itu sudah dikerjakan 2026-10-20 lewat urutan
   hemat biaya. §8 perlu dicoret ulang supaya dua dokumen tidak bilang beda.
4. **Status asli project #1 "[DEMO] Website Toko Roti"** - jatuh ke `request` karena run dummy 2026-10-19;
   nilai sebelumnya tidak tercatat di mana pun.
5. **`ends_on` di tengah periode** - nunggu jawaban tim, bukan nunggu kode.
6. **Dua temuan uji instal ZIP di WordPress lokal** - README dalam ZIP masih menyuruh hapus theme lama, dan
   foto klien yang sudah ada di Media Library belum dipakai ulang berdasarkan nama file.
7. **Sisa yang sengaja ditinggal dan tercatat sebagai keputusan** - 9 kontrol toolbar tanpa border, copy
   Inggris dua form, kolom `wants_website` yang tidak pernah disimpan, data SEO/backlink yang ditinggal saat
   centang layanannya dilepas, blok Status duplikat yang masih jadi komentar di form Edit Project.

## Rantai yang harus diketahui yang melanjutkan

Urutan tahap: `target_market` (Gemini, fail soft) → `competitor_research` → `website_content` (gpt-5.6,
streaming) → `design_blueprint` (gpt-5-mini, 3 kandidat) → `mockup_assets` (foto) → `proposal_document` →
approval → build theme ZIP (deterministik, nol AI). Enam penjaga yang jangan dikorbankan demi hemat:

1. Blueprint dibekukan sebelum satu foto pun dibayar; setelah itu komposisi tidak boleh berubah.
2. `reusePersisted()`: retry tidak membayar ulang foto yang sudah di disk.
3. Paritas PNG = demo = ZIP, semuanya membaca manifest foto yang sama.
4. Approval tetap jalan tanpa provider apa pun.
5. Kandidat `degraded` tidak boleh ditampilkan sebagai desain selesai.
6. Nomor aset deterministik per kandidat, supaya approval tidak bisa mengambil foto opsi lain.

## Cara mengukur ulang tanpa memakai uang

```
php artisan test tests/Feature/PipelineIdempotencyTest.php tests/Feature/PipelineResilienceTest.php \
  tests/Feature/MockupAssetLifecycleTest.php tests/Feature/BlueprintParityTest.php \
  tests/Feature/ApproveProposalTest.php tests/Feature/DesignGeneratorV2Test.php
```
Semua tes itu memalsukan endpoint gambar, jadi nol token. Baseline terukur 2026-10-20: **65 passed
(290 assertion)**.
