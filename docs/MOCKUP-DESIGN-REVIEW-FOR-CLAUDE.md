# Arahan review visual untuk Claude

Tanggal: 1 Oktober 2026. Peran Codex: analisis desain; implementasi oleh Claude.

## Bukti dan batas review

- Handoff: `docs/HANDOFF.md`, termasuk update 1 Oktober.
- Output yang benar-benar dilihat: `storage/app/public/mockups/QUALITY-V1-20260930-074409-gpt-option-3.png`, Supplier Wood Pellet, 1440 × 9561, waktu modifikasi lokal 1 Oktober 10:26.
- Dipilih sebagai screenshot terbaru yang tersedia, bukan karena user menetapkan proyek ini.
- Review visual ini menilai komposisi halaman dari screenshot yang ditampilkan diperkecil. Belum menilai keterbacaan ukuran asli, mobile, interaksi, atau output WordPress terbaru. Jangan mengklaim semuanya lolos.
- Ada perubahan kerja yang belum di-commit. Jangan menimpa perubahan itu. Review ini tidak mengubah kode aplikasi, blueprint, aset, atau data proyek.

## Diagnosis

Warna hijau, aksen kuning, dan fotografi industri sudah memberi arah. Kesan generik terutama datang dari pemilihan foto dan ritme informasi: hero serta pengantar sama-sama menampilkan pekerja memegang pellet; empat manfaat pendek masing-masing mendapat blok foto tinggi; lalu dua ajakan kontak besar berurutan. Halaman terlihat panjang tanpa penambahan informasi yang sebanding.

## Urutan implementasi yang diminta

### 1. Perbaiki pembeda struktur kandidat

Lokasi: `app/Services/GenerateMockupGptService.php`, `designFingerprint()` dan `enforceDistinctDesigns()` sekitar baris 604–685.

Temuan kode: fingerprint mengandung hero. Setelah tahap pertama membuat hero berbeda, pemeriksaan fingerprint penuh bisa langsung lolos meskipun semua komposisi setelah hero identik. Komentar yang menjanjikan perbedaan bagian lain belum dijamin implementasi.

Tambahkan pemeriksaan susunan non-hero secara terpisah. Bila ada komposisi alternatif yang cocok dengan bentuk konten, variasikan minimal satu section utama non-hero. Bila tidak ada, catat keterbatasannya; jangan memaksa konten menjadi bentuk yang salah. Lakukan sebelum blueprint dibekukan dan sebelum foto dibuat. Tambahkan regresi kasus hero berbeda tetapi susunan non-hero identik, serta kasus tanpa alternatif yang kompatibel.

### 2. Padatkan empat manfaat pada contoh ini

Lokasi: prompt desain `GenerateMockupGptService.php`, aturan `CompositionSpec.php`, renderer `sections/alternating.blade.php`, dan pasangan Gutenberg dalam `ElementorPageBuilderService.php`.

Untuk empat manfaat dengan hanya satu kalimat per item, pilih komposisi ringkas yang kompatibel seperti grid manfaat; jangan otomatis memberi empat foto portrait ukuran besar. Simpan alternating untuk cerita yang memang memiliki isi dan gambar berbeda. Ini arahan untuk isi contoh ini, bukan larangan global terhadap alternating atau halaman panjang.

Target visual: pengguna bisa memahami keempat manfaat sebagai satu kelompok informasi; ruang kosong tetap ada tetapi tidak memisahkan satu kalimat dari satu kalimat berikutnya dengan satu layar foto. Pertahankan seluruh copy yang lolos integrity gate.

### 3. Beri setiap slot foto fungsi berbeda

Lokasi: `app/Services/MockupAssetService.php`, perencanaan slot dan `photoPrompt()`/`photographyDirection()`.

Pada screenshot, foto sudah tidak tampak seperti poster bertulisan. Masalah berikutnya ialah pengulangan subjek. Arah yang diusulkan: hero konteks industri dengan produk jelas, pengantar detail pellet atau proses penanganan, aplikasi menunjukkan mesin/konteks pemakaian. Foto orang memegang pellet jangan menjadi motif default berulang.

