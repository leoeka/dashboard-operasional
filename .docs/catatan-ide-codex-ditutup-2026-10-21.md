# Ide Codex: sudah dicoba, terkendala autentikasi (ditutup 2026-10-21)

Catatan penutup. Permukaan Codex sudah dihapus dari aplikasi ini: menu "AI Coding",
panel "Hubungkan akun", halaman daftar tugas, tabelnya, konfigurasinya, dan dua
dokumen rancangannya. Generator mockup sudah kembali memakai jalur API yang lama.
Dokumen ini satu-satunya jejak yang ditinggalkan, supaya ide yang sama tidak
dibangun ulang dari nol kalau nanti dibuka lagi.

## Apa yang dicoba dan apa yang berhasil

Idenya: menyambungkan agen koding Codex ke dashboard supaya ada tempat bertanya
tentang isi kode, terpisah dari generator. Yang dibangun: daftar tugas baca-saja
(tanpa mode tulis), satu panel untuk masuk akun, dan satu tombol "Hubungkan akun".

Sisi login-nya sebenarnya **berhasil**. 21 Oktober pukul 03:50 permintaan login
ditekan dari dashboard, tautan dan kode sekali pakai muncul, pemilik membukanya di
peramban, dan prosesnya ditutup dengan baris `Successfully logged in` pada
04:04:37. Jadi akun ChatGPT di mesin ini nyambung. Yang macet bukan login-nya.

## Kenapa macet di autentikasi

Tiga tembok, dan semuanya di luar aplikasi:

1. **Aplikasi tidak bisa melihat perintah Codex.** Codex terpasang lewat npm di
   folder milik akun pengguna. Folder itu ada di PATH pengguna, tidak ada di PATH
   mesin. Apache — yang menjalankan halaman dashboard — warisan PATH mesin.
   Akibatnya halaman tetap bilang "Codex belum terpasang" padahal di terminal
   perintah yang sama jalan. Terukur: dijalankan dengan PATH mesin hasilnya gagal,
   dengan path penuh hasilnya sukses. Solusinya menyentuh PATH seisi mesin, dan
   itu bukan keputusan yang bisa diambil dari dalam aplikasi.
2. **Cek status login membaca tempat yang salah.** Codex menulis jawabannya
   ("sudah masuk pakai akun ChatGPT") ke saluran galat, bukan saluran keluaran
   biasa. Pemeriksaannya hanya membaca keluaran biasa, jadi dia selalu dapat
   string kosong dan menebak "belum terbukti masuk" walaupun faktanya sudah masuk.
   Ini bug kecil, tapi tipe bug yang membuat operator berhenti percaya layar.
3. **Butuh pekerja antrean yang hidup terus.** Tombol "Hubungkan akun" hanya
   memasukkan permintaan ke antrean; yang menjalankan proses login adalah pekerja
   antrean. Tanpa `php artisan queue:work` yang sedang jalan, layar berhenti di
   "Login sedang menunggu Anda" tanpa keluaran apa pun. Itu kejadian jam 03:48.
   Untuk fitur yang dipakai sesekali, meminta satu proses menyala terus adalah
   syarat yang tidak masuk akal.

Gabungannya: supaya fitur ini terasa benar, satu mesin harus diubah PATH-nya,
satu proses harus menyala terus, dan satu pemeriksaan harus ditulis ulang. Itu
bukan kerja semalam, dan hasilnya tetap rapuh kalau ada pembaruan npm.

## Keputusan pemilik

Karena penerapannya terlalu rumit, permukaan Codex dihapus seluruhnya dan
generator dipakai lewat API seperti sebelumnya. Tidak ada yang berubah pada hasil
generate: hanya jalurnya yang tetap sama seperti sebelum percobaan ini.

## Angka yang terukur sesudah bersih

- Generator (saringan tes terkait): **152 hijau dari 152**, 38 detik. Selama masih
  tersambung ke Codex, saringan yang sama 24 merah dan 9 menit 16 detik — karena
  tesnya benar-benar menjalankan Codex asli dan memakan langganan.
- Suite penuh: lihat angka terbaru di catatan proyek; tiga merah yang tersisa
  semuanya lama dan tidak terkait Codex (dua tes billing dan satu penjaga
  rollback).
- Tabel tugas berisi **0 baris** saat dihapus, jadi tidak ada riwayat yang hilang.

## Arsip

Seluruh kode dan dokumen Codex dibungkus sebelum dihapus, kalau-kalau perlu
dibaca lagi: `storage/app/backup/codex-dihapus-20261021.zip` (18 berkas, tidak
ikut git). Kalau tidak diperlukan lagi, hapus saja.

## Cara membuka lagi (kalau nanti)

Urutan yang masuk akal, supaya tidak mengulang tiga tembok di atas: cek dulu
Codex terlihat dari proses Apache (bukan dari terminal); perbaiki pemeriksaan
status agar membaca kedua saluran keluaran; dan baru pikirkan antrean setelah
dua hal itu lolos. Sambil menunggu, ide "agen koding bertanya tentang isi kode"
bisa diuji tanpa dashboard sama sekali: cukup lewat terminal, karena yang
sulit dari ide ini bukan idenya, tapi cara masuknya ke mesin.
