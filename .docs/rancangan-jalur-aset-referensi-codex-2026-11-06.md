# Rancangan — Jalur referensi & aset sendiri ke Codex (2026-11-06)

Status: **tahap 1 selesai (2026-11-06) — salin aset + `aset.txt` + sebut di prompt, brief tidak disentuh.** Tahap 2 (isi slot `$referensiPath`), 3 (foto asli lolos sampai ZIP), dan 4 (uji nyata + ukur) menunggu.

Keputusan yang sudah dipakai sebagai patokan: aset diambil dari kategori logo/referensi/foto, maksimal 8 gambar, 4 MB per berkas, dokumen ditinggalkan. Brief tidak diubah; aturan pemakaian bahan ditulis di `aset.txt` yang dibuat kode.

## 1. Keadaan yang terukur hari ini

| Kenyataan | Bukti |
|---|---|
| Aset klien per proyek sudah bisa diunggah | panel "Unggah Aset Tambahan" di gerbang mockup, 6 kategori (logo, foto, dokumen, company profile, referensi, pendukung) — `resources/views/workspace/mockup-gate.blade.php:496` |
| Berkasnya tersimpan di disk + baris `mockup_assets` | `ProjectController::storeFile` → `storage/app/public/mockup-assets/{project_id}` |
| **Isinya masih kosong** | tabel `mockup_assets` = 0 baris; folder `storage/app/public/mockup-assets` belum pernah dibuat; `design_reference_path` NULL di semua proyek |
| Codex hanya menerima satu berkas: `brief.txt` | `MockupCodexRunner::tulisBrief` satu-satunya penulis isi folder kerja (`app/Services/MockupCodexRunner.php:252`) |
| Codex tidak bisa membaca di luar folder kerja | dipanggil `codex exec -s workspace-write -C <folder-kerja>` (`MockupCodexRunner.php:488`) — folder kerja ada di temp, bukan di storage |
| Slot referensi di brief sudah ada tapi tidak pernah diisi | `MockupCodexBrief::build(..., ?string $referensiPath = null)`; pemanggilnya `MockupCodexRunner.php:256` tidak pernah melewatkan nilai → bagian "## 03 — Desain referensi (acuan mengikat)" tidak pernah muncul |
| Brief melarang bahan asli klien | baris 112 & 119: foto harus "no logo", dan "Jangan memakai screenshot desain atau gambar dari internet" |

Kesimpulan: jalurnya bukan belum dibuat — pengumpulnya sudah jadi, **penyalurannya yang belum ada**.

## 2. Arah yang diusulkan

Sebelum Codex dijalankan, folder kerjanya diisi bahan milik klien sendiri:

```
folder-kerja/
  brief.txt          (sudah ada, tidak diubah)
  aset.txt           (baru: daftar bahan + path + aturan pakainya)
  aset/logo-1.png
  aset/foto-1.jpg
  aset/foto-2.jpg
  rujukan.png        (gambar referensi desain, bila ada)
```

- **`aset.txt` ditulis oleh kode, bukan oleh brief.** Pemilik tidak perlu menyetujui perubahan teks brief: satu kalimat di prompt Codex (yang memang sudah di kode, `MockupCodexRunner.php:506`) berubah dari "baca brief.txt" menjadi "baca brief.txt dan aset.txt".
- **Referensi masuk lewat slot `$referensiPath` yang sudah dia desain sendiri** — hanya mengirim nilai, tidak mengubah satu kata pun di brief.
- Batas/penjaga: hanya gambar (png/jpg/webp/svg), maksimal N berkas, perkas maksimal M MB, sisanya dilewati dan dicatat di `aset.txt`.

## 3. Tahap kerja (setelah disetujui)

1. **Tahap 1 — salin & daftar.** Fungsi penyalin aset di runner + `aset.txt`; uji: folder kerja berisi berkas yang benar, kandidat tanpa aset berjalan seperti sekarang.
2. **Tahap 2 — referensi.** Kirim path ke brief lewat `$referensiPath`; uji: bagian 03 muncul di `brief.txt` arsip kandidat.
3. **Tahap 3 — lolos sampai tema.** Pastikan foto asli klien tidak ditimpa `MockupPhotoRecover` dan ikut masuk ZIP + Elementor.
4. **Tahap 4 — uji nyata + ukur.** Satu kandidat project 11 dengan aset sungguhan; catat waktu & token sebelum/sesudah dibandingkan ronde 6 (43 menit, 4,89 juta token, 5–6 gambar AI per kandidat).

