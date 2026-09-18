# Billing & Finance — Buku Manual Pengguna

**Versi:** 1.0  
**Terakhir diperbarui:** 18 September 2026  
**Fitur:** Billing & Finance Phase 1–4  
**Feature checkpoint:** `1cbcb4c`  
**Dokumentasi teknis:** [`BILLING_FINANCE_STATUS.md`](BILLING_FINANCE_STATUS.md)

> Dokumen ini untuk operator/admin yang memakai Billing & Finance sehari-hari. Untuk arsitektur, retry, timezone, idempotency, dan keputusan teknis, gunakan dokumentasi handoff developer.

---

## 1. Fitur yang tersedia

Billing & Finance saat ini mendukung:

- langganan recurring bulanan dan tahunan;
- invoice renewal otomatis;
- reminder renewal otomatis via email;
- invoice project manual (DP, pelunasan/final payment, full payment);
- pencatatan pembayaran;
- dashboard ringkasan finance;
- histori reminder;
- detail invoice dan reminder timeline;
- pause, resume, cancel, dan pengelolaan subscription.

Belum termasuk:

- PDF/download invoice otomatis;
- client billing portal;
- payment gateway;
- receipt otomatis;
- export CSV/accounting;
- accounting integration;
- manual generate renewal invoice;
- recurring WhatsApp reminder untuk renewal.

---

## 2. Masuk ke Billing & Finance

1. Login ke Dashboard Operasional.
2. Buka menu **Billing & Finance**.
3. Halaman utama berada di `/finance`.
4. Gunakan tab bagian atas untuk berpindah menu.

| Tab | Fungsi |
| --- | --- |
| **Overview** | Ringkasan kondisi billing dan perpanjangan mendatang |
| **Langganan** | Membuat dan mengelola recurring subscription |
| **Invoice** | Melihat invoice project dan renewal |
| **Pembayaran** | Melihat payment yang sudah tercatat |
| **Riwayat Reminder** | Memantau reminder renewal otomatis |

Tombol utama di halaman:

- **Langganan Baru**
- **Invoice Manual**

---

## 3. Membaca Overview

### Belum Dibayar / Outstanding
Invoice yang statusnya masih unpaid.

### Terlambat / Overdue
Invoice unpaid dengan due date yang sudah lewat menurut tanggal bisnis billing.

```text
status != paid
DAN
due_date < tanggal bisnis billing
```

Overdue dihitung otomatis dan tidak disimpan sebagai status permanen.

### Jatuh Tempo 7 Hari / Due Soon
Invoice unpaid yang jatuh tempo mulai hari ini sampai 7 hari ke depan.

### Diterima Bulan Ini
Total pembayaran yang tercatat pada bulan berjalan.

### Langganan Aktif
Jumlah subscription berstatus aktif.

### Perpanjangan 30 Hari
Subscription aktif yang renewal-nya masuk 30 hari ke depan.

---

## 4. Upcoming Renewals

Sebelum renewal mendekat, cek:

- client;
- nama layanan;
- nominal;
- billing cycle;
- next renewal date;
- status;
- Auto Invoice;
- Auto Reminder;
- kontak billing.

---

## 5. Membuat Langganan Baru

Klik **Langganan Baru**.

| Field | Wajib | Keterangan |
| --- | --- | --- |
| Client | Ya | Client yang menerima layanan/tagihan |
| Paket Layanan | Tidak | Template; custom tanpa paket tetap boleh |
| Nama Layanan | Ya | Contoh Hosting + Domain, SEO Monthly |
| Jenis | Ya | Jenis service |
| Project | Tidak | Hanya project milik client |
| Catatan | Tidak | Informasi tambahan |
| Siklus | Ya | Tahunan atau Bulanan |
| Nominal | Ya | Harga per periode |
| Mulai | Ya | Anchor cycle |
| Perpanjangan Berikutnya | Ya | Renewal berikutnya |
| Status | Ya | Aktif, Dijeda, Dibatalkan, Berakhir |
| Invoice Otomatis | Pilihan | Auto-generate renewal invoice |
| Reminder Otomatis | Pilihan | Auto-send reminder email |

