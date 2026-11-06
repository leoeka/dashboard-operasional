# Rancangan: foto yang ikut membangun halaman, bukan ditempel

Tanggal: 2026-11-02
Status: **rancangan untuk dicoret pemilik**. Nol berkas kode disunting.
Dasar: kandidat Codex proyek 8 (`storage/app/mockup-work/8/candidate-*`), tema terpasang di `wp-exito-test`, `.docs/aturan-desain-sisi-klien.md`.

## 1. Arah

Setiap foto punya satu peran, satu rasio, satu titik potong, satu keterangan yang menambah informasi, dan satu nada warna dengan foto lain. Foto tidak memakai berkas yang sama dua kali, tidak dipotong bentuk hiasan, dan tidak diberi label yang menempel di atasnya.

Perbaikan ada di tiga lapis:

1. **Brief foto** yang dikirim ke Codex. Hari ini isinya dua baris tanpa arahan.
2. **Aturan K/M/P** di `.docs/aturan-desain-sisi-klien.md`, supaya ronde kritik dan meja periksa memakai bahasa yang sama.
3. **Satu bug penyusunan CSS tema** yang membuat foto tidak tampil sesuai desain. Tanpa ini, foto yang sudah bagus pun tetap terlihat seperti tempelan di WordPress.

## 2. Yang diukur hari ini

| Yang diukur | Hasil | Bukti |
| --- | --- | --- |
| Foto kandidat 22 | dua berkas berbeda | md5 `d0dac863…` dan `db5f75c9…` |
| Foto kandidat 23 | dua slot, satu berkas | md5 `50b795d2…` dua kali |
| Foto kandidat 24 | dua slot, satu berkas | md5 `7aa09f85…` dua kali |
| Tema terpasang | memakai kandidat 23, jadi satu pemandangan tampil dua kali | `hero.png` dan `galeri-1.png` di tema, md5 sama |
| Arahan foto di brief Codex | 2 baris: "foto utama" dan "foto pendukung". Tanpa adegan, rasio, titik potong | `app/Services/MockupCodexBrief.php` baris 53-55 |
| Aturan foto di dokumen | tidak ada satu pun aturan K, M, atau P tentang foto | `.docs/aturan-desain-sisi-klien.md` bagian 2-4 |
| CSS foto kandidat 22 | tinggi tetap 510px, ponsel 360/300, fokus bawaan 50% 50% | `candidate-22/index.html` |
| Keterangan foto kandidat 22 | kotak putih `position:absolute` menempel di atas foto | `.photo-caption` |
| Foto di tema, lebar 1440 | hero 365x440 di wadah 365x520, sisa kosong 80px; foto cerita 424x440 | ukur peramban |
| Pemotongan foto tema | hero 38% lebar terbuang, foto cerita 28% | sumber 1448x1086 |
| Lebar isi halaman | 948px, padahal desain meminta sekitar 1268px | `main` 1150px dikurangi padding 100,8px |

Dua penyebab yang bukan soal selera:

- **Tidak ada arahan foto.** Model bebas memilih adegan, dan pada 2 dari 3 kandidat ia memakai berkas yang sama untuk dua slot. Tidak ada pemeriksa yang menolak.
- **CSS semua halaman digabung dalam satu berkas dengan lingkup yang sama.** Halaman isi menulis `main{max-width:1150px}` dan `.hero{grid-template-columns:1fr 1fr}`, lalu aturan itu menimpa beranda karena letaknya lebih akhir. Akibatnya foto hero menyempit dari sekitar 700px menjadi 365px dan tingginya 440px di dalam wadah 520px. Ini terlihat di `style.css` baris 16-19 dan 131-132.

## 3. Apa yang difoto

| Slot | Isi | Komposisi |
| --- | --- | --- |
| `hero` | satu momen kerja: tangan memegang, menyambung, atau menyelesaikan panel; bahan terlihat sedang diproses | ada lapisan depan, tengah, belakang; cahaya dari satu sisi; subjek agak ke samping; tanpa teks atau logo di dalam foto |
| `galeri-1` | satu detail bahan: potongan, sambungan, anyaman, atau tumpukan panel dari dekat | adegan dan arah cahaya berbeda dari hero; latar sederhana |

Dua berkas wajib berbeda. Kalau alat gambar mengembalikan berkas yang sama untuk dua slot, kandidat ditolak dan dibuat ulang. *(Penolakan otomatis ini dicabut sesudah penerapan — lihat revisi di bagian 5.)*

