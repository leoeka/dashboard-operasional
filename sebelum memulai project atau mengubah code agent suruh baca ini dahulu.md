# Aturan Kerja Agent untuk Dashboard Operasional

Aturan ini berlaku untuk setiap agent yang membaca atau mengubah kode di repositori ini.

## Sebelum mengubah kode

- Baca instruksi yang berlaku, lalu telusuri implementasi, pemanggil, model/data, route, tampilan, dan tes yang berhubungan dengan tugas. Jangan menebak perilaku hanya dari nama file atau permintaan singkat.
- Ikuti alur data dan kontrak fitur sampai ke hasil yang dilihat pengguna. Jika perilaku aktual berbeda dari asumsi, jelaskan perbedaannya dan implementasikan perilaku yang diminta.
- Cari implementasi serupa terlebih dahulu. Gunakan ulang layanan, komponen, helper, validasi, dan pola yang ada; hindari membuat duplikasi.
- Sebelum mengedit, identifikasi perubahan pengguna yang sudah ada di working tree. Jangan menghapus atau menimpa perubahan tersebut tanpa alasan yang jelas.
- Jika kebutuhan belum lengkap, lanjutkan bagian yang aman dan independen. Tanyakan hanya hal yang benar-benar mengubah perilaku atau keputusan produk.

## Cara bekerja dengan pengguna

- Bersikap toleran terhadap brief yang belum lengkap, variasi data lama, dan perubahan arah. Jangan menganggap ketidaklengkapan kecil sebagai alasan untuk menghentikan pekerjaan.
- Lanjutkan bagian yang aman dengan asumsi yang disebutkan jelas. Tanyakan hanya hal yang mengubah perilaku produk atau keputusan yang sulit dibatalkan.
- Gunakan jawaban dan koreksi yang sudah diberikan; jangan menanyakan ulang hal yang sama atau meminta izin lagi untuk pekerjaan yang sudah diminta.
- Jaga perubahan tetap pada cakupan yang diminta. Jangan memaksakan refactor, cleanup, atau pola baru yang tidak diperlukan.
- Pertahankan perubahan pengguna yang sudah ada. Bila ada konflik, kerjakan bagian independen dan jelaskan bagian yang berbenturan.

## Saat menulis atau mengubah kode

- Gunakan standar bahasa dan framework yang sudah dipakai proyek. Untuk PHP/Laravel, ikuti PSR-12, konvensi Laravel, dependency injection, validasi request, authorization yang sesuai, dan pola error handling yang konsisten.
- Pilih implementasi paling sederhana yang memenuhi kebutuhan. Pecah tanggung jawab secara jelas, gunakan nama yang menjelaskan maksud, dan hindari fungsi panjang, magic values, duplikasi, dead code, serta komentar yang mengulang kode.
- Pertahankan kompatibilitas kontrak yang sudah dipakai route, API, database, job, dan UI. Perubahan schema harus memiliki migration yang bisa dijalankan dan rollback yang masuk akal.
- Validasi dan normalisasi input di batas sistem. Escape output sesuai konteks. Jangan mencatat secret, token, data sensitif, atau payload pribadi ke log.
- Jangan menambahkan dependency, konfigurasi, atau abstraksi baru jika pola yang ada sudah cukup.
- Perbarui dokumentasi pengguna/teknis yang relevan. Untuk setiap pekerjaan kode, tambahkan entri kronologis ke bagian teratas `reports/HANDOFF.md`: tanggal, ringkasan perilaku, file utama, keputusan penting, tes/pemeriksaan yang dijalankan beserta hasilnya, dan keterbatasan. Jangan menulis klaim tes yang belum dijalankan.

## Review wajib sebelum menyelesaikan tugas

- Baca ulang seluruh diff sendiri, bukan hanya potongan yang baru ditulis.
- Pastikan kode memenuhi permintaan dan jalur sukses maupun gagal ditangani.
- Periksa duplikasi, kontrak lama, status kosong, batas input, keamanan, aksesibilitas UI, serta apakah dokumentasi dan pesan antarmuka masih sesuai perilaku.
- Pastikan tidak ada debug statement, secret, file sementara, perubahan tak terkait, atau hasil generate yang tidak dimaksudkan.
- Jalankan `git diff --check` dan pemeriksaan sintaks/lint yang tersedia untuk file yang berubah.

## Tes wajib

- Tambahkan atau perbarui tes otomatis untuk setiap perubahan perilaku yang bisa diuji. Tes harus mencakup hasil yang diminta serta kasus gagal/batas yang relevan.
- Jalankan tes spesifik terlebih dahulu, kemudian suite yang lebih luas bila waktu dan lingkungan memungkinkan. Untuk perubahan UI, validasi kompilasi view/build dan alur tampil yang relevan.
- Jangan menonaktifkan, melemahkan, atau menghapus tes hanya agar suite hijau. Jika tes gagal karena masalah yang tidak terkait, catat nama tes, gejala, dan bukti bahwa kegagalan tidak berasal dari perubahan ini.
- Jika tes tidak dapat dijalankan, jelaskan penyebabnya dan langkah verifikasi pengganti. Jangan menyebut implementasi "teruji" jika hanya ditinjau secara manual.

## Laporan akhir

- Jelaskan apa yang berubah dan perilaku yang dihasilkan dengan bahasa langsung.
- Sebutkan catatan dokumentasi yang diperbarui.
- Laporkan perintah tes/pemeriksaan beserta hasil akuratnya, termasuk kegagalan atau keterbatasan.
- Sebutkan temuan review yang belum diperbaiki dan dampaknya. Jangan menyatakan pekerjaan selesai jika ada bagian penting yang belum dikerjakan.
