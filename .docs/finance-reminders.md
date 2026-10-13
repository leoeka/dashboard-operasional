# Perubahan Finance dan reminder

## Finance

- `FinanceController::summary()` menghitung total klien terdaftar, langganan aktif, dan perpanjangan dalam 30 hari. Ringkasan tidak menghitung status invoice atau pembayaran karena pembayaran berlangsung di luar sistem.
- `finance/partials/summary.blade.php` menampilkan tiga ringkasan tersebut.
- `FinanceController::TABS` dan navigasi halaman menghapus tab Pembayaran. Finance menampilkan invoice dan statusnya untuk menentukan pengingat, bukan memproses atau merangkum pembayaran.
- Form invoice manual, route `pages.finance.store`, dan `InvoiceController::store()` dihapus. Invoice renewal tetap dibuat oleh proses billing.

## Reminder otomatis dan kirim ulang

- `routes/console.php` menjalankan `billing:process-renewals` setiap hari pukul 09.00 pada zona waktu billing.
- `BillingRenewalService::processDue()` memeriksa tanggal jatuh tempo, membuat reminder pada threshold kebijakan, dan mengantrekan email serta WhatsApp bila nomor klien tersedia. Email dan WhatsApp dikirim melalui job terpisah supaya kegagalan WhatsApp tidak mengubah status email. `SendBillingReminderEmail` menyimpan status `sent` atau `failed` berdasarkan hasil pengiriman.
- `FinanceController::retryReminder()` hanya menerima log gagal yang masih di bawah batas percobaan. Ia meminta service billing mengantrekan ulang log yang sama dan memberi pesan sukses atau gagal kepada operator.
- `BillingRenewalService::retryDelivery()` mengubah status dengan update bersyarat di database (`failed` dan percobaan masih tersedia). Setelah retry pertama mengklaim log sebagai `pending`, permintaan berikutnya tidak membuat antrean duplikat.
- Riwayat reminder menampilkan tombol Kirim ulang selama masih ada percobaan. Log yang mencapai batas percobaan tetap berstatus gagal dan tombol disembunyikan.

## Alur SQL dan pemeriksaan

Summary memakai hitungan database untuk total klien dan langganan; metrik status invoice sudah dihapus. Riwayat gagal tidak membuat log duplikat karena retry memakai `billing_reminder_logs` yang sama.

Tes fitur baru berada di `tests/Feature/FinanceDashboardRequirementsTest.php`. Tes ini memakai SQLite test database untuk memeriksa alur tambah klien melalui CRM dan data SQL terkait, memastikan ringkasan tidak menampilkan hitungan status invoice/pembayaran, penghapusan entry point manual, serta pengiriman ulang reminder yang gagal. Jalankan dengan `php artisan test --filter=FinanceDashboardRequirementsTest`.
