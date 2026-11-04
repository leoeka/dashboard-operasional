# Rancangan: rujukan desain untuk Codex

Tanggal: 2026-11-02
Status: **menunggu persetujuan pemilik**. Berkas ini bukan kode; nol berkas kode disunting untuk menulisnya.
Pasangan: `.docs/aturan-desain-sisi-klien.md` (daftar aturan yang dikirim ke model) dan `.docs/rancangan-mata-kandidat-2026-11-02.md` (usulan ronde kritik yang sudah **ditolak** pemilik karena terlalu banyak kode — berkas itu dibiarkan sebagai arsip, tidak dijalankan).

## 1. Jawaban atas pertanyaan kamu

Ya. Ketiga desain itu dibuat otomatis, tanpa tangan saya sama sekali. Satu kandidat = satu kali Codex bekerja sendiri dari awal sampai jadi gambar pratinjau.

Yang terukur hari ini, satu kandidat (kandidat 2 dari proyek 9):

| Yang diukur | Angka |
| --- | --- |
| Lama kerja | 4 menit 42 detik (07:10:21 → 07:15:03 UTC) |
| Token terpakai | 286.232 |
| Versi Codex | 0.163.0 |
| Biaya | nol rupiah (langganan yang sudah ada) |

## 2. Angka yang kamu usulkan sudah ada di hasilnya — jadi itu bukan sumber slopnya

Kamu memberi ukuran yang harusnya dipakai desainer profesional: wadah 1140–1200px, jarak antar bagian 72–104px, paragraf melebar dibatasi, kelipatan 8px. Saya ukur berkas hasil Codex (kandidat 27, proyek 9) sebelum setuju menambah mesin apa pun:

| Usulanmu | Hasil nyata di kandidat 27 | Status |
| --- | --- | --- |
| Wadah 1140–1200px | 1160px | **sudah terpenuhi** |
| Jarak antar bagian 72–104px | sampai 96px (nilai adaptif) | **sudah terpenuhi** |
| Lebar paragraf dibatasi | 760px | **sudah terpenuhi** |
| Sudut konsisten | 4px di 4 tempat | **sudah terpenuhi** |
| Kisi 12 kolom | kisi pakai pembagian bebas, bukan 12 kolom | belum |
| Irama jarak kelipatan 8px | pakai nilai adaptif, bukan kelipatan | belum |

Kesimpulannya: membangun "mesin sistem desain" + "pemeriksa mutu" tidak akan mengubah kesan ngeslop, karena halaman yang sudah ngeslop itu **sudah lulus** angka-angkanya. Yang membuat sebuah halaman kelihatan buatan mesin adalah warna, huruf, dan susunan bagian — dan tiga hal itu tidak bisa ditangkap oleh pemeriksaan angka.

Lapisan "mesin render mockup" yang kamu usulkan juga sudah ada: setiap kandidat difoto jadi gambar ukuran 1440px utuh dari atas sampai bawah sebelum muncul di kartu. Tidak perlu mesin baru.

## 3. Yang saya usulkan: dua perubahan kecil, tanpa mesin baru

**Perubahan 1 — Codex melihat contoh, bukan cuma membaca larangan.**

Formulir permintaan klien sudah punya tempat menempelkan rujukan desain (gambar, berkas rancangan, atau alamat situs), dan datanya sudah tersimpan per proyek. Masalahnya: rujukan itu tidak pernah ikut dikirim ke Codex. Codex mengerjakan desain tanpa pernah melihat apa pun yang kamu sukai.

Diperbaiki dengan:

- menuliskan rujukan itu ke dalam teks tugas, dan
- menyodorkan gambarnya langsung ke Codex lewat opsi gambar yang sudah didukung Codex baris perintah (sudah saya verifikasi dari dokumentasi resmi versi 0.163.0).

Kalau jenis rujukannya alamat situs, diambil potret halamannya lebih dulu dengan alat potret yang sudah ada di aplikasi. Perkiraan: satu berkas tugas ditambah sekitar sepuluh baris di jalur Codex. Tidak ada tabel baru, tidak ada pekerjaan antrean baru, tidak ada mesin penilai.

**Perubahan 2 — angka desainmu menjadi teks aturan, bukan perangkat lunak.**

Kisi 12 kolom, irama jarak kelipatan 8px, jarak antar bagian 72–104px, satu alur enam langkah, dan arah warna cerah saya tulis sebagai tambahan di berkas aturan yang memang sudah dikirim utuh ke model. Perkiraan: nol baris kode, hanya teks.

## 4. Yang harus kamu putuskan lebih dulu (satu saling bentrok)

Aturan yang berlaku sekarang **sengaja menahan warna**. Butir K-1 membatasi warna merek hanya untuk tombol dan tautan, dan butir M-3 menargetkan pemakaiannya tidak lebih dari delapan kali dalam satu halaman. Aturan inilah yang mendorong hasil ke arah krem dan hijau pudar seperti yang kamu lihat.

Jadi "warna cerah dan tidak itu-itu saja" dan aturan K-1/M-3 tidak bisa dua-duanya menang. Pilih satu:

1. K-1 dan M-3 dilonggarkan untuk jalur Codex saja (jalur lama tetap ketat), atau
2. K-1 dan M-3 dibiarkan, dan yang diubah hanya rujukan serta arah huruf/susunan.

## 5. Sebelum dan sesudah

Perubahan 1 butuh contoh rujukan nyata supaya bisa dibuktikan. Rencana buktinya sederhana: satu proyek yang sama, satu kandidat tanpa rujukan dan satu kandidat dengan rujukan yang kamu tunjuk, lalu keduanya difoto dan kamu bandingkan sendiri. Tidak ada penilaian otomatis.

## 6. Yang tidak saya bangun

- mesin sistem desain,
- pemeriksa mutu tersendiri,
- penilai/ronde kritik (sudah kamu tolak),
- perangkat rendering baru.

## 7. Cadangan

Sebelum menyentuh berkas apa pun, berkas aturan dan berkas tugas Codex yang lama disalin ke `storage/app/arsip-rujukan-2026-11-02/`. Memulihkan = menyalin kembali dua berkas itu; tidak ada perubahan basis data yang perlu dibatalkan.

## 8. Revisi arah (2026-11-02 sore, usulan pemilik)

Pemilik mengusulkan arah yang lebih luas: **Codex yang mencari referensinya sendiri** dari isi permintaan klien, bukan kita yang menyiapkan enam potret. Alasannya masuk akal — kalau kita yang mencari, bahan yang bisa dipelajari Codex cuma seluas yang kita ambil.

Sebelum menyetujui, saya ukur tiga hal di mesin ini dengan Codex versi 0.163.0 (empat kali jalan, masing-masing sekitar 39-40 ribu token):

| Yang diuji | Hasil |
| --- | --- |
| Apakah Codex punya alat pencarian web sendiri | **Ya, jalan.** Dua item pencarian web benar-benar terkirim dan hasilnya membawa tautan nyata (Framer marketplace, dsb). |
| Apakah shell Codex boleh keluar internet | **Tidak.** `curl.exe` ke figma.com menghasilkan `HTTP=000` (percobaan 1), dan tetap `HTTP=000` setelah izin jaringan sandbox dibuka lewat konfigurasi (percobaan 2). |
| Apakah dia benar-benar melihat gambar hasil pencarian | **Setengah.** Saya tanyakan desain yang gambarnya sudah saya pegang di disk (Donut Daze). Dia menjawab "donat warna-warni glasir pink, judul bulat playful" yang cocok, tapi menyebut latar "krem muda" padahal aslinya biru muda, dan mengutip halaman Behance milik orang lain. Jadi dia melihat sesuatu, tapi bisa salah objek. |

Kesimpulan dari angka: idenya jalan, tapi tidak bisa berdiri sendiri. Codex bisa **mencari dan menuliskan alamat** rujukan, namun tidak bisa **mengunduh gambarnya** — internet untuk perintah shell dikunci oleh sandbox, dan membuka kuncinya tidak menolong.

Bentuk yang saya usulkan (tetap kecil, dan tetap ide pemilik yang jadi inti):

1. Di awal tugas, Codex diminta mencari 3 rujukan untuk industri klien dan menuliskan alamatnya ke berkas `rujukan.txt` di folder kerjanya.
2. Aplikasi (yang internetnya bebas) mengunduh tiga gambar dari alamat itu, lalu menyerahkannya kembali ke sesi Codex yang sama lewat `codex exec resume <id sesi> -i <gambar>`.
3. Baru setelah itu Codex menyusun rencana dan menulis halaman, dengan gambar rujukan benar-benar ada di depannya.
4. Kalau `rujukan.txt` kosong atau tiga-tiganya gagal diunduh, jalur lama dipakai: rujukan dari formulir permintaan, lalu enam potret yang sudah diunduh hari ini sebagai penanda selera pemilik.

Perkiraan sentuhan kode: satu langkah tambahan di jalur Codex dan satu pembantu kecil pengunduh gambar; tidak ada tabel baru, tidak ada antrean baru, tidak ada mesin penilai.

Yang belum diputuskan: (a) apakah enam potret hari ini tetap dipakai sebagai jangkar selera di semua proyek, atau hanya jadi cadangan; (b) batas waktu tambahan untuk langkah pencarian + unduh (satu kandidat hari ini 4 menit 42 detik).

## 9. Tambahan 2026-11-03: tiga butir, semuanya di teks

Pemilik meminta saran lain agar hasil Codex kelihatan modern dan buatan tangan. Sebelum menjawab, saya ukur berkas hasil Codex yang sudah ada (proyek 9, kandidat 27, 29, 30). Tiga butir di bawah lahir dari angka itu, bukan dari dugaan. **Belum ada satu berkas pun yang saya sunting untuk butir 9 ini** — semuanya menunggu persetujuan.

### 9.1 Butir 1 — huruf: tidak ada satu kandidat pun memakai huruf sungguhan

| Kandidat | Huruf judul yang dipakai di berkas | Jumlah `@font-face` / alamat huruf web |
| --- | --- | --- |
| 27 | `Georgia, serif` | 0 |
| 29 | `Arial, sans-serif` | 0 |
| 30 | `var(--font-judul)` (variabel, tidak pernah diisi) | 0 |

Ini temuan paling keras. Warna bisa bagus, tapi selama judulnya Georgia atau Arial, halaman tetap kelihatan dokumen kantor, bukan situs agency — dan itu tidak bisa diperbaiki oleh rujukan gambar apa pun.

