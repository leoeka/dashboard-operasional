# Rancangan migrasi mockup dari Codex CLI ke OpenAI API

Tanggal: 2026-11-06
Status: jalur API sudah diterapkan untuk pembuatan kandidat melalui web. Entry point command dan launcher Codex lama sudah dihapus. Uji otomatis memakai HTTP fake lulus; uji API berbayar dan uji cPanel belum dilakukan.

## Status implementasi

- Controller web sekarang menjalankan `mockup:openai`; jalur Codex lama masih tersedia sebagai command, tetapi tidak dipakai oleh tombol pembuatan kandidat.
- Setiap kandidat memakai satu request Responses API dengan tool `image_generation`. Batch awal dari web tetap membuat tiga kandidat, sehingga dapat mengirim tiga request dan memakai biaya gambar pada masing-masing kandidat.
- Brief Codex tetap dipakai. Laravel mem-parsing hasil, menyimpan HTML dan gambar, memvalidasi berkas, lalu membuat pratinjau sebelum kandidat masuk ke review.
- Tes fitur runner API, controller, dan mockup gate lulus dengan respons API palsu: 45 tes, 214 assertion. Ini tidak membuktikan model live menghasilkan keluaran yang sama.
- Tidak ada request API live yang dijalankan. Uji berbayar, validasi gambar nyata, build tema dari kandidat API, dan deploy ke cPanel masih perlu dilakukan.

## Tujuan

Memindahkan pembuatan kandidat mockup dari Codex CLI ke OpenAI Responses API agar proses tidak bergantung pada instalasi Codex dan login Codex di server. Pengguna tetap menerima tiga kandidat, dapat meninjau dan menyetujui hasil, lalu memakai kandidat terpilih untuk membangun tema WordPress.

Rancangan mempertahankan satu permintaan Responses API utama untuk setiap kandidat. Model utama mengikuti brief, menghasilkan HTML, dan dapat memanggil alat `image_generation` untuk membuat gambar dalam alur yang sama. Alat gambar memakai model gambar yang didukung. Satu API key OpenAI dapat dipakai untuk keduanya.

Istilah “satu permintaan” di sini berarti satu permintaan utama dari aplikasi ke Responses API per kandidat. Itu bukan jaminan alat gambar hanya dipanggil sekali, bukan jaminan semua aset selalu lengkap, dan bukan berarti gambar serta teks memakai model yang sama. Pemanggilan alat gambar dapat memiliki biaya tersendiri. Hal tersebut harus diuji sebelum migrasi produksi.

## Kondisi sebelum migrasi

- Sebelum perubahan, [MockupCandidateController.php](../app/Http/Controllers/MockupCandidateController.php) membuat batch tiga kandidat dan menggunakan `MockupCodexLauncher` untuk melepas proses Codex di latar. Launcher tersebut sudah dihapus.
- [MockupCodexRunner.php](../app/Services/MockupCodexRunner.php) menyiapkan folder kerja, memanggil Codex CLI, membaca progres, menyalin hasil kerja, memeriksa foto, lalu membuat pratinjau.
- [MockupCodexBrief.php](../app/Services/MockupCodexBrief.php) memuat arahan desain, data proyek, halaman wajib, aturan foto, instruksi implementasi, QA, dan berkas keluaran. Brief juga meminta gambar mockup acuan dan foto.
- [MockupCodexPhotos.php](../app/Services/MockupCodexPhotos.php) memulihkan gambar berdasarkan manifest dan cache gambar Codex.
- [MockupCodexAssets.php](../app/Support/MockupCodexAssets.php) menyalin aset gambar klien ke folder kerja. [MockupCodexReference.php](../app/Support/MockupCodexReference.php) menyalin gambar referensi ke folder tersebut.
- Hasil kandidat masuk ke alur yang sama: HTML, foto, pratinjau, status review, dan pembangunan tema WordPress melalui [WpThemeBuilder.php](../app/Services/WpThemeBuilder.php).
- Pratinjau dibuat oleh [PageSnapshot.php](../app/Support/PageSnapshot.php) menggunakan Browsershot dan Chromium.

## Prinsip rancangan

