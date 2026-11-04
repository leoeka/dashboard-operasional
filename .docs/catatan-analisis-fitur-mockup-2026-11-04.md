# Catatan analisis: fitur mockup keadaan 4 November 2026

Status: **analisis keadaan sekarang**. Nol berkas kode disunting di tahap ini.

Dasar: berkas yang dibaca hari ini (lihat daftar peta), empat berkas status Codex asli di
`storage/app/mockup-work/*/codex/status.json`, isi 52 folder kandidat di disk, dan
`arsip-jalur-api-2026-11-04.tar.gz` untuk jalan kembali.

Angka di catatan ini saya ukur sendiri hari ini, bukan ingatan dari ronde sebelumnya.

---

## 1. Peta alur hari ini: satu jalan, tanpa cabang

Sejak sakelar mesin dicabut pagi ini, satu-satunya cara membuat kandidat adalah Codex CLI.

| Tahap | Berkas | Peran |
| --- | --- | --- |
| Tombol "Buat 3 kandidat" | `app/Http/Controllers/MockupCandidateController.php` | membuat tiga baris klaim lalu melepas proses di latar |
| Pelepas proses | `app/Support/MockupCodexLauncher.php` | menjalankan `php artisan mockup:codex <id>` sebagai anak, tidak menunggu |
| Titik masuk | `app/Console/Commands/GenerateMockupCodex.php` | bisa dijalankan tangan dari terminal untuk uji |
| Pelarian utama | `app/Services/MockupCodexRunner.php` | satu kandidat selesai → baru kandidat berikutnya |
| Surat kerja | `app/Services/MockupCodexBrief.php` + `MockupBrief.php` | brief per kandidat, dibaca Codex dari `brief.txt` |
| Alat | `app/Support/MockupCodexState.php` | `status.json` (layar), `kunci.lock` (satu pelarian per proyek), meja kerja di luar repo |
| Foto | `app/Services/MockupCodexPhotos.php` | menyalin foto dari jalur yang dicatat model, lalu dari cache Codex |
| Mata | `app/Support/PageSnapshot.php` | potret `index.html` jadi `preview.png` lewat Chromium |
| Layar | `resources/views/workspace/mockup-gate.blade.php` | kartu kandidat, bendera berhenti, tabel periksa tema |
| Hilir | `MockupProposalPdf.php`, `WpThemeBuilder.php` | proposal PDF, lalu tema WordPress dari folder kandidat yang dipilih klien |

Kontrak foto antara dua ujung: halaman menandai tempat foto dengan
`__EXITO_IMAGE:<nama-slot>`, nama slot diambil dari halaman itu sendiri (bukan dari daftar
nama yang ditulis di sisi kita), dan pembangun tema mengganti tanda itu dengan berkas nyata.

---

## 2. Angka nyata

### Waktu dan token satu batch tiga kandidat

| Proyek | Klien | Selesai | Waktu nyata | Token tercatat |
| --- | --- | --- | --- | --- |
| 8 | PT Sinar Bamboo | 31 Okt | 12 m 32 dtk | 2.566.309 |
| 9 | Nirwara | 3 Nov | 11 m 14 dtk | 1.161.841 |
| 10 | Kalpataru Interior | 4 Nov | **43 m 13 dtk** | **4.886.303** |

Proyek 10 adalah ronde dengan surat kerja baru (rancangan visual lebih dulu, lalu halaman).
Bedanya besar: **3,5 kali lebih lama dan 1,9 kali lebih banyak token** daripada proyek 8,
tetapi itu ronde yang hasilnya Anda pilih. Rata-rata per kandidat jadi 14 m 24 dtk.

Proyek 11 (PT SH Transport) sedang berjalan saat catatan ini ditulis: 1 kandidat, tahap
"membuat foto", denyut terakhir 25 detik sebelum pengukuran. Tidak saya sentuh.

### Isi meja kerja

| Ukur | Angka |
| --- | --- |
| Total di `storage/app/mockup-work` | **351 MB** |
| Folder kandidat | 52 (proyek 8: 24, 9: 18, 10: 3, 11: 7) |
| Folder yang sampai punya potret | 25 dari 52 (48 %) |
| Kandidat gagal yang tidak menyisakan `codex.log` | **23 dari 27 (85 %)** |
| Foto per kandidat sukses (proyek 10) | 3 sampai 4 berkas |

---

## 3. Bagian yang sudah keras, jangan diubah dulu

- **Satu pelarian per proyek, dibuktikan kunci sistem** (`flock`), bukan isi `status.json`.
  Proses yang mati melepas kunci sendiri, jadi layar tidak pernah menipu soal "masih jalan".
