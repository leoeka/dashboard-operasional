# Rancangan: alur "gambar dulu, HTML kemudian" untuk Codex

Tanggal: 3 November 2026
Status: **rancangan — belum ada satu baris kode yang disunting**
Pemilik usul: pemilik agency (chat 3 Nov, sesudah melihat hasil ronde 5)
Yang dirancang: mengganti cara Codex bekerja, bukan menambah mesin baru.

---

## 1. Peristiwa pemicunya

Ronde 5 (kandidat 40-42) sudah memperbaiki angka-angka tata letak, tapi penilaian pemilik
atas hasilnya tetap: "desain yang dibuat masih jelek banget". Saya tidak membela diri. Saya
baca dua berkas yang pemilik kirim (paket situs "Copani Nismara" hasil ChatGPT + ekspor
percakapan yang menjelaskan cara ChatGPT membuatnya), lalu saya ukur paket itu dengan
penggaris yang sama persis dengan yang saya pakai pada kandidat Codex.

## 2. Cara ChatGPT bekerja (dari ekspor percakapan)

Enam pesan, tiga langkah nyata:

1. **Konsep dulu, tertulis.** Nama brand, industri, warna (navy/putih/abu), gaya, target.
2. **Satu gambar konsep utuh dibuat lebih dulu** dengan pembuat gambar AI. Gambar itu dipakai
   sebagai acuan posisi navbar, komposisi hero, ukuran judul, letak foto, tata letak tiap
   bagian, dan komposisi bilah bawah.
3. **Baru kode ditulis** (HTML + CSS + JavaScript) sambil mengikuti gambar itu, dan **sebagian
   gambar konsep dipotong jadi aset JPG** yang dipanggil dari halaman.

Pemilik menyimpulkan: urutan "visual → kode" itu yang membuat hasilnya enak dilihat, dan
dia usul kita pakai alur itu saja, tanpa aturan/larangan.

## 3. Yang saya ukur dari paket ChatGPT itu (bukan tebakan)

Saya render berkas `index.html` + `style.css` bawaan paket itu di Chromium, lebar 1440 px,
lalu saya ukur. Angka di kanan adalah kandidat Codex ronde 5 (40-42) dengan alat yang sama.

| yang diukur | paket ChatGPT | kandidat Codex ronde 5 |
|---|---|---|
| tinggi halaman | 1.853 px | 3.000-4.000-an px |
| unsur gambar sungguhan (`<img>`) | **1** (392×275) | 2 |
| luas halaman yang tertutup gambar | **4%** | 6-9% |
| gambar yang dipasang lewat latar CSS | 2 (hero + komunitas) | 0 |
| kendali yang bisa ditekan lebih kecil dari 44px | **18 dari 23 (78%)** | 11 dari 31 (35%) |
| ikon asli (vektor) | **0** — keempat ikon keunggulan adalah huruf aneh (♧ ▣ ◷ ◇) | 0 |
| huruf | DM Sans dari layanan huruf web (judul = isi) | huruf bawaan Windows, maksimal 2 keluarga |
| teks judul yang terbaca sebagai teks | **TIDAK** di hero — judulnya dibakar ke dalam gambar | ya |
| data kontak | **dibuat-buat** (nomor contoh, email contoh, alamat kota lain) | dari data proyek |

## 4. Temuan yang paling penting

**Gambar "armada" di paket itu bukan foto.** `fleet-hero.jpg` adalah potret sebuah bagian
hero: judul, satu paragraf, dan dua tombol ikut tercetak di dalam pikselnya. Halaman HTML
lalu menumpuk teks sungguhan di atas gambar itu, jadi judul "Reliable Transportation for
Your Journey" tampil **dua kali** — yang asli dan yang kabur di belakangnya — dan ada dua
hantu tombol di sisi kiri. Saya lihat sendiri di hasil render.

Itu sebabnya paket itu kelihatan bagus DAN tidak bisa dipakai untuk situs klien kita:

- Judul yang dibakar ke gambar tidak bisa diedit pemilik situs di Elementor, tidak terbaca
  mesin pencari, dan tidak bisa diperiksa kontrasnya oleh alat periksa kita.
