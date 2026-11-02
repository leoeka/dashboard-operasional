# Rancangan: Jalur B yang dijaga (GPT menata letak, hasil tetap terjamin)

Tanggal: 2026-10-20
Status: **rancangan untuk dicoret pemilik**. Nol berkas kode disunting untuk dokumen ini.
Persetujuan lisan: "tulis dulu" (2026-10-20), setelah pemilik menyatakan: *"saya mau GPT juga tataletaknya kayak gimana, terus ukuran card yang tepat, gaya nav yang cocok, jadi tidak pake yang punya php. soal token iya memang boros tapi mau gimana lagi, maunya desainnya bagus dan keren."*
Arah pengganti: mutu dijaga **berkas aturan**, bukan tiruan tema berbayar dari ThemeForest.

---

## 1. Yang berubah dan yang tidak

| Bagian alur | Hari ini | Setelah rancangan ini disetujui |
| --- | --- | --- |
| Baca request, riset pesaing, tulis isi halaman | GPT | **tetap sama**, tidak disentuh |
| Susunan halaman, gaya menu, ukuran kartu, jarak, irama | PHP memilih dari daftar kosakata yang sudah ada | **GPT yang menulis**, tanpa daftar bentuk dari PHP |
| Foto | generate per slot, disisipkan lewat penanda | **tetap sama**, penanda dan slotnya tidak berubah |
| Gambar mockup yang disetujui klien | foto layar dari hasil render PHP | foto layar dari **artefak tulisan GPT yang sama** |
| Demo langsung | render PHP | **artefak yang sama**, dirakit oleh satu perakit |
| ZIP WordPress | theme berisi CSS + blok halaman hasil render PHP | theme berisi **CSS dan markah hasil tulisan GPT**, apa adanya |
| Menu WordPress asli (dibuat importer) | sudah jalan, teruji | **tetap sama**, hanya isi kepala/kaki halamannya yang sekarang ditulis GPT |
| Gerbang approval, antrean build, endpoint progres | sudah jalan | **tetap sama** |

Poin terpenting: **jaminan "yang disetujui klien = yang dikirim ke WordPress" tidak bergantung pada siapa pengarang desainnya.** Jaminan itu bergantung pada satu hal, yaitu ketiga keluaran difabrik dari artefak yang sama. Jadi selama artefak tulisan GPT disimpan apa adanya dan hanya ada satu tempat yang merakitnya, jaminannya utuh. Yang hilang bukan jaminannya, yang hilang adalah kemampuan PHP untuk memperbaiki desain secara sepihak.

---

## 2. Bentuk artefaknya

Satu kandidat mockup menyimpan empat benda:

```
kandidat/
  gaya.css        seluruh tampilan: nilai merek di kepala berkas, sisanya aturan
  kepala.html     baris menu + tombol aksi, memakai tempat kosong untuk daftar tautan
  kaki.html       blok penutup halaman, tempat kosong yang sama
  halaman/
    beranda.html  isi satu halaman, tanpa kepala dan kaki
    tentang.html
    ...
```

Aturan main artefak:

1. **Kepala berkas `gaya.css` wajib berisi blok nilai tetap**: warna merek, warna teks, warna latar, keluarga huruf, skala jarak, dan sudut. Ganti warna = ubah blok itu, bukan tulis ulang halaman. Ini yang menjaga "minta ganti warna sedikit" tetap murah dan tidak mengundi ulang desain.
2. **Foto tidak pernah ditulis sebagai alamat asli.** Tetap penanda `__EXITO_IMAGE:<kunci>__`, jadi foto bisa dipakai ulang, ditukar foto milik klien, dan tidak ikut terfoto ke dalam gambar layar.
3. **Tautan antar halaman tetap penanda** `__EXITO_PAGE_URL:<slug>__`, jadi demo dan WordPress sama-sama menunjuk halaman yang benar.
4. **Kepala dan kaki halaman dipisah dari isi halaman**, persis seperti yang sudah kita bangun untuk menu WordPress. GPT boleh menatanya semaunya, tetapi daftar tautannya tetap dibuatkan WordPress lewat menu, bukan ditulis manual di tiap halaman.
5. Satu-satunya alamat luar yang boleh muncul di artefak adalah berkas huruf dari Google, itu pun hanya keluarga yang sudah disebut di blok nilai.

