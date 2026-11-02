# Rancangan bangun ulang: mockup 3 kandidat + WP siap install

Tanggal: 2026-10-21 (malam, setelah penghapusan total).
Status: **draf untuk dicoret pemilik**. Nol suntingan kode sampai rancangan ini dicoret.
Pasangan: `.docs/catatan-web-builder-dihapus-2026-10-21.md` (apa yang dulu ada dan sudah tiup),
`.docs/aturan-desain-sisi-klien.md` (berkas aturan desain untuk GPT), `.agents/skills/antislop/` (penolak slop).

## 0. Kata pemilik yang mengikat (ditranskrip, bukan tafsiran)

1. Tim cuma mengisi **request klien**: jenis webnya apa, webnya main untuk apa, targetnya siapa,
   isi/fitur-fiturnya apa. Form ini sudah ada dan sudah lengkap (form Request sekarang).
2. **AI membaca request** yang ditulis tim, lalu membuat **proposal mockup** yang akan dikirim ke klien.
3. Mockup yang dibuat **harus 3 kandidat** untuk dipilih.
4. **BARU, tidak ada di sistem lama:** sebelum proposal dikirim ke klien, **sistem menampilkan mockup
   buatan AI ke tim dulu untuk ditinjau**. Tim yang memutuskan layak kirim atau tidak.
5. Setelah klien memilih, **tim di dashboard mengeklik mockup yang dipilih klien**.
6. Setelah diklik, AI melanjutkan membuat **WP siap install**: konten lengkap, nav sesuai permintaan
   klien, **semua CTA aktif** (mengarah ke nomor klien, email klien, toko klien, dan semacamnya).
7. Desain WP harus **mirip dengan desain mockup yang dipilih** (paritas), dan harus **terlihat bagus,
   ala web modern, bukan slop AI**.
8. Mutu desain dijaga dengan **aturan UI/UX terbaru untuk AI**, dicari dari beberapa platform dan
   komunitas. Hasil riset ada di bagian 7.

Pembagian kerja yang sudah dia pegang sejak 2026-10-21 malam dan tidak dibuka lagi:
**Laravel hanya meja kerja + tangan, GPT otaknya.** GPT yang menata letak, memutuskan butuh gambar apa,
mencarinya, memasangnya sendiri. Alasannya terukur: ketiga kandidat hari ini memakai tata letak yang
sama selama PHP yang menata; selama itu, tiga mockup tidak akan pernah terasa seperti tiga konsep.

## 1. Arsitektur: meja kerja (Laravel) + tangan (alat) + otak (GPT)

Laravel menyediakan empat hal, tidak lebih:

1. **Meja kerja per kandidat.** Satu folder di `storage/app/mockup-work/{project}/{kandidat}/`,
   satu catatan status di DB, satu potret PNG hasil render.
2. **Tangan berupa alat** yang bisa dipanggil GPT lewat function calling:
   - `cari_gambar(kata_kunci)` - pencarian gambar web lewat kunci Google Custom Search yang
     sudah ada di config (bagian 9c).
   - `unduh_gambar(url, slot)` - menyimpan berkas ke folder kerja, mengembalikan nama slot.
   - `tulis_berkas(nama, isi)` - hanya boleh menulis di dalam foldernya sendiri.
   - `lihat_potret()` - GPT melihat PNG render halamannya sendiri untuk kritik.
3. **Potret halaman** lewat Chromium tanpa-jendela, seperti sistem lama (kodenya di arsip,
   ditulis ulang bersih, bukan disalin).
4. **Pengemasan**: ZIP tema, unduhan, status.

GPT menjalankan **satu loop per kandidat** (satu job antrean per kandidat, jadi satu gagal tidak
menyeret dua lainnya), dengan batas ronde alat supaya loop tidak menggantung (jumlah ronde, bukan
plafon uang; angkanya disetel saat pengukuran pertama):

