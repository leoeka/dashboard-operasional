# Rancangan: memperbaiki desain website klien yang masih terasa AI slop

Tanggal: 2026-10-20
Status: **rancangan**. Belum satu baris kode pun saya sunting. Menunggu persetujuan per nomor.

## 0. Cara saya memeriksa

Saya tidak menilai dari ingatan. Yang saya buka:

| Bukti | Isi |
| --- | --- |
| `storage/app/public/mockups/CHE-9972-gpt-option-1.png` | supplier wood pellet, palet hijau |
| `storage/app/public/mockups/CHE-9972-gpt-option-2.png` | proyek sama, palet oranye |
| `storage/app/public/mockups/REVIEW-PELLET-20261001-gpt-option-1.png` | proyek sama, opsi ketiga |
| `storage/app/dummy-preview/installed-home-top.png`, `installed-home-bottom.png` | build terbaru jalur deterministik, nol panggilan AI |

Kesimpulan pertama penting: **semua tell yang saya temukan ada di kode Blade dan token PHP, bukan hasil GPT.** Tiga proyek berbeda (tour, pellet, tour lagi) dan empat palet berbeda menghasilkan halaman yang bentuknya sama. Karena sumbernya kode, sekali diperbaiki semua proyek ikut berubah, dan jaminan "yang disetujui = yang dikirim" (PNG = demo = ZIP) tetap utuh karena ketiganya dirender dari berkas yang sama.

Yang **tidak** saya hitung sebagai temuan: foto generate yang dulu bertulisan acak. Itu sudah dibereskan (`app/Services/MockupAssetService.php:967` melarang teks di dalam foto, `:824` peninjau menolak foto yang tetap bertulisan). Build terbaru bersih.

## 1. Akar masalah: arah, bukan larangan

`DESIGN.md` mengatur aplikasi admin SiteFlow (dashboard, invoice, CRM). Website yang dikirim ke klien **tidak punya dokumen arah sama sekali**. antislop itu filter, bukan pembuat rasa; tanpa arah, keluarannya cenderung monoton, dan itu persis yang terjadi.

Usulan: satu dokumen arah untuk sisi klien, isinya memisahkan dua hal:
- yang **boleh** beda per proyek: palet, keluarga huruf, komposisi tiap seksi (sudah ditangani blueprint),
- yang **tidak boleh** beda apa pun proyeknya: daftar larangan di bagian 3 bawah ini.

Dokumen itu saya tulis lebih dulu kalau kamu menyetujui nomor T-0.

## 2. Temuan, berurutan dari yang paling keras

Format tiap temuan: apa yang terlihat, kenapa terbaca AI, di mana kodenya, dan penggantinya.

### T-1. Satu warna aksen dipakai di ~19 tempat sekaligus (paling merusak)

Di semua proyek. Daftar lengkap dari `resources/views/mockup/styles.blade.php`:

| Baris | Elemen | Putusan |
| --- | --- | --- |
| 39 | garis bawah tautan nav aktif | **pertahankan** (menandai state nyata) |
| 40, 53 | tombol (nav, seksi) | **pertahankan** (aksi utama) |
| 55 | garis bawah `.btn--link` | ganti jadi ink |
| 61 | lingkaran aksen 620px di belakang hero, opacity .22 | **hapus** (orb dekoratif tanpa isi) |
| 112 | label kecil kapital di atas judul seksi | lihat T-2 |
| 118 | badge ikon bulat penuh aksen | ganti: latar band, glyph ink |
| 131 | angka `01 02 03` aksen | lihat T-3 |
| 145 | harga di kartu | ganti jadi ink tebal (angka adalah isi, bukan hiasan) |
| 161 | balok aksen geser di belakang foto editorial | **hapus** |
| 167 | penomoran `01..` daftar editorial | lihat T-3 |
| 180 | panel genap pada baris berganti jadi sekujur aksen | ganti jadi band |
| 181 | angka pada baris editorial | lihat T-3 |
| 199 | tanda kutip 84px aksen di atas testimoni | **hapus** glyph-nya |
| 201 | garis kiri 3px aksen di kutipan kecil | **hapus** (setrip kiri dekoratif) |
| 222 | kotak galeri kosong diwarnai aksen selang-seling | ganti band semua |
| 241 | tanda `+` buka/tutup FAQ | ganti ink (fungsional, tidak perlu berteriak) |
| 101, 246 | pita CTA sekujur aksen | lihat T-4 |

Kenapa ini tell AI: aksen berhenti jadi aksen begitu dipakai di mana-mana. Ini juga alasan kenapa ganti palet tidak mengubah rasa halaman. Aturan: antislop-ui "Excessive Accent Color", R-29, R-31.

