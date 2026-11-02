# Perubahan Finance dan reminder

## Finance

- `FinanceController::summary()` menghitung total klien terdaftar, langganan aktif, dan perpanjangan dalam 30 hari. Ringkasan tidak menghitung status invoice atau pembayaran karena pembayaran berlangsung di luar sistem.
- `finance/partials/summary.blade.php` menampilkan tiga ringkasan tersebut.
- `FinanceController::TABS` dan navigasi halaman menghapus tab Pembayaran. Finance menampilkan invoice dan statusnya untuk menentukan pengingat, bukan memproses atau merangkum pembayaran.
- Form invoice manual, route `pages.finance.store`, dan `InvoiceController::store()` dihapus. Invoice renewal tetap dibuat oleh proses billing.

## Siklus, masa langganan, dan gulir otomatis

- Siklus tidak dipilih operator. `BillingSubscription::cycleForServiceType()` menurunkannya dari `service_type`: hosting dan domain tahunan, selain itu bulanan. Dropdown Siklus di form dihapus, `billing_cycle` dihapus dari aturan validasi `BillingSubscriptionController::validated()`, dan nilai hasil turunan selalu ditimpa saat menyimpan — kiriman `billing_cycle` dari luar form diabaikan.
- Kolom `ends_on` (date, nullable) pada `billing_subscriptions` menandai akhir masa langganan; form punya isian "Berakhir" dan kosong berarti tidak bermasa. Validasi: `after:start_date`.
- Form langganan hanya punya dua isian tanggal: "Mulai" dan "Berakhir". `next_renewal_date` bukan input lagi — saat membuat langganan nilainya diisi dari `start_date` (`BillingSubscriptionController::store()`), dan sesudah itu hanya mesin billing yang menulisnya. Aturan validasi `validated()` tidak menerima `next_renewal_date`, jadi `update()` pun tidak bisa menggesernya; kolom kiriman luar diabaikan. Dua pegang untuk satu fakta adalah cara langganan berakhir menginvoice hari yang salah.
- `BillingRenewalService::rollToCurrentPeriod()` menggulir `next_renewal_date` mengikuti tanggal, bukan mengikuti pembayaran, karena pembayaran berlangsung di luar sistem. Gulir memakai `BillingCycle::nextRenewalAfter()` yang menghitung dari `start_date`, jadi tidak ada drift sehari tiap periode meski scheduler tertinggal bertahun-tahun.
- Yang diinvoice setelah gulir adalah periode **terakhir yang menutup**, bukan periode tertua yang terlewat. `rollToCurrentPeriod()` mengembalikan `closed` (tanggal yang baru saja terlewat), dan `invoiceForPeriod($subscription, $opens)` menangani kedua kasus — periode terlewat dan periode berjalan — dengan reuse lewat `existingInvoiceFor()`. `due_date` invoice diambil dari awal periode itu sendiri, bukan `next_renewal_date` langganan, supaya tagihan atas periode yang sudah lewat tetap berstatus jatuh tempo.
- `BillingSubscription::coversRenewalOn()` menghentikan gulir: tanggal hasil gulir harus benar-benar sebelum `ends_on`, karena tanggal perpanjangan membuka periode yang akan diinvoice. Bila tidak, status langganan menjadi `expired` dan run berikutnya tidak menyentuhnya lagi — bukan invoice tambahan untuk layanan yang tidak dibeli.
- Sakelar "Invoice otomatis" (`auto_invoice`) **dihapus** (2026-10-15): dari form, dari aturan validasi, dari `$fillable`/`$casts` model, dan dari gerbang di `invoiceForPeriod()`. Alasannya: invoice renewal hanya bisa terbit lewat mesin dan entry point invoice manual sudah dihapus, jadi sakelar itu cuma punya satu efek nyata — membuat langganan Aktif tidak pernah menagih sekaligus mematikan reminder tanpa sebab yang terlihat. Yang bisa dimatikan per langganan sekarang hanya reminder (`auto_reminder`); berhenti menagih = ubah status jadi Dijeda. Kolom `auto_invoice` masih ada di database (default `true`) dan tidak lagi dibaca atau ditulis; menurunkannya butuh migrasi destruktif dan belum dikerjakan.
- Order pengerjaan satu langganan: gulir → invoice periode yang baru menutup → invoice periode berjalan → threshold reminder. Semuanya di dalam satu transaksi `lockForUpdate()` yang sama, jadi dua run pada pagi yang sama tidak bisa berdebat tentang periode mana yang sedang berjalan.
- Di halaman edit, tanggal periode berjalan hanya ditampilkan ("Periode berjalan dibuka ..."), tidak lagi bisa diubah. Di daftar langganan, kolom Perpanjangan ikut menampilkan tanggal berakhir bila ada.
- Penghitung baru `periods_rolled` dan `subscriptions_expired` ikut dilaporkan `billing:process-renewals`.