```
baca brief + berkas aturan
  -> tulis RENCANA DESAIN (palet 4-6 hex, pasangan huruf, konsep tata letak,
     SATU elemen tanda tangan, satu kalimat ide)              [ronde 1]
  -> kritik sendiri: "kalau briefnya proyek lain, apakah rencanaku akan sama?"
     kalau ya: revisi sebelum menulis satu baris markah        [ronde 2, metode frontend-design]
  -> tulis HTML+CSS per halaman dengan penanda foto/tautan     [ronde 3..n, pakai tulis_berkas]
  -> Laravel render + potret
  -> GPT lihat_potret, bandingkan dengan aturan, perbaiki      [maks 2 ronde kritik]
  -> selesai: Laravel menyimpan kalimat ide + PNG + folder
```

Tiga kandidat = tiga rencana desain yang **wajib berbeda tata letaknya**, bukan cuma beda warna dan
huruf. Ini kriteria terima yang paling dipegang pemilik, dan sekarang dipegang oleh struktur loop
itu sendiri (rencana per kandidat dipaksa jadi keputusan terpisah, dan ronde kritik menanyakan
keunikan tiap rencana).

## 2. Empat kontrak penahan (dari rancangan-jalur-b-terjaga, tiga lama + satu baru)

1. **Satu artefak, tiga render.** Yang disimpan di folder kerja adalah satu-satunya sumber untuk
   PNG (kartu di dashboard), halaman pratinjau klien, dan tema WP. Paritas tidak bergantung pada
   siapa pengarangnya, tapi pada satu artefak.
2. **Foto dan tautan masuk sebagai penanda**, bukan piksel: `__EXITO_IMAGE:slot` dan
   `__EXITO_PAGE_URL:tujuan`. Foto tetap bisa ditukar foto asli klien; tautan CTA diisi data
   kontak klien saat build.
3. **Warna dan huruf wajib variabel CSS di kepala berkas.** "Ganti warna" tetap empat nilai,
   bukan undian ulang.
4. **BARU - tema lahir dari kandidat yang sama.** Tema WP dibangun GPT dengan membaca artefak
   kandidat terpilih (bukan mendesain ulang dari nol), jadi "yang dipilih klien = yang diinstall"
   tidak butuh kepercayaan, ia dibangun.

## 3. Alur lengkap dan status

```
[Tim] isi form Request (sudah ada)
  -> [AI] 3 kandidat, masing-masing: rencana desain + HTML/CSS + PNG + kalimat ide
  -> [TIM] GERBANG TINJAU: 3 kartu di Workspace (PNG + kalimat ide + status)
       boleh: regenerasi satu kandidat (bukan tiga sekaligus), atau setujui semua
  -> [AI] PDF proposal: templat lama dipulihkan dari arsip (sampul, halaman per
       kandidat berisi PNG + kalimat ide, tabel paket & biaya) + KOTAK CENTANG
       pilihan yang belum ada di templat lama
  -> [TIM] mengunduh PDF dari dashboard, mengirimnya ke klien
  -> [Klien] mencentang/mencoret kandidat pilihannya di PDF, mengembalikan ke tim
  -> [TIM] mengeklik kandidat pilihan klien di dashboard  -> pilihan terkunci
  -> [AI] bangun tema WP dari kandidat terpilih (loop tulis_berkas)
       - konten semua halaman lengkap sesuai brief
       - nav = menu WordPress terdaftar sesuai nav kandidat
       - CTA penanda diganti tel: / mailto: / tautan toko (bagian 5)
  -> [Mesin] MEJA PERIKSA (bagian 6) -> ZIP siap unduh
```

Status kandidat: `draf -> menunggu-tinjau -> disetujui-tim -> terkirim -> dipilih | ditolak`.
Status build: `menunggu -> membangun -> meja-periksa -> siap-unduh | gagal-periksa`.

Catatan keamanan: dengan PDF, tidak ada permukaan publik sama sekali. Klien tidak pernah
menyentuh sistem; pilihan tetap dicatat oleh tim yang mengeklik. Persis kalimat pemilik.

## 4. Data baru (migrasi)

- `mockup_candidates`: project_id, nomor kandidat, kalimat_ide, folder, status, jalur PNG,
  penanda gagal/perbaikan, timestamp. Satu project punya 3+ baris (regenerasi menambah baris,
  lama tidak ditimpa).