1. Pertahankan aturan dan tujuan desain pada brief. Ubah instruksi yang hanya berlaku untuk Codex CLI, seperti menjalankan perintah shell, menyimpan path dari cache Codex, dan menulis manifest hasil alat lokal.
2. Pertahankan antarmuka hasil yang dibutuhkan alur review dan builder tema. Jangan mengubah status kandidat atau format tema sebagai bagian dari migrasi API.
3. Biarkan Laravel menyimpan dan memvalidasi setiap berkas hasil. Model API tidak menulis langsung ke filesystem aplikasi.
4. Jangan menampilkan kandidat sebagai berhasil jika keluaran API tidak lengkap atau gagal divalidasi.
5. Tetapkan batas waktu, biaya, ukuran respons, jumlah gambar, dan jumlah percobaan sebagai konfigurasi. Nilai awal ditentukan setelah uji, bukan diasumsikan dari batas Codex.
6. Jangan kirim API key ke browser atau masukkan key ke berkas kandidat, log, maupun respons yang ditampilkan kepada pengguna.

## Alur target

Untuk setiap kandidat:

1. **Siapkan input.** Laravel menyusun brief, data proyek, daftar halaman, gambar referensi, logo, dan foto klien yang boleh digunakan. Gambar diberikan sebagai input gambar yang didukung API, bukan hanya sebagai path lokal.
2. **Kirim satu permintaan Responses API utama.** Kirim instruksi brief dan kontrak keluaran, sertakan bahan visual, lalu aktifkan alat `image_generation`. GPT menyusun rencana desain, meminta gambar acuan/aset yang diperlukan, dan menghasilkan isi berkas teks.
3. **Terima hasil terstruktur.** Aplikasi membaca teks keluaran dan setiap hasil gambar dari item respons API. Respons mentah tidak langsung dianggap sebagai kandidat siap tinjau.
4. **Simpan hasil.** Laravel menulis HTML, JSON rencana/token, catatan ide, dan gambar yang telah dipetakan ke folder kandidat. Gambar diberi nama file yang aman dan direferensikan dengan token `__EXITO_IMAGE:<slot>`.
5. **Validasi kontrak.** Pastikan beranda, semua halaman wajib, JSON valid, slot gambar, file gambar, tautan internal, dan ukuran berkas sesuai aturan. Jika ada syarat yang gagal, kandidat ditandai gagal dengan penjelasan yang terlihat.
6. **Buat pratinjau.** Render halaman melalui Browsershot, lalu simpan `preview.png`.
7. **Teruskan ke review.** Hanya setelah semua pemeriksaan wajib lolos, ubah status kandidat menjadi menunggu review. Persetujuan dan build tema tetap memakai alur yang ada.

Respons API dapat berisi teks dan hasil dari alat gambar dalam satu respons. Aplikasi perlu memeriksa jenis item keluaran dan menghubungkan hasil gambar ke slot yang disebut pada manifest keluaran. API tidak otomatis membuat file lokal dengan nama seperti `hero.png`; penyimpanan dan pemetaan tersebut tetap tanggung jawab aplikasi.

## Kontrak hasil kandidat

Kontrak berikut adalah target kompatibilitas untuk satu kandidat. Nama final dan kewajiban tiap berkas harus dicocokkan dengan pemeriksaan yang sudah ada di runner dan builder sebelum implementasi.

- `index.html`: beranda lengkap.
- `pages/<slug>.html`: satu berkas untuk setiap halaman wajib.
- `rencana.json`: JSON valid, mengikuti kunci dan isi yang diminta brief.
- `design-tokens.json`: token desain yang dipakai dalam HTML.
- `ide.txt`: satu kalimat ide kandidat.
- `desain-referensi.txt`: asal gambar referensi, bila ada.
- `foto-slot.txt`: manifest slot gambar dan file yang dipetakan.
- File gambar hasil API atau aset asli klien yang dirujuk HTML.
- `preview.png`: hasil render beranda untuk kartu review.

Gunakan penanda `__EXITO_IMAGE:<slot>` pada lokasi gambar agar pratinjau dan pembangun tema tetap dapat menemukan gambar. Logo klien harus tetap dibedakan dari gambar generatif dan menggunakan file asli bila tersedia serta diizinkan.

## Penyesuaian brief

Pertahankan substansi arahan yang sudah ada di [MockupCodexBrief.php](../app/Services/MockupCodexBrief.php):