Yang tetap dibutuhkan dari PHP: tempat menampung artefak, perakit (gaya + kepala + halaman + foto), pemotret layar, penulisan theme, dan importer. Yang tidak dipakai lagi: daftar kosakata susunan dan pemilih komposisi. Berkas-berkas itu tidak dihapus di tahap rancangan ini; penghapusannya jadi keputusan terpisah setelah jalur baru terbukti.

---

## 3. Berkas aturan: satu berkas, dua pembaca

Nama usulan: `.docs/aturan-desain-sisi-klien.md`. Isinya tiga macam aturan, dan aturan yang tidak bisa diukur **tidak masuk** berkas, karena ia hanya jadi hiasan dokumen.

### 3.1 Larangan (menolak yang jelek)

Sebagian besar sudah terkumpul di audit 2026-10-20 (`rancangan-desain-sisi-klien.md`, temuan T-1 sampai T-8) dan diserap ke sini, jadi kerja itu tidak terbuang:

- satu warna merek maksimal dipakai pada tombol dan penanda keadaan nyata;
- tidak ada baris kapital berjarak lebar di atas judul yang mengulang judul;
- tidak ada nomor kalau daftarnya tidak berurutan;
- tidak ada blok warna penuh di ujung halaman sebagai penutup bawaan;
- tidak ada lingkaran/blur samar di belakang konten, garis kiri dekoratif, atau tanda kutip raksasa;
- tidak ada nama perusahaan yang dirender sebagai teks dan mengaku sebagai pelanggan;
- tidak ada kontrol yang tidak ke mana-mana.

### 3.2 Positif (menuntut yang bagus)

Ini yang belum pernah kita tulis. Semuanya pernyataan yang bisa dibuktikan pada artefak jadi:

- **Satu fokus per layar.** Ada satu hal yang paling menonjol, dan ia bukan hiasan.
- **Kontras skala.** Ukuran judul terbanding jauh dengan isi (rasio minimum, diukur, bukan dirasakan), supaya urutan baca jelas.
- **Irama jarak.** Minimal tiga tingkat jarak vertikal berbeda dipakai di satu halaman; satu nilai untuk semua ditolak.
- **Komposisi tidak seragam.** Tidak lebih dari satu baris kartu identik per tiga layar; halaman yang isinya banyak harus punya satu bagian yang diperlakukan istimewa.
- **Ide yang dinyatakan.** Tiap opsi wajib menulis satu kalimat idenya ("halaman ini dibuka dengan angka terbesar klien"), dan kalimat itu ikut ditampilkan ke pemilik saat memilih. Ini yang membuat tiga opsi benar-benar beda dan membuat alur tiga mockup punya arti lagi.
- **Setiap bagian punya alasan ada.** Bagian yang isinya cuma mengisi template dibuang.

### 3.3 Terukur mesin (yang benar-benar menahan mutu)

Daftar ini dijalankan pada artefak jadi, sebelum kandidat ditampilkan ke pemilik. Yang gagal keras: opsi diminta ulang. Yang gagal lunak: tetap ditampilkan, dengan catatan di kartu kandidat supaya kamu tahu letak lemahnya.