- `client_proposals`: project_id, jalur berkas PDF, dikirim_pada, kandidat_terpilih_id,
  dipilih_pada.
- `wp_builds`: project_id, candidate_id, folder, jalur ZIP, status, hasil meja periksa (json),
  timestamp.
- Kolom `store_url` TIDAK jadi - "toko" cuma contoh pemilik; intinya semua CTA aktif dan bisa
  ditekan (bagian 9b).
- Form Request tidak berubah: jenis/tujuan/user story/target/fitur sudah lengkap hari ini.

## 5. CTA aktif: dari mana datanya

Kontak klien (nomor, email) sudah ada di data klien CRM. Saat build, penanda tautan diganti:

- `__EXITO_PAGE_URL:telepon` -> `tel:` nomor klien
- `__EXITO_PAGE_URL:email` -> `mailto:` email klien
- `__EXITO_PAGE_URL:whatsapp` -> tautan wa.me nomor klien (pola WhatsAppService yang sudah ada)

CTA lain di luar kontak (peta lokasi, katalog, dan sejenisnya) hanya lahir kalau datanya
benar-benar ada dari request klien; target yang tidak punya data tidak dirender sama sekali -
aturan K-7 (kontrol yang tidak ke mana-mana) tetap berlaku untuk hasil klien.

Semua nav terdaftar sebagai menu WordPress sungguhan dengan lokasi menu di header/footer, mengikuti
pola installer lama yang sudah pernah diuji install di Laragon (menu + pustaka media + lampiran).

## 6. Meja periksa sebelum "siap unduh" (usulan saya, bukan kata pemilik)

Setiap ZIP melewati pemeriksaan mesin dari artefak yang sama dengan yang dilihat tim dan klien:

- `php -l` semua templat; berkas wajib tema ada dan kepala `style.css` sah.
- Nol penanda `__EXITO_` tersisa di keluaran.
- Ada lokasi menu terdaftar dan terisi.
- Kontras pasangan warna variabel CSS diukur (ambang M-1 di berkas aturan; pemilih warna teks
  di atas warna merek pakai "rasio tertinggi", M-2, yang sekaligus memperbaiki cacat tombol
  2,99:1 dari berkas aturan).
- Tanpa skrip luar, tanpa iframe, tanpa alamat jaringan di tema (K-9).
- Ukuran berkas di bawah ambang M-11.

Gagal = status `gagal-periksa` dengan daftar pelanggarannya, ulang sekali otomatis, kalau masih
gagal tim melihat daftarnya. Ini "rules yang bisa membuktikan dirinya" - kalimat pemilik
"kita pake rules agar dia membuat desain yang bagus" butuh pembaca hasil; meja periksa itu pembacanya.

## 7. Berkas aturan untuk AI (jawaban atas riset platform & komunitas)

Satu berkas aturan dikirim ke GPT di ronde 1, ringkas, dengan id (dua pembaca: prompt desainer dan
meja periksa - prinsip yang sudah disepakati). Isinya empat lapis, tiga di antaranya sudah ada:

1. **Aturan repo** - bagian 2-4 `.docs/aturan-desain-sisi-klien.md`: larangan K-1..K-10, terukur
   mesin M-1..M-12 (yang benar-benar menahan mutu), positif P-1..P-6. Sudah ditulis persis untuk
   dikonsumsi GPT, angka dasarnya dari build nyata.
2. **Metode dua ronde** (skill resmi **frontend-design** milik Anthropic - ditemukan di riset,
   dan isinya sejalan dengan arah pemilik): rencana token dulu (palet, huruf, tata letak, SATU
   elemen tanda tangan), kritik "apakah ini jawaban generik untuk brief apa pun", baru menulis
   kode; keberanian dibelanjakan di satu tempat; lantai mutu wajib (responsif, fokus keyboard).
3. **Tiga wajah default yang dihindari** (dari skill yang sama, belum ada di berkas aturan kita):
   krem + serif + aksen terakota; hampir-hitam + satu aksen asam; gaya koran garis tipis. Ketiganya
   sah untuk brief tertentu, tapi jangan jatuh ke situ cuma karena default.
