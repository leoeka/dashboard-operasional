# ATURAN-MOCKUP v1 (2026-10-22)

Satu berkas aturan untuk loop mockup, dibaca GPT di setiap ronde. Ada dua
pembaca: prompt desainer (ronde 1-3) dan meja periksa sebelum ZIP siap unduh.
Aturan larangan/terukur/positif (K, M, P) tidak ditulis ulang di sini —
disalin otomatis dari `.docs/aturan-desain-sisi-klien.md` bagian 2-4 saat
berkas ini dimuat, supaya tidak pernah ada dua versi.

## Resep (dibaca pertama, sebelum daftar larangan)

Daftar larangan membuat halaman aman. Daftar di bawah ini yang membuat
halaman kelihatan dirancang orang. Wujudkan enam belas keputusan ini lebih
dulu, baru sisanya:

1. Judul bagian utama 3-5 kata, satu baris, minimal 2,5 kali ukuran teks isi.
2. Satu kalimat pembuka yang melebar sampai hampir ke tepi wadah, tidak rata
   tengah.
3. Foto besar menempel ke tepi layar, bukan kotak kecil di tengah kolom.
4. Minimal satu foto dipotong bentuk lain lewat `clip-path: polygon(...)` yang dipasang di
   WADAH foto: lengkung atas (busur), satu atau dua sudut terpotong, atau satu sisi melengkung.
   Lingkaran penuh hanya boleh untuk foto berbanding 1:1; di foto melebar ia jadi ellips gepeng. Halaman yang semua fotonya persegi kelihatan seperti laporan.
5. Minimal satu bagian memakai latar warna penuh (bukan putih), dengan teks
   terang di atasnya.
6. Satu warna aksen dipakai ulang tepat tiga kali di tempat yang berjauhan
   (misalnya tombol, angka besar, satu garis penanda judul bagian).
7. Jarak antara dua bagian minimal dua kali jarak di dalam satu bagian.
8. Satu elemen tanda tangan yang berani (angka raksasa, potongan miring,
   garis penanda tinggi) dipakai satu kali saja, di satu tempat.
9. Satu garis tepi kiri untuk seluruh halaman: bilah atas, tiap bagian isi,
   dan bilah bawah memakai jarak samping yang sama. Dua bagian yang isinya
   mulai pada posisi horizontal berbeda membuat halaman kelihatan gesit.
10. Ukuran judul dibatasi lebar kolomnya: satu baris judul harus memuat
    minimal tiga kata. Kalau tidak muat, perkecil judulnya atau lebarkan
    kolomnya — jangan biarkan satu kata jatuh ke baris sendiri.
11. Satu baris isi tidak boleh menyisakan lebih dari seperempat lebarnya
    sebagai ruang kosong. Kalau satu sisi tetap kosong, isi dengan pasangan
    yang nyata: teks, daftar, angka, atau foto kedua.
12. Tombol berada di bawah atau tepat di samping kalimat yang memintanya,
    bukan di tepi berlawanan dari bagian yang sama.
13. Setiap yang bisa diklik — tombol, tautan, kartu — tingginya minimal 44
    piksel, berjarak minimal 8 piksel dari tetangga, diberi `cursor:pointer`,
    dan berubah yang terlihat saat kursor di atasnya (warna, garis, atau
    bergeser sedikit). Tautan navigasi teks pendek diberi tinggi dengan
    gembur ruang, bukan dibiarkan setinggi hurufnya.
14. Satu baris teks isi memuat 65-75 karakter. Kalau ada baris lebih panjang
    dari 90 karakter, sempitkan kolomnya — jangan perkecil hurufnya.
15. Jarak vertikal antar bagian diambil dari tiga nilai yang dipakai ulang
    (misalnya 64, 96, 140 piksel), bukan satu nilai yang sama untuk seluruh
    halaman dan bukan angka acak per bagian.
16. Lapisan gelap di bawah judul yang ditumpuk di atas foto adalah satu unsur
    sungguhan, misalnya `<span class="lapisan">` dengan latar `rgba(0,0,0,.55)`,
    bukan `::before`. Alasannya bisa diperiksa: yang bukan unsur tidak terbaca
    alat periksa kontras dan dihapus saat halaman dipasang ke tema, jadi
    judulnya jatuh jadi teks putih di atas latar terang.

Judul boleh ditumpuk di atas foto hero. Kalau ditumpuk, teksnya HTML biasa di
atas lapisan gelap solid, bukan huruf yang dibakar ke dalam gambar, dan
kontrasnya diukur seperti teks lain.