### Validasi Project
Project yang dipilih harus milik Client yang dipilih.

### Validasi Tanggal
`next_renewal_date` tidak boleh lebih awal dari `start_date`.

### Paket Layanan
Paket hanya template pengisian dan bukan binding permanen.

---

## 6. Auto Invoice dan Auto Reminder

### Auto Invoice aktif
Renewal invoice dibuat otomatis saat masuk window.

### Auto Invoice nonaktif
Sistem tidak membuat renewal invoice otomatis.

> Saat ini belum ada tombol **Generate Renewal Invoice Now**.

### Auto Reminder aktif
Reminder email dikirim sesuai threshold.

### Auto Reminder nonaktif
Reminder renewal otomatis tidak dijalankan ke depan.

---

## 7. Jadwal Tahunan

- Invoice mulai dibuat: **H-30**
- Reminder: **H-30, H-7, H-3**

Contoh renewal 30 Oktober:

| Tanggal | Aksi |
| --- | --- |
| 30 September | Invoice + reminder H-30 |
| 23 Oktober | Reminder H-7 jika belum lunas |
| 27 Oktober | Reminder H-3 jika belum lunas |
| 30 Oktober | Renewal date |

---

## 8. Jadwal Bulanan

- Invoice mulai dibuat: **H-7**
- Reminder: **H-7, H-3, H-1**

Contoh renewal 30 Oktober:

| Tanggal | Aksi |
| --- | --- |
| 23 Oktober | Invoice + reminder H-7 |
| 27 Oktober | Reminder H-3 |
| 29 Oktober | Reminder H-1 |
| 30 Oktober | Renewal date |

---

## 9. Invoice Catch-up vs Reminder

Invoice boleh catch-up.

Contoh:

```text
H-30 scheduler mati
H-29 scheduler aktif lagi
```

Hasil:

```text
invoice dibuat pada H-29
H-30 email tidak dikirim terlambat
```

Reminder hanya dikirim pada threshold yang tepat.

---

## 10. Mengedit Langganan

Subscription dapat diedit untuk:

- nama layanan;
- service type;
- project;
- service package;
- amount;
- billing cycle;
- next renewal date;
- status;
- Auto Invoice;
- Auto Reminder.

Perubahan harga hanya berlaku ke periode berikutnya.

Invoice historical yang sudah terbit tidak berubah.

---

## 11. Status Langganan

### Aktif
Diproses recurring billing.

### Dijeda
Untuk layanan yang berhenti sementara.

### Dibatalkan
Untuk layanan yang dihentikan.

### Berakhir
Untuk layanan yang masa berlakunya selesai.

Invoice yang sudah terbit tetap dipertahankan.

---

## 12. Dua Jenis Invoice

### Project Payment
Invoice manual untuk:

- DP;
- Pelunasan / Final Payment;
- Full Payment.

### Renewal
Invoice recurring dari Billing Subscription.

---

## 13. Membuat Invoice Project Manual

1. Klik **Invoice Manual**.
2. Pilih Project.
3. Pilih DP / Final Payment / Full Payment.
4. Masukkan nominal.
5. Pilih due date.
6. Simpan.

> Jangan menggunakan Invoice Manual untuk renewal.

---

## 14. Tab Invoice

Gunakan filter untuk mencari:

- paid;
- unpaid;
- overdue;
- project payment;
- renewal;
- client/date bila tersedia.

Buka detail untuk melihat rincian invoice.

---

## 15. Detail Invoice

Detail invoice dapat menampilkan:

- nomor invoice;
- purpose;
- status;
- client;
- billing contact;
- project;
- subscription;
- service;
- billing cycle;
- issue date;
- due date;
- paid date;
- billing period;
- invoice items;
- subtotal;
- discount;
- tax;
- total;
- payment history;
- reminder timeline.