**Pengganti konkret:** aksen hanya boleh hidup di **dua** tempat per halaman, tombol utama dan penanda state nyata. Sisanya jatuh ke `--ink`, `--muted`, `--line`, `--band`. Perubahannya murni CSS di satu berkas, jadi bisa dilihat langsung di PNG sebelum kamu putuskan sisanya.

### T-2. Label kapital berjarak lebar di atas setiap judul

`app/Support/MockupSite.php:241` menaruh **nama seksi** di atas **judul seksi**, lalu `styles.blade.php:112` menyalanya: kapital, `letter-spacing:2px`, warna aksen. Hasil di foto: "TENTANG WOOD PELLET" tepat di atas judul yang sudah bicara soal wood pellet. Muncul di 8 dari 12 jenis seksi.

Kenapa tell AI: label itu mengulang judul, jadi ia menambah satu baris baca tanpa menambah satu fakta. Ia mendarat di posisi yang sama di halaman buatan AI mana pun. Aturan: "Eyebrow Badge Above the Headline", R-06, R-09.

**Pengganti konkret:** penyaring di `MockupSite::eyebrow()` sekarang hanya membuang label yang **persis sama** dengan judul. Perketat: buang juga bila sebagian besar kata label sudah ada di judul. Gaya sisanya turunkan jadi kalimat biasa (tanpa kapital paksa, tanpa jarak huruf, warna muted), dan hanya untuk label yang benar-benar berisi informasi (misal nama paket di kartu harga). Tidak ada lagi baris di atas judul yang hanya mengulang judul.

### T-3. Angka `01 02 03 04` di setiap daftar

`resources/views/mockup/sections/features.blade.php:33` memberi nomor aksen berhuruf serif di setiap fitur, `styles.blade.php:167,181` mengulang pola yang sama di seksi editorial dan baris berganti. Setiap halaman punya barisan kotak bernomor dengan garis rambut di atasnya.

Kenapa tell AI: "langkah 1-2-3-4 dengan ikon bulat" adalah cetakan template, bukan bentuk asli proses klien. Fitur "Grup kecil" dan "Pemandu lokal" tidak berurutan; memberi mereka nomor 01/02 memaksa isi masuk ke bentuk template. Aturan: "'How It Works' Always 3 Steps", R-05.

**Pengganti konkret:** nomor hanya dirender bila seksinya benar-benar berurutan (proses pemesanan, langkah pembayaran). Untuk daftar fitur: judul + keterangan, garis pemisah tipis antar baris, tanpa angka, tanpa kotak. Butuh satu flag baru di rencana seksi (misal `ordered`), dan flag itu ikut masuk kosakata blueprint supaya AI boleh memakainya secara sadar, bukan kita tebak.

### T-4. Pita CTA sekujur warna merek di akhir setiap halaman

`CompositionSpec.php:113` memaksa `cta => accent`. Setiap halaman, semua industri, berakhir dengan blok warna penuh, judul serif besar di tengah, tombol putih. Supplier pelet kayu dan operator tur tampak sama di sini.

Kenapa tell AI: ini bagian template yang selalu ada, bukan keputusan dari isi. Aksen paling kuat di halaman dipakai untuk penutup yang sama di mana-mana. Aturan: R-05, R-31.

**Pengganti konkret:** pita CTA memakai latar halaman (`--secondary`) atau band, dan aksen tetap hanya pada tombol. Judul rata kiri mengikuti sisa halaman, bukan rata tengah. Bila blueprint memang menandai satu tujuan konversi tunggal, blok warna penuh boleh dipakai, tapi sebagai pilihan sadar dari kosakata, bukan bawaan.

### T-5. Kaki halaman gelap dengan judul kapital, warna tetap, untuk semua klien

`MockupDesignSpec.php:98-102` menulis `#1c1a17` sebagai warna latar kaki halaman, dan `styles.blade.php:334` membuat `h4` kapital berjarak. Jadi setiap situs klien ditutup dengan blok nyaris hitam yang sama, apa pun paletnya. Kolom "NAVIGASI" juga mengulang tautan yang sudah ada di kepala halaman.

Kenapa tell AI: warna yang tidak turun dari palet adalah warna default, bukan keputusan; dan kolom tautan yang ada karena template punya kolom itu. Aturan: R-05, R-21, "Sections That Fill a Template".

**Pengganti konkret:** warna kaki halaman diturunkan dari token `ink`/`primary` milik proyek itu sendiri, bukan konstanta. Judul kolom jadi kalimat biasa. Kolom Navigasi di kaki halaman dicabut karena menu sudah ada di kepala (dan sekarang benar-benar menu WordPress), sisa ruang untuk kontak dan satu baris hak cipta.

### T-6. Ritme seragam: semua seksi jaraknya sama, selang-seling band/putih

`MockupDesignSpec.php:76` mengunci jarak vertikal 60px untuk semua seksi, `CompositionSpec.php:108-114` memberi warna latar per jenis seksi sehingga halaman selalu berganti band-abu lalu putih. Kosakata jarak sebenarnya sudah punya empat tingkat (`CompositionSpec.php:165`), tapi tidak pernah dipakai berbeda.