4. **Daftar audit 16 pola slop** dari komunitas (tulisan "AI Design Slop: 16 Patterns", 2026):
   satu huruf di mana-mana, aksen ungu, gelap + kontras rendah, gradien, glow, hero di tengah,
   lencana di atas judul, bingkai kartu warna, ikon identik, langkah bernomor, banner statistik,
   label kapital, emoji navigasi, dst. Delapan di antaranya sudah tertangkap K/M kita; sisanya
   masuk daftar pemeriksa akhir ronde kritik.

Yang **tidak** masuk berkas aturan: palet/huruf/suasana per industri (tetap larang, sesuai bagian 5
berkas aturan - itu arah per proyek yang lahir dari brief klien di ronde 1, bukan tabel tebakan).

## 8. Tema WordPress yang dihasilkan

Format yang sudah diputuskan dari pembandingan BeTheme tetap: **satu ZIP tema, satu langkah
(aktifkan), tanpa jaringan, tanpa lisensi.** Isi ditulis ulang dari nol (kode lama di arsip sebagai
bacaan pola, bukan disalin): header/footer PHP dengan lokasi menu, front-page dari kandidat,
halaman-halaman isi, screenshot.png dari PNG kandidat, pustaka media diisi unduhan slot foto,
menu dibuat dan ditetapkan. Struktur detail (klasik vs blok) diputuskan saat ngoding dengan cek
Context7 ke standar tema WordPress terbaru; klasik lebih memaafkan keluaran AI dan polanya sudah
terbukti pernah ter-install, jadi itu titik awal saya.

## 9. Pertanyaan terbuka - SEMUA SUDAH DIPUTUSKAN pemilik (9a sore, 9b-9d susulannya)

Tinggal satu keputusan: **rancangan ini sendiri dicoret untuk mulai ngoding.**

a. **DIPUTUSKAN pemilik 2026-10-21 (sore, susulan rancangan): kirim lewat PDF, dan klien
   mencentang pilihannya di PDF.** Kalimat dia: "AI membuat proposal yang bisa di unduh oleh tim.
   lalu proposal itu di kirimkan oleh tim ke klient lalu klient mencentang atau mencoret salah
   satu mockup yang di pilih lalu di berikan kembali ke tim. proposal harus berformat .pdf."
   Templatnya memang pernah ada dan masih utuh di arsip penghapusan
   (`resources/views/pdf/proposal.blade.php` + `proposal-pages.blade.php` + `mockup-render.blade.php`):
   sampul dengan nama klien, halaman per kandidat berisi PNG + label, tabel paket & biaya,
   penutup agency. Mesin rendernya (paket dompdf) masih terpasang di repo dan dipakai invoice;
   gambar sampul dan logo masih ada di `public/images/`. Yang baru dibuat: **kotak centang
   pilihan** per kandidat (templat lama tidak punya, karena dulu pilihan dicatat lewat dashboard).
   Pembagian kerja PDF (usulan saya, terbuka untuk dikoreksi): teks naratifnya (ringkasan rencana
   web dari request) ditulis GPT; perakitan PDF-nya tetap milik Laravel dari templat yang sama -
   PNG kandidat ditempel persis seperti yang dilihat tim di gerbang tinjau, jadi "yang klien
   centang = yang tim lihat" tetap satu artefak, dan pembuatan PDF tidak bisa menggantung di
   satu loop AI. Tabel paket & biaya di templat lama adalah teks agency tetap (fallback
   "IDR 5.000.000" dan daftar fitur paket) - dipakai ulang apa adanya; kalau mau diubah itu
   keputusan terpisah.
b. **DIPUTUSKAN pemilik 2026-10-21: "toko" cuma perumpamaan; intinya CTA aktif semua dan bisa
   ditekan.** Tidak ada kolom toko baru. Sumber target CTA tetap seperti bagian 5: nomor, WA,
   dan email dari data klien CRM, plus halaman internal situs itu sendiri; target tanpa data
   tidak dirender sama sekali, jadi tidak pernah ada tautan mati.