## 4. Yang harus pemilik putuskan

**A. Sumber gambar referensi.** Rekomendasi: unggah per proyek lewat kategori "Referensi Desain" yang sudah ada (satu gambar acuan = satu proyek, paling mudah dijaga). Alternatif: pilih dari 6 potret gaya yang sudah di disk (`storage/app/referensi-desain-2026-11-02/`, belum tersambung ke kode mana pun), atau taruh berkas di folder proyek.

**B. Boleh atau tidak Codex memakai logo & foto asli klien.** Brief sekarang melarang ("no logo", "jangan pakai gambar dari internet"). Kalau jawabannya boleh, bahan aslinya dipakai lewat `aset.txt` (tanpa menyentuh brief) ATAU brief-nya diedit — edit brief hanya atas izin tertulis, dan arsip bertanggal dibuat lebih dulu.

**C. Batas jumlah & ukuran.** Setiap gambar acuan yang dikirim ke Codex menaikkan token. Usulan awal: maksimal 8 gambar, 4 MB per berkas, logo selalu ikut, foto klien membatasi jumlah gambar AI yang dibuat (bukan menambah).

## 5. Yang tidak diubah

Teks brief, aturan desain, gerbang tinjau, alur build tema, dan biaya dasar kandidat tanpa aset (jalur lama tetap jalan persis seperti sekarang kalau proyek tidak punya aset).

## 6. Tahap 4 sudah diukur (2026-11-06, satu kandidat nyata)

Aset uji dibuat sendiri (bukan berkas klien): logo digambar dengan GD, dua foto
diambil dari kandidat 52 punya proyek yang sama supaya subjeknya cocok (armada
truk & gudang kargo), satu potret tata letak korporat dari arsip referensi.
Keempatnya didaftarkan lewat jalur yang sama dengan tombol unggah: berkas di
`storage/app/public/mockup-assets/11/` + baris `mockup_assets`.

| yang diukur | kandidat 52 (ronde 6, tanpa aset) | kandidat 56 (dengan aset) |
|---|---|---|
| lama proses | 18,7 menit | 6,7 menit |
| token sesi | 1.256.721 | 499.231 |
| perintah Codex | 15 | 10 |
| gambar yang dibuat AI | 6 (1 rancangan + 5 foto) | 2 (1 rancangan + 1 hero) |
| foto klien yang terpasang | 0 | 2, identik byte dengan yang diunggah |
| logo di HTML | tidak ada | `aset/logo-1.png` di header + footer, 3 halaman |
| berkas HTML | 5 | 3 |

Bukti jalurnya hidup: perintah pertama Codex adalah
`Get-Content -Raw brief.txt; Get-Content -Raw aset.txt`, dan `foto-slot.txt`
menulis `armada: aset/foto-1.png`, `gudang: aset/foto-2.png`.

**Angka ini tidak memurnikan sebab.** Brief antara 4 November dan hari ini
tumbuh dari 88 jadi 125 baris (struktur halaman wajib, batas 20 menit jadi 35,
maks foto 5 jadi 6), jadi waktu dan token yang turun bukan semata karena aset.
Yang bisa diklaim dari aset saja: dua dari tiga foto tidak perlu dibuat AI, dan
logo asli sekarang muncul di halaman.

**Dua lubang yang terlihat hasil ini:**
1. Logo dipasang Codex dengan path biasa (`aset/logo-1.png`), bukan token
   `__EXITO_IMAGE:`. Path itu lolos di folder kandidat dan di potret, tetapi
   mati di layar pratinjau web (tidak ada rute untuk `aset/*`) dan mati di tema
   (pembangun hanya mengganti token). Ini bagian dari tahap 3.
2. Foto klien yang berformat JPG tidak akan lolos `MockupCodexPhotos::sah()` —
   ceknya kepala berkas PNG. Uji ini sengaja memakai PNG supaya tahap 1 terukur
   bersih; klien yang unggah JPG harus menunggu tahap 3.