Alasan: situs ini menjual bahan dan pengerjaan. Foto tumpukan barang tanpa manusia terlihat seperti stok, bukan bukti bahwa ada bengkel yang bekerja.

## 4. Bagaimana foto duduk di halaman

| Hal | Nilai | Alasan |
| --- | --- | --- |
| Rasio | hero 4:3, galeri 1:1, ponsel sama | potongan bisa diperkirakan dan subjek tidak terpotong tiap lebar layar |
| Ukuran | `aspect-ratio` + `width:100%`, tanpa tinggi piksel | tidak ada sisa kosong di dalam wadah |
| Potong | `object-fit:cover`, `object-position` dari titik fokus slot, bawaan `50% 40%` | subjek biasanya di atas tengah |
| Keterangan | di bawah foto, 13px, warna meta, garis 1px di atasnya | keterangan menambah informasi, bukan menempel seperti label |
| Sudut | 4px, sama dengan sistem halaman | tidak ada bentuk hiasan yang memotong foto |
| Nada | satu nilai untuk semua foto: `filter:saturate(.94) contrast(1.04)` | menyatukan foto dari sumber berbeda ke palet krem dan hijau |

Bentuk hiasan (potongan lengkung, cincin, setengah lingkaran) dicabut. Foto yang tampil sebagai potongan bentuk membuat halaman terlihat seperti kolase, bukan halaman perusahaan.

## 5. Yang diperiksa

Aturan baru, menyusul penomoran yang sudah ada:

| Id | Aturan | Sifat |
| --- | --- | --- |
| K-11 | Dua slot foto wajib dua berkas berbeda dan dua adegan berbeda | keras |
| K-12 | Tidak ada teks yang ditempel di atas foto (`position:absolute` di dalam wadah foto) | keras |
| K-13 | Foto tidak dipotong bentuk hiasan; sudut mengikuti sistem halaman | keras |
| M-13 | Setiap wadah foto punya `aspect-ratio` dan `object-fit:cover`; tidak ada tinggi piksel tetap | keras |
| M-14 | Sidik jari `hero` dan `galeri-1` berbeda | keras |
| M-15 | Titik fokus ditulis per slot dan dipakai; bukan `50% 50%` | lunak, naik setelah tiga build |
| P-7 | Keterangan foto menyebut bahan atau proses, bukan mengulang judul bagian | ronde kritik |

Pemeriksa M-13 dan M-14 dijalankan pada berkas kandidat sebelum kartu tampil, jadi kandidat yang gagal tidak sampai ke meja tim.

**Revisi sesudah penerapan (2026-11-02, keputusan pemilik):** M-14 dicabut. Pemeriksaan sidik jari di `MockupCodexPhotos::recover()` menghapus penolakan foto kembar, dan K-11 turun menjadi bahan penilaian ronde kritik. Arahan di brief ke Codex supaya dua slot memakai dua foto berbeda tetap ditulis. Yang tetap berjalan: M-13.

## 6. Bug CSS tema yang ikut diperbaiki

CSS tiap halaman sekarang digabung ke satu `style.css` dengan lingkup yang sama. Halaman isi membawa aturan umum (`main`, `.hero`, `.photo`) yang menimpa beranda. Ini yang membuat foto hero menyempit dan menyisakan 80px kosong di dalam wadahnya.

Perbaikan: setiap potongan CSS halaman diberi lingkup halaman itu sendiri (misalnya kelas badan halaman), dan salinan CSS untuk Elementor memakai lingkup yang sama. Dengan begitu aturan halaman Tentang tidak lagi menyentuh Beranda. Aturan `.hero img` yang lama juga dibuang supaya foto mengisi wadahnya.

## 7. Bukti sebelum diserahterimakan

1. Satu kandidat dibuat ulang dengan brief baru. Biaya mengikuti uji 2026-10-31: sekitar 13 menit dan 53 ribu token per kandidat.
2. Tangkapan layar 1440 dan 375, sebelum dan sesudah.
3. Angka yang dilaporkan: dua sidik jari berbeda, `aspect-ratio` terbaca di peramban, keterangan berada di aliran normal (bukan `absolute`), foto mengisi wadah tanpa sisa, pemotongan sesuai rasio.
4. Setelah disetujui: bangun tema, pasang, impor ulang, lalu tangkapan layar ulang di WordPress.

## 8. Yang perlu diputuskan

1. Setujui arah dan nilai di bagian 3 sampai 5?
2. Rasio: hero 4:3 dan galeri 1:1, atau keduanya 4:3?
3. Bug CSS di bagian 6: dikerjakan dalam paket ini, atau dicatat sebagai pekerjaan terpisah?