| Yang diukur | Cara mengukurnya | Sifat |
| --- | --- | --- |
| Jarak warna teks dan latar | baca blok nilai + hitung rasio | keras, ambang baca sudah ada angkanya |
| Berapa kali warna merek terpakai | hitung pemakaian warna dari blok nilai di aturan CSS | lunak, batas awal: 6 |
| Jumlah tingkat jarak vertikal | kumpulkan nilai jarak yang benar-benar terpakai | keras, minimal 3 |
| Dua bagian beruntun berlatar sama | bandingkan latar antar bagian bertetangga | lunak |
| Lebar meluber di 390 / 768 / 1024 / 1440 | peramban nyata, periksa batas isi | keras |
| Kontrol mati dan tautan tidak jelas sasaran | periksa setiap tautan dan tombol di DOM | keras |
| Tempat foto kosong | cocokkan penanda dengan slot yang tersedia | lunak |
| Ukuran kendali sentuh | tinggi kendali pada tampilan ponsel | lunak |
| Isi halaman kosong di salah satu lebar | bandingkan wilayah terisi | keras |

Alat pengukurnya sebagian sudah ada di repo: pemindaian empat lebar layar dan pemotret layar. Yang perlu dibangun adalah pembaca blok nilai CSS dan penjumlah pemakaian warna; keduanya periksa berkas, bukan menebak.

**Yang tidak bisa diukur mesin dan sengaja tidak kita pura-pura amankan:** rasa. Karena itu ada ronde kritik di bagian 4, dan keputusan akhir tetap tangan kamu.

---

## 4. Ronde kritik (satu panggilan tambahan per opsi)

Setelah GPT selesai menata satu halaman, ia dikirimi **hasil rendernya sendiri** (gambar layar, bukan teks yang ia tulis barusan) bersama berkas aturan, lalu diminta: sebutkan tiga aturan yang paling dilanggar hasilnya sendiri, perbaiki ketiganya, kirim ulang.

Alasannya konkret: model jauh lebih mampu menilai rupa daripada mengingat semua larangan sambil menulis. Ini satu-satunya bagian rancangan yang menaikkan mutu secara nyata, bukan cuma menyaring.

Biayanya: satu panggilan teks + satu gambar per opsi per halaman utama. Ini yang paling menentukan hasilnya terasa dipikir, bukan dicetak.

---

## 5. Penjaga keselamatan (bukan rasa, tapi keamanan berkas)

Artefak tulisan model akan dieksekusi peramban klien dan dipasang di situs klien, jadi pemeriksaan ini wajib dan tidak bisa ditawar:

- tanpa skrip, tanpa pengambilalihan kejadian di dalam markah, tanpa formulir, tanpa iframe, tanpa objek luar;
- alamat luar hanya berkas huruf yang terdaftar;
- markah harus bisa dibaca ulang tanpa kehilangan struktur, dan gaya tidak boleh memotong bloknya sendiri;
- batas ukuran per halaman dan per berkas gaya, supaya ZIP tetap wajar;
- tempat daftar tautan menu wajib ada di kepala dan kaki;
- semua yang gagal tercatat alasannya, bukan diam-diam dikirim.

Kebijakan ulang: maksimal tiga percobaan per opsi. Setelah itu kandidat ditandai **cacat**, dan di sinilah keputusan yang kamu ambil: apakah sistem jatuh ke susunan lama milik PHP supaya klien tetap dapat sesuatu, atau berhenti dan melaporkan. Usulan saya: jatuh ke susunan lama, dan kartu kandidatnya ditulis jujur bahwa opsi itu hasil cadangan.

---

## 6. Yang benar-benar hilang (jujur, tidak saya poles)

1. **Tata letak tidak bisa diedit dengan klik di WordPress.** Ia tinggal di berkas gaya tema. Teks dan foto tetap bisa diganti lewat halaman, susunannya tidak. Perlu kamu putuskan: apakah ini mengganggu cara kamu bekerja, atau tidak masalah karena yang mengedit memang tim kamu sendiri.
2. **Hasil tidak berulang persis.** Permintaan yang sama bisa melahirkan susunan yang berbeda. Itu memang maunya, tapi konsekuensinya: revisi kecil bisa menggeser sesuatu yang sudah kamu suka. Penahannya: halaman yang sudah disetujui klien dikunci, revisi berikutnya tidak menyentuhnya kecuali diminta, dan artefak yang disetujui disimpan beserta sidik jarinya.
3. **Model bisa gagal menaati aturan.** Sekarang kegagalan itu jadi kelas baru: opsi diminta ulang, atau kandidat cacat. Alur UI harus bisa menampilkan itu, bukan membiarkan layar berputar.
4. **Biaya naik.** Berapa naiknya belum bisa saya sebut sebagai angka, karena sampai hari ini tidak ada satu pun panggilan yang mencatat pemakaiannya. Yang terukur baru foto: sampai 27 foto per proposal. Karena itu bagian 7 menaruh pencatat pemakaian di urutan paling awal.