---

## 16. Menandai Invoice Lunas

1. Buka invoice unpaid.
2. Klik **Tandai Lunas**.
3. Konfirmasi.
4. Sistem mencatat Payment.
5. Invoice menjadi paid.

Untuk renewal:

- Payment dibuat;
- invoice menjadi paid;
- subscription maju satu periode.

Repeated mark-paid tidak seharusnya membuat payment ganda atau memajukan renewal dua kali.

---

## 17. Reminder Manual

### Project invoice unpaid
Tombol **Kirim Reminder** tersedia.

Jalur legacy dapat Email + WhatsApp.

### Renewal invoice
Tidak ada manual reminder.

Renewal reminder adalah **Email only** dan mengikuti threshold otomatis.

---

## 18. Hapus Invoice

| Kondisi | Bisa dihapus? |
| --- | --- |
| Project + unpaid | Ya |
| Project + paid | Tidak |
| Renewal + unpaid | Tidak |
| Renewal + paid | Tidak |

Paid invoice dan renewal invoice dipertahankan sebagai histori keuangan.

---

## 19. Tab Pembayaran

Menampilkan:

- tanggal;
- invoice;
- client;
- tujuan/layanan;
- nominal;
- currency;
- metode;
- reference;
- recorded by.

Filter dapat mencakup client, metode, rentang tanggal, dan nomor invoice.

---

## 20. Payment History pada Invoice

Detail invoice menampilkan:

- tanggal pembayaran;
- nominal;
- metode;
- reference;
- pencatat;
- notes bila ada.

Jika kosong:

```text
Belum ada pembayaran tercatat.
```

---

## 21. Riwayat Reminder

Kolom utama:

- Client;
- Invoice;
- Layanan;
- Threshold;
- Dijadwalkan;
- Penerima;
- Status;
- Percobaan;
- Terkirim.

Filter:

- status;
- threshold;
- client.

---

## 22. Status Reminder

### Menunggu
Reminder sudah diklaim dan menunggu queue worker.

### Terkirim
Email berhasil dikirim.

### Gagal
Pengiriman gagal dan dapat masuk retry hingga batas attempt.

---

## 23. Reminder Timeline

| Label | Arti |
| --- | --- |
| **Terkirim** | Reminder berhasil dikirim |
| **Gagal** | Reminder gagal |
| **Menunggu kirim** | Menunggu worker |
| **Tidak diperlukan** | Sudah lunas sebelum/pada threshold |
| **Belum waktunya** | Threshold masih di masa depan |
| **Dijadwalkan hari ini** | Threshold jatuh hari ini |
| **Reminder nonaktif** | Auto Reminder nonaktif |
| **Tidak tercatat** | Tidak ada log untuk threshold historis |

Tidak adanya log tidak selalu membuktikan scheduler gagal, sehingga UI menggunakan label netral **Tidak tercatat**.

---

## 24. Pembayaran Sebelum Reminder Berikutnya

Jika H-30 sudah terkirim lalu client membayar sebelum H-7:

```text
H-30 -> Terkirim
H-7  -> Tidak diperlukan
H-3  -> Tidak diperlukan
```

---

## 25. Kontak Billing

Prioritas email:

```text
billing_email
fallback ke email utama client
```

Billing name/email/phone juga dapat fallback ke contact utama.

---

## 26. SOP Harian Operator

1. Buka Overview.
2. Cek Terlambat.
3. Cek Jatuh Tempo 7 Hari.
4. Cek Perpanjangan 30 Hari.
5. Cek Riwayat Reminder untuk Gagal/Pending.
6. Follow-up overdue.
7. Tandai invoice lunas saat pembayaran diterima.
8. Pastikan payment muncul.

Prioritas:

```text
reminder gagal
-> invoice overdue
-> renewal terdekat
-> rekonsiliasi pembayaran
```