- **Meja kerja di luar repo.** Kalau dijalankan di dalam repo, Codex ikut membaca
  `AGENTS.md`/`DESIGN.md` milik aplikasi ini lalu berhenti meminta pilihan mode.
- **Status ditulis lewat berkas sementara lalu diganti**, jadi layar tidak pernah membaca
  JSON setengah jadi; denyut lebih tua dari 5 menit dibaca sebagai "berhenti", bukan terus berputar.
- **Draf yatim diklaim, bukan ditumpuk**: baris draf tanpa alasan gagal dianggap "proses
  berhenti sebelum selesai" lalu digantikan.
- **Dua lapis pemulihan foto** (catatan jalur model, lalu cache sesi) dengan cek PNG utuh
  minimal 50 KB, penolakan berkas rancangan, dan penolakan satu berkas untuk dua slot.
- **Isi gambar tidak pernah lewat model** (temuan trial 31 Okt: baris perintah Windows terlalu panjang).

---

## 4. Temuan

Disusun dari yang paling mahal untuk dompet dan kepercayaan klien.

### M-1. Aset klien tidak pernah dibaca Codex

Brief hanya menyalin enam baris teks (nama proyek, jenis, nama klien, nama website, target
pasar, fitur, cerita). Berkas yang diunggah tim di layar yang sama (logo, foto, company
profile, dan kategori "Referensi Desain") **tidak pernah masuk ke surat kerja**.

Bukti: satu-satunya pemanggil surat kerja adalah `MockupCodexRunner.php:256` dan ia hanya
mengirim empat isian. Kelas surat kerja punya pintu kelima untuk gambar acuan (baris
"Desain referensi (acuan mengikat)", `MockupCodexBrief.php:69-77`) yang tidak pernah diisi
siapa pun sejak jalur lama dicabut.

Efek ke pemilik: setiap kandidat memakai foto karangan AI dan tanpa logo klien. Proposal
terlihat bagus tetapi bukan website klien. Ini juga sebabnya rancangan "rujukan dicari
sendiri oleh Codex" menggantung: bahan rujukan sudah ada di tangan, tidak pernah diserahkan.

### M-2. Log kegagalan ikut terhapus bersama meja kerja

Pelarian menyalin hasil kerja **setelah** proses Codex selesai (`MockupCodexRunner.php:196`).
Kalau Codex mati, keluar dengan kode galat, atau kena batas waktu, pelemparan terjadi lebih
dulu, salinan tidak pernah terjadi, dan blok penutup menghapus folder kerja
(`MockupCodexRunner.php:242`).

Dampak terukur: **23 dari 27 kandidat gagal tidak menyisakan `codex.log`**, sementara pesan
galat yang melihat mata Anda berbunyi "Rincian ada di codex.log kandidat"
(`MockupCodexRunner.php:285` dan `:426`). Jadi pemilik disuruh membuka berkas yang sudah tidak ada.

### M-3. Surat kerja masih menjanjikan 20 menit, pelarian membiarkan 35

Batas nyata sekarang 2100 detik (`config/services.php`, `MOCKUP_CODEX_TIMEOUT`). Surat kerja
baris 43 menulis "Batas keras proses ini 20 menit. Targetkan selesai dalam 14 menit", dan
potongan komentar kelasnya ikut menulis 20 menit. Ronde 6 benar-benar memakai 14,4 menit per
kandidat rata-rata, jadi model disuruh berhenti sebelum waktu yang sudah Anda bayar habis.
Ini suntingan teks aturan saja, bukan mesin baru.

### M-4. Tidak ada jejak biaya per kandidat

Token hanya hidup di `status.json` milik proyek dan **ditimpa setiap kali pelarian dilepas**;
kolom biaya tidak ada. Artinya 52 percobaan di disk tidak bisa menjawab pertanyaan yang
sudah lama menunggu: berapa modal (menit dan token) satu proposal yang sampai ke klien.

### M-5. Meja kerja tumbuh tanpa batas

351 MB untuk empat proyek, dan 27 folder di antaranya adalah jejak kegagalan. Bersih-bersih
(`MockupWorkspace::deleteProject`) hanya dipanggil dari berkas tes, tidak pernah dari aplikasi.
Sebagian ukuran karena foto dobel: berkas slot tersimpan di akar folder **dan** di dalam
`assets/` hasil Codex sendiri.

### M-6. Dua klik berurutan bisa meninggalkan kartu "sedang dibuat" tanpa pelarian