Pertimbangkan ringkasan subjek slot lain dalam satu kandidat untuk mencegah pengulangan. Jangan memasukkan headline atau instruksi layout kembali ke prompt foto. Hindari foto ilustratif yang menyiratkan pabrik, staf, sertifikasi, atau fasilitas tersebut milik klien bila brief tidak menyatakannya. Jangan menganggap foto AI sebagai bukti klaim produk. Generasi berbayar ulang tidak diperlukan untuk memperbaiki layout awal; render ulang dengan aset tersimpan dahulu.

### 4. Bedakan CTA penawaran dari informasi kontak

Pada screenshot ada dua area kuning berurutan dengan ajakan WhatsApp. Telusuri blueprint dan hasil `describeSections()` untuk memastikan sumbernya; jangan menganggap footer penyebabnya.

Pertahankan satu CTA penawaran yang dominan. Jadikan kontak berikutnya area lebih tenang dengan informasi kontak yang benar-benar tersedia. Jangan menghapus konten atau section berisi, mengarang nomor, atau mengubah fakta. Jika bentuk kontak belum didukung, perluas aturan dan kedua renderer secara konsisten; jangan hanya menyembunyikannya dengan CSS.

### 5. Jadikan navbar sesuai arah kandidat

Lokasi: `resources/views/mockup/styles.blade.php:299` dan token/header tema WordPress.

Semua `.full-page .nav` saat ini memakai navbar mengambang, transparansi, blur, radius 18px, dan shadow. Itu keputusan gaya global yang membuat kandidat kembali serupa. Untuk contoh pemasok industri, coba header solid yang sederhana sebagai alternatif. Pilihan perlakuan header harus berasal dari blueprint tervalidasi dan diterapkan pada preview serta WordPress, bukan override khusus screenshot. Ini prioritas setelah struktur isi diperbaiki.

## Arah susunan contoh

Hero dan CTA utama → pengantar singkat dengan gambar yang berbeda → empat manfaat ringkas → spesifikasi teknis → galeri aplikasi → ketentuan transaksi → FAQ → satu CTA penawaran → kontak yang tenang dan footer.

Urutan ini adalah usulan review, bukan instruksi mengubah proposal yang sudah disetujui. Jangan menambah angka, klaim, rating, logo pelanggan, atau testimoni demi membuat desain terlihat penuh.

## Bukti yang harus dikembalikan Claude

1. Screenshot sebelum/sesudah desktop 1440px dan mobile 390px, serta pemeriksaan tablet 768px; perlihatkan hero, manfaat, dan penutup dalam ukuran yang terbaca.
2. Tiga kandidat ditampilkan berdampingan dengan daftar komposisi hero dan non-hero, agar perbedaan struktur bisa dinilai.
3. Konfirmasi teks tidak terpotong, foto tidak merusak crop subjek, navigasi mobile bisa digunakan, dan CTA menuju tujuan yang benar jika sudah ada dalam data. Renderer hero/CTA saat ini memiliki `href="#"`; jangan menyebut alur kontak berfungsi hanya karena tampilannya bagus.
4. Jalankan tes yang sesuai perubahan, khususnya distinctness, content integrity, blueprint parity, dan smoke responsif. Jika mengubah Gutenberg/header tema, verifikasi juga hasil WordPress; kelulusan PNG bukan bukti parity.
5. Pertahankan prinsip LIVE PREVIEW = APPROVED BLUEPRINT = WORDPRESS RESULT; jangan memodifikasi kandidat yang sudah disetujui. Jangan membangun seluruh rule engine baru dalam perbaikan ini; handoff menempatkannya pada branch terpisah.

## Status pengiriman

Dokumen disiapkan di workspace bersama agar bisa dibaca Claude. Daftar agen sesi Codex hanya menampilkan `/root`; belum ada sesi Claude yang dapat dituju. Belum ada pesan langsung yang terkirim ke Claude dan belum ada implementasi yang diklaim selesai.