- Latar-gambar (bukan unsur gambar) tidak bisa diterjemahkan menjadi widget gambar Elementor.
- 78% kendali di bawah 44px adalah angka yang lebih buruk daripada kandidat Codex ronde 3.

Jadi yang bikin bagus bukan "tanpa aturan". Yang bikin bagus adalah **model punya gambar
acuan sebelum menulis kode**, plus huruf web, plus gambar yang benar-benar padat isi.

## 5. Diagnosis "masih jelek" pada kandidat kita sendiri

Dari enam ronde yang sudah diukur, tiga penyebabnya struktural, bukan soal kalimat aturan:

1. **Halaman kita miskin gambar.** Kontrak kita cuma menyuruh Codex membuat **tepat 2 foto**
   untuk 5-6 bagian. Akibatnya 3-4 bagian tidak punya gambar sama sekali. Ini yang paling
   kelihatan: kandidat 40 punya 2 bagian berfoto dari 5, kandidat 41 dan 42 cuma 1.
2. **Isi tiap bagian tipis.** Rata-rata 123-456 karakter per bagian. Sedikit isi + jarak
   longgar = pita kosong lebar. Kandidat 41 malah cuma 1.853-3.000 px berisi, sisanya ruang
   kosong; bagian "Perhatian pada setiap proses" isinya satu angka "01" dan dua paragraf.
3. **Hurufnya huruf sistem.** Aturan kita membatasi ke keluarga huruf yang sudah ada di
   Windows (Candara, Sitka, Constantia, Segoe). Hasilnya rapi tapi "aman", tidak punya
   karakter. Paket ChatGPT memakai satu huruf web untuk judul dan isi — itu menyumbang
   sebagian besar rasa "mahal" yang pemilik lihat.

Yang TIDAK termasuk penyebab: geometri yang saya kejar enam ronde terakhir. Sasaran sentuh,
skala jarak, bentuk foto, dan lapisan gelap sudah bergerak dan sudah benar. Pemilik tidak
salah merasa hasilnya tetap jelek — saya terlalu lama membetulkan hal yang paling mudah
diukur, bukan hal yang paling kelihatan.

## 6. Arah yang saya usulkan

**Ubah URUTAN kerja Codex, dan ubah SUMBER gambarnya. Jangan tambah mesin baru.**

Satu kali jalan Codex per kandidat menjadi dua langkah, masih di folder kerja yang sama,
masih lewat langganan yang sama:

- **Langkah 1 — gambar konsep lebih dulu.** Codex membuat satu gambar utuh halaman yang
  direncanakannya (navbar sampai bilah bawah), sebagai acuan komposisi. Gambar konsep ini
  **boleh** berisi teks palsu: tujuannya cuma menunjukkan susunan, bukan dipakai di situs.
- **Langkah 2 — tulis HTML sambil melihat gambar itu.** Aturan kerjanya satu kalimat:
  "kode harus mengikuti gambar konsep yang baru kamu buat; kalau tidak muat, betulkan
  kodenya, bukan gambar."
- **Pemisahan aset (ini yang menyelamatkan kita dari cacat paket ChatGPT):** gambar konsep
  tidak pernah dipasang ke halaman. Yang dipasang tetap foto bersih hasil pembuatan gambar
  terpisah, dan aturan "tanpa teks, logo, atau tulisan di dalam gambar" tetap berlaku untuk
  slot foto.

Tiga perubahan pendamping, semuanya kecil dan sudah lama menggantung:

1. **Slot foto 2 → 4** (Usul C yang sudah saya catat, belum pernah Anda setujui). Tanpa ini,
   langkah 1 percuma: model akan membuat konsep yang padat gambar lalu tidak punya bahan
   untuk mewujudkannya.
2. **Izinkan satu huruf web** per halaman (satu berkas, judul + isi), bukan hanya huruf
   Windows. Ini perlu keputusan Anda karena situs klien harus tetap bisa memuatnya.