Layar menolak buat-ulang kalau kunci masih dipegang, tetapi cek itu terjadi sebelum
pelarian dilepas. Bila dua permintaan lolos berdampingan, pelarian kedua menemukan kunci
sudah terpegang lalu keluar dengan kode 0 tanpa membuat apa pun (`MockupCodexRunner.php:56-60`),
sementara baris klaim yang baru dibuat tetap berstatus draf. Kartunya diam-diam "sedang
dibuat" sampai pemilik menekan tombol lagi.

Catatan kecil satu paket: pelepas menulis pid ke berkas kunci sebelum anaknya memegang
`flock`; bila proses lain sedang memegang kunci, tulisan itu bisa gagal atau memangkas
berkas yang sama. Kunci asli tetap milik anak, jadi ini bukan kerusakan, tetapi tulisannya
tidak pernah dibaca siapa pun.

### M-7. "Hero wajib" hanya dijanjikan di teks, tidak ditagih di kode

Pelarian menuntut setiap slot yang **sudah tertulis di halaman** terpasang sebagai gambar,
tetapi tidak menuntut adanya slot `hero`. Halaman tanpa hero lolos, dan potret yang melihat
klien kehilangan tempat paling penting.

### M-8. Foto dari cache dipasang berurutan, bukan menurut kecocokan

Saat catatan jalur model kosong, sisa slot diisi dari folder cache theo urutan
(`MockupCodexPhotos.php:76-86`). Kalau Codex membuat 4 percobaan untuk 2 slot, gambar
terbaru bisa jatuh ke slot yang salah caption-nya. Aman untuk uji cepat, berisiko untuk
proposal.

### M-9. Sisa teks zaman jalur lama di berkas yang dibaca orang

- `critique_rounds` masih di isi-massal, nilai awal, dan pelarian menulisnya 0
  (`MockupCandidate.php:27`, `MockupCodexRunner.php:235`); kolomnya menunggu keputusan Anda.
- Potongan komentar melayang dan teks "GANTI method jalankanCodex() dengan versi ini" masih
  tertinggal di tengah `MockupCodexRunner.php:290-305`. Arahannya bukan untuk pembaca
  berikutnya, itu bekas percakapan.
- Baris judul `MockupCodexBrief.php:9` masih menulis "saat mesin = codex", padahal tidak ada
  mesin lain lagi.

### M-10. Tidak ada batas untuk berapa klien jalan berdampingan

Kunci hanya per proyek. Tiga klien paralel = sampai sembilan proses Codex dan sembilan
Chromium di mesin yang sama, dengan batas 35 menit per kandidat sehingga satu batch bisa
makan 105 menit. Belum terukur; masuk akal untuk dites sebelum dipaksakan, bukan langsung
dibatasi.

---

## 5. Urutan yang saya sarankan

1. **M-2 dulu** (paling murah, paling membuka mata): simpan `codex.log` dan `events.jsonl`
   sebelum penyalinan hasil, supaya kegagalan berikutnya punya bukti.
2. **M-1**: serahkan logo dan berkas kategori "Referensi Desain" ke folder kerja, isi pintu
   kelima yang sudah ada di surat kerja. Ini perubahan besar pada rasa hasil, bukan pada mesin.
3. **M-3**: samakan angka di teks surat kerja dengan batas nyata (satu baris).
4. **M-4**: catat menit dan token per kandidat agar biaya satu proposal bisa dijawab dengan angka.
5. **M-7 dan M-6**: tagih hero di kode, tutup celah dua klik.
6. **M-5, M-8, M-9**: rapikan meja, cara pasang foto cache, dan sisa teks lama.

---

## 6. Yang belum teruji

Suite hijau (398 tes, 1551 pernyataan, jalan dengan sqlite), tetapi tidak ada satu tes pun
yang menjalankan Codex sungguhan atau Chromium. Yang tidak terbukti oleh tes:

- pemulihan foto dari cache sesi (hanya uji dengan berkas palsu di folder sementara);
- potret halaman hasil Codex (butuh Node + Chromium di mesin ini);
- jalur M-1: tidak ada tes yang menuntut aset klien ikut masuk brief, karena fitur itu memang
  belum pernah disambungkan.

## 7. Jalan kembali

Seluruh berkas jalur API (17 berkas) ada di `arsip-jalur-api-2026-11-04.tar.gz` di root repo;
`tar -xzf arsip-jalur-api-2026-11-04.tar.gz` dari folder proyek mengembalikannya apa adanya.
Rincian pencabutan: `.docs/catatan-jalur-api-dipensiunkan-2026-11-04.md`.