Kenapa tell AI: satu nilai jarak di seluruh halaman menghapus ritme; seksi-seksi melebur jadi satu strip panjang. Aturan: "Uniform Spacing", "Uniform Section Rhythm", R-05.

**Pengganti konkret:** pemeta komposisi memilih tingkat jarak dari peran seksi (hero dan CTA longgar, daftar padat), dan aturan baru: dua seksi beruntun tidak boleh berbagi latar yang sama. Keduanya tetap deterministik, jadi PNG dan ZIP tetap sama.

### T-7. Semua industri jatuh ke wajah huruf yang sama

`styles.blade.php:26,35` memakai `Arial` dan `Georgia` sebagai cadangan, dan keluarga huruf datang dari blueprint. Pada praktiknya ketiga opsi CHE-9972 dan dummy Nusa Trails sama-sama "serif editorial besar + sans kecil", padahal satu jual tur, satu jual pelet kayu.

Kenapa tell AI: huruf dipilih karena tersedia, bukan karena cocok dengan karakter usaha. Aturan: "Generic AI Typography", R-06.

**Pengganti konkret:** cadangan huruf mengikuti kelas keluarga yang dipilih (serif ke serif, sans ke sans), dan kosakata blueprint dibatasi pada beberapa keluarga dengan karakter yang jelas berbeda, masing-masing dengan catatan untuk usaha seperti apa ia cocok. Ini perubahan kecil di `MockupSite::fontsUrl()` dan prompt, bukan perombakan.

### T-8. Susunan seksi hampir selalu sama

Pola yang berulang di semua bukti: hero, fitur, editorial, testimoni, statistik, galeri, FAQ, CTA. Untuk supplier pelet kayu, seksi "logos" bahkan dirender sebagai **teks nama perusahaan** (`sections/logos.blade.php:6`, `styles.blade.php:230`), jadi halaman mengklaim pelanggan yang tidak pernah ada.

Kenapa tell AI: seksi ada karena template punya, bukan karena isi membutuhkannya. Yang paling serius: baris nama "pelanggan" tanpa bukti melanggar R-18, R-36, C-5.

**Pengganti konkret:** renderer `logos` dicabut dari daftar yang boleh dipilih sampai ada unggahan logo nyata dari klien; seksi lain dibiarkan, tapi penulis konten diberi batas keras agar tidak menaruh semua jenis seksi di satu halaman (misal maksimal satu seksi testimoni dan satu statistik per halaman).

## 3. Yang tidak saya ubah

- Palet, komposisi tiap seksi, dan isi tulisan tetap milik blueprint per proyek. Yang saya sentuh hanya hal-hal yang **sama** di semua proyek, karena justru itu tell-nya.
- Jaminan satu sumber render: PNG persetujuan, demo langsung, dan ZIP WordPress tetap dari `styles.blade.php` + parsial `mockup/*` yang sama.
- Menu WordPress, importer, dan alur build tidak tersentuh.
- Aplikasi admin SiteFlow tidak tersentuh (punya `DESIGN.md` sendiri).

## 4. Yang saya butuhkan darimu

Jawab per nomor, boleh sebagian saja.

1. **T-0:** boleh saya tulis dokumen arah untuk sisi klien lebih dulu (nol kode), supaya temuan di atas punya patokan?
2. **T-1** paling besar hasilnya dan paling kecil risikonya (hanya CSS, satu berkas). Kerjakan dulu, lalu saya tunjukkan PNG sebelum dan sesudah?
3. **T-2 sampai T-5** mengubah bentuk halaman, jadi akan menyalakan ulang perbedaan dengan mockup lama yang sudah disetujui klien. Setuju kita pakai aturan baru hanya untuk proyek baru, dan proyek lama dibiarkan seperti aslinya?
4. **T-8:** cabut renderer "logos" sekarang juga? Ini satu-satunya temuan yang menampakan klaim palsu, bukan sekadar rasa.

## 5. Urutan kerja dan penjaga

Bila disetujui, urutannya: T-0 (dokumen) lalu T-1, T-2, T-3, T-4, T-5, T-6, T-7, T-8. Tiap nomor dikerjakan sendiri, tidak digabung, supaya kamu bisa menolak satu tanpa membatalkan yang lain.

Penjaga tiap langkah: `php artisan test tests/Unit/GutenbergBlockValidityTest.php tests/Feature/BlueprintParityTest.php`, lalu `--group=browser` untuk memastikan PNG dan halaman demo bergeser **bersama**, lalu build dummy ulang (`storage/app/dummy-preview`) untuk melihat hasilnya tanpa biaya AI. Angka tes lama tidak saya ubah kecuali perubahannya memang menuntutnya, dan itu saya sebutkan lebih dulu.
