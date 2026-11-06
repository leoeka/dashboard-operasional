# Aturan desain sisi klien

Tanggal: 2026-10-20
Status: **draf untuk dicoret pemilik**. Berkas ini bukan kode, dan tidak ada berkas kode yang disunting untuk menulisnya.
Pasangan: `.docs/rancangan-jalur-b-terjaga.md` (rancangan arsitekturnya) dan `.docs/rancangan-desain-sisi-klien.md` (audit yang melahirkan sebagian larangan di sini).

## Cara berkas ini dipakai

Dua pihak membaca berkas yang sama:

- **GPT** membacanya sebagai perintah saat menata letak. Yang dikirim ke model hanya bagian 3 dan 4, dalam bentuk ringkas, dengan id aturan.
- **Pemeriksa** membacanya sebagai daftar uji pada artefak jadi. Yang bisa dihitung dijalankan oleh kode; sisanya dinilai ronde kritik.

Setiap aturan punya id supaya model dan pemeriksa memakai bahasa yang sama. Setiap ambang di bawah punya **angka dasar dari build nyata**, bukan dari perasaan; kalau tidak ada angkanya, tertulis "belum terukur" dan aturannya belum boleh jadi keras.

---

## 1. Angka dasar hari ini (terukur 2026-10-20)

Yang diukur: berkas gaya hasil build nyata di `storage/app/dummy-preview/extracted/exito-client-theme__style.css` (30.353 byte, jalur deterministik, nol panggilan AI), plus tes peramban nyata.

| Yang diukur | Hasil | Cara mengukurnya |
| --- | --- | --- |
| Rujukan ke warna merek di berkas gaya | **28 kali**, 14 di antaranya latar penuh | hitung `var(--accent)` pada berkas jadi |
| Jarak vertikal antar bagian | **1 tingkat untuk semua**: 60px, dan di jalur baru satu nilai clamp yang sama | baca aturan bagian di berkas jadi |
| Tingkat jarak yang tersedia di kosakata | 4 tingkat, tidak pernah dipakai berbeda | `CompositionSpec`, tingkat jarak |
| Rasio ukuran judul terbesar : teks isi | **3,85** (50px : 13px) | nilai token yang benar-benar dirender |
| Label kapital berjarak lebar | 3 tempat kapital paksa, 7 tempat jarak huruf | hitung di berkas jadi |
| Meluber horizontal dan bagian kosong di 390 / 768 / 1024 / 1440 | **bersih**, 122 pemeriksaan lolos | `php artisan test --group=browser` |
| Kontras teks tombol (putih di atas warna merek proyek ini) | **2,99:1, gagal** | rasio WCAG dari nilai merek yang tertulis di kepala berkas |
| Kontras teks tombol kalau memakai warna hitung tua | **5,81:1, lolos** | rasio yang sama, warna teks diganti |
| Kontras label merek kecil di atas latar halaman dan di atas band | **2,65:1 dan 2,72:1, gagal** | rasio WCAG |
| Delapan pasangan warna lain (isi, redup, kaki halaman) | lolos semua, 5,29 sampai 15,81 | rasio WCAG |

Dua hal dari tabel itu yang bukan soal selera dan harus dibereskan lebih dulu dari apa pun:

1. **Tombol di situs klien hari ini tidak terbaca.** Putih di atas oranye `#E07A3F` = 2,99:1, di bawah ambang 3 untuk teks besar. Penyebabnya satu aturan tua: warna teks dipilih dari ambang terang-gelap 0,6, dan warna tengah seperti oranye ini jatuh ke sisi yang salah. Kalau pemilihnya diganti jadi "ambil yang rasionya paling tinggi", angka yang sama jadi 5,81:1. Ini cacat nyata, bukan rasa, dan ia ada di jalur lama maupun jalur baru.
2. **Irama halaman memang seragam secara harfiah.** Semua bagian berjarak 60px. Kosakata jarak sudah punya empat tingkat tetapi tidak pernah dipakai berbeda. Jadi keluhan kamu "tata letaknya itu-itu aja" punya dasar ukur, bukan cuma kesan.

---

## 2. Larangan (K)

Ditolak kalau dilanggar. Sebagian besar lahir dari audit 2026-10-20.