- arahan visual dan standar mutu;
- data proyek dan struktur halaman;
- larangan mengarang harga, kontak, statistik, testimoni, dan penghargaan;
- gambar referensi sebagai acuan desain;
- konsistensi style foto;
- responsivitas dan QA;
- daftar berkas wajib.

Ubah bagian yang mengasumsikan kemampuan lingkungan Codex:

- “gunakan alat gambar bawaan” menjadi permintaan eksplisit melalui alat `image_generation`;
- “baca path hasil alat” dan “pulihkan dari cache Codex” menjadi kontrak respons serta manifest yang diproses Laravel;
- instruksi `Select-String`, `grep`, dan perintah shell menjadi validasi aplikasi;
- “tulis file di folder kerja” menjadi pengembalian isi berkas dalam keluaran terstruktur, lalu disimpan oleh aplikasi.

Sebaiknya aturan desain dipisah ke komponen brief yang tidak terikat provider, sedangkan format permintaan API dan pembacaan respons ditempatkan pada adapter OpenAI. Jangan menyalin seluruh brief menjadi prompt kedua yang berpotensi berbeda.

## Komponen jalur API

- `MockupOpenAiLauncher` menjalankan command `mockup:openai` sebagai proses latar menggunakan `proc_open`.
- `MockupOpenAiRunner` menjalankan request per kandidat, mengelola progres, memetakan hasil, memvalidasi keluaran, dan menulis file.
- `MockupOpenAiClient` memanggil Responses API, mengirim gambar input, serta membaca teks dan gambar keluaran.
- `MockupCodexBrief` tetap menjadi sumber brief. Kelas ini belum dipisahkan menjadi brief provider-neutral.
- `MockupCodexState` dan sebagian utilitas bernama Codex masih dipakai bersama, termasuk untuk menjaga kompatibilitas status dan lokasi berkas.
- Command `mockup:codex` dan launcher Codex lama sudah dihapus agar jalur web dan terminal tidak memulai generator Codex secara tidak sengaja.
- Implementasi runner dan helper Codex yang belum dipisahkan masih disimpan untuk menjaga kompatibilitas data, test, dan komponen bersama. Riwayat kandidat serta berkas lama tidak dihapus.

## Batas awal untuk uji coba

- Satu permintaan utama Responses API per kandidat.
- Endpoint web saat ini tetap membuat batch tiga kandidat. Setiap kandidat memakai satu request, jadi pengguna perlu mempertimbangkan biaya sebelum membuat batch. Untuk uji visual pertama, buat satu kandidat saja.
- Batasi keluaran pada gambar yang benar-benar dirujuk halaman, dengan `hero` wajib. Jumlah dan ukuran maksimum ditetapkan setelah memeriksa biaya dan kemampuan model gambar.
- Aset/logo klien hanya dikirim jika proyek memilikinya dan kategori aset memang boleh dipakai.
- Terapkan timeout dan batas biaya per kandidat. Catat ID respons, model, durasi, penggunaan token, jumlah hasil gambar, serta status validasi. Jangan mencatat API key atau isi sensitif yang tidak diperlukan.
- Hindari percobaan ulang otomatis yang dapat menggandakan biaya. Bila retry diperlukan, tampilkan kegagalan dan mulai retry hanya dengan kebijakan yang jelas.

## Validasi dan uji penerimaan

Uji dengan proyek dan brief yang sama pada jalur Codex serta jalur API, sejauh kedua jalur masih tersedia. Catat model, konfigurasi, waktu, biaya yang terukur, dan kegagalan. Jangan menyimpulkan kualitas hanya dari satu hasil.

Satu kandidat API lulus untuk masuk ke tahap berikutnya bila:

- semua berkas wajib tersedia dan dapat dibaca;
- semua halaman wajib dibuat dan tautan internalnya menunjuk tujuan yang ada;
- JSON rencana dan token dapat diparse;
- setiap token gambar yang dipakai HTML dipetakan ke file gambar yang valid;
- gambar referensi tidak keliru dipasang sebagai foto konten;
- logo dan foto klien, bila digunakan, tetap berasal dari aset yang benar;
- pratinjau berhasil dibuat dan tidak ada penanda gambar yang rusak;
- hasil bisa dibaca oleh review kandidat dan dibangun menjadi tema WordPress tanpa mengubah kontrak builder;
- kegagalan API, respons kosong, gambar hilang, respons tidak valid, dan timeout tercatat sebagai kegagalan yang terlihat, bukan sukses palsu.