Rantainya jelas, dan yang menahan bukan Codex:

- `resources/ai/aturan-mockup.md` baris 93-94 memang **menyuruh** huruf sistem: "pakai tumpukan huruf sistem (mis. `system-ui`, Georgia, Arial). Jangan memuat huruf dari alamat luar; meja periksa menolak alamat jaringan."
- Alasannya ada di berkas lain: pemeriksaan tema (`app/Services/WpThemeInspector.php:830`) hanya mengizinkan tiga alamat — `wa.me`, `www.w3.org`, `w3.org`. Alamat huruf luar apa pun membuat tema **gagal periksa**.

Jadi perlu keputusan pemilik dulu, karena ada dua jalan dengan harga berbeda:

**Jalan 1-A (nol kode, saya sarankan).** Tulis di berkas aturan satu menu 5-6 **pasangan huruf** yang boleh dipilih model, diambil dari huruf yang benar-benar terpasang di mesin ini — saya periksa daftarnya: Candara, Calibri, Cambria, Constantia, Corbel, Sitka, Georgia, Verdana, Impact, Bahnschrift, Segoe UI, Gabriola. Contoh pasangan: judul `Bahnschrift` (kondensat, seperti huruf poster) + isi `Calibri`; judul `Cambria` + isi `Corbel`; judul `Impact` + isi `Verdana`. Satu kalimat tambahan: "setiap kandidat wajib memakai pasangan yang berbeda dari kandidat lain di proyek yang sama."

- Foto kandidat dirender Chromium **di mesin ini**, jadi selama pasangannya diambil dari daftar di atas, hasilnya dijamin tampil seperti yang dimaksud.
- Batas jujur yang harus diketahui: di situs klien yang asli, huruf bawaan Windows (Candara, Calibri, Cambria) tidak terpasang di semua komputer pengunjung. Itu ditambal dengan menuliskan tumpukannya selalu dengan ujung yang sama-sama ada (`"Bahnschrift", "Impact", "Arial Narrow", sans-serif`), bukan dengan menambah kode.

**Jalan 1-B (± satu baris).** Menambahkan alamat huruf web ke daftar izin pemeriksa tema, supaya Google Fonts boleh dipakai. Harganya: situs klien jadi menarik satu berkas huruf dari luar setiap kali halaman dibuka — satu permintaan tambahan yang menahan tampilan. Itu bertentangan dengan pekerjaan percepatan yang sedang kita tunggu di `baliatvride.online` (baseline LCP 4,95 detik, 88 permintaan). Saya tidak menyarankan jalan ini sekarang; kalau setelah 1-A hasil foto tetap terasa datar, kita bahas lagi.

**Jalan 1-C.** Menempelkan berkas huruf ke dalam tema (unduh sekali saat build, simpan di folder tema). Ini solusi paling benar untuk performa, tapi bukan suntingan kecil — perlu jalur unduh, penyimpanan, dan pemeriksaan baru. Tidak saya usulkan sekarang.

### 9.2 Butir 2 — resep positif, bukan cuma daftar larangan

Angka satu proyek, tiga kandidat, warna yang paling sering dipakai:

| Kandidat | Palet nyata |
| --- | --- |
| 27 | `#f5f2e9` krem, `#17483f` hijau tua, `#c4b8a4`/`#a5b5a8` sage pudar, `#b84b29` terakota |
| 29 | `#f2f0e8` krem, `#173f35` hijau tua, `#b8c8a5` sage pudar |
| 30 | `#e9f2ec` krem kehijauan, `#18312d` hijau tua, `#b8d8c7` sage pudar |

Tiga kandidat, satu arah: krem + hijau pudar + hijau tua. Itulah persis yang kamu keluhkan. Yang perlu dicatat: aturan yang ada **sudah** melarang arah itu — `resources/ai/aturan-mockup.md` bagian 3 menyebut "krem + huruf serif + aksen terakota" sebagai wajah default yang dihindari, dan kandidat 27 jatuh persis ke situ. Artinya daftar larangan tidak cukup kuat; kalimatnya lunak ("sah untuk brief tertentu, tapi jangan jatuh ke situ cuma karena kebiasaan"), dan model membacanya sebagai saran.

Perbaikan yang saya usulkan, nol kode, dua langkah:

1. Menambah **resep** 5-6 kalimat positif di paling atas berkas aturan — yang sekarang hilang karena isi berkas nyaris semuanya bernada "jangan". Isinya perintah yang bisa dikerjakan, misalnya: judul 3-5 kata; satu kalimat besar yang memotong kolom; gambar hero menempel ke tepi layar, bukan di tengah; satu warna aksen dipakai ulang tepat tiga kali; satu bagian memakai latar warna penuh, bukan putih; jarak antara dua bagian Minimal satu kali lebih besar daripada jarak di dalamnya.
2. Membuat daftar tiga wajah default itu **mengikat** untuk jalur Codex: satu kandidat dilarang memakai dua dari tiga wajah itu sekaligus, dan krem + hijau pudar + putih tidak boleh jadi latar utama lebih dari satu kandidat dalam satu proyek.

Cek kontras lama tetap dipasang, supaya "warna cerah" tidak berubah jadi teks yang tidak terbaca.

### 9.3 Butir 3 — satu nama gaya per kandidat, bukan cuma "buat 3 kandidat"

Berkas tugas yang ada sudah membedakan kandidat, tapi cuma lewat kalimat: judulnya "kandidat N dari J", lalu bagian "Kandidat lain yang sudah ada — jangan mengulang arah berikut" yang berisi ide kandidat sebelumnya (`app/Services/MockupCodexBrief.php:30` dan `:37-44`). Hasil terukurnya ada di tabel 9.2: tiga kandidat yang "berbeda" ternyata tiga variasi palet yang sama.

Usulannya: tiap nomor kandidat dikunci ke **satu nama gaya** yang sudah punya contoh gambarnya — enam karakter yang potret-potretnya sudah saya unduh (Modern Corporate, Editorial Minimalist, Premium/Luxury, Bold & Colorful, Modern SaaS, Creative Agency). Satu kandidat = satu nama = satu palet + satu pasangan huruf + satu susunan yang berbeda, bukan sekadar geser tombol.

Ini juga menjawab salah satu pertanyaan terbuka di bagian 8 dengan sendirinya: kalau nomor kandidat dipetakan ke nama gaya, enam potret itu tidak lagi jadi "rujukan yang sama untuk semua", tapi jadi bahan yang dipakai satu kandidat sesuai namanya — sisanya tetap jadi cadangan.

Biaya: teks beberapa baris di berkas tugas. Tidak ada tabel baru, tidak ada antrean baru, tidak ada mesin penilai.

### 9.4 Ringkasan tiga butir

| Butir | Suntingan | Berkas yang kena | Perkiraan besar |
| --- | --- | --- | --- |
| 1 huruf | teks saja (1-A) | `resources/ai/aturan-mockup.md` | ± 8 baris teks |
| 2 resep | teks saja | `resources/ai/aturan-mockup.md` + bagian warna di `.docs/aturan-desain-sisi-klien.md` | ± 10 baris teks |
| 3 nama gaya | teks di berkas tugas | `app/Services/MockupCodexBrief.php` | ± 6 baris |

Yang tidak berubah: tidak ada mesin sistem desain, tidak ada penilai otomatis, tidak ada ronde kritik tambahan, tidak ada antrean baru, tidak ada perangkat rendering baru (tetap seperti yang kamu putuskan).

Urutan pengerjaan yang saya sarankan kalau kamu setujui: butir 2 dan 1-A lebih dulu (nol kode, bisa langsung dibuktikan di foto), baru butir 3, dan bagian 8 (rujukan dicari Codex sendiri) terakhir karena itu satu-satunya yang butuh kode sungguhan.

## 10. Hasil tes 2026-11-03 (butir 1-A, 2, dan 3 sudah dipasang)

Persetujuan pemilik: "boleh dicoba diterapkan, lalu coba tes, berikan aku ke-3 kandidatnya, lalu catat". Yang dipasang: butir 1 lewat jalan 1-A (menu huruf, nol alamat luar), butir 2 (resep + daftar wajah dibuat mengikat + pelonggaran K-1/M-3), butir 3 (nama gaya per kandidat).

Berkas yang disunting, semuanya diarsipkan lebih dulu ke `storage/app/arsip-rujukan-2026-11-03/` (empat berkas aturan/kode + tiga html hasil sebelum):

- `resources/ai/aturan-mockup.md`: blok Resep tujuh keputusan di paling atas, bagian 3 jadi larangan tegas, baris huruf tidak lagi menyuruh Arial/Georgia, bagian 6 menu pasangan huruf.
- `.docs/aturan-desain-sisi-klien.md`: K-1 dan M-3 dilonggarkan untuk jalur Codex; M-1/M-2 kontras tetap keras dan tetap ditegakkan kode.
- `app/Services/MockupCodexBrief.php`: enam gaya tetap, tiap gaya membawa palet + pasangan huruf + susunan; trio per proyek bergeser menurut id proyek.

Tes nyata: proyek 9 (Nirwara), tiga baris kandidat baru (id 31, 32, 33 untuk nomor 1, 2, 3). Baris lama 27/29/30 tidak disentuh dan tetap jadi pembanding. `php artisan mockup:codex 9`, 03:45:06 sampai 03:56:16 UTC.

| Yang diukur | Sebelum (27, 29, 30) | Sesudah (31, 32, 33) |
| --- | --- | --- |
| Huruf judul sungguhan | 0 dari 3 (Georgia, Arial, variabel kosong) | **3 dari 3** sesuai pasangan yang ditugaskan |
| Latar krem/hijau pudar | 3 dari 3 | **0 dari 3** |
| Palet sama antar kandidat | tiga variasi arah yang sama | tiga palet yang tidak tumpang tindih |
| Penanda gaya | tidak ada | tiap kandidat bawa nama gaya di berkas tugas |
| Lama | 4 menit 42 detik per kandidat | 11 menit 10 detik untuk tiga (rata-rata 3 menit 43 detik) |
| Token | 286.232 per kandidat | 438.620 + 756.492 + 487.165 = 1.682.277 |
| Biaya | nol rupiah | nol rupiah (langganan yang sama) |

Rinci hasil sesudah:

| Nomor | Gaya | Huruf | Warna yang dideklarasikan | Bagian |
| --- | --- | --- | --- | --- |
| 1 | Corporate modern | Candara + Segoe UI | `#123A5A` biru, `#F2B632` kuning, dasar `#F3F6F8` | 5 |
| 2 | Editorial minimalis | Sitka + Corbel | `#171918` arang, `#A5422A` bata, `#F1F3F0` | 5 |
| 3 | Premium | Constantia + Candara | `#14251F` hijau gelap, `#C6A66B` emas | 4 |

Yang jujur dicatat, belum beres:

1. Kandidat 2 paling dekat dengan selera lama (arang + bata di atas putih). Tidak melanggar huruf atau larangan, tapi secara visual paling kurang berani.
2. Kandidat 3 memakai `#F2EFE7` sebagai warna teks di atas latar gelap. Bukan latar krem, jadi tidak melanggar, tapi masih hangat.
3. `ide.txt` kandidat 1 dan 3 diawali byte BOM, jadi kalimat ide di kartu mulai dengan karakter asing. Ini bug lama yang sudah diketahui, belum diperbaiki, dan tidak berkaitan dengan perubahan hari ini.
4. Gambar rujukan belum dikirim ke Codex: opsi gambar pada jalur jalan belum dipasang, jadi tes hari ini murni efek teks. Bagian 8 tetap menunggu.
5. Penamaan gaya masih berputar menurut id proyek, belum dipilih menurut jenis usaha klien.

Suite: `DB_CONNECTION=sqlite php artisan test tests/Feature --filter "Codex|Mockup|Rules"` = 65 lolos, 339 assertions, sebelum tes dilepas.

## 11. Hasil tes 2026-11-03 ronde 2: tulisan di atas foto dan foto jangan kotak

Permintaan pemilik: tulisannya jangan dipisah dari gambar, dan kalau pun terpisah, fotonya jangan persegi terus — boleh bulat, setengah lingkaran, atau bentuk lengkung. Pilihan beliau untuk tulisannya: huruf HTML di atas foto, bukan huruf yang dibakar ke dalam piksel.

Yang dipasang, semuanya teks, nol suntingan logika:

- `resources/ai/aturan-mockup.md`: Resep bertambah satu butir (minimal satu foto dipotong bentuk lain) dan satu paragraf penutup yang mengizinkan judul ditumpuk di atas foto hero dengan syarat lapisan gelap solid dan kontras diukur seperti teks lain.
- `.docs/aturan-desain-sisi-klien.md`: K-12 dan K-13 ditulis ulang. Keterangan foto biasa tetap di bawah; judul di atas hero boleh; bentuk bulat, setengah lingkaran, lengkung, dan `clip-path` organik boleh, asal `aspect-ratio` + `object-fit: cover` tetap dipakai dan minimal satu foto per halaman memakai bentuk selain persegi.
- `app/Services/MockupCodexBrief.php`: dua baris perintah — satu cara memasang lapisan (`<h1>` di dalam `<figure class="hero-media">` + `::before` gelap) dan satu cara memotong bentuk foto.

Arsip: ronde ini tidak membuat salinan `LAMA` baru. Berkas `*.LAMA.md` dan `MockupCodexBrief.LAMA.php` di `storage/app/arsip-rujukan-2026-11-03/` masih berisi keadaan sebelum ronde 1, jadi memulihkan dari sana berarti menghapus butir 1, 2, 3 sekaligus perubahan ronde 2. HTML ronde 1 ada di `setelah1/`, HTML ronde 2 di `setelah2/`, potongan gambarnya di `ronde2/`, dan tiga berkas ukur (`elementor-survival.php`, `potong-preview.php`, `potong-sajian.php`, `rakit-sajian.php`) ikut di folder yang sama.

Tes nyata: proyek 9 (Nirwara), baris 34, 35, 36 untuk nomor 1, 2, 3. Gaya: Corporate modern, Editorial minimalis, Premium.

| Yang diukur | Hasil |
| --- | --- |
| Huruf dari menu bagian 6 | 3 dari 3 (Candara + Segoe UI / Sitka + Corbel / Constantia + Candara) |
| Latar krem | 0 dari 3 |
| Judul benar-benar di atas foto hero | 1 dari 3 dengan struktur yang diminta; 1 dari 3 lagi tumpang tindih secara visual tapi judulnya duduk di div sebelah; 1 dari 3 tidak memasang sama sekali |
| Foto dipotong bukan persegi | 3 dari 3, tapi semuanya jatuh di galeri; hero kandidat 34 dan 35 tetap persegi panjang |

Rinci:

- **34** — `<h1>` di dalam `<figure class="hero-media">`, lapisan `::before` `rgba(0,0,0,.56)`, urutan lapisan benar (`isolation:isolate` di wadah, foto `z-index:-2`, lapisan `-1`). Foto galeri dipotong bulat penuh.
- **35** — tidak ada overlay dan tidak ada lapisan gelap; foto ditaruh di bawah teks. Galeri bulat.
- **36** — hero foto penuh satu layar dengan judul putih di atas lapisan `rgba(5,17,24,.68)`; ini yang secara visual paling dekat dengan yang diminta. Galeri dipotong lengkung di atas latar gelap.

Yang rusak dan ketahuan gara-gara ronde ini:

1. **Foto kembar.** Kandidat 34 dan 35 punya `hero.png` dan `galeri-1.png` yang identik byte per byte (md5 sama). Hanya 36 yang membuat dua foto berbeda. Perintah "dua berkas berbeda, beda adegan dan beda cahaya" sudah ada di brief, jadi ini kegagalan model, bukan kekurangan teks.
2. **Judul keluar layar.** Kandidat 35 memakai `font-size:clamp(3rem,8.5vw,7.5rem)` bersama `white-space:nowrap`. Di lebar 1440 hurufnya 122px dan kalimat "Kerja berangkat dari kebutuhan" terpotong di tepi kanan. Ada penambal di layar kecil (`white-space:normal` di bawah 800px), tapi yang desktop lolos dari aturan.
3. **Penerjemah Elementor menolak setiap kandidat Codex.** Ini yang paling besar dan tidak berkaitan dengan perubahan hari ini. `ElementorPageMapper` hanya menerima marka yang ada di daftarnya dan `figcaption` tidak terdaftar, jadi build berhenti dengan error "Markah \<figcaption\> tidak dikenal penerjemah Elementor". Brief sejak Oktober menyuruh tiap foto dipasang sebagai `<figure>` + `<figcaption>`, sehingga sembilan kandidat yang ada (27, 29, 30, 31, 32, 33, 34, 35, 36) punya 2 sampai 4 figcaption dan semuanya akan ditolak. `WpThemeBuilderTest` tetap hijau karena HTML contoh di tes itu tidak memakai figcaption; lubang ini belum tertutup tes.

Uji sisa: apakah bentuk dan overlay ikut terbawa ke halaman Elementor

Metode: `index.html` kandidat 34 dibaca, CSS dari blok `<style>` dan isi `<main>` dipisahkan, lalu dilewatkan `ElementorPageMapper::compile()` dan `ElementorCssBridge::build()` apa adanya. figcaption dibuang hanya di berkas ukur supaya bagian lain sempat diperiksa — berkasnya `storage/app/arsip-rujukan-2026-11-03/elementor-survival.php`, tidak menyentuh basis data.

| Efek | Di HTML kandidat | Di keluaran pemetaan |
| --- | --- | --- |
| Foto bulat `.gallery-photo img{clip-path:ellipse(...)}` | ada | ada, dengan dua lingkup (halaman tampil dan kanvas Elementor) |
| Lapisan gelap `.hero-media::before{...rgba(0,0,0,.56)}` | ada | ada |
| Judul dan paragraf absolute di atas foto | ada | ada, 8 salinan berlingkup |
| Wadah penanda | `figure class="hero-media"` dan `figure class="gallery-photo"` | keduanya jadi kontainer Elementor dengan kelas asli, fotonya jadi widget gambar di dalam kontainer itu |

Kesimpulan sementara: bentuk dan overlay tidak hilang waktu halaman masuk Elementor, karena kelas penandanya ikut pindah dan aturan CSS-nya disalin ulang dengan bobot lebih tinggi. Yang belum terbukti adalah render sungguhan di situs uji — angka di atas berasal dari pemeriksaan berkas, bukan dari layar.

Biaya dan waktu ronde 2:

| Yang diukur | Nilai |
| --- | --- |
| Token masuk | 559.630 + 595.670 + 334.549 = 1.489.849 |
| Token keluar | 10.507 + 10.572 + 9.391 = 30.470 |
| Biaya | nol rupiah (langganan yang sama) |
| Jam | tidak tercatat utuh; bukti yang ada: potret halaman selesai 11:17:29, 11:20:01, 11:22:52 (jarak 2 menit 32 detik dan 2 menit 51 detik) |

Yang jujur dicatat:

1. Perintah overlay baru patuh 1 dari 3 secara struktur. Teks saja belum cukup; kemungkinan besar baru meyakinkan kalau Codex melihat contoh gambar yang memakai teknik itu.
2. Tidak ada satu pun yang memotong foto hero — bentuk non-persegi semuanya mendarat di galeri. Aturan hari ini bilang "minimal satu foto", dan model menafsirkan itu satu foto saja.
3. Pemeriksaan Elementor di atas berjalan di memori, belum dipasang ke `wp-exito-test`, jadi belum ada bukti visual di situs.
4. figcaption harus dibuang supaya pemeriksaan bisa jalan. Itu sendiri bukti bahwa blocker nomor 3 di atas nyata, bukan teori.
5. Bug byte BOM di `ide.txt` masih ada, tidak tersentuh.

Suite sesudah perubahan teks: `DB_CONNECTION=sqlite php artisan test tests/Feature --filter "Codex|Mockup|Rules|Elementor"` = 67 lolos, 349 assertions.

Yang menunggu keputusan pemilik:

1. figcaption: tambahkan satu marka ke daftar penerjemah (keterangan foto jadi widget teks, satu baris) atau ubah brief supaya keterangan memakai `<p class="caption">` dan bukan `<figcaption>`.
2. Foto kembar: suruh jalur periksa membandingkan md5 kedua foto dan mengulang kalau sama, atau terima dan ganti manual saat pilih kandidat.
3. Enam potret rujukan dikirim ke Codex lewat opsi gambar, supaya bentuk dan overlay ditiru dari contoh sungguhan, bukan dari kalimat.