## Pertanyaan terbuka: `ends_on` yang jatuh di tengah periode (BELUM DIPUTUSKAN)

Status 2026-10-15: kode sengaja **tidak** diubah. Menunggu jawaban tim penjualan.

Isinya begini. Form menerima tanggal "Berakhir" apa pun asalkan setelah "Mulai", tapi invoice hanya bisa dicetak per paket utuh — hosting/domain setahun, website/maintenance sebulan. Tidak ada yang mencocokkan keduanya.

Contoh nyata: hosting mulai 2026-01-15, Berakhir 2026-06-30.

1. Invoice terbit untuk periode 2026-01-15 s/d 2027-01-14 — setahun penuh, nominal harga setahun.
2. Masa yang dijanjikan cuma sampai 30 Juni (5,5 bulan). Selisih 6,5 bulan tidak pernah dipertanyakan sistem.
3. Saat gulir berikutnya, tanggal hasil gulir sudah tidak sebelum `ends_on` → langganan langsung `expired`, tidak ada invoice tambahan. Tidak ada crash dan tidak ada test yang menangkap ini; secara teknis semuanya "berjalan".

Aman kalau tanggal berakhir jatuh pas di batas periode. Contoh yang sudah dipakai: mulai 2026-10-14, berakhir 2033-10-14 = tepat 7 tahun → tidak ada masalah sama sekali.

Pertanyaan untuk tim: **apakah agency pernah menjual hosting/domain dengan masa yang bukan kelipatan tahun penuh** (misalnya "langsung sampai tanggal 30 Juni", atau domain yang habis di tengah tahun)?

- Kalau **tidak pernah** → validasi form menolak `ends_on` yang tidak jatuh di batas siklus, dengan pesan yang menjelaskan kenapa. Perubahan kecil, hanya di `BillingSubscriptionController::validated()`.
- Kalau **pernah** → invoice terakhir harus dipotong sampai `ends_on` dan nominalnya prorata. Lebih besar: `BillingCycle::periodFor()` perlu clamp ke `ends_on`, nominal prorata, dan baris item invoice ikut menyesuaikan.
- Opsi ketiga, yang paling murah: biarkan perilakunya dan ganti makna labelnya — "Berakhir" dibaca sebagai "berhenti menagih mulai tanggal ini", bukan "masa layanan". Persis perilaku kode hari ini; yang berubah hanya helper text di form.

## Reminder otomatis dan kirim ulang

- `routes/console.php` menjalankan `billing:process-renewals` setiap hari pukul 09.00 pada zona waktu billing.
- `BillingRenewalService::processDue()` memeriksa tanggal jatuh tempo, membuat reminder pada threshold kebijakan, dan mengantrekan email serta WhatsApp bila nomor klien tersedia. Email dan WhatsApp dikirim melalui job terpisah supaya kegagalan WhatsApp tidak mengubah status email. `SendBillingReminderEmail` menyimpan status `sent` atau `failed` berdasarkan hasil pengiriman.
- `FinanceController::retryReminder()` hanya menerima log gagal yang masih di bawah batas percobaan. Ia meminta service billing mengantrekan ulang log yang sama dan memberi pesan sukses atau gagal kepada operator.
- `BillingRenewalService::retryDelivery()` mengubah status dengan update bersyarat di database (`failed` dan percobaan masih tersedia). Setelah retry pertama mengklaim log sebagai `pending`, permintaan berikutnya tidak membuat antrean duplikat.
- Riwayat reminder menampilkan tombol Kirim ulang selama masih ada percobaan. Log yang mencapai batas percobaan tetap berstatus gagal dan tombol disembunyikan.

## Penanda lunas (ditambahkan 2026-10-17)

