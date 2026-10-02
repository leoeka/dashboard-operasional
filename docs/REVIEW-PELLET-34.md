# Review desain — Supplier Wood Pellet, proyek #34

Tanggal: 1 Oktober 2026. Reviewer: Codex. Pelaksana perubahan kode: Claude.

## Proyek uji

- Nama: Supplier Wood Pellet - Review Desain 1 Oktober.
- Kode: `REVIEW-PELLET-20261001`.
- Workspace: `http://localhost/project-workspace?project=34` (base URL dari konfigurasi aplikasi).
- Brief disalin dari proyek pellet #30; data dan proposal lama tidak diubah.
- Pengguna mengizinkan secara eksplisit pengiriman brief ke Gemini/OpenAI dan biaya API. Generator yang ada dijalankan tanpa mengubah kode aplikasi.
- Satu proyek, tiga kandidat; bukan tiga proyek baru. Belum di-approve dan belum dibangun menjadi WordPress.

## Hasil generasi dan keputusan desain

Generasi selesai: proposal #34 berstatus `pending`; kelima checkpoint `completed`. Run kedua memakai checkpoint dan melengkapi foto yang belum tersimpan. PDF: `storage/app/public/proposals/Proposal-Mockup-supplier-wood-pellet-REVIEW-PELLET-20261001.pdf`.

| Kandidat | Penilaian | Keputusan |
|---|---|---|
| 1 — Industrial editorial | Hero cukup tegas, tetapi spesifikasi menjadi galeri portrait dan manfaat terlalu panjang. PNG 1440 × 9408. | Jangan dipilih sebagai dasar utama. |
| 2 — Modern industrial | Spesifikasi paling mudah dipindai; warna gelap dan aksen hangat cukup sesuai. Hero terlalu sempit, manfaat bergambar masih berlebihan, syarat transaksi salah bentuk. PNG 1440 × 7579. | **Dasar revisi yang paling layak**, belum layak approval. |
| 3 — Calm editorial | Manfaat ringkas, tetapi spesifikasi malah menjadi empat blok foto besar; foto nilai kalori berisi batu hitam dan meteran. PNG 1440 × 8833. | Jangan dipilih sebagai dasar utama. |

Preview aplikasi (perlu login dan server aplikasi berjalan pada base URL yang sesuai):

- Opsi 1: `http://localhost/projects/34/mockup/0/demo`
- Opsi 2: `http://localhost/projects/34/mockup/1/demo`
- Opsi 3: `http://localhost/projects/34/mockup/2/demo`

Rekomendasi ini adalah hasil review. Tidak mengubah kandidat terpilih pada database atau menyetujui proposal.

## Penilaian dari hasil baru

**Belum layak disetujui sebagai desain akhir.** Foto baru yang sudah diperiksa tidak lagi menampilkan headline acak seperti hasil lama. Namun, pemilihan subjek, hierarki informasi, dan bentuk section masih membuatnya terasa seperti template AI bertema industri.

Ini penilaian desain terhadap hasil render, bukan klaim bahwa pengunjung pasti dapat mengenali teknologi pembuatnya.

### 1. Informasi teknis kalah oleh foto dekoratif

Opsi 1 menampilkan foto pekerja sangat besar untuk menjelaskan kalori. Nilai 4.300–4.700 Kcal/Kg, diameter 6–10 mm, abu 2,00–2,30%, dan kemasan 30 kg justru menjadi paragraf kecil di bawah gambar. Dalam render responsif desktop 1440px, section spesifikasi tingginya 2.246px.

**Arahan untuk Claude:** ubah penyajian spesifikasi menjadi tabel definisi atau grid data ringkas; label kecil, angka utama jelas, satuan konsisten. Foto cukup satu detail produk yang membantu melihat bentuk pellet. Jangan memakai portrait pekerja untuk setiap parameter. Tampilkan spesifikasi sebelum pengantar panjang atau beri ringkasannya dekat hero.

Bukti: `storage/app/design-review/screenshots/new-option-1-1440-section-2.png`.

### 2. Fotografi terlalu sering memakai orang sebagai jawaban semua topik

Hero, pengantar, kalori, kadar abu, dimensi, dan manfaat berulang kali menggunakan pekerja. Pada opsi 2, foto penyimpanan malah menunjukkan karton, sedangkan brief menyebut kemasan karung 30 kg. Foto manfaat energi terbarukan tampak seperti serpihan kayu, sehingga tidak cukup menjelaskan produk pellet yang dijual. Gambar harus dinilai terhadap topik, bukan sekadar ada nuansa pabrik.

**Arahan untuk Claude:** rencanakan daftar subjek sebelum generasi. Prioritaskan detail pellet, karung, pemuatan barang, dan aplikasi industri yang masuk akal. Jangan otomatis menambahkan pekerja untuk semua slot. Jangan menyajikan aset ilustratif sebagai bukti fasilitas atau staf milik klien.