---

## 27. SOP Onboarding Client

1. Pastikan data Client benar.
2. Isi email client.
3. Isi billing contact jika finance contact berbeda.
4. Buat Langganan Baru.
5. Pilih cycle.
6. Masukkan nominal.
7. Isi start date.
8. Isi next renewal date.
9. Aktifkan Auto Invoice.
10. Aktifkan Auto Reminder.
11. Simpan.
12. Verifikasi di tab Langganan.

---

## 28. Troubleshooting

### Renewal invoice tidak muncul
Cek status aktif, Auto Invoice, next renewal, window H-30/H-7, dan scheduler.

### Reminder tidak muncul
Cek status aktif, Auto Reminder, threshold, invoice renewal, dan scheduler.

### Reminder Pending terlalu lama
Cek queue worker.

### Reminder Failed
Cek error message, SMTP, penerima, dan worker.

### Email salah
Perbaiki billing email/email utama client.

### Tombol Reminder tidak ada
Normal untuk renewal.

### Tombol Delete tidak ada
Normal untuk paid/renewal invoice.

---

## 29. Kebutuhan Server

### Scheduler

Server perlu menjalankan:

```bash
php artisan schedule:run
```

setiap menit.

Billing scheduler menjalankan:

```bash
php artisan billing:process-renewals
```

pukul **09:00 billing timezone**.

Konfigurasi saat ini:

```text
APP_TIMEZONE=UTC
BILLING_TIMEZONE=Asia/Makassar
```

### Queue Worker

Worker harus aktif, misalnya:

```bash
php artisan queue:work
```

Queue connection menggunakan database.

### SMTP

Konfigurasi mail production harus benar.

`MAIL_MAILER=log` hanya cocok untuk local/testing.

---

## 30. Residual Limitation

Jika process hard-crash tepat setelah reminder menjadi `pending` tetapi sebelum job queue tercatat, row dapat tertinggal pending tanpa job.

Dispatch exception biasa sudah mengembalikan row ke `failed`.

Retry harian hanya mengambil `failed`, bukan semua pending.

Pending yang benar-benar stranded perlu operational inspection/manual recovery.

---

## 31. Quick Reference

| Item | Tahunan | Bulanan |
| --- | --- | --- |
| Invoice mulai | H-30 | H-7 |
| Reminder 1 | H-30 | H-7 |
| Reminder 2 | H-7 | H-3 |
| Reminder 3 | H-3 | H-1 |
| Channel renewal | Email | Email |
| Scheduler | 09:00 WITA | 09:00 WITA |

---

## 32. FAQ

**Apakah renewal invoice perlu dibuat manual?**  
Tidak jika Auto Invoice aktif.

**Apakah renewal reminder bisa dikirim manual?**  
Tidak dari UI.

**Project invoice lama masih bisa dipakai?**  
Ya.

**Renewal pakai WhatsApp?**  
Tidak. Renewal recurring saat ini Email only.

**Edit harga subscription mengubah invoice lama?**  
Tidak.

**Scheduler mati pada H-30?**  
Invoice dapat catch-up; reminder H-30 tidak dikirim terlambat.

**Bisa hapus renewal invoice?**  
Tidak.

**Sudah ada PDF invoice/payment gateway?**  
Belum.

---

## 33. Aturan Emas

> **Gunakan Langganan untuk recurring service, Invoice Manual untuk pembayaran project, Tandai Lunas untuk mencatat pembayaran, dan Riwayat Reminder untuk memonitor email otomatis.**

---

## Referensi

- [Billing & Finance — Development Status & Handoff](BILLING_FINANCE_STATUS.md)
- `/finance`
- `resources/views/pages/finance.blade.php`
- `resources/views/finance/subscription-form.blade.php`
- `resources/views/finance/invoice-detail.blade.php`
- `resources/views/finance/partials/reminders.blade.php`
