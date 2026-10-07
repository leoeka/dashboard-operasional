# Follow-up: Audit 001, Alur Mockup

Tanggal: 6 Oktober 2026
Temuan yang disetujui: 2, 3, 4

## Temuan 3 — Ditangani

`GenerateProposalJob` kini unik per project saat dispatch, dengan lock berlaku 20 menit. Middleware `WithoutOverlapping` juga mencegah dua worker menjalankan pipeline project yang sama bersamaan. Keduanya memakai cache lock Laravel; masa lock melampaui timeout job 15 menit.

Endpoint generate tidak lagi mengganti status `queued` atau `processing` ketika menerima request tambahan. Cache progress aktif disimpan 20 menit agar tetap tersedia sepanjang batas waktu job.

Tes dan click-through belum dijalankan. Perubahan telah ditinjau dari kode, tetapi perilaku queue dan cache lock belum diverifikasi saat runtime.

## Temuan 4 — Ditangani

Saat build baru dimulai, catatan bundle terakhir yang ada berubah ke status `building` dan tidak lagi dianggap siap diunduh. Jalur build/provider maupun ekspor yang gagal mengubahnya ke `failed`; build sukses memperbarui catatan yang sama menjadi `exported`. Build pertama yang gagal tidak membuat baris bundle palsu.

Halaman bundle membaca catatan terakhir berdasarkan `updated_at`, menampilkan status build, dan hanya mengaktifkan unduh untuk status `exported`. Endpoint unduh memeriksa status yang sama, jadi ZIP lama yang masih tersimpan di disk tidak dapat disajikan sebagai hasil terbaru.

Tes dan click-through belum dijalankan. Perubahan telah ditinjau dari kode, tetapi kondisi gagal build dan penolakan unduh belum diverifikasi saat runtime.

## Temuan 2 — Ditangani

`MockupPreviewController` kini memakai satu resolver kandidat untuk format proposal baru dan lama. Jika `mockup_candidates` kosong atau tidak ada, resolver menggunakan `mockup` lama sebagai opsi tunggal. Halaman demo juga menghitung jumlah kandidat melalui resolver yang sama, sehingga route demo dan navigasi opsinya konsisten.

Tes dan click-through belum dijalankan. Perubahan telah ditinjau dari kode, tetapi route demo proposal lama belum diverifikasi saat runtime.