**Bukti paling kuat pada opsi 3:** foto `candidate-3/section-2-item-0.jpg` menunjukkan pekerja dengan meteran, alat berbentuk sendok/sekop yang janggal, dan tumpukan batu hitam. Foto ditempatkan untuk “Kadar Kalori”. Ini tidak membantu pembeli memahami produk atau nilai kalorinya. Perlu pemeriksaan kecocokan visual sebelum foto dianggap siap hanya karena berhasil dihasilkan.

Path lengkap: `storage/app/public/mockup-assets/review-pellet-20261001/candidate-3/section-2-item-0.jpg`.

### 3. Ketentuan transaksi terlihat seperti dua paket yang harus dipilih

Minimum pesanan dan pembayaran setelah penimbangan dirender sebagai dua kartu pricing dengan dua tombol Hubungi Sales. Padahal keduanya adalah ketentuan yang berlaku bersama, bukan dua opsi paket.

**Arahan untuk Claude:** tampilkan blok syarat transaksi dengan tiga informasi utama: minimum 10 ton; karung 30 kg; pelunasan sesudah penimbangan disaksikan pembeli. Satu CTA cukup. Jangan menambah langkah operasional yang belum ada di brief.

Bukti: `storage/app/design-review/screenshots/new-option-1-1440-section-5.png`.

### 4. Hero terlalu banyak kata dan crop mobile tidak diarahkan

Judul opsi 1 menjadi enam baris pada 390px. Foto memotong pekerja di sisi kanan sehingga objek yang dominan tidak tersusun bersama teks. Opsi 2 pada PNG desktop juga memiliki kolom judul sangat sempit; judul menjadi banyak baris sementara sisi kanan terasa renggang.

**Arahan untuk Claude:** jadikan inti headline singkat, misalnya “Wood Pellet Sengon untuk Kebutuhan Industri”, lalu pindahkan detail ke subjudul dan baris spesifikasi. Ini usulan editorial, bukan fakta tambahan. Atur lebar teks dan titik crop berdasarkan gambar nyata; jangan hanya mengecilkan font atau menyembunyikan overflow.

Bukti: `storage/app/design-review/screenshots/new-option-1-390-hero.png`.

### 5. Bahasa promosi generik melemahkan kredibilitas

Kata premium, berkualitas tinggi, ramah lingkungan, efisiensi, dan transparan diulang di banyak heading. Beberapa kalimat pada blueprint melampaui brief: “mencegah kerusakan dini”, “tanpa campuran zat kimia berbahaya”, dan “bebas dari manipulasi volume”. Brief tidak memberi bukti untuk pernyataan tersebut.

**Arahan untuk Claude:** pertahankan fakta yang diberikan klien, ringkas judul menjadi nama informasi yang jelas, dan periksa ulang jalur konten/integrity gate untuk klaim kualitatif. Halaman pemasok terasa lebih meyakinkan ketika spesifik daripada terus menyebut dirinya premium.

## Temuan teknis yang memengaruhi desain

- `GenerateMockupGptService::generateMockupImage()` tidak memberi opsi `webfonts: true` pada `MockupSite::build()`, sedangkan controller live preview memberikannya. Opsi 2 meminta Poppins tetapi PNG yang diperiksa terlihat menggunakan serif fallback. Claude perlu menyamakan pemuatan font dan menunggu font benar-benar siap sebelum screenshot. Status `document.fonts.status = loaded` saja tidak membuktikan keluarga font yang diminta tersedia.
- `runProposalGeneration()` menetapkan batas 300 detik. Run pertama benar-benar terhenti dengan `Maximum execution time of 300 seconds exceeded` saat menunggu gelombang foto. Proses dilanjutkan pada proyek yang sama dari checkpoint. Ini temuan untuk Claude, bukan perubahan kode oleh reviewer.
- Renderer CTA memiliki tautan `#`. Penilaian visual dan ketiadaan overflow bukan bukti alur WhatsApp sudah berfungsi.

## Arah desain yang disarankan

Pemasok material industri yang ringkas dan jelas: header solid sederhana; hero dengan produk terlihat jelas; ringkasan spesifikasi; detail produk dan kemasan; manfaat singkat; aplikasi; syarat pemesanan; FAQ; CTA penawaran; footer kontak.

Gunakan hijau gelap atau charcoal sebagai dasar dan satu aksen hangat untuk tindakan utama. Hindari perubahan dekorasi sebagai satu-satunya perbaikan. Keputusan terpenting adalah mendahulukan data pembelian dan mengurangi gambar yang tidak menambah informasi.

## Pemeriksaan browser dan bukti