---

## 7. Urutan kerja usulan (tiap langkah bisa dinilai sendiri)

| # | Pekerjaan | Kenapa urut di sini |
| --- | --- | --- |
| B0 | Coretan kamu atas dokumen ini | tidak ada kode sebelum ini |
| B1 | Berkas aturan tiga macam ditulis, lalu kamu corek lagi | aturan adalah bahan baku jalur baru, bukan hiasan |
| B2 | Pencatat pemakaian tiap panggilan | tanpa ini, semua klaim biaya di jalur baru cuma aritmetika |
| B3 | Perakit tunggal: satu tempat menggabungkan gaya + kepala + halaman + foto | ini penjaga paritas; dites dengan membandingkan bytes yang sama di gambar, demo, dan ZIP |
| B4 | Tempat penyimpanan artefak per kandidat | jalur baru hidup berdampingan dengan yang lama |
| B5 | GPT menulis artefak (menggantikan pemilihan kosakata) | inti perubahan |
| B6 | Penjaga keselamatan + pemeriksaan terukur + kebijakan ulang | yang menahan mutu |
| B7 | Ronde kritik | kenaikan rasa |
| B8 | Foto cukup hero saat masih draf | membayar B5 dan B7 dari kantong yang sama; tugas hemat yang sudah tercatat |
| B9 | Kartu kandidat menampilkan kalimat ide + catatan pemeriksaan | kamu memilih dengan informasi, bukan menebak |
| B10 | Uji ujung ke ujung memakai artefak tersimpan | nol token, bisa diulang, sama seperti blueprint tersimpan hari ini |

Yang tidak disentuh selama B1 sampai B10: alur approval, gerbang build, antrean, importer WordPress, menu, tabel pembayaran, dan seluruh halaman aplikasi SiteFlow.

---

## 8. Keputusan yang saya butuhkan darimu

1. **Berkas aturan lebih dulu (B1) atau perakit lebih dulu (B3)?** Usulan saya: aturan, karena perakit tanpa aturan hanya memindahkan masalah.
2. **Jatuh ke susunan lama kalau tiga percobaan gagal**, atau berhenti dan lapor? Usulan saya: jatuh ke susunan lama, ditandai jujur.
3. **Tata letak tidak bisa diedit lewat Gutenberg.** Ganggu atau tidak?
4. **Jumlah opsi tetap tiga**, atau turun ke dua supaya biaya desain dan foto tertahan sementara jalur baru belum terukur?
5. **Saat draf, foto cukup hero saja** (sisanya tempat kosong), lalu foto lengkap baru dibuat setelah klien memilih? Ini sumber uang untuk membayar ronde kritik.
6. Temuan audit lama (T-1 sampai T-8) saya perlakukan sebagai **bahan baku berkas aturan**, bukan sebagai pekerjaan terpisah di jalur lama. Setuju?

Sesuai aturan main kamu: tidak satu baris kode pun saya sentuh sampai kamu mencoret dokumen ini dan menyebutkan nomor yang boleh dikerjakan.

---

## 9. Jawaban saya atas bagian 8 (ditulis 2026-10-20, atas perintah "jawab pertanyaan bagian 8")