c. **DIPUTUSKAN pemilik 2026-10-21: AI mencari/menyelam internet sendiri untuk mencari foto
   yang cocok dengan request klien.** Jalur konkretnya = alat `cari_gambar` di atas kunci
   Google Custom Search yang sudah ada di config (API itu mendukung pencarian khusus gambar;
   100 pencarian/hari gratis). Hasilnya diunduh Laravel ke folder kerja, bukan ditempel
   sebagai tautan, supaya ikut ke paket final. Catatan jujur yang tetap berlaku: hasilnya
   gambar web, bukan stok berlisensi - pemilik sudah menerima gambar pinjaman masuk paket
   karena bisa ditukar klien nanti di pustaka media ("kan nanti bisa di ganti"); slot tetap
   tertanda sumbernya. Cadangan kalau kunci itu dibatasi: Chromium tanpa-jendela memanen hasil
   pencarian biasa (lebih rapuh), atau API stok berlisensi (Pexels) dengan kewajiban kredit.
d. **DIPUTUSKAN pemilik 2026-10-21: otaknya lewat API OpenAI** ("modelnya memakai API model
   openchatgpt" - yang tersambung adalah API OpenAI, kuncinya sudah dipakai sistem ini sejak
   awal; tidak ada penyedia baru). Model spesifik mengikuti kunci config yang ada
   (`mockup_model`, saat ini gpt-5-mini); ganti model = satu baris setting, bukan kode. Mutu
   tata letak model mini belum pernah diukur pada bentuk agen ini - kalau hasil ronde pertama
   hambar, naik model lebih dulu daripada menambah aturan.

## 10. Urutan ngoding kalau rancangan ini dicoret

Setiap tahap: selesai -> `php artisan test` (sqlite, jangan pernah mysql) -> review CodeRabbit ->
cek Context7 untuk pustaka yang dipakai tahap itu -> baru lanjut.

| Tahap | Isi | Verifikasi |
| --- | --- | --- |
| 0 | Migrasi tiga tabel + kolom toko + model + status | migrasi naik-turun di sqlite |
| 1 | Folder kerja + mesin potret Chromium | PNG nyata dari halaman statis |
| 2 | Klien OpenAI + loop alat (function calling) | tes dengan OpenAI di-fake, pola tes lama |
| 3 | Ronde 1-3: rencana desain + markah per kandidat | 3 folder berisi artefak sah dari fixture |
| 4 | Ronde kritik (lihat_potret) | tes: kritik memicu perbaikan, maks 2 ronde |
| 5 | Gerbang tim di Workspace: 3 kartu + regenerasi + setujui | tes Feature + cek visual browser |
| 6 | PDF proposal: templat lama dipulihkan dari arsip + kotak centang + tombol unduh di dashboard | tes: berkas .pdf sah berisi 3 PNG kandidat + kotak centang |
| 7 | Klik pilihan + kunci build | tes: build sebelum pilihan = ditolak |
| 8 | Loop bangun tema dari kandidat | tes: penanda kontak terganti semua |
| 9 | Meja periksa + ZIP + unduh | tes tiap aturan periksa; install manual di WP Laragon |
| 10 | Suite penuh + satu run nyata berbayar diukur | angka nyata dicatat di sini |

Aturan ngoding pemilik yang mengikat sepanjang tahap: `use` selalu di kepala berkas, tidak pernah
di dalam fungsi; kode mudah dibaca; review CodeRabbit selalu; referensi terbaru via Context7.

## 11. Yang sengaja tidak diusulkan lagi

- Pencatat pemakaian dan plafon dolar per proposal - sudah dicabut pemilik dengan tegas
  ("cabut total", "ini nggak usah"); tidak dikembalikan kecuali diminta.
- Peniruan struktur tema ThemeForest - sudah ditutup: mutu dijaga aturan, bukan contoh luar.
- Slot/peran/batas foto dari PHP - bertentangan dengan arah agen; gambar jadi keputusan GPT,
  meja kerja hanya menyimpan hasil unduhannya.