Render diekspor dari blueprint proyek #34 melalui renderer aplikasi yang sama, dengan foto tersimpan dibaca menjadi data URL. Puppeteer yang sudah terpasang dipakai untuk screenshot lokal karena CLI agent-browser tidak tersedia. Tidak melewati login aplikasi atau mengganti template produksi.

| Ukuran | Opsi 1: tinggi halaman | Opsi 2: tinggi halaman | Opsi 3: tinggi halaman |
|---|---:|---:|---:|
| Desktop 1440px | 9.202px | 7.373px | 8.628px |
| Tablet 768px | 9.420px | 8.334px | 10.305px |
| Mobile 390px | 9.415px | 9.022px | 10.009px |

Kesembilan kombinasi memiliki `scrollWidth` sama dengan lebar viewport, seluruh gambar termuat, dan tidak ada event error JavaScript yang tertangkap. Ini pemeriksaan render lokal, bukan pengujian interaksi lengkap. Menu, pengiriman WhatsApp, halaman lain, dan WordPress belum diuji. Ketersediaan font eksternal yang sebenarnya belum terverifikasi; screenshot lokal masih memperlihatkan fallback serif. Angka di tabel adalah render responsif lokal, bukan dimensi PNG proposal fixed-width, sehingga jangan disamakan.

Perbandingan paling relevan: tinggi section spesifikasi pada desktop **opsi 1: 2.246px; opsi 2: 473px; opsi 3: 3.488px** untuk konten yang sama. Inilah alasan utama memilih opsi 2 sebagai dasar revisi. Angka teknis pada opsi 2 tetap perlu dibuat lebih menonjol daripada paragraf deskripsinya.

Di mobile, opsi 2 dan 3 menjadi sangat mirip: susunan judul enam baris, paragraf, tombol putih, lalu foto pekerja. Mengganti warna dan foto belum menghasilkan arah desain yang terasa berbeda pada layar kecil.

- PNG asli: `storage/app/public/mockups/REVIEW-PELLET-20261001-gpt-option-{1,2,3}.png`.
- Screenshot responsif: `storage/app/design-review/screenshots/new-option-{1,2,3}-{1440,768,390}-{full,hero}.png`.
- Crop section desktop: `storage/app/design-review/screenshots/new-option-{1,2,3}-1440-section-{1..7}.png`.
- Data pengukuran: `storage/app/design-review/new-option-metrics.json`.
- Data kandidat dan URL demo: `storage/app/design-review/summary.json`.

Claude perlu mengembalikan empat bukti setelah revisi: hero desktop/mobile; spesifikasi yang ringkas; foto yang tepat konteks; ketentuan transaksi yang tidak menyerupai pilihan paket. Jangan menyatakan kualitas visual selesai hanya karena semua tes teknis lulus.

## Batas perubahan

Claude menangani implementasi. Reviewer hanya membuat proyek uji, menjalankan generator, mengekspor render untuk pemeriksaan, mengambil screenshot, dan menulis catatan. Tidak ada perubahan pada kode aplikasi, stylesheet, template, atau prompt generator oleh reviewer.

Setiap perbaikan harus konsisten di preview, PNG, dan WordPress. Jangan mengubah proposal lama yang sudah disetujui. Setelah implementasi, kembalikan screenshot baru desktop dan mobile dari brief yang sama agar dinilai ulang.

## Titik implementasi untuk Claude

- Susunan isi dan pemilihan komposisi: `app/Services/GenerateMockupGptService.php`, `app/Support/CompositionSpec.php`, serta klasifikasi bentuk isi di `SectionContent.php` dan `ElementorPageBuilderService.php`.
- Rencana subjek, konteks produk, dan pemeriksaan relevansi foto: `app/Services/MockupAssetService.php`. Jangan memperbaiki dengan sekadar melarang teks di foto; larangan tersebut belum menjamin subjek benar.
- Penyajian tabel spesifikasi dan ketentuan transaksi: renderer Blade dan pasangan Gutenberg melalui plan yang sama. Buat elemen baru hanya bila komposisi yang ada tidak bisa menjaga makna konten.
- Font PNG/live preview: `GenerateMockupGptService::generateMockupImage()`, `MockupSite::build()`, `ScreenshotService`, dan `MockupPreviewController`.
- Klaim berlebihan: `AnalisisGeminiService.php` dan `ContentIntegrityService.php`.
- Batas waktu eksekusi: `WebsiteBuilderController::runProposalGeneration()` perlu dinilai bersama timeout job, retry_after, rate limit, dan waktu pembuatan foto.

Arahan awal umum ada di `docs/MOCKUP-DESIGN-REVIEW-FOR-CLAUDE.md`; dokumen proyek #34 ini memiliki bukti lebih baru dan menjadi acuan utama untuk review pellet.