## 0. Kontrak artefak

- Satu artefak, tiga render: folder kerja kandidat adalah sumber tunggal
  untuk PNG kartu di dashboard, halaman pratinjau klien, dan tema WordPress.
- Foto masuk sebagai penanda, bukan piksel: `__EXITO_IMAGE:slot` (slot = nama
  yang dipakai saat unduh_gambar). Foto asli klien bisa menggantikan slot
  nanti tanpa mengubah markah. Penanda ditulis di dalam `src="..."` (atau
  `background-image: url(...)`), tidak pernah berdiri sendiri sebagai teks —
  potret kartu dan tema WordPress membaca tempatnya.
- Tautan CTA masuk sebagai penanda: `__EXITO_PAGE_URL:telepon`,
  `__EXITO_PAGE_URL:email`, `__EXITO_PAGE_URL:whatsapp`. Kontak diisi data
  klien saat build. Target yang tidak punya data tidak ditulis sama sekali.
- Warna dan huruf wajib variabel CSS di kepala berkas. "Ganti warna" berarti
  ubah nilai variabel, bukan undian ulang desain.

## 1. Bentuk keluaran ronde rencana (ronde 1 dan ronde 2)

Balas HANYA dengan JSON satu objek:

```json
{
  "kalimat_ide": "satu kalimat yang membuat mockup ini beda dari kandidat lain",
  "palet": ["#0f4c81", "#f5f1e8", "#ffffff", "#1b1b1b"],
  "pasangan_huruf": "nama huruf judul + nama huruf isi",
  "konsep_tata_letak": "satu paragraf: pola halaman yang dipakai",
  "elemen_tanda_tangan": "satu elemen berani yang jadi ciri kandidat ini"
}
```

- Palet 4-6 warna hex. Setiap pasangan warna teks di atas warna dasar atau
  warna merek wajib lolos rasio kontras 4,5:1 (M-1); pilih warna teks dengan
  rasio tertinggi (M-2).
- Kalimat ide adalah janji ke tim review dan ke klien — bukan deskripsi
  teknis.

## 2. Metode dua ronde

1. Ronde 1: rencana token dulu (bentuk JSON di bagian 1), sebelum menulis
   satu baris markah pun.
2. Ronde 2: kritik sendiri dengan satu pertanyaan — "kalau briefnya proyek
   website lain, apakah rencanaku akan kelihatan sama?" Kalau ya, rencanamu
   terlalu generik: revisi, dan balas dengan JSON versi akhir (skema sama).
   Keberanian desain dibelanjakan di satu tempat: elemen tanda tangan.
   Selebihnya lantai mutu wajib: responsif, fokus keyboard terlihat.

## 3. Tiga wajah default yang dilarang

Tiga arah ini bukan lagi "boleh kalau cocok". Untuk jalur Codex, tiga arah
ini dilarang, termasuk kombinasi warnanya. Kalau rencanamu jatuh ke salah
satunya, tulis ulang rencanamu sebelum menulis markah:

1. krem + huruf serif + aksen terakota,
2. hampir-hitam + satu aksen warna asam,
3. gaya koran: garis tipis, banyak tipografi, minim warna.

Latar utama tidak boleh krem (derajat `#f5f2e9`) dan tidak boleh seluruh
halaman putih-hijau pudar-hijau tua. Warna merek boleh (dan dituntut) jadi
latar bagian, warna judul, dan elemen dekorasi: lihat K-1 dan M-3. Yang
menahan warna berani hanya cek kontras M-1 dan M-2, dan itu tetap keras.

## 4. Daftar audit pola slop (dibaca saat ronde kritik)

Delapan pola berikut sudah tertahan aturan K/M dan meja periksa, tetap
dibaca ulang saat kritik: satu huruf/branding berlebih di mana-mana; aksen
ungu seragam; gelap dengan kontras rendah; gradien; efek glow; hero terpusat
bersymetri; lencana kecil di atas judul; bingkai kartu warna-warni.

Sisa daftar 16 pola dari artikel komunitas "AI Design Slop: 16 Patterns"
belum dirinci di sini — pengembang menandai ini sebagai pekerjaan berikutnya
saat merapikan daftar; jangan mengarang delapan sisanya.

## 5. Ronde 3 — menulis markah

- Satu berkas `index.html` di folder kerja; CSS di kepala berkas sebagai
  variabel.