3. **Pangkas daftar aturan** jadi yang terbukti menggerakkan angka, buang yang tidak:
   - tetap: sasaran sentuh 44px; potongan foto lewat poligon pada WADAH; lapisan gelap sebagai
     unsur sungguhan; bagian harus berisi apa yang dijanjikan namanya.
   - buang atau tulis ulang: "satu baris judul minimal tiga kata" (malah memburuk, 32% → 46%);
     pagaran panjang baris dalam jumlah karakter (tidak mempan, ganti angka lebar kolom).
   - aturan yang melarang mengarang data (nomor, email, alamat) **tetap**, karena paket
     ChatGPT persis melanggar ini dan menulis "ganti sebelum tayang" di README-nya.

## 7. Yang harus tetap benar supaya situs klien masih bisa diedit visual

Ini bukan aturan gaya, ini syarat teknis supaya hasil langkah 2 masih bisa jadi tema:

- judul dan paragraf harus teks sungguhan di HTML, bukan bagian dari gambar;
- foto dipasang sebagai unsur gambar, bukan sebagai latar CSS;
- gaya ditulis pada kelas atau unsur, bukan pada `body` atau `*` — ini yang sudah saya ukur
  bertahan lewat jembatan Elementor;
- keterangan foto memakai marka yang diterima penerjemah (hari ini `<figcaption>` masih
  ditolak, dan keenam kandidat masih memakainya 2 kali — ini pekerjaan terpisah).

## 8. Biaya dan waktu (perkiraan, belum diukur)

Ronde 5: 11 m 14 d dan 1,16 juta token untuk tiga kandidat. Menambah satu gambar konsep per
kandidat dan satu langkah penulisan ulang kemungkinan menaikkan jadi kisaran 15-25 menit per
tiga kandidat. Saya tidak punya angka ini sampai satu ronde nyata dijalankan, jadi saya
tulis sebagai perkiraan, bukan hasil ukur.

## 9. Risiko yang saya lihat

1. **Model bisa "menjiplak" gambarnya sendiri** dan menaruh gambar konsep ke halaman (cacat
   paket ChatGPT). Mitigasi: alat periksa kita sudah bisa mengenali teks yang dibakar? Belum.
   Yang sudah ada: cek keutuhan PNG dan jalur berkas. Perlu satu pemeriksaan sederhana:
   gambar konsep disimpan dengan nama yang tidak boleh dipakai slot foto.
2. **Waktu per kandidat naik**, dan ronde percobaan jadi lebih mahal.
3. **Gambar konsep belum tentu konsisten** dengan gaya yang sudah ditentukan per nomor
   kandidat; bisa jadi model membuat konsep bagus lalu HTML-nya menyusut.
4. Satu ronde tidak membuktikan apa-apa; kita sudah melihat variasi antar-ronde.

## 10. Yang saya butuhkan dari Anda

Tiga keputusan, semuanya belum saya kerjakan:

1. Setuju alurnya jadi "gambar konsep dulu, baru HTML" (langkah 1 + 2 di atas)?
2. Slot foto dinaikkan dari 2 jadi 4?
3. Satu huruf web diizinkan di halaman hasil?

Kalau ketiganya "ya", saya mulai dari suntingan teks (brief + aturan) dan satu suntingan
kecil di bagian yang menentukan daftar slot foto — lalu jalankan tiga kandidat dan ukur
sebelum/sesudah seperti barusan. Kalau ada yang "tidak", saya tulis ulang rancangan ini
sebelum menyentuh apa pun.

## 11. Cara membatalkan

Sampai dokumen ini disimpan, tidak ada satu berkas kode, aturan, atau brief yang saya sunting
untuk arah baru ini. Semua yang masih terpasang adalah ronde 5, dan salinan teks sebelum
ronde 5 ada di `storage/app/arsip-rujukan-2026-11-03/` dengan nama berakhiran `.RONDE4`.
Berkas ukur ronde ini: `ukur-rujukan-chatgpt.cjs`, hasil render
`rujukan-chatgpt/render-desktop.png`, dan gambar konsep aslinya
`rujukan-chatgpt/konsep-ai.png` (semua dari paket milik Anda, bukan buatan saya).