| Id | Aturan | Angka dasar hari ini |
| --- | --- | --- |
| K-1 | Warna merek boleh jadi latar bagian, warna judul, angka besar, dan dekorasi yang punya fungsi (penanda, pemisah, bidang baca). Yang tetap dilarang: hiasan tanpa fungsi, yaitu lingkaran samar di belakang konten, balok geser di belakang foto, kotak kosong selang-seling | dilonggarkan 2026-11-03 untuk jalur Codex (sebelumnya: 28 rujukan, harus ≤ 8) |
| K-2 | Tidak ada baris kapital berjarak lebar di atas judul yang isinya mengulang judul | 3 kapital paksa, 7 jarak huruf |
| K-3 | Nomor hanya pada daftar yang memang berurutan | nomor dirender untuk semua daftar fitur |
| K-4 | Bagian penutup tidak boleh otomatis berupa blok warna penuh dengan judul di tengah | terjadi di setiap halaman |
| K-5 | Warna kaki halaman diturunkan dari warna proyek itu, bukan konstanta yang sama untuk semua klien | konstanta `#1c1a17` untuk semua |
| K-6 | Tidak ada nama perusahaan atau angka yang ditampilkan sebagai pelanggan/statistik kalau tidak bisa dibuktikan | bagian "logos" merender teks nama sebagai logo |
| K-7 | Tidak ada kontrol yang tidak ke mana-mana: tautan kosong, tombol mati, tempat yang belum dibangun | belum terukur |
| K-8 | Bagian yang isinya hanya mengisi template dibuang, bukan diisi | belum terukur |
| K-9 | Tanpa skrip, tanpa formulir, tanpa iframe, tanpa alamat luar selain berkas huruf yang terdaftar | belum terukur di jalur baru |
| K-10 | Tidak ada emoji sebagai hiasan, dan tidak ada panah kecil di setiap tombol | belum terukur |
| K-11 | Dua slot foto (`hero`, `galeri-1`) wajib dua berkas berbeda dan dua adegan berbeda | dinilai ronde kritik; penolakan otomatis dicabut 2026-11-02 |
| K-12 | Judul dan kalimat pendek boleh ditumpuk di atas foto hero, asal ada lapisan gelap (atau terang) yang solid di bawah teks dan kontrasnya lolos M-1. Lapisan itu wajib satu unsur sungguhan (mis. `<span class="lapisan">`), bukan `::before`, karena yang bukan unsur tidak terbaca alat periksa dan hilang saat halaman dipasang ke tema. Keterangan foto biasa tetap ditulis di bawah foto. Teks di atas foto galeri atau foto detail tetap dilarang | dilonggarkan 2026-11-03 untuk foto hero; kalimat "unsur sungguhan" ditambah 2026-11-03 setelah dua kandidat diukur putih-di-atas-putih karena lapisan `::before` (sebelumnya: kandidat 22 menempel keterangan putih di atas foto tanpa lapisan) |
| K-13 | Foto boleh dipotong bentuk lewat `clip-path: polygon(...)` yang dipasang di wadah foto: lengkung atas (busur), satu atau dua sudut terpotong, atau satu sisi melengkung. Lingkaran penuh hanya untuk foto berbanding 1:1 — di foto melebar ia jadi ellips gepeng. Yang tetap dilarang: bentuk yang memotong wajah subjek sampai tidak dikenali, dan bingkai palsu bergaya tempel. Wajib tetap memakai `aspect-ratio` + `object-fit: cover` (M-13) supaya bentuknya tidak gepeng | dilonggarkan 2026-11-03: minimal satu foto per halaman memakai bentuk selain persegi (sebelumnya: foto berhenti pada persegi 4px). Urutan diubah 2026-11-03: "bulat" tidak lagi disebut pertama karena enam kandidat berturut-turut menghasilkan ellips gepeng |

## 3. Terukur mesin (M) - yang benar-benar menahan mutu

Keras = artefak ditolak dan diminta ulang. Lunak = tetap dikirim, catatannya ditampilkan di kartu kandidat.