- Uang tetap mengalir di luar sistem. Yang dicatat aplikasi hanya **fakta bahwa tagihan sudah diselesaikan dan tanggalnya**: `status` jadi `paid` dan `paid_at` diisi. Tidak ada nominal, metode, atau referensi, dan tidak ada baris baru di `payments`. Tabel serta model `Payment` dibiarkan apa adanya supaya invoice yang diselesaikan sebelum perubahan ini tetap punya riwayat.
- Dua route: `pages.finance.invoices.paid` dan `pages.finance.invoices.unpaid` (PATCH), keduanya `InvoiceController`. Tombolnya hanya di halaman detail invoice, bukan di daftar.
- Form mengirim tanggal dengan default hari ini dan `max` hari ini. Batas atasnya dibandingkan lewat `BillingClock` dan **kedua** sisi dibungkus `businessDate()`: `today()` adalah tengah malam zona bisnis sedangkan hasil `parse()` adalah tengah malam UTC, jadi dibandingkan sebagai instan akan mengira pembayaran hari ini sebagai besok. `Invoice::isOverdue()` sudah membungkus kedua sisi untuk alasan yang sama.
- Yang ditolak: invoice `cancelled`, invoice yang sudah lunas (tanggalnya tidak tertimpa oleh klik kedua), penandaan tanggal di masa depan, dan pembatalan pada invoice yang belum pernah ditandai. Menyembunyikan tombol bukan aturan, jadi pemeriksaan ini ada di controller, bukan hanya di Blade.
- Efeknya ke mesin: `BillingRenewalService` melewati threshold yang tersisa untuk invoice lunas, dan `retryDelivery()` menolak mengirim ulang log milik invoice yang sudah ditandai. Test menegakkan keduanya, bukan hanya yang pertama.
- Yang TIDAK berubah: periode langganan tetap bergulir mengikuti tanggal meski lunas tercatat. Gulir tidak menunggu pembayaran karena pembayaran terjadi di tempat yang tidak bisa dilihat aplikasi.
- Test: `tests/Feature/FinanceMarkPaidTest.php` (13 test, jalankan dengan `php artisan test --filter=FinanceMarkPaidTest`).

## Alur SQL dan pemeriksaan

Summary memakai hitungan database untuk total klien dan langganan; metrik status invoice sudah dihapus. Riwayat gagal tidak membuat log duplikat karena retry memakai `billing_reminder_logs` yang sama.

Tes fitur baru berada di `tests/Feature/FinanceDashboardRequirementsTest.php`. Tes ini memakai SQLite test database untuk memeriksa alur tambah klien melalui CRM dan data SQL terkait, memastikan ringkasan tidak menampilkan hitungan status invoice/pembayaran, penghapusan entry point manual, serta pengiriman ulang reminder yang gagal. Jalankan dengan `php artisan test --filter=FinanceDashboardRequirementsTest`.

Perilaku masa dan gulir diuji di `tests/Feature/BillingRenewalEngineTest.php` (gulir otomatis, satu invoice per periode, menagih periode terakhir yang menutup bukan yang tertua, berhenti di `ends_on`, `ends_on` kosong = tanpa batas) dan `tests/Feature/FinanceWorkspaceTest.php` (siklus turunan dari `service_type`, `billing_cycle` dan `next_renewal_date` kiriman luar diabaikan, `next_renewal_date` awal sama dengan `start_date`, validasi `ends_on`). Jalankan dengan `php artisan test --filter="Billing|Finance"`. Tiga test masih merah dan sudah merah sebelum perubahan ini: dua kekurangan template `billing-renewal-reminder.blade.php` (tidak merender siklus maupun rincian item) dan satu `BillingRollbackGuardTest` yang menuntut ledger tidak bergeser sama sekali. Penyebab yang terakhir bukan lagi soal jumlah langkah: test ini sekarang menghitung sendiri jendela rollback dari daftar `BILLING_MIGRATIONS`, jadi migrasi baru yang tidak berhubungan (misalnya `create_ai_coding_tasks_table`) tidak lagi menggesernya. Yang tersisa adalah guard-nya sendiri: ia berada di `2026_09_18_000007_add_billing_contact_to_clients_table`, sedangkan rollback berjalan dari file terbaru, jadi `add_whatsapp_sent_at...`, `expand_design_reference_url_column`, dan `add_ends_on...` sudah tergulir sebelum guard sempat menolak. Perbaikannya = memindahkan atau menyalin pemeriksaan itu ke `2026_10_14_000001_add_ends_on_to_billing_subscriptions_table` (file billing terbaru), dan itu belum disetujui pemilik.