- Setiap tautan nav internal (`__EXITO_PAGE_URL:<slug>` selain telepon,
  email, whatsapp) wajib punya berkas `pages/<slug>.html` — satu halaman
  penuh per berkas. Tautan tanpa berkas dibuang saat build; halaman isi
  tidak pernah ditautkan tanpa wujudnya.
- Halaman isi boleh membawa `<header>`/`<footer>` sendiri; keduanya dibuang
  saat build karena chrome global diambil dari `index.html`. Isi badan
  halaman yang dipakai.
- CSS halaman isi ditulis di dalam `<style>` di `<head>` berkas halaman itu;
  berkas `.css` terpisah tidak dibaca build, jadi halamannya akan tampil
  tanpa gaya.
- Navigasi halaman di dalam `<header>` ditulis sebagai daftar
  `<ul><li><a href="__EXITO_PAGE_URL:siapa">Label</a></li></ul>`; tautan
  kontak (whatsapp/telepon/email) ditulis di luar daftar itu. Tema mengganti
  daftar tersebut dengan menu WordPress sungguhan.
- Foto: `__EXITO_IMAGE:slot`. Tautan CTA: `__EXITO_PAGE_URL:telepon`,
  `__EXITO_PAGE_URL:email`, `__EXITO_PAGE_URL:whatsapp`.
- Huruf: pilih satu pasangan dari menu bagian 6, lalu tulis kedua nama
  keluarga itu di variabel kepala berkas. Judul tidak boleh `Arial`,
  `Georgia`, atau `system-ui`. Jangan memuat huruf dari alamat luar: meja
  periksa menolak alamat jaringan (K-9), jadi hanya huruf menu bagian 6 yang
  bisa tampil.
- Bungkus navigasi dengan `<header>...</header>` dan kaki halaman dengan
  `<footer>...</footer>`; pembangun tema memisahkan chrome dari isi lewat tag itu.
- Tanpa skrip (termasuk skrip data), formulir, iframe, atau alamat jaringan
  (K-9). Ukuran berkas di bawah ambang M-11.
- Tulis warna teks dan latar pada aturan yang sama (mis.
  `body { color: var(--teks); background: var(--dasar); }`) supaya kontras
  M-1 bisa diukur; meja periksa menolak tema yang pasangan warnanya tidak
  bisa diukur.
- Setelah semua berkas tertulis lewat alat `tulis_berkas`, balas dengan satu
  kata: `selesai`.

## 6. Menu pasangan huruf

Enam pasangan ini terpasang di mesin yang merender potret kandidat, jadi
dijamin tampil seperti yang kamu tulis. Pilih satu pasangan per kandidat, dan
kandidat dalam satu proyek wajib memakai pasangan yang berbeda.

| Gaya | Huruf judul | Huruf isi | Cara pakainya |
| --- | --- | --- | --- |
| Corporate modern | `Candara` | `Segoe UI` | judul berat biasa, isi rapi, tanpa kapital paksa |
| Editorial minimalis | `Sitka` | `Corbel` | judul besar 3-5 kata, isi pendek, banyak ruang kosong |
| Premium | `Constantia` | `Candara` | judul ukuran sedang dengan jarak huruf lebar, garis tipis |
| Berani & warna cerah | `Bahnschrift` | `Calibri` | judul kapital besar, boleh miring dengan `skew`, tempel tepi |
| SaaS modern | `Corbel` | `Calibri` | judul semi-besar rata kiri, isi kecil, sudut membulat |
| Studio kreatif | `Impact` | `Verdana` | satu kata raksasa sebagai penanda visual, isi kecil di bawahnya |

Tulis tumpukannya selalu dengan ujung yang ada di komputer pengunjung,
bukan berhenti di nama pertama:

- `"Candara", "Trebuchet MS", "Segoe UI", sans-serif`
- `"Segoe UI", "Helvetica Neue", Arial, sans-serif`
- `"Sitka", "Palatino Linotype", Georgia, serif`
- `"Corbel", "Lucida Grande", "Segoe UI", sans-serif`
- `"Constantia", "Times New Roman", serif`
- `"Bahnschrift", "Franklin Gothic Medium", "Arial Narrow", sans-serif`
- `"Calibri", "Carlito", "Segoe UI", sans-serif`
- `Impact, "Haettenschweiler", "Arial Narrow Bold", sans-serif`
- `Verdana, Geneva, "DejaVu Sans", sans-serif`

Catatan yang harus diikuti: `Impact` dan `Bahnschrift` hanya punya satu bobot,
jadi keduanya untuk judul saja dan jangan dipaksa tebal. `Gabriola` boleh
dipakai untuk satu kata saja, jangan untuk badan teks.
