# ATURAN-MOCKUP v1 (2026-10-22)

Satu berkas aturan untuk loop mockup, dibaca GPT di setiap ronde. Ada dua
pembaca: prompt desainer (ronde 1-3) dan meja periksa sebelum ZIP siap unduh.
Aturan larangan/terukur/positif (K, M, P) tidak ditulis ulang di sini —
disalin otomatis dari `.docs/aturan-desain-sisi-klien.md` bagian 2-4 saat
berkas ini dimuat, supaya tidak pernah ada dua versi.

## 0. Kontrak artefak

- Satu artefak, tiga render: folder kerja kandidat adalah sumber tunggal
  untuk PNG kartu di dashboard, halaman pratinjau klien, dan tema WordPress.
- Foto masuk sebagai penanda, bukan piksel: `__EXITO_IMAGE:slot` (slot = nama
  yang dipakai saat unduh_gambar). Foto asli klien bisa menggantikan slot
  nanti tanpa mengubah markah.
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

## 3. Tiga wajah default yang dihindari

Sah untuk brief tertentu, tapi jangan jatuh ke situ cuma karena kebiasaan:

1. krem + huruf serif + aksen terakota,
2. hampir-hitam + satu aksen warna asam,
3. gaya koran: garis tipis, banyak tipografi, minim warna.

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
  variabel. Subfolder boleh (mis. `pages/tentang.html`).
- Foto: `__EXITO_IMAGE:slot`. Tautan CTA: `__EXITO_PAGE_URL:telepon`,
  `__EXITO_PAGE_URL:email`, `__EXITO_PAGE_URL:whatsapp`.
- Tanpa skrip luar, tanpa iframe, tanpa alamat jaringan (K-9). Ukuran
  berkas di bawah ambang M-11.
- Setelah semua berkas tertulis lewat alat `tulis_berkas`, balas dengan satu
  kata: `selesai`.