## 12. Hasil tes 2026-11-03 ronde 3: empat kalimat tata letak

Pemilik melihat kandidat 3 ronde 2 dan bilang yang ketiga lumayan tapi tata letak bagian-bagiannya masih berantakan. Diagnosa diukur, bukan ditebak: lima penyebab, semuanya hal yang bisa ditulis sebagai kalimat.

Yang dipasang, semuanya teks, nol suntingan logika:

- `resources/ai/aturan-mockup.md`: Resep butir 9 sampai 12 (satu garis tepi kiri untuk seluruh halaman; satu baris judul minimal tiga kata; satu baris isi tidak boleh menyisakan lebih dari seperempat lebarnya kosong; tombol di bawah atau tepat di samping kalimat yang memintanya). Kalimat pengantar Resep berubah jadi "Wujudkan dua belas keputusan ini lebih dulu".
- `app/Services/MockupCodexBrief.php`: susunan gaya Premium ditulis ulang. Dulu "ruang kosong lebar antar bagian" dan "angka kecil sebagai penanda bagian"; sekarang "ruang antar bagian longgar tapi tidak kosong", foto besar yang sisi kosongnya diisi teks pendamping, dan plafon eksplisit "angka penanda bagian maksimal 2,5 kali tinggi teks isi".

Cara ukur ronde ini berbeda dan lebih jujur daripada ronde sebelumnya. Ronde 2 hanya membaca teks CSS. Ronde 3 menjalankan tiap berkas kandidat di Chromium sungguhan (server lokal `php -S 127.0.0.1:8099` di folder kerja, lebar 1440 px sama seperti potret halaman) lalu mengukur kotak tiap bagian: berapa nilai tepi kiri yang berbeda, kata per baris judul, persentase lebar kosong di ujung baris grid, jarak tombol ke kalimatnya, dan rasio angka penanda terhadap teks isi. Skripnya `ukur-browser.cjs` di folder arsip; `ukur-tata-letak.php` tetap ada untuk pembacaan CSS saja.

Tes nyata: proyek 9 (Nirwara), baris 37, 38, 39 untuk nomor 1, 2, 3. Gaya: Corporate modern, Editorial minimalis, Premium.

| Yang diukur di Chromium | Ronde 2 (34 · 35 · 36) | Ronde 3 (37 · 38 · 39) |
| --- | --- | --- |
| garis tepi kiri yang berbeda | 2 · 4 · 4 | 2 · 2 · 1 |
| bagian yang tepinya sama dengan bilah atas | 2/8 · 2/9 · 1/8 | 7/8 · 6/7 · 6/6 |
| judul dengan `white-space:nowrap` | 1 dari 3 | 0 dari 3 |
| baris judul yang isinya cuma satu kata | 10 dari 31 (32%) | 16 dari 35 (46%) |
| ukuran judul terbesar | 86 · 120 · 102 px | 78 · 130 · 98 px |
| angka penanda bagian lebih dari 2,5x teks isi | 2 kandidat (160 px dan 320 px) | 0 kandidat (terbesar 38 px = 2,2x) |
| baris isi menyisakan lebih dari 25% lebar kosong | 1 (26%) | 0 |
| tombol jauh dari kalimat yang memintanya | 1 (888 px) | 0 |
| foto dipotong bentuk selain persegi | 3 dari 3 | 3 dari 3 |
| judul duduk di atas foto | 1 dari 3 | 2 dari 3 |
| `hero.png` identik dengan `galeri-1.png` | 2 dari 3 | 1 dari 3 |
| jarak vertikal antar bagian terbesar | 112 · 158 · 173 px | 115 · 144 · 128 px |

Rinci per kandidat:

- **37, Corporate modern** (Candara + Segoe UI; biru #173A56, teal #08736D, dasar #F2F5F7). Tujuh dari delapan bagian mulai pada 72 px; yang meleset hanya lajur logo klien (744 px, memang terpusat). Tidak ada angka penanda. Foto galeri dipotong lingkaran penuh dan isinya beda dari hero. Cacatnya: judul masih di samping foto, pecah jadi empat baris, dan "terarah" jatuh sendirian; keterangan di bawah foto hero kontrasnya rendah.
- **38, Editorial minimalis** (Sitka + Corbel; tinta #172A32, terakota #A3472F). Enam dari tujuh bagian pada 72 px; bagian "Ketelitian pada Setiap Langkah" mulai 104 px. Judul putih di atas foto dengan `.hero-media:before` `rgba(0,0,0,.58)` rata dan `isolation:isolate` di wadah. Cacatnya: judul terbesar di ronde ini, 130 px, sampai "Kerja yang / Terasa" terbelah; tidak ada kelas pada `<h1>` maupun `<img>`.
- **39, Premium** (Constantia + Candara; hijau arang #102B2A, kuningan #C6A56A). Semua enam bagian, bilah atas sampai bilah bawah, mulai pada 86 px — satu-satunya yang genap. Angka "01" sekarang 38 px terhadap teks isi 17 px, 2,2 kali, di bawah plafon yang baru dipasang; ronde 2 kandidat ini memakai 317 px. Judul di atas foto dengan lapisan `rgba(8,25,24,.66)`. Cacatnya: `hero.png` dan `galeri-1.png` identik byte per byte, tombol "Kenali layanan" melebar seukuran kolom, keterangan foto di dasar hero nyaris tak terbaca.

Selamat di pemetaan Elementor (diperiksa di memori lewat `elementor-survival.php`, `figcaption` dibuang hanya di berkas ukur):

| Berkas | CSS kandidat | CSS jembatan | clip-path | lapisan gelap judul | kelas `<img>` | kelas judul |
| --- | --- | --- | --- | --- | --- | --- |
| 37 | 6.259 byte | 15.678 byte | 1, di `.gallery-photo` | tidak dipasang | kosong | `section-heading` |
| 38 | 3.741 byte | 8.341 byte | 1, di `.gallery-photo img` | `.hero-media:before` ikut tersalin | kosong | kosong |
| 39 | 3.664 byte | 8.573 byte | 1, di `.gallery-photo img` | `.hero-media:before` ikut tersalin | kosong | `section-title` |

Koreksi untuk catatan ronde 2: di sana saya menulis "`::before` tidak ikut tersalin". Itu salah baca saya sendiri — Codex menulis lapisan gelap dengan satu titik dua (`.hero-media:before`), dan penghitung saya waktu itu hanya mencari dua titik dua. Diperiksa ulang dengan pencarian `:before`: 38 dan 39 masing-masing dua salinan (cakap ganda `.siteflow-page` dan `.elementor-edit-area`), jadi lapisan gelapnya selamat. Yang tetap benar: kelas gambar kosong di ketiga kandidat karena Codex menaruh kelas di `<figure>`, bukan di `<img>`.

Biaya dan waktu ronde 3:

| Yang diukur | Nilai |
| --- | --- |
| Token masuk | 532.101 + 415.372 + 960.385 = 1.907.858 |
| Token keluar | 11.743 + 10.566 + 21.428 = 43.737 |
| Total | 1.951.595 token, nol rupiah (langganan yang sama) |
| Jam | baris dibuat 11:59:09, kandidat terakhir selesai 12:11:33 → 12 menit 24 detik untuk tiga halaman (37: 4 menit 27 detik, 38: +2 menit 42 detik, 39: +5 menit 15 detik) |

Suite sesudah sunting aturan: `DB_CONNECTION=sqlite php artisan test` penuh = 415 lolos, 1.639 penegasan, 112,53 detik.

Yang jujur dicatat:

1. Butir 10 (satu baris judul minimal tiga kata) tidak patuh, malah memburuk: baris berjudul satu kata naik dari 32% jadi 46% dari total baris judul. Codex tetap menulis judul 3-5 kata dengan huruf 78-130 px, jadi kata terakhir jatuh sendiri. Kalimat ini belum terbukti bisa diperbaiki lewat teks saja; perlu contoh gambar atau aturan yang memberi jalan keluar eksplisit (perpendek judulnya, bukan perkecil hurufnya).
2. Butir 9 bekerja tapi belum sempurna: 37 dan 38 masih punya satu bagian yang menyimpang, dan penyimpangan 37 (lajur logo terpusat) justru bukan cacat. Hanya 39 yang benar-benar satu garis.
3. Butir 11 diukur sebagai lebar baris yang tidak terisi. Ukuran itu tidak menangkap ruang kosong di dalam satu kolom (kolom lebar, teks pendek), jadi "0" di atas berarti tidak ada baris yang kosong lebih dari seperempat lebar di sisi kanan — bukan berarti tidak ada ruang kosong sama sekali.
4. Foto kembar belum hilang, tinggal 1 dari 3. Perintah "dua berkas berbeda" sudah ada di brief, jadi ini kegagalan model.
5. Tidak ada satu pun kandidat yang memotong foto hero; bentuk non-persegi masih semuanya mendarat di galeri, sama seperti ronde 2.
6. `figcaption` masih 2 per kandidat, jadi pembangunan tema sungguhan tetap berhenti di daftar marka. Pemeriksaan di atas berjalan karena marka itu saya buang di berkas ukur.
7. Semua pemeriksaan Elementor masih di memori, belum dipasang ke `wp-exito-test`; belum ada bukti visual di situs.
8. Bug byte BOM di `ide.txt` dan `foto-slot.txt` masih ada, tidak tersentuh.

Arsip ronde 3 di `storage/app/arsip-rujukan-2026-11-03/`: `aturan-mockup.RONDE3.md` dan `MockupCodexBrief.RONDE3.php` (keadaan sesudah empat kalimat dipasang), `setelah3/candidate-{37,38,39}-index.html`, potongan potret di `ronde3/kandidat-{37,38,39}-{hero,foto}.jpg`, plus alat ukur `ukur-browser.cjs`, `potong-sajian3.php`, `rakit-sajian3.php`. Berkas `*.LAMA.*` masih keadaan sebelum ronde 1 dan `*.RONDE2.*` sebelum empat kalimat ini, jadi memulihkan tinggal salin salah satunya kembali ke tempat asal.

Yang menunggu keputusan pemilik (tidak berubah dari ronde 2):

1. `figcaption`: tambahkan satu marka ke daftar penerjemah (keterangan foto jadi widget teks, satu baris) atau ubah brief supaya keterangan memakai `<p class="caption">`.
2. Foto kembar: bandingkan md5 kedua foto di jalur periksa dan ulang kalau sama, atau terima dan ganti manual saat pilih kandidat.
3. Enam potret rujukan dikirim ke Codex lewat opsi gambar, supaya bentuk, overlay, dan pemenggalan judul ditiru dari contoh sungguhan.

## 13. Riset rujukan internet + ukur resep bentuk foto (2026-11-03 siang)

Arah dulu: pemilik keberatan dua hal — foto jangan selalu kotak, dan hero jangan itu-itu
melulu. Ini masih diperbaiki lewat teks yang dibaca Codex, tidak ada mesin baru. Sebelum
menawarkan kalimat, saya (a) baca rujukan desain yang beredar, (b) ukur sendiri resep bentuk
foto di Chromium sungguhan, (c) cek resep mana yang lolos jembatan Elementor. Semua angka di
bawah hasil ukur, bukan tebakan.

### 13.1 Apa yang dikatakan rujukan luar

Dari artikel yang membandingkan-enam bentuk hero yang dipakai orang sungguhan: latar foto
layar penuh, dua kolom seimbang, minimalis ruang kosong, teks dominan, interaktif, dan
asimetris. Yang bisa dipakai halaman statis kita (tanpa skrip dan video): latar foto, dua
kolom, minimalis, asimetris — ditambah mosaik. "Teks dominan" dan "interaktif" tidak cocok
karena halaman ini harus tetap bisa diedit visual di Elementor.

Angka dari pemindaian 137 situs penghargaan dan situs perangkat lunak: kisi mosaik (bento)
dipakai 29% situs penghargaan, huruf judul 80 px ke atas 41% situs penghargaan tapi hanya
19% situs perangkat lunak, judul tanpa H1 21% vs 4%. Dari pustaka rujukan resmi alat desain:
jarak antar bagian di layar besar sampai 10 kali ukuran huruf dasar, kolom judul bisa 6,5
kali ukuran huruf isi.

Yang penting untuk kasus kita: lingkaran penuh di situs bagus memang ada, tapi pemakaiannya
untuk potret orang berukuran kecil (avatar, tim, testimoni) — bukan untuk foto pekerjaan.
Contoh yang pemilik kirim sendiri cocok dengan ini: satu templat blog memakai lingkaran untuk
thumbnail penulis, sedangkan foto produk besarnya memakai busur atas.

### 13.2 Ukur 12 resep bentuk di Chromium 1440 px

Alat: `storage/app/arsip-rujukan-2026-11-03/bentuk/uji-bentuk2.html`, potret hasilnya
`hasil-uji2.png`. Cara ukur: gulung tiap kotak ke tengah layar, lalu cek enam titik per
kotak (empat sudut, tengah atas, tengah) dengan `elementFromPoint` — titik yang "bocor"
berarti bentuknya tidak memotong di situ.

| Resep | Nilai CSS | Ukuran | Sudut terpotong | Kesimpulan |
| --- | --- | --- | --- | --- |
| Busur atas 3:4 | `border-radius:999px 999px 0 0` | 300x400 | dua atas | bersih, lengkungannya setengah lingkaran sungguhan |
| Busur atas 16:10 | sama | 300x188 | dua atas | tetap bersih (radius otomatis dikecilkan ke setengah lebar) |
| Busur sisi kanan | `border-radius:0 999px 999px 0` | 300x400 | dua kanan | bersih, tapi memakan hampir separuh lebar |
| Daun | `border-radius:100% 0 100% 0` | 300x400 | kiri atas + kanan bawah | bersih, dua lengkung berlawanan |
| Dua sudut lawan | `border-radius:150px 22px 150px 22px` | 300x400 | keempatnya | bersih, paling lunak |
| Satu sudut dipotong | `clip-path:polygon(0 0,88% 0,100% 12%,100% 100%,0 100%)` | 300x188 | kanan atas | bersih |
| Miring bawah | `clip-path:polygon(0 0,100% 0,100% 88%,0 100%)` | 300x188 | kanan bawah | bersih |
| Lingkaran 1:1 | `aspect-ratio:1/1` + `border-radius:9999px` | 300x300 | keempatnya | lingkaran sempurna — hanya sah kalau wadahnya 1:1 |
| **Elips gepeng** | `border-radius:50%` di wadah 3:4 | 300x400 | keempatnya | **cacat yang terjadi di ronde 2 dan 3** |
| Bentuk di wadah, tanpa `overflow:hidden` | wadah `border-radius:999px 999px 0 0`, gambar persegi di dalamnya | 300x400 | **nol** | bentuk tidak kelihatan sama sekali — gambar menutupi lengkungnya |
| Bentuk di wadah + `overflow:hidden` | sama + `overflow:hidden` | 300x400 | dua atas | bersih, hasil identik dengan memasang di gambar |
| Daun di wadah + `overflow:hidden` | sama | 300x400 | kiri atas + kanan bawah | bersih |

Dua temuan yang mengubah kalimat aturan:

1. Penyebab lingkaran gepeng: `border-radius: 50%` di wadah yang tingginya bukan sama dengan
   lebarnya. Persen dibaca per sumbu, jadi 50% lebar = 150 px tapi 50% tinggi = 200 px.
   Angka piksel besar (999px) aman karena dipotong otomatis ke setengah sisi terpendek.
2. Memasang bentuk di wadah saja tidak cukup — tanpa `overflow:hidden` foto perseginya
   menutupi lengkung dan hasilnya tetap kotak. Ini persis kegagalan yang membuat bentuk
   kadang hilang di tema.

### 13.3 Cek lolos jembatan Elementor

Halaman uji `bentuk/ukur-elementor/index.html` (hero busur + judul di atas foto + lapisan
gelap, galeri daun, galeri sudut dipotong) dilewatkan pemetaan dan jembatan CSS yang asli:

| Yang diukur | Nilai |
| --- | --- |
| Panjang CSS masuk → keluar | 927 → 3.232 byte |
| `border-radius` di hasil jembatan | 2 aturan (busur hero + daun galeri) |
| `clip-path` | 1 aturan |
| `aspect-ratio` / `object-fit` | 3 / 3 |
| Lapisan gelap `::before` | 2 salinan (scope situs + scope editor) |
| Wadah foto di pohon elemen | `container [hero-media]`, `container [gallery-photo]`, `container [sudut-potong]` — kelas bentuk ikut pindah |
| Kelas pada widget gambar | kosong (gambar Elementor tidak membawa kelas dari HTML asal) |

Artinya: bentuk yang dipasang pada **wadah** selamat, karena wadah menjadi elemen pembungkus
yang kelasnya ikut dipindahkan, dan aturan `.wadah img { object-fit: cover }` tetap cocok.
Bentuk yang dipasang langsung pada `<img>` tidak punya jaminan yang sama — kelas gambar
selalu kosong. Jadi resep yang aman untuk tema adalah: bentuk di wadah + `overflow:hidden` +
gambar persegi yang mengisi.

### 13.4 Kenapa dua keluhan pemilik terjadi

1. **Semua foto jadi lingkaran/gepeng.** Kalimat saya sendiri yang menyuruh, di tiga berkas:
   `resources/ai/aturan-mockup.md:19`, `.docs/aturan-desain-sisi-klien.md:60` (K-13), dan
   `app/Services/MockupCodexBrief.php:128`. Ketiganya menulis "bulat" paling dulu, dan Codex
   menurunya dengan `border-radius: 50%` di wadah 3:4 — keluaran yang diukur di 13.2 sebagai
   cacat. Jadi ini salah arahan saya, bukan sifat Codex.
2. **Bentuk selalu mendarat di galeri, tidak pernah di hero.** Kalimatnya berbunyi "minimal
   satu foto", dan itu tafsir paling murah: satu foto kecil di galeri sudah memenuhi. Tidak
   ada satu pun kalimat yang menyebut hero harus ikut dipotong.
3. **Hero itu-itu melulu.** Enam nama gaya hanya beda di warna/huruf/susunan umum; tidak ada
   satu pun yang mengikat susunan hero, jadi tiga kandidat dalam satu proyek berakhir dengan
   pola yang sama: teks kiri, foto kanan.

### 13.5 Kalimat yang diusulkan (belum dipasang, menunggu persetujuan)

Empat suntingan, semuanya teks:

**(1) Ganti butir 4 di `resources/ai/aturan-mockup.md` baris 19-21 menjadi:**

> 4. Foto hero wajib punya bentuk selain persegi, dan minimal satu foto lain ikut berubah
>    bentuk. Bentuknya dipasang pada wadah foto, wadah itu diberi `overflow: hidden`, dan
>    gambar di dalamnya tetap persegi mengisi dengan `object-fit: cover`. Lingkaran penuh
>    khusus potret kecil 1:1 (avatar, tim), jangan untuk foto pekerjaan.

**(2) Ganti K-13 di `.docs/aturan-desain-sisi-klien.md` baris 60 menjadi daftar nilai siap
pakai** (hasil ukur 13.2), plus satu larangan eksplisit:

> - Busur atas: `border-radius: 999px 999px 0 0` pada wadah tinggi 3:4 atau lebih lancip
> - Busur menyamping: `border-radius: 0 999px 999px 0`
> - Daun: `border-radius: 100% 0 100% 0`
> - Dua sudut melengkung: `border-radius: 150px 22px 150px 22px`
> - Satu sudut dipotong: `clip-path: polygon(0 0, 88% 0, 100% 12%, 100% 100%, 0 100%)`
> - Bidang miring: `clip-path: polygon(0 0, 100% 0, 100% 88%, 0 100%)`
>
> Dilarang: `border-radius: 50%` atau `clip-path: ellipse()` pada wadah yang lebarnya tidak
> sama dengan tingginya — hasilnya elips gepeng, bukan bentuk. Wajib `aspect-ratio` +
> `object-fit: cover` (M-13) supaya wajah dan bahan tidak terpotong.

**(3) Ganti baris 128 di `app/Services/MockupCodexBrief.php` menjadi satu perintah yang
menunjuk hero dan menyebut wadahnya:**

> '   Foto HERO jangan persegi. Pilih satu bentuk dari daftar aturan (busur atas, daun, dua
>    sudut melengkung, satu sudut dipotong, atau bidang miring), pasang di wadahnya
>    `<figure>`, lalu beri wadah itu `overflow: hidden` dan `aspect-ratio` — gambar di
>    dalamnya tetap persegi dengan `object-fit: cover`. Jangan pakai `border-radius: 50%`
>    atau `clip-path: ellipse()` pada wadah yang tidak sama lebar dan tingginya.'

**(4) Tambah satu bagian baru di brief: susunan hero per nomor kandidat**, diambil dari empat
bentuk yang terbukti dipakai di situs sungguhan dan tetap aman untuk halaman statis yang
diedit Elementor:

> - kandidat 1: "foto jadi latar" — satu foto menempel keempat tepi layar, lapisan gelap,
>   judul di kiri bawah
> - kandidat 2: "dua kolom busur" — judul dan kalimat di kiri, satu foto tinggi bentuk busur
>   menempel tepi kanan
> - kandidat 3: "tumpang tindih" — foto melebar, kartu berisi judul menindih seperempat sisi
>   bawah foto, teks pendamping di kolom samping
> - kandidat 4: "judul di pita warna" — lajur warna penuh berisi judul di atas, di bawahnya
>   foto menempel tepi bawah layar
> - kandidat 5: "mosaik" — tiga keping foto berbeda ukuran dan bentuk plus satu keping teks,
>   tidak ada dua keping yang sama besar
> - kandidat 6: "kolom sempit" — judul besar di kolom teks yang sempit, tanpa foto di layar
>   pertama; foto baru muncul di bagian kedua
>
> Pemilihannya mengikuti nomor kandidat, jadi tiga kandidat dalam satu proyek tidak pernah
> memakai susunan hero yang sama.

### 13.6 Yang jujur dicatat

1. Angka 13.2 dan 13.3 adalah ukur berkas uji buatan saya sendiri, bukan hasil Codex. Bentuk
   yang terbukti di sana baru terbukti bisa dirender; belum terbukti Codex mau menirunya.
2. Kalimat (1) dan (3) sengaja menyebut hero secara eksplisit. Selama kalimatnya "minimal satu
   foto", Codex punya bukti tiga ronde bahwa ia cukup memotong satu foto galeri.
3. Larangan `border-radius: 50%` belum diuji terhadap kepatuhan Codex — baru diuji terhadap
   hasil render. Ronde berikutnya yang membuktikan.
4. Busur menyamping memakan hampir separuh lebar pada wadah 3:4; kalau pemilik tidak suka
   foto terpotong banyak, bentuk itu sebaiknya dipakai pada wadah yang lebih sempit.
5. Rujukan luar tidak memberi angka untuk "rasa manusiawi"; yang bisa dikutip hanya pola
   yang benar-benar dipakai orang. Karena itu empat bentuk hero di butir (4) saya ambil dari
   daftar yang ada, bukan dari selera saya.
6. Belum ada satu karakter pun yang saya sunting di berkas aturan. Semua di atas menunggu
   pemilik bilang ya.

Alat dan bukti tersimpan di `storage/app/arsip-rujukan-2026-11-03/bentuk/`:
`uji-bentuk2.html` (12 resep), `hasil-uji2.png` (potret Chromium), `ukur-elementor/index.html`
(bahan cek jembatan), plus `foto-a.png` dan `foto-b.png` dari kandidat 38.

## 14. Bagian harus berisi apa yang namanya janjikan + temuan bahwa tema membunuh bentuk foto (2026-11-03 sore)

Permintaan pemilik: halaman bukan cuma tulisan rapi. Kalau ia membuat bagian produk, produknya
harus benar-benar tampil sebagai kartu; kalau bagian galeri, isinya foto; dan Codex harus sadar
setiap bagian punya tugas, bukan asal mengisi paragraf.

### 14.1 Ukur isi 32 bagian pada enam kandidat

Alat: `storage/app/arsip-rujukan-2026-11-03/ukur-isi-section.php` (hanya membaca `index.html`).
Satu bagian dihitung "punya kartu" kalau ada wadah berulang dengan kelas sama minimal dua kali
dan namanya mengarah ke kartu/layanan/produk.

| Kandidat | Jumlah bagian | Bagian berfoto | Bagian kartu tanpa foto | Bagian tulisan saja | Bagian menyebut harga | Slot foto tersedia |
| --- | --- | --- | --- | --- | --- | --- |
| 34 | 6 | 2 | 1 (3 kartu) | 3 | 0 | 2 |
| 35 | 7 | 1 | 1 (3 baris) | 5 | 0 | 2 |
| 36 | 6 | 2 | 1 (4 kartu) | 3 | 0 | 2 |
| 37 | 4 | 1 | 1 (3 kartu) | 2 | 0 | 2 |
| 38 | 5 | 1 | 0 | 4 | 0 | 2 |
| 39 | 4 | 1 | 0 | 3 | 0 | 2 |
| **Total** | **32** | **8 (25%)** | **4 (13%)** | **20 (63%)** | **0** | **2 per halaman** |

Dua hal yang terbaca dari angka itu:

1. Enam dari sepuluh bagian isinya cuma judul + satu paragraf 18-46 kata. Ini persis keluhan pemilik.
2. Bukan Codex malas: brief memang memerintahkan **tepat 2 foto** per kandidat
   (`MockupCodexBrief.php:21` dan baris 116). Bagian produk dengan tiga kartu tidak mungkin
   diberi foto karena tidak ada fotonya. Jadi ini batas kontrak, bukan sifat model.

### 14.2 Temuan yang lebih besar: tema asli membunuh bentuk foto dan lapisan gelap

Ini muncul saat mengecek apakah resep di bagian 13 benar-benar sampai ke WordPress. Pembangun
tema memasang CSS dasar foto (`WpThemeBuilder.php:1007`, dirakit di baris 781 **sesudah**
jembatan Elementor) yang memaksa satu rasio per slot. Isinya antara lain: wadah foto dipaksa
`border-radius:0` + `overflow:visible`, `::before`/`::after`-nya dimatikan, dan gambar dipaksa
`aspect-ratio` per slot + `border-radius:4px`.

Ukur dengan membandingkan dua keadaan pada markup yang sama (CSS jembatan asli + CSS dasar
diambil langsung dari kelas aslinya lewat cermin, bukan ditulis ulang):

| Bingkai | Hanya CSS jembatan (pratinjau mockup) | Sesudah CSS dasar tema (tema asli) |
| --- | --- | --- |
| hero busur `border-radius:999px` | radius `999px 999px 0 0`, `overflow:hidden`, lapisan gelap `rgba(0,0,0,.55)` hidup | radius `0px`, `overflow:visible`, lapisan gelap `content:none` — **bentuk dan lapisan hilang** |
| galeri daun `border-radius:100% 0 100% 0` | radius `100% 0`, hidden | radius `0px` — **hilang** |
| galeri `clip-path: polygon(...)` | clip hidup | **clip tetap hidup** (CSS dasar tidak menyentuh clip-path) |
| busur lewat `clip-path: polygon(...)` + lapisan `<span class="scrim">` | clip hidup, span `rgba(0,0,0,.55)` posisi absolut | **clip hidup, span hidup** |
| gambar di dalam bingkai | `aspect-ratio:auto` | dipaksa `4/3` (hero) atau `1/1` (galeri-1), radius `4px` |

Potret perbandingan berdampingan: `bentuk/timpa-baseline2.jpg`. Yang paling telak terlihat di
pojok kanan atas potret itu: judul "Meja jati yang dibuat dengan tangan" jadi huruf putih di
atas latar krem — lapisan gelapnya dicabut, teksnya tidak terbaca, dan aturan kontras M-1
jatuh. Artinya dua permintaan pemilik (foto berbentuk, judul di atas foto) sedang **dihapus
sengaja** oleh jalur bangun tema, jadi memperbaikinya lewat teks aturan saja tidak akan pernah
kelihatan di situs.

Yang belum saya punya angkanya: berapa lama pembuatan satu foto tambahan. Log kejadian Codex
tidak menyimpan stempel waktu, dan berkas kerja ditulis sekaligus di akhir, jadi perkiraan
biaya waktu untuk menambah slot foto baru bisa didapat dari ronde tes berikutnya.

### 14.3 Yang saya usulkan

Tiga kelompok, dan pemilik boleh ambil sebagian:

**(a) Teks saja — kontrak isi per bagian.** Ditambah sebagai butir 13-15 di `resources/ai/aturan-mockup.md` dan satu baris di brief:

> 13. Nama bagian adalah janji. Bagian produk/layanan/paket wajib menampilkan minimal tiga
>     entitas nyata sebagai kartu: nama yang berbeda, satu kalimat konkret, dan harga atau
>     durasi. Bagian galeri wajib berisi foto, bukan paragraf tentang foto. Bagian testimoni
>     wajib nama dan peran orang. Bagian statistik wajib angka.
> 14. Tidak ada bagian yang isinya hanya judul dan satu paragraf. Kalau tidak ada entitas
>     nyata untuk ditampilkan, gabungkan bagian itu dengan tetangganya.
> 15. Setiap kartu dalam satu baris harus berbeda isinya; tiga kartu dengan kalimat yang sama
>     dianggap belum diisi.

**(b) Teks saja — bentuk dan lapisan yang terbukti selamat hari ini.** Ganti isi bagian 13.5 butir
(2) dan (3): bentuk dipasang lewat `clip-path: polygon(...)`, dan lapisan gelap memakai elemen
`<span class="scrim"></span>` di dalam bingkai, bukan `::before`. Nilai busur siap pakai untuk
wadah 3:4:

> `clip-path: polygon(0% 37.5%, 1.7% 27.8%, 6.7% 18.8%, 14.6% 11%, 25% 5%, 37.1% 1.3%, 50% 0%, 62.9% 1.3%, 75% 5%, 85.4% 11%, 93.3% 18.8%, 98.3% 27.8%, 100% 37.5%, 100% 100%, 0% 100%)`

Ini membuat mockup dan tema tampil sama tanpa menyentuh kode, tapi bentuknya terbatas pada yang
bisa dibuat dengan poligon (busur, sudut potong, bidang miring) dan rasio foto tetap dipaksa
4:3 / 1:1 oleh CSS dasar.

**(c) Perlu persetujuan karena ini suntingan kode (tiga nilai, bukan mesin baru).**

1. `WpThemeBuilder::photoBaselineCss()` baris 1014 dan 1015: berhenti memaksa `border-radius:0`
   dan `overflow:visible` pada wadah, dan berhenti mematikan `::before`/`::after`. Dua aturan itu
   yang membuat bentuk dan lapisan hilang. Perlindungan tinggi piksel (`height:auto`,
   `max-height:none`) tetap dipertahankan.
2. `WpThemeBuilder::PHOTO_RATIOS` baris 34: rasio per slot disesuaikan supaya wadah 3:4 tidak
   dipaksa jadi 1:1.
3. `MockupCodexBrief::SLOT_FOTO` baris 21: dari 2 foto jadi 4 (`hero`, `galeri-1`, `produk-1`,
   `produk-2`) supaya bagian produk dan galeri benar-benar bisa berisi foto. Ini menambah waktu
   jalan per kandidat; besar tambahannya baru terukur di ronde berikutnya.

### 14.4 Yang jujur dicatat

1. Bagian 14.2 adalah temuan yang mengubah arah: selama ini saya mengukur "selamat di jembatan"
   dan menyimpulkan bentuk bisa dipakai. Ternyata langkah berikutnya (CSS dasar tema) menghapusnya.
   Kesimpulan di bagian 13 soal `::before` yang selamat perlu dibetulkan: selamat di jembatan,
   mati di tema.
2. Angka 14.1 berasal dari berkas HTML enam kandidat yang sudah ada, bukan dari render tema, jadi
   ia mengukur niat Codex — dan niat itu sudah buruk (63% bagian hanya tulisan).
3. Usul (b) terbukti di perender untuk bentuk dan lapisan; belum terbukti Codex mau menulis
   poligon sepanjang itu kalau hanya disuruh lewat kalimat. Kemungkinan besar ia butuh satu contoh
   nilai persis, seperti nilai di atas.
4. Usul (c) belum saya sentuh sama sekali. Tanpa (c), bentuk lengkung dan judul di atas foto tidak
   akan pernah muncul di `wp-exito-test` walau Codex menulisnya dengan benar.
5. Penambahan slot foto menyentuh pemulihan cache dan pemeriksaan foto; saya belum tahu biaya
   waktunya dan tidak akan berpura-pura tahu.

Bukti: `bentuk/uji-timpa.html`, `bentuk/timpa-baseline.jpg`, `bentuk/timpa-baseline2.jpg`,
`bentuk/ukur-elementor/jembatan.css`, `rakit-uji-timpa.php`, `ukur-isi-section.php`.




## 15. Ronde 4: enam kandidat diukur dengan penggaris skill desain (2026-11-03)

Ukuran banding diambil dari skill UI/UX Pro Max (domain `ux` dan `landing`): sasaran sentuh 44px
dan jarak 8px, baris teks 65-75 karakter, tingkat judul berurutan, `:hover`/`:focus` wajib ada,
kontras 4,5, dan tidak boleh meluber horizontal di ponsel. Angka sesungguhnya saya ukur sendiri
di berkas HTML kandidat 34-39 lewat perender Chromium pada 1440px dan 375px, bukan dari tebakan.

### 15.1 Angka per kandidat

| Kandidat | Teks isi | Kendali di bawah 44px | Kontras terendah | Baris terpanjang | Nilai jarak antar bagian | `cursor:pointer` | `:hover` | Lubur 375px |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 34 | 17px | 9 dari 12 (terkecil 23px) | **1,10** (2 gagal) | 101 karakter | 50 / 76 / 112 | 0 | 0 | tidak |
| 35 | 18px | 7 dari 9 (terkecil 19px) | 5,07 | 73 karakter | 64 / 112 / 115 / 158 | 0 | 1 | tidak |
| 36 | 18px | 7 dari 8 (terkecil 19px) | 7,05 | 79 karakter | 173 (satu nilai) | 0 | 2 | tidak |
| 37 | 16px | 11 dari 14 (terkecil 20px) | 5,68 | **205 karakter** | 115 (satu nilai) | 0 | 3 | tidak |
| 38 | 18px | 9 dari 12 (terkecil 24px) | **1,00** (2 gagal) | 87 karakter | 144 (satu nilai) | 0 | 0 | tidak |
| 39 | 17px | 9 dari 12 (terkecil 19px) | 4,53 | **196 karakter** | 128 (satu nilai) | 0 | 1 | **ya, 8px** |

Yang sudah benar dan jangan diubah: tingkat judul tidak pernah melompati level (1 lalu 2 lalu 3),
tiap foto punya teks alternatif, nol emoji sebagai hiasan, dan `viewport` selalu dipasang.

### 15.2 Lima temuan

1. **Nol `cursor:pointer` di keenam kandidat.** Kartu layanan dan tautan jadi bidang yang bisa
   diklik tanpa memberi tanda apa pun. Aturan M-8 sudah menuntut tautan yang sasaran, tapi tidak
   ada satu kalimat pun yang menuntut tanda kalau sesuatu bisa diklik.
2. **73-79 persen kendali lebih kecil dari 44 piksel.** Tautan navigasi tingginya 19-24 piksel di
   semua kandidat. Ambang 44px memang sudah tertulis (M-10) tapi berstatus lunak dan belum
   terukur, jadi model membacanya sebagai catatan, bukan perintah.
3. **Dua kandidat diukur putih di atas putih** (34 dan 38, kontras 1,10 dan 1,00). Sebabnya bukan
   salah pilih warna: keduanya menumpuk judul di atas foto dan memakai lapisan `::before`. Alat
   ukur hanya membaca warna wadah karena `::before` bukan unsur, dan CSS dasar tema menghapus
   `::before` (bagian 14.2). Dua alat berbeda menemukan satu sebab yang sama — lapisan yang bukan
   unsur sungguhan.
4. **Baris teks isi 196 dan 205 karakter** di 39 dan 37 (rujukan desain memagang 65-75). Keduanya
   huruf 12-13px yang melebar sampai 1280 piksel. Bukan model suka teks panjang; tidak ada kalimat
   yang memagang lebar kolom teks isi.
5. **Tiga dari enam kandidat memakai satu nilai jarak untuk seluruh halaman.** M-4 menuntut minimal
   tiga nilai dan berstatus keras, jadi ini tertolak di meja periksa — bukan lolos diam-diam.

### 15.3 Kalimat yang dipasang ronde ini

Semuanya murni teks aturan yang dibaca Codex. Tidak ada berkas kode yang berubah, dan Usul C
(bagian 14.3) tetap menunggu.

Di `resources/ai/aturan-mockup.md`, Resep ditambah empat butir (13-16):

- **13** menagih tinggi 44px, jarak 8px, `cursor:pointer`, dan satu perubahan yang terlihat saat
  kursor di atasnya untuk setiap yang bisa diklik.
- **14** memagang baris teks isi pada 65-75 karakter dan melarang obat "perkecil huruf": yang
  diperbaiki adalah lebar kolom.
- **15** menuntut tiga nilai jarak antar bagian yang dipakai ulang, bukan satu nilai untuk semua.
- **16** mengganti `::before` dengan satu unsur sungguhan (`<span class="lapisan">`) untuk lapisan
  gelap di bawah judul yang ditumpuk di atas foto, dengan alasan yang bisa diperiksa.

Butir 4 yang lama diubah: "bulat" dihapus dari daftar dan lingkaran penuh dibatasi hanya untuk foto
bujursangkar, karena bentuk inilah yang enam ronde berturut-turut menghasilkan ellips gepeng.
Pemotongan utama sekarang diarahkan ke `clip-path: polygon(...)` di wadah foto.

Di `.docs/aturan-desain-sisi-klien.md`, K-13 ditulis ulang dengan urutan yang sama supaya dua
berkas tidak pernah berbeda versi, dan K-12 ditambah satu kalimat: lapisan harus unsur sungguhan.

Di `app/Services/MockupCodexBrief.php`: langkah 2 ditambah kontrak isi bagian (bagian produk berisi
kartu bernama plus harga atau durasi; bagian galeri berisi foto; bagian yang hanya judul dan satu
paragraf digabung atau dibuang), dan kalimat `::before` di langkah 5 diganti ke unsur lapisan.

### 15.4 Yang jujur dicatat

1. Angka kontras 34 dan 38 adalah temuan pengukuran, tapi bukan berarti judulnya benar-benar
   tidak terbaca di berkas HTML — lapisan `::before` itu ada di render. Yang terbukti: lapisan itu
   tidak terbaca alat periksa DAN dihapus tema, jadi di situs klien ia sungguh-sungguh hilang.
2. Penggaris sasaran sentuh 44px datang dari panduan web seluler; halaman mockup ini dilihat orang
   di desktop lewat Elementor. Angka 44 saya pakai karena sudah tercantum di M-10, bukan karena
   saya ukur pengguna ponsel.
3. Empat butir baru ini belum terbukti mengubah hasil. Baru terbukti bahwa keenam kandidat
   sekarang melanggar keempatnya. Bukti perubahannya ada di ronde berikutnya.
4. Lubur horizontal 8 piksel di kandidat 39 tertangkap oleh pengukuran saya; aturan M-7 sudah
   melarangnya dan berstatus keras, jadi tidak perlu kalimat baru — hanya perlu dijalankan.
5. Tidak ada satu pun temuan 15 ini yang menyanggah bagian 14: isi bagian masih 20 dari 32 bagian
   hanya tulisan, dan itu masih menunggu Usul A dan C.

6. Empat kalimat baru ini terbukti masuk ke teks yang dibaca Codex (berkas aturan yang dimuat
   mesin bertambah jadi 17.337 byte). Yang BELUM saya cek: apakah gaya 44 piksel dan
   `cursor:pointer` ikut pindah saat halaman dipasang ke tema lewat penerjemah Elementor, karena
   penerjemah hanya membawa sebagian kelas CSS. Kalau nanti terbukti tertinggal, ini kasus
   terpisah, bukan alasan menolak kalimat aturannya.

Bukti: pengukuran perender pada `storage/app/mockup-work/9/candidate-*/index.html` (kandidat
34-39), `ukur-browser.cjs`, dan pencarian skill `ui-ux-pro-max` domain `ux` dan `landing`.

### 15.5 Cara memulihkan

Tiga berkas aturan disalin sebelum dan sesudah suntingan di
`storage/app/arsip-rujukan-2026-11-03/`:

- keadaan sesudah ronde 4 (yang sekarang aktif): `aturan-mockup.RONDE4.md`,
  `MockupCodexBrief.RONDE4.php`, `aturan-desain-sisi-klien.RONDE4.md`
- keadaan SEBELUM ronde 4: `aturan-mockup.RONDE3.md` dan `MockupCodexBrief.RONDE3.php`
  (keduanya sudah dicek berbeda hanya pada suntingan ronde 4 ini). Untuk
  `.docs/aturan-desain-sisi-klien.md` tidak ada salinan utuh sebelum ronde 4; baris yang
  saya ganti persis ini, tinggal tempel kembali ke selnya:
  - K-12: `Judul dan kalimat pendek boleh ditumpuk di atas foto hero, asal ada lapisan gelap
    (atau terang) yang solid di bawah teks dan kontrasnya lolos M-1. Keterangan foto biasa tetap
    ditulis di bawah foto. Teks di atas foto galeri atau foto detail tetap dilarang`
  - K-13: `Foto boleh dipotong bentuk: bulat, setengah lingkaran, lengkung atas (arch), atau
    bentuk organik lewat `clip-path`. Yang tetap dilarang: bentuk yang memotong wajah subjek
    sampai tidak dikenali, dan bingkai palsu bergaya tempel. Wajib tetap memakai
    `aspect-ratio` + `object-fit: cover` (M-13) supaya bentuknya tidak gepeng`

## 16. Ronde 5: tiga kandidat baru sesudah empat kalimat dipasang (2026-11-03)

Batch baru id 40/41/42 di proyek 9, nomor kandidat 1/2/3, jadi tiga gaya yang dipakai
sama persis dengan 37/38/39 (Corporate modern, Editorial minimalis, Premium). Satu-satunya
yang berbeda adalah teks aturan. Cara ukur: `ukur-skill-node.cjs` (puppeteer, 1440px dan 375px),
jalan di keenam kandidat supaya kedua sisi dibandingkan dengan alat yang sama.

### 16.1 Sebelum dan sesudah

| Yang diukur | Sebelum (37/38/39) | Sesudah (40/41/42) |
| --- | --- | --- |
| Kendali lebih kecil dari 44px | 29 dari 38 (76%) | 11 dari 31 (35%) |
| Aturan `cursor:pointer` ditulis sendiri oleh Codex | 0 | 1 aturan di 40, 1 di 41, 0 di 42 |
| Baris teks terpanjang (karakter) | 131 / 95 / 78 | 93 / 87 / 101 |
| Jumlah nilai jarak antar bagian | 2 / 1 / 1 | 5 / 2 / 1 |
| Bentuk foto yang dipakai | ellips di 3 dari 3, selalu di foto galeri 1:1 | poligon di 3 dari 3, dan jatuh di foto besar (hero 1,33 dan 2,05) |
| Lapisan gelap sebagai unsur sungguhan | 0 / 1 / 2 | 4 / 2 / 2 |
| Teks yang gagal kontras | 0 / 2 / 0 | 0 / 2 / 1 |
| Bagian yang berisi foto | 1 dari 4-5 bagian | 1-2 dari 5-6 bagian |
| Bagian layanan bernama + satu kalimat konkret | 37 ya, 38 tidak, 39 tidak | 41 ya, 42 ya, 40 bagian terasnya bukan layanan |
| Harga atau durasi yang tertulis | 0 | 0 (data proyek tidak punya angka, dan aturannya memang melarang mengarang) |

Waktu dan token: 11 menit 14 detik dan 1.161.841 token untuk tiga kandidat. Ronde 3:
12 menit 24 detik dan 1.951.595. Berkas brief yang dibaca model naik dari 20.276-20.678 byte di
ronde 3 jadi 22.964-23.365 byte di ronde 5 (+2,7 KB), jadi token tidak ikut naik.

### 16.2 Yang berubah dan yang tidak

1. **Sasaran sentuh 44px: terbukti.** 76 persen kendali kecil jadi 35 persen. Kandidat 40 dan 41
   tinggal satu kendali yang kurang (terkecil 21px dan 32px), kandidat 42 tidak patuh sama sekali
   (9 dari 11, terkecil 19px). Jadi kalimatnya bekerja pada dua dari tiga.
2. **Skala jarak: terbukti di satu kandidat.** 40 memakai 32/48/64/96/140 - persis tiga angka
   yang saya tulis sebagai contoh di aturan, jadi model meniru angka itu apa adanya. 41 dua nilai,
   42 masih satu nilai.
3. **Bentuk foto lewat poligon: terbukti, dan malah berpindah ke tempat yang benar.** Enam ronde
   sebelumnya potongan hanya mendarat di foto galeri 1:1 (itu sebabnya "lingkaran" keluar sebagai
   ellips). Sekarang tiga-tiganya poligon, dan dua di antaranya memotong foto besar.
4. **Lapisan sebagai unsur: terbukti.** 0/1/2 jadi 4/2/2. Ini yang membuat judul di atas foto
   akhirnya bisa dibaca alat ukur - dan bisa bertahan di tema.
5. **Panjang baris: baru separuh.** 131 turun ke 93 di kandidat yang sama, tapi 42 naik ke 101
   karakter (kolom 1.184 piksel). Pagaran "65-75 karakter" tidak cukup kuat; yang dibutuhkan
   angka lebar kolom dalam piksel, bukan jumlah karakter yang harus dihitung model.
6. **Isi bagian layanan: terbukti di 41 dan 42** (tiga layanan bernama, masing-masing satu
   kalimat konkret). Tanpa harga karena data proyek memang tidak punya angka.
7. **Yang belum: bagian tanpa foto masih mayoritas.** 40 punya dua bagian berfoto, 41 dan 42 satu.
   Ini mentok di kontrak dua slot foto, jadi tetap urusan Usul C.

### 16.3 Koreksi atas bagian 15

1. Butir 15.2 nomor 1 saya tulis terlalu jauh. Yang benar: **tidak ada kandidat yang MENULIS aturan
   `cursor:pointer`**, tapi tautan tetap tampil sebagai tangan karena peramban memberi itu sebagai
   bawaan untuk `<a href>` - diukur 12 dari 12 dan 14 dari 14 kendali sudah ber-kursor pointer tanpa
   aturan apa pun. Yang hilang sebenarnya umpan balik saat kursor di atasnya (aturan `:hover` 0-3 per
   halaman) dan tinggi kendali. Kalimat 13 tetap perlu, hanya alasannya saya betulkan.
2. Angka kontras di 15.1 memakai alat yang cacat: ia hanya menelusuri leluhur teks, jadi lapisan
   gelap berupa `<span>` di SEBELAH teks tidak terlihat. Setelah alatnya diperbaiki
   (`latarSebenarnya`, menggabungkan warna lapisan di atas latar aslinya), kontras kandidat 40 naik
   dari 1,09 jadi 5,28 dan gagal-nol. Angka 34 sampai 39 yang lama jadi tidak sebanding; tabel 16.1
   di atas memakai alat baru untuk keenamnya.
3. Tetap tidak sempurna: lapisan `::before` (kandidat 38) masih tidak terbaca alat, karena alat tidak
   membaca unsur semu. Itu bukan pembelaan - justru itu alasan butir 16: yang bukan unsur tidak bisa
   diperiksa DAN dihapus tema.

### 16.4 Yang jujur dicatat

1. Gagal kontras yang tersisa sekarang bukan judul di atas foto, tapi **tautan berwarna di atas
   panel berwarna**: 41 dua tautan di 3,82 dan 42 satu tautan di 2,00. Kalimat baru yang perlu ditulis: warna
   tautan dan warna latar bagian dipilih dari pasangan yang lolos M-1, bukan warna aksen telanjang.
2. Foto kembar masih ada 1 dari 3 (kandidat 41: `hero.png` dan `galeri-1.png` md5-nya sama,
   d2d0757b). Sudah tiga ronde berturut-turut; perintah "dua berkas berbeda" ada di brief.
3. Tiga kandidat ini belum lewat meja periksa tema, jadi "poligon dan lapisan bertahan di
   `wp-exito-test`" masih belum terbukti. Yang terbukti baru: jembatan Elementor TIDAK membuang
   aturan itu. Diukur di `ukur-jembatan-interaksi.php`: `min-height`, `padding`, `gap`, `max-width`,
   `:hover` semuanya berpindah (jumlah aturan bertahan atau bertambah karena tiap aturan ditulis
   ulang untuk dua lingkup), yang dibuang hanya aturan yang berakar `html`, `body`, `*`, `:root`,
   atau `main` - lihat konstanta di `ElementorCssBridge.php:40` dan saringannya di
   `ElementorCssBridge.php:321`. Artinya gaya 44px dan bentuk poligon aman selama ditulis pada kelas
   atau unsur, bukan pada `body *`. Diulang untuk tiga kandidat ronde 5 (`ukur-jembatan-interaksi.php
   candidate-40 41 42`): `clip-path` dan `polygon(` bertahan 3:3, 1:1, 2:2; `inset:0` 1:1 di
   ketiganya; `rgba(` 1:1, 2:2, 1:1; `min-height` 6:6, 6:6, 4:4; `:hover` malah bertambah
   (3 jadi 10, 2 jadi 6, 2 jadi 6) karena tiap aturan ditulis ulang untuk dua lingkup.
   Yang MASIH memblokir meja periksa tema bukan CSS: keenam kandidat (37-42) masing-masing memakai
   2 `<figcaption>`, dan `ElementorPageMapper.php:216` menolak marka itu sebelum halaman sempat
   dibangun. Jadi pernyataan "belum terbukti sampai tema" tetap berlaku, hanya karena alasan yang
   berbeda dari yang saya sangka semula.
4. Run-to-run tetap punya variasi; satu ronde belum bisa memisahkan "kalimatnya bekerja" dari
   "kebetulan model sedang patuh". Tapi arah perubahannya konsisten di empat sumbu berbeda.
5. Suite sesudah suntingan aturan: 415 lolos, 1.639 penegasan, 81,38 detik.

Arsip ronde 5: `ronde4/kandidat-{37..39,40..42}-{hero,bentuk}.jpg`, angka rinci di
`hasil-skill-37-38-39-40-41-42.json`, alat ukur `ukur-skill-node.cjs`, pemotret `potong-ronde4.cjs`,
pemeriksa isi bagian `isi-layanan.cjs`, pembanding jembatan `ukur-jembatan-interaksi.php`.
Baris basis data kandidat 40/41/42 berstatus `awaiting_review`; 37/38/39 tetap di tempatnya
(kandidat terbaru per nomor yang menjadi set aktif), jadi membatalkan ronde ini tinggal memanggil
kandidat 37/38/39 ulang dari layar.