Untuk membandingkan mutu, tinjau kesesuaian brief, konsistensi gaya gambar, kelengkapan halaman, tampilan desktop dan mobile, serta hasil tema WordPress. Hasil visual mungkin berbeda dari Codex walaupun brief dan bahan sama.

## Dampak hosting cPanel

Jalur API menghilangkan ketergantungan pembuatan kandidat web pada Codex CLI, Node.js untuk Codex, login Codex server, serta cache gambar lokal Codex. Implementasi saat ini tetap membutuhkan:

- `proc_open` dan PHP CLI untuk meluncurkan `mockup:openai` di latar. Jika cPanel menonaktifkan `proc_open`, launcher tidak dapat digunakan dan perlu diganti dengan antrean/cron yang didukung provider;
- koneksi HTTPS keluar ke OpenAI;
- kebutuhan Chromium dan dependensinya selama pratinjau masih memakai Browsershot;
- kebutuhan izin tulis untuk folder storage;
- batas waktu, memori, koneksi keluar, dan kebijakan proses yang ditetapkan provider hosting.

Sebelum memilih shared hosting cPanel, pastikan provider mengizinkan koneksi HTTPS keluar ke API, menyediakan cron/worker yang memadai, dan mendukung Chromium untuk proses pratinjau. Jika tidak, pertimbangkan worker API dan render pratinjau pada VPS.

## Risiko dan keputusan terbuka

1. **Kelengkapan dalam satu permintaan.** Model dapat tidak meminta semua gambar, memberi HTML yang tidak lengkap, atau tidak mengikuti pemetaan slot. Validasi wajib dilakukan; jika konsistensi tidak cukup, ubah ke dua tahap API.
2. **Gambar acuan dan foto aset.** Perlu uji jumlah gambar yang dapat dihasilkan dan kualitas penautan setiap hasil ke slot. Jangan menganggap satu pemanggilan alat akan selalu mengembalikan jumlah gambar yang diminta.
3. **Biaya dan durasi.** Harga bergantung pada model teks, penggunaan token, alat gambar, kualitas dan jumlah gambar. Ukur biaya pada proyek nyata sebelum menetapkan batas produksi.
4. **Kontrak respons.** Bentuk keluaran terstruktur, dukungan input gambar, serta format hasil alat harus dipastikan terhadap endpoint dan model yang dipilih saat implementasi.
5. **Umpan balik visual.** Satu permintaan tidak otomatis menyediakan siklus render, kritik screenshot, lalu revisi. Jika tahap itu diperlukan untuk mempertahankan mutu, kemungkinan dibutuhkan permintaan lanjutan. Tentukan apakah kompromi satu permintaan lebih penting daripada iterasi kualitas.
6. **Kebijakan data.** Pastikan aset klien yang dikirim ke API sesuai izin dan pengaturan privasi yang dipakai aplikasi.

## Langkah lanjutan

1. Jalankan satu kandidat dengan API key yang dikonfigurasi, lalu tinjau biaya, gambar, HTML, hasil render, dan waktu proses.
2. Pastikan hasil yang disetujui dapat dibangun menjadi tema WordPress dan berfungsi seperti kandidat dari jalur lama.
3. Uji pada lingkungan cPanel target. Konfirmasi `proc_open`, PHP CLI, koneksi keluar, Chromium, izin storage, serta batas waktu dan memori.
4. Jika lingkungan menolak `proc_open`, siapkan mekanisme queue/cron yang sesuai sebelum deploy. Jangan menganggap API saja menghapus kebutuhan proses latar.
5. Pertahankan jalur Codex dan data lama sampai uji penerimaan produksi lulus dan pengguna memutuskan cutover.

## Dokumentasi OpenAI

- [Image generation guide](https://developers.openai.com/api/docs/guides/image-generation)
- [Image generation tool guide](https://developers.openai.com/api/docs/guides/tools-image-generation)
- [Responses API model page](https://developers.openai.com/api/docs/models/gpt-6-astra)

Dokumentasi ini menjadi rujukan konsep. Nama model, dukungan parameter, format respons, biaya, serta ketersediaan akun perlu dicek kembali saat implementasi.
