# Jalur API mockup dipensiunkan — 4 November 2026

Sejak hari ini dashboard hanya punya satu jalan untuk membuat kandidat mockup:
Codex. Saklar `MOCKUP_ENGINE` / `services.mockup.engine` sudah tidak ada, jadi
tidak ada lagi pilihan mesin di layar maupun di berkas konfigurasi.

## Yang dihapus

| Berkas | Dulu fungsinya |
| --- | --- |
| `app/Jobs/GenerateMockupCandidateJob.php` | pekerjaan antrean satu kandidat |
| `app/Services/MockupCandidateRunner.php` | penulis halaman lewat API OpenAI |
| `app/Services/MockupToolLoop.php` | loop alat (function calling) |
| `app/Services/MockupToolBox.php` | daftar alat yang boleh dipakai model |
| `app/Services/MockupOpenAiClient.php` | klien HTTP ke API OpenAI |
| `app/Services/MockupTurn.php` | bentuk satu jawaban model di dalam loop |
| `app/Support/MockupRules.php` | pembaca berkas aturan `resources/ai/aturan-mockup.md` |
| `tests/Feature/MockupCandidateRunnerTest.php` | tes loop + runner API |
| `tests/Feature/MockupToolLoopTest.php` | tes loop alat |
| `tests/Feature/MockupRulesTest.php` | tes pembaca aturan |

Ikut dirapikan: kunci `openai.mockup_model`, `openai.image_*`,
`openai.images_per_minute`, `openai.review_generated_images`,
`openai.image_review_model` dan `openai.mockup_candidate_count` di
`config/services.php` (tidak ada satu pun yang membaca lagi); `MockupBrief`
hanya menyisakan data proyek karena brief Codex yang merakit kalimatnya.

## Cara memulihkan

Arsip lengkap (versi terakhir sebelum dihapus, 17 berkas):
`arsip-jalur-api-2026-11-04.tar.gz` di akar proyek.

```
tar -xzf arsip-jalur-api-2026-11-04.tar.gz
```

Isi arsip sama dengan keadaan git sebelum pembersihan ini, jadi `git checkout`
berkas yang sama juga mengembalikan. Berkas brief lama ada di dalam arsip dengan
nama `app/Services/MockupBrief.API-LAMA.php` supaya tidak menimpa versi ramping
yang sekarang dipakai Codex.

## Catatan yang masih terbuka

1. `resources/ai/aturan-mockup.md` sudah tidak dibaca kode mana pun — brief
   Codex menulis instruksinya sendiri. Berkasnya dibiarkan karena berisi rumusan
   desain yang masih dirujuk dokumen lain; bilang saja kalau mau ikut dibuang.
2. Kolom `critique_rounds` di tabel `mockup_candidates` adalah sisa ronde kritik
   jalur API. Sekarang selalu 0. Menurunkan kolom berarti migrasi, jadi belum
   disentuh.
3. Brief Codex masih menulis "Batas keras proses ini 20 menit", padahal batas
   runner sudah dinaikkan ke 35 menit (2100 detik). Angka di brief itu punya
   pemilik brief — belum diubah.

## Verifikasi

`DB_CONNECTION=sqlite php artisan test` → 398 tes lolos, 1551 pernyataan.
`tests/Feature/MockupTeamGateTest.php` tidak lagi bicara soal antrean job;
yang dulu memeriksa "tiga job dikirim" sekarang memeriksa "satu runner Codex
dilepas", dan penolakan buat ulang diuji dengan kunci proyek yang dipegang
sungguhan, bukan dengan job yang sedang jalan.