| Id | Yang diukur | Ambang | Sifat | Hari ini |
| --- | --- | --- | --- | --- |
| M-1 | Rasio tiap pasangan warna teks dan latar yang benar-benar terpakai | ≥ 4,5 teks biasa; ≥ 3,0 teks besar | **keras** | 3 pasangan gagal |
| M-2 | Warna teks di atas warna merek dipilih dari kandidat berasio tertinggi, bukan dari ambang terang-gelap | wajib | **keras** | aturan lama salah untuk warna tengah |
| M-3 | Jumlah rujukan warna merek di berkas gaya | plafon ≤ 8 dan gagal > 16 dicabut 2026-11-03 untuk jalur Codex; yang mengikat tinggal palet 4-6 warna dan kontras M-1/M-2 | lunak | sebelum: 28 rujukan, gagal |
| M-4 | Jumlah tingkat jarak vertikal antar bagian yang berbeda dalam satu halaman | ≥ 3 | **keras** | 1, gagal |
| M-5 | Rasio ukuran judul terbesar terhadap teks isi | ≥ 2,5 | **keras** | 3,85, lolos |
| M-6 | Dua bagian beruntun berlatar sama | 0 kali | lunak | belum terukur |
| M-7 | Meluber horizontal dan wilayah kosong di 390 / 768 / 1024 / 1440 | 0 | **keras** | lolos, jaga tetap hidup |
| M-8 | Tiap tautan dan tombol punya sasaran nyata | 100 persen | **keras** | belum terukur |
| M-9 | Tempat foto terisi penanda yang dikenali, tanpa alamat luar | 100 persen | **keras** | lolos di jalur lama |
| M-10 | Tinggi kendali yang bisa disentuh | ≥ 44px | lunak | belum terukur |
| M-11 | Ukuran berkas gaya dan tiap halaman | gaya ≤ 60 KB, halaman ≤ 120 KB | **keras** | 30 KB, lolos |
| M-12 | Keluarga huruf yang dipakai harus terdaftar di blok nilai kepala berkas | 100 persen | **keras** | lolos |
| M-13 | Foto memakai `aspect-ratio` + `object-fit: cover`, tanpa tinggi piksel tetap; hero 4:3, galeri-1 1:1, sama di ponsel | 100 persen | **keras** | kandidat memakai tinggi mati 510/440/300px |
| M-15 | Titik fokus foto bukan `50% 50%`; `object-position` mengikuti subjek | wajib | lunak | semua foto dipotong tengah |

## 4. Positif (P) - menuntut yang bagus

Yang bisa dihitung ikut ke bagian 3; yang tidak bisa dihitung ditagihkan ke ronde kritik, dan itu sah karena ronde kritik melihat hasil render, bukan teks.

- **P-1 Tiap opsi wajib menulis satu kalimat idenya**, dan kalimat itu harus terbukti di hasilnya. Contoh bentuk yang sah: "halaman ini dibuka dengan angka terbesar klien, bukan dengan foto". Kalimat ini ikut ditampilkan di kartu kandidat, jadi kamu memilih dengan alasan, bukan menebak.
- **P-2 Satu fokus per layar.** Ada satu hal yang paling menonjol, dan ia bukan hiasan.
- **P-3 Tidak lebih dari dua bagian beruntun dengan susunan kartu yang identik.** Kalau isinya banyak, satu bagian diperlakukan istimewa.
- **P-4 Halaman membuka dengan sesuatu yang spesifik untuk usaha itu**, bukan dengan judul besar di atas latar gelap yang sama seperti semua halaman.
- **P-5 Setiap bagian bisa ditunjuk: isinya apa yang tidak bisa dipindahkan ke bagian lain.** Kalau bisa, bagian itu digabung atau dibuang.
- **P-6 Angka dan bukti nyata atau tidak sama sekali.** Tidak ada testimoni, statistik, atau nama pelanggan yang tidak bisa diverifikasi klien.
- **P-7 Keterangan foto menyebut bahan atau proses yang terlihat**, bukan mengulang nama bagian. Contoh sah: "Serat bambu setelah diserut dan disambung". Contoh ditolak: "Foto produk".

## 5. Yang sengaja tidak dijadikan aturan

Supaya berkas ini tidak berubah jadi selera saya:

- **Palet, keluarga huruf, dan suasana** bukan aturan. Itu arah per proyek, diambil dari request klien, dan di jalur baru ia tetap ditulis di blok nilai kepala berkas.
- **Industri mana cocok dengan gaya apa** bukan aturan. Daftar "katering harus hangat, hukum harus biru" itu tebakan, dan tebakan massal menghasilkan halaman yang seragam.
- **Gaya tertentu yang saya atau model sukai** tidak masuk. Yang masuk cuma yang bisa dibuktikan pada artefak jadi.

Alasannya sama seperti waktu audit aplikasi: tanpa pembedaan ini, berkas aturan cuma memindahkan rasa generik dari satu pihak ke pihak lain.

## 6. Yang perlu kamu putuskan

1. **Ambang M-3.** Saya menaruh sasaran 8 rujukan dan gagal di atas 16, dengan sifat lunak dulu. Angka 8 itu pilihan, bukan hasil ukur; yang terukur cuma 28 hari ini. Mau kamu tarik ke berapa?
2. **Naik ke keras kapan.** Usulan saya: setelah tiga build nyata lewat jalur baru, supaya kita tidak menolak semua opsi di minggu pertama.
3. **M-10, lantai sentuh 44 piksel.** Di aplikasi admin kamu sudah memilih 44. Untuk situs klien, ikut 44 atau turun ke 40?
4. **Cacat kontras tombol (M-2).** Ini bukan bagian Jalur B: ia ada di jalur lama sekarang juga, di setiap tombol setiap klien. Boleh saya perbaiki sendiri, di luar rancangan ini, dengan tes yang membuktikan kedua warna dibandingkan dan yang lebih terbaca yang dipilih?