**Koreksi lebih dulu, karena mengubah jawaban nomor 3.** Bagian 2 dan 6 dokumen ini menyebut halaman ZIP dikirim sebagai potongan HTML biasa. Sudah diukur ulang dari kodenya: yang dikirim sekarang adalah **blok Gutenberg sungguhan**, dengan hitungan marker di satu berkas perakit: 13 grup, 10 kolom, 8 baris kolom, 6 paragraf, 6 gambar, 6 cover, 3 judul, 2 galeri, 2 kutipan, 2 daftar, 2 pemisah, 2 tombol. Jadi kerugian "tidak bisa diedit dengan klik" itu nyata, bukan kosmetik.

| # | Pertanyaan | Jawaban saya | Alasan |
| --- | --- | --- | --- |
| 1 | Aturan dulu atau perakit dulu | **Aturan dulu** | Perakit tidak peduli isinya apa, jadi ia bisa dibuat kapan saja; aturan menentukan apa yang akan ditulis model. Bonusnya: begitu aturan selesai, saya bisa **mengukur mockup yang ada hari ini terhadap aturan itu** dan memberi kamu angka "seberapa slop sekarang", sebelum satu baris pun berubah. |
| 2 | Jatuh ke susunan lama kalau tiga percobaan gagal | **Ya, jatuh, ditandai jujur di kartunya** | Alur ini berujung ke klien. Kandidat cacat hari ini juga sudah ada, jadi polanya bukan barang baru. Bayarannya: susunan lama tetap hidup sebagai cadangan, tidak bisa dihapus dalam waktu dekat. |
| 3 | Tata letak tidak bisa diedit lewat Gutenberg | **Jangan diterima begitu saja. Ubah bentuk artefaknya: model menulis markah blok Gutenberg, bukan HTML bebas** | Ini jalan tengah yang lebih baik daripada yang saya tulis di bagian 2. Grup dan kolom adalah bahasa asli tujuannya, jadi kebebasan menata tetap luas (jumlah kolom, lebar, jarak, latar, susunan bertumpuk) **dan** tim kamu masih bisa menyeret serta mengubahnya di editor. Bayarannya: lebih sulit bagi model, jadi percobaan ulang lebih sering dan berkas pemeriksa blok yang sudah ada harus diperlebar. Kalau kamu pilih HTML bebas, hasilnya bisa lebih liar tapi situs jadi tidak bisa ditata ulang tanpa sentuhan kode. |
| 4 | Tiga opsi atau dua | **Tetap tiga** | Seluruh alasan membuka jalur ini adalah supaya opsi benar-benar beda; dua opsi memperkecil peluang kena. Ini baru aman dikerjakan kalau nomor 5 disetujui, karena tiga opsi dibayar dari sana, bukan dari memotong desain. |
| 5 | Saat draf cukup foto hero | **Ya, dengan satu syarat** | Foto adalah biaya yang terukur (sampai 27 lembar per proposal), sedangkan desain hanyalah teks. Syaratnya: karena foto lain baru dibuat setelah klien memilih, situs jadi berisi foto yang belum pernah dilihat klien. Jaminan "yang disetujui = yang dikirim" perlu diamandemen kecil di titik ini: susunan dan tempat fotonya persis, subjek fotonya baru. Penahannya, satu layar konfirmasi murah setelah approval dan sebelum ZIP dibuat. Kalau kamu tidak mau amandemen itu, jawabannya jadi "foto tetap lengkap saat draf" dan biaya naik lagi. |
| 6 | Temuan audit T-1..T-8 jadi bahan baku berkas aturan | **Ya** | Di jalur baru perhiasan PHP itu memang tidak ada lagi, jadi memperbaikinya di jalur lama adalah kerja yang langsung dibuang. Nilai temuan itu bertahan justru sebagai daftar larangan di berkas aturan. |

**Yang masih benar-benar milik kamu:** nomor 3 (bebas-menata vs bisa-diedit) dan nomor 5 (amandemen jaminan foto). Dua-duanya soal cara kamu dan tim bekerja, bukan soal teknis, jadi jangan pakai jawaban saya tanpa menimbang.

