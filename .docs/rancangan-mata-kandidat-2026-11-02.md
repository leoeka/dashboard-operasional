# Rancangan: mata untuk kandidat Codex

Tanggal: 2026-11-02
Status: menunggu persetujuan pemilik. Tidak ada berkas kode yang disentuh sampai dokumen ini disetujui.

## 1. Arah

Satu kandidat tidak lagi langsung masuk meja tinjauan setelah Codex selesai. Halaman yang sudah jadi difoto, dinilai oleh juri yang tidak menulis halaman itu, lalu temuannya dikembalikan ke Codex untuk diperbaiki. Maksimal dua ronde. Yang dinilai bukan selera: ritme bagian, label kecil di atas judul, judul yang masih bisa dipasang di usaha lain, dan daftar pola slop yang hari ini sudah dikirim ke Codex tetapi tidak pernah diperiksa terhadap gambar sungguhan.

## 2. Kenapa hasil sekarang masih ngeslop (terukur hari ini)

- Potret dibuat **setelah** Codex selesai, dan tidak ada satu langkah pun sesudahnya yang membaca potret itu ([MockupCodexRunner.php:231](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupCodexRunner.php#L231)).
- Daftar pola slop memang ikut terkirim (bagian 4 `resources/ai/aturan-mockup.md`, muncul sebagai "Daftar audit pola slop (dibaca saat ronde kritik)" di baris 105 `candidate-27/brief.txt`), tetapi di jalur Codex tidak pernah ada ronde kritik. Aturan itu dibaca sekali oleh model lalu dilupakan.
- Arah desain tidak pernah dikirim. `DESIGN.md` muncul **0 kali** di brief kandidat 3. Yang masuk hanya penolak (bagian K/M/P). Penolak tanpa arah menghasilkan halaman yang aman dan seragam, persis yang Anda sebut.
- Gejala di [preview.png kandidat 3](file:///C:/Program%20Codding/dashboard-operasional/storage/app/mockup-work/9/candidate-27/preview.png): pola "label kecil + judul serif besar + paragraf + tautan" berulang 4 kali; lima bagian memakai susunan yang sama (kiri teks / kanan foto, blok penuh, tiga kolom, kiri teks / kanan foto, ajakan); tiga kolom dengan garis tipis di atasnya; judul "Ide baik jadi langkah nyata", "Perhatian ada pada detail kecil", "Mulai dari kebutuhan Anda" yang masih sah kalau dipasang di toko bangunan sebelah.

**Koreksi atas klaim saya kemarin.** Saya bilang Codex tidak bisa melihat gambar. Itu salah, dan saya periksa versi yang terpasang: `codex exec` menerima `-i, --image <FILE>` dan sesi bisa dilanjutkan dengan `codex exec resume [SESSION_ID] [PROMPT]`. Id sesi kandidat sudah direkam runner di [MockupCodexRunner.php:438](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupCodexRunner.php#L438). Jadi ronde kedua tidak perlu alat baru.

## 3. Bentuk yang diusulkan: dua mata, satu tukang

1. **Juri.** Satu panggilan ke `MockupOpenAiClient::send()` yang sudah ada ([MockupOpenAiClient.php:18](file:///C:/Program%20Codding/dashboard-operasional/app/Services/MockupOpenAiClient.php#L18)), non-streaming, tanpa alat. Isinya `preview.png` kandidat plus daftar aturan bernomor. Keluarannya JSON temuan: id aturan, bagian mana, apa yang salah, apa yang harus diganti. Hakimnya terpisah karena model yang menilai pekerjaannya sendiri cenderung menyatakan sudah selesai.
2. **Tukang.** `codex exec resume <thread-id> -i preview.png` dengan prompt berisi temuan juri, dijalankan di folder kerja yang sama supaya foto, halaman, dan rencana tidak berubah alamat.
3. **Berhenti.** Ronde diulang paling banyak 2 kali. Berhenti lebih awal kalau `md5(index.html)` sama sebelum dan sesudah ronde (berarti Codex tidak mengubah apa pun), atau kalau juri sudah tidak menemukan pelanggaran keras.

Yang tetap jalan kalau juri mati: kegagalan juri **tidak** membuat kandidat gagal. Kandidat masuk meja tinjauan dengan catatan "tidak dinilai". Mata baru tidak boleh menjadi sumber kegagalan baru, karena hari ini saja dua dari tiga kandidat gagal dan itu cukup mengganggu.

## 4. Aturan baru yang ditulis

| Id | Aturan | Sifat |
| --- | --- | --- |
| K-14 | Tidak lebih dari dua bagian beruntun dengan latar yang sama, dan halaman wajib punya minimal satu bagian yang memecah susunan kolom dua sisi (pita penuh, tumpuk, teks menabrak tepi) | keras |
| K-15 | Label kecil di atas judul wajib menyebut hal konkret (bahan, kota, proses), bukan kata abstrak seperti "ruang lingkup" atau "cara kami mendampingi" | keras |
| K-16 | Judul bagian wajib lolos uji tukar: kalau judul itu masih sah dipasang di usaha lain di kota yang sama, ganti | keras |
| M-16 | Skor juri tercatat per kandidat (temuan keras dan lunak sebelum serta sesudah ronde) dan ditampilkan di kartu kandidat | lunak |
| P-8 | Satu keputusan desain yang tidak biasa per halaman, dan alasannya ditulis di `rencana.json` | ronde kritik |

## 5. Biaya dan waktu

Belum diukur, dan saya tidak akan mengarang angkanya. Yang diukur pada tahap pertama dan dilaporkan apa adanya:

- token masuk dan keluar satu panggilan juri (satu PNG 1440 + daftar aturan + keluaran JSON),
- durasi satu ronde `resume` Codex,
- selisihnya terhadap pesanan tiga kandidat hari ini (yang terakhir berjalan sekitar 9 menit dari mulai sampai kandidat 3 selesai).

Perkiraan sebelum ukur: 3 panggilan juri per pesanan plus 1 sampai 2 ronde Codex tambahan per kandidat. Kalau angkanya ternyata tidak masuk akal, sakelar `off` tinggal dinyalakan dan perilaku kembali persis seperti hari ini.

## 6. Bukti sebelum diserahterimakan

1. Satu proyek nyata, satu pesanan penuh. PNG sebelum dan sesudah disimpan di `storage/app/mata-2026-11-02/`.
2. Angka yang dilaporkan: jumlah bagian beruntun berlatar sama (sebelum / sesudah), jumlah pola label + judul yang berulang, temuan juri ronde pertama versus ronde akhir, durasi, dan token.
3. Tangkapan layar kartu kandidat di dasbor yang memperlihatkan catatan juri.
4. Tes: unit untuk pengurai JSON juri dan pembanding `md5`, feature untuk alur "juri menemukan, Codex dipanggil ulang, kandidat tersimpan dengan catatan" memakai klien HTTP dan proses palsu, lalu suite penuh sqlite.

## 7. Jalan pulang

- Sakelar baru `services.mockup.critic` bernilai `off` atau `on`. `off` mengembalikan perilaku hari ini persis: satu ronde Codex, tanpa juri, tanpa aturan baru.
- Sebelum sunting: `MockupCodexRunner.php`, `MockupCodexBrief.php`, `resources/ai/aturan-mockup.md`, dan `.docs/aturan-desain-sisi-klien.md` disalin ke `storage/app/arsip-mata-2026-11-02/`. Kandidat 3 yang sekarang dibiarkan utuh sebagai pembanding "sebelum".
- Semua pekerjaan masih belum di-commit; commit terakhir `9c47212`.

## 8. Yang tidak disentuh

- Penjaga foto kembar tidak dipasang kembali.
- Pembangun tema, importer WordPress, meja periksa ZIP, dan jembatan CSS Elementor.
- Tuas B (dokumen arah desain per proyek situs klien). `DESIGN.md` yang ada sekarang adalah arah untuk dasbor aplikasi ini, bukan untuk situs klien, dan isinya harus Anda tulis. Masuk ronde berikutnya kalau Anda mau.
- Billing, migrasi, dan `ReportTest` yang sudah merah sejak awal.

## 9. Yang perlu keputusan Anda

1. Setuju bentuk "dua mata, satu tukang" (juri API menilai, Codex memperbaiki), atau hemat satu mata saja (Codex dikirimi gambarnya sendiri, nol biaya tambahan, tapi menilai hasil kerjanya sendiri)?
2. Aturan mana yang masuk: K-14, K-15, K-16, M-16, P-8.
3. Plafon ronde: 2 (usul saya) atau 1.
