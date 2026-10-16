# DESIGN.md - SiteFlow

Dokumen arah. Isinya keputusan pemilik produk, bukan hasil tafsir agen.
Filter anti-slop ada di `.agents/skills/antislop*`; dokumen ini yang memberi identitas,
filter itu yang menolak template. Kalau keduanya bertabrakan, aturan kontras di bagian 2
tetap berlaku karena itu angka ukur, bukan selera.

Setiap rasio kontras di bawah diukur dengan
`.agents/skills/antislop-human/contrast-check.py` terhadap pasangan yang ditulis di sini,
bukan diambil dari dokumen lain. Ambang: teks normal 4,5:1, teks besar 3,0:1,
batas komponen non-teks 3,0:1.

## 0. Design Read

> Reading this as: dashboard operasional internal untuk 1-3 orang agency, dibaca tiap hari
> kerja di laptop, bahasa visual "buku besar yang rapi", dial ENERGY 1 / RHYTHM 2 / MOTION 1.

Arti dial untuk pekerjaan apa pun di repo ini:

| Dial | Nilai | Konsekuensi yang bisa diperiksa |
|---|---|---|
| ENERGY | 1 | Tidak ada hero, tidak ada warna penuh layar, tidak ada dekorasi. Sapaannya diam. |
| RHYTHM | 2 | Semua halaman pakai rangka kartu yang sama, dengan satu penyimpangan yang disengaja per halaman (bagian 6). |
| MOTION | 1 | Hover, focus, dan transisi drawer. Tidak ada scroll-reveal, tidak ada animasi latar. |

Naik satu tingkat di salah satu dial butuh alasan tertulis di dokumen ini lebih dulu.

## 1. Identitas dan suasana

SiteFlow adalah alat kerja, bukan wajah perusahaan. Pembacanya operator agency yang membuka
halaman ini sambil menelepon klien, dan yang butuh satu jawaban: apa yang belum selesai dan
apa yang harus ditagih hari ini.

- Suasana yang dikejar: padat, tenang, bisa dibaca delapan jam tanpa melelahkan mata.
- Yang bukan SiteFlow: landing page, portofolio agency, marketing. Halaman ini tidak pernah
  perlu menjual apa pun ke orang yang melihatnya.
- Identitas datang dari tipografi dan ritme angka (bagian 3 dan 5), bukan dari warna.
  Ini pilihan sadar pada ENERGY 1: kalau warna ditahan, yang membuat layar terasa milik
  SiteFlow adalah cara nominal dan tanggal ditulis.
- Sumber kebenaran bisnis: request, project, langganan, invoice, reminder. Pembayaran klien
  terjadi di luar sistem, jadi tidak ada satu layar pun yang boleh berpura-pura mencatat uang.

Satu titik fokus per layar:

| Layar | Titik fokusnya |
|---|---|
| Dashboard | Apa yang jatuh tempo atau tersendat hari ini. |
| CRM | Nama klien yang bisa disentuh, bukan jumlah klien. |
| Request Order | Antrean request yang belum diubah jadi project. |
| Workspace | Tahap pengerjaan project yang sedang dibuka. |
| Finance | Tagihan yang pengingatnya belum terkirim. |
| Reports | Pemasukan enam bulan terakhir. |

Elemen lain di layar yang sama boleh ramai, tidak boleh bersaing.

## 2. Palet

### Warna aksi: biru saja

Biru dipilih bukan karena suka. `brand.500/600/700` sudah menjadi warna aksi di kode hari
ini lewat `.nav-active` dan tautan, jadi ini mengakui fakta yang ada, bukan mengganti merek.
Ungu berhenti jadi identitas (temuan 9 audit 003): gradien ungu-biru adalah tanda paling
khas hasil tanpa arah.

| Token | Hex | Dipakai untuk | Kontras putih di atasnya |
|---|---|---|---|
| `brand-50` | `#eef4ff` | latar menu aktif, latar chip aktif | - |
| `brand-500` | `#3b6fe0` | **hanya** elemen non-teks: batang grafik, ring fokus, garis aksen | 4,63:1 lolos, tapi tipis |
| `brand-600` | `#2f5bc4` | tombol primer, tautan, angka aksi | 6,15:1 |
| `brand-700` | `#25489c` | hover/pressed tombol primer, teks di atas `brand-50` | 8,45:1 |

Dua larangan yang lahir dari hasil ukur, bukan dari selera:

- Teks `brand-500` di atas `brand-50` = **4,19:1, gagal AA**. `.nav-active` sudah pindah ke
  `brand-700` (7,65:1), tapi utilitas `text-slate-500` di tautan sidebar sempat menimpanya:
  di peramban warna menu aktif terukur 4,31:1. Kelas warna kini hanya salah satu:
  `nav-active` untuk item aktif, `text-slate-500` untuk yang lain.
- `brand-500` tidak dipakai untuk teks kecil di latar apa pun. Untuk teks, selalu `brand-600`
  ke atas.

### Netral: slate saja, satu skala

Latar halaman `#f4f6fb`, permukaan kartu putih, semua teks dan garis dari skala slate.
Skala `gray-*` dilarang, dan hari ini nol di seluruh `resources/views`. 153 pemakaian yang dulu
tersisa tinggal di kerangka Breeze dan halaman bawaan Laravel; berkas-berkas itu dibuang
2026-10-16 atas keputusan pemilik (bagian 8 item 1). Yang sengaja bertahan dari daftar lama cuma
`components/*-button`, `text-input`, `input-label`, `input-error`, dan `modal`, karena
`/profile/edit` masih memakai mereka.

| Peran | Kelas | Di atas putih | Di atas `#f4f6fb` |
|---|---|---|---|
| Judul | `text-slate-900` | 17,85:1 | 16,51:1 |
| Body, angka, label | `text-slate-800` | 14,63:1 | 13,53:1 |
| Sekunder, caption tabel | `text-slate-600` | 7,58:1 | 7,01:1 |
| Meta, **hanya di atas putih** | `text-slate-500` | 4,76:1 | **4,40:1 gagal** |
| Dilarang | `text-slate-400`, `text-slate-300` | 2,56:1 / 1,48:1 | gagal |

Aturan praktis yang bisa diperiksa: `slate-500` hanya boleh di dalam kartu putih. Di atas
latar halaman, naik ke `slate-600`. Permukaan tint yang tampak netral ternyata ikut menekan
angka: `slate-500` di atas `slate-50` = 4,55:1 (lolos tapi tipis), di atas `slate-100` =
**4,34:1 gagal**, di atas `brand-50` = **4,31:1 gagal**, di atas latar halaman `#f4f6fb` =
**4,40:1 gagal**; hanya di atas putih dia lolos, 4,76:1. Untuk teks di atas tint apa pun,
mulai dari `slate-600` (`slate-600` di atas `slate-100` = 6,92:1).

### Warna status: menempel di badge, tidak menular

Enam warna status tetap ada karena status memang enam, dan pasangan badge hari ini lolos AA
semua (terukur): slate 6,92; blue 5,49; purple 5,92; amber 4,51; emerald 4,84; pink 5,14;
red 5,30. Batasnya: warna status hanya hidup di badge, titik status, dan baris alert. Tidak
untuk judul kartu, tidak untuk angka, tidak untuk tombol. Purple dan pink kehilangan akses ke
tata letak umum.

Untuk teks merah/amber/emerald langsung di atas putih (bukan badge): red-700 6,47:1,
amber-700 5,02:1, emerald-700 5,48:1. Selalu tingkat 700, jangan 500 atau 600.
Di atas tint-nya sendiri angkanya turun, dan 600 tetap gagal: red-600 di atas red-50 =
**4,41:1**, emerald-600 di atas emerald-50 = **3,58:1**; pasangannya yang 700 lolos
(red-700 di atas red-50 5,91:1, emerald-700 di atas emerald-50 5,21:1, amber-700 di atas
amber-50 4,84:1). Tombol berisi warna status juga gagal: putih di atas emerald-600 =
**3,77:1**, putih di atas amber-600 = **3,19:1**. Aksi tidak memakai warna status; yang primer
`brand-600`, yang lain gaya sekunder.

### Aksen yang disengaja, satu saja

Bar kiri 3px `brand-600` pada kartu atau baris tabel yang menuntut tindakan hari ini
(invoice lewat jatuh tempo, reminder gagal, request yang menunggu). Itu satu-satunya aksen
identitas di aplikasi, dan hanya muncul ketika memang ada yang menunggu. Nol aksen bikin
layar mati; aksen di semua kartu bikin aksen tidak berarti.

### Yang dihapus

`.grad-blue`, `.grad-purple`, `.grad-orange`, `.grad-teal` dari `layouts/app.blade.php`, dan
semua pemakaiannya (tombol CTA CRM/Finance/Reports, batang grafik omzet, tile `x-empty-state`).
Penggantinya solid: tombol `brand-600`, batang grafik `brand-500` dengan hover `brand-600`,
tile empty-state `slate-100` dengan ikon `slate-600`. Kontrasnya jadi bisa diuji pada satu
titik, bukan dua ujung gradien.

## 3. Tipografi

**Inter** untuk semua teks. Alasan yang sebelumnya tidak tertulis, sekarang tertulis: sudah
dimuat di setiap halaman, cakupannya lengkap untuk teks Indonesia tanpa subset tambahan, dan
netral pada kepadatan data. Mengganti font utama adalah keputusan merek dan butuh alasan yang
lebih kuat dari "ingin beda".

**JetBrains Mono** untuk angka: nominal `Rp`, jumlah invoice, jumlah hari, tanggal,
persentase. Ini satu-satunya font kedua, dan satu-satunya alasan memakainya. Angka tagihan
sekarang ditulis dengan lebar digit proporsional, jadi kolom nominal di Finance dan Reports
tidak pernah sejajar rata kanan. Dimuat sebagai satu permintaan Google Fonts tambahan,
berat yang dibayar untuk kerapian kolom; boleh di-subset ke `0-9 . , Rp` kalau muatannya
merusak.

Skala yang boleh dipakai, tidak ada nilai di antaranya:

| Ukuran | Kelas Tailwind | Peran |
|---|---|---|
| 12px | `text-xs` | meta, caption tabel, bantuan form |
| 14px | `text-sm` | body, kontrol, baris tabel |
| 16px | `text-base` | sub-judul kartu |
| 20px | `text-xl` | judul halaman (`x-page-header`) |
| 28px | `text-2xl` | satu angka utama per kartu statistik |

Bobot: 400 body, 600 label dan judul kartu, 700 judul halaman dan angka. 500 tidak dipakai.

Berat huruf dan ukuran tidak boleh bergerak bersamaan di dua tingkat; kalau angka sudah 28px
dan 700, labelnya 12px dan 400, tidak 20px/600.

## 4. Komponen

Semua radius dari tiga nilai. Nilai yang beredar hari ini lima (lg 95, md 39, full 29, xl 20,
2xl 9 pemakaian) dan tidak satu pun punya fungsi.

| Radius | Nilai | Hak memakainya |
|---|---|---|
| `rounded-md` | 6px | kontrol form, tombol, badge status |
| `rounded-lg` | 8px | permukaan kecil: chip filter, tile ikon, popover |
| `rounded-xl` | 12px | kartu (`x-card`) |
| `rounded-full` | - | **hanya** dot status dan avatar |

`x-badge` pindah dari pill ke `rounded-md`. Pill jadi punya arti (chip filter aktif), badge
status jadi kotak kecil yang rapi di kolom tabel.

**Kartu.** `bg-white`, `border border-slate-200`, `rounded-xl`, `p-6` default, tidak ada
shadow pada kartu biasa. Peringatan nyata dari pengukuran: batas kartu tidak akan pernah
mencapai 3:1 terhadap latar (`slate-200` vs `#f4f6fb` = 1,14:1, `slate-300` pun 1,37:1), jadi
pemisah kartu harus datang dari **permukaan putih di atas latar abu + border + jarak antar
kartu yang konsisten**, dan itu bagian dari ritme, bukan dari border. Untuk elemen yang
boundary-nya harus dikenali tanpa label (input, field pencarian ikon-only), pakai
`border-slate-500` = 4,76:1 terhadap putih, lolos ambang non-teks 3:1.

**Tombol primer.** `bg-brand-600 text-white rounded-md px-4 py-2 text-sm font-semibold`,
hover `bg-brand-700`, fokus ring 2px `brand-500` dengan offset 2px. Satu tombol primer per
layar; yang lain sekunder (`border-slate-300 text-slate-700 bg-white`) atau tautan teks.

**Baris tabel.** `border-b border-slate-100`, header `text-xs text-slate-600 font-semibold`
di atas `bg-slate-50`, hover baris `bg-slate-50`. Nominal dan tanggal di kolom kanan, rata
kanan, monospasi.

**Alert.** Bukan pill dan bukan gradien. Kotak `rounded-md`, `border-l-4`, latar `red-50`
dengan teks `red-700` untuk gagal/lewat jatuh tempo, `amber-50`/`amber-700` untuk menunggu,
`slate-50`/`slate-700` untuk informasi. Warna kiri tebal itu penanda jenis, teksnya yang
menjelaskan; warna tidak pernah sendirian (sesuai aturan status-only-by-color).

Bar kiri ikut diukur, bukan hanya teksnya. Tingkat 700 di atas tint-nya sendiri: red 5,91:1,
emerald 5,21:1, amber 4,84:1, slate 9,90:1, dan `brand-700` di atas `brand-50` 7,65:1
(`brand-600` 5,57:1). Tingkat 600 gagal sebagai bar amber (`amber-600` di atas `amber-50` =
**3,07:1**), jadi aksen alert selalu 700. Sejak bagian ini dijalankan, tidak ada lagi tombol
berwarna status: `primary-button`, `secondary-button`, `danger-button`, dan tiga tombol
berbingkai `border-emerald-200` di Workspace sudah pindah ke gaya sekunder `border-slate-300`.

**Empty state.** `x-empty-state` dipakai di semua daftar, bukan hanya Finance. Wajib
membedakan dua hal yang berbeda: belum ada data sama sekali, dan hasil filter kosong. Teks
"Belum ada data" tanpa aksi tidak diterima; tiap empty state menunjuk satu langkah berikutnya.

**Ikon.** Tidak ada ikon tanpa teks yang bisa dibaca. Tombol ikon-only dapat `aria-label` dan
tooltip, dan labelnya harus ikut terbaca oleh keyboard, bukan hanya atribut `title`.

## 5. Layout dan ritme

- Basis 4px. Langkah yang boleh: 4, 8, 12, 16, 24, 32, 40. Nilai di luar daftar (20px dari `p-5`, 10px dari `py-2.5`, 6px dari
  `gap-1.5`) dipindah ke langkah terdekat.
- Rangka halaman: sidebar 256px tetap, konten `px-6 py-6`, lebar maksimum 1400px agar
  tabel tidak meregang sampai mata kehilangan baris.
- Jarak antar kartu 24px (`space-y-6`), jarak dalam kartu 16px, judul kartu ke isi 12px.
- Dashboard grid 12 kolom: baris statistik 3+3+3+3, pipeline 8 kolom, aktivitas 4 kolom.
  Di <768px semuanya satu kolom, statistik dua kolom di `sm`.
- whitespace adalah struktur, bukan sisa. Kalau satu blok terasa kosong, yang salah adalah
  urutan isi, bukan kebutuhan menambah elemen.

Sweep bagian 4 dan 5 sudah jalan, dan ini hitungan pemakaiannya di 51 berkas view aplikasi
(pdf dan email tidak ikut). Kolom "sebelum" adalah commit `HEAD` sebelum
restyle; semua angka dihitung ulang dengan pencocokan batas kata, bukan `grep -c` per baris.
Enam halaman auth tadinya tidak ikut hitungan ini karena tidak pernah menerima CSS aplikasi
(bagian 5); sejak mereka pindah ke `layouts/auth`, token mereka ikut disisir dan hasil akhirnya
nol untuk semua baris di atas:

| Token | Sebelum | Sesudah | Alasan pindah |
|---|---|---|---|
| `rounded-2xl` | 11 | 0 | kartu pakai `rounded-xl` |
| `rounded` (polos, 4px) | 10 | 0 | radius keempat tanpa fungsi; jadi `rounded-md` |
| `rounded-full` | 41 | 29 | 12 badge status pindah ke `rounded-md`; sisanya titik status, avatar, dan track progress |
| `p-5` / `px-5` (20px) | 13 | 0 | `p-4` / `px-4` (16px) |
| `py-2.5` / `px-2.5` (10px) | 37 | 0 | `py-3` / `px-3` (12px) |
| `gap-1.5`, `py-1.5`, `px-1.5` (6px) | 47 | 0 | `gap-2`, `py-2`, `px-2` (8px) |
| `gap-5`, `mb-5`, `mt-5`, `space-y-5` (20px) | 36 | 0 | 24px antar kartu, 16px dalam kartu, 12px judul kartu ke isi |
| `space-y-0.5`, `py-0.5`, `mt-0.5` (2px) | 18 | 0 | langkah 4px |
| `text-[10px]`, `text-[11px]` | 24 | 0 | `text-xs` (12px), satu-satunya ukuran kecil di skala |
| `text-3xl` pada teks | 1 | 0 | `text-2xl`; dua pemakaian tersisa ukuran ikon |
| `text-lg` pada teks | 25 | 0 | `text-base`; 16 pemakaian tersisa semuanya ukuran ikon |
| `font-medium` (500) | 154 | 5 | 600 untuk label, sesuai aturan bobot |

Lima `font-medium` yang tinggal berada di `components/nav-link`, `components/responsive-nav-link`,
dan `components/auth-session-status`; ketiga file itu ikut dibuang 2026-10-16, jadi `font-medium`
sekarang nol di seluruh view, dan angka 5 pada tabel di atas adalah keadaan sebelum pembuangan.
Yang masih memakai nilai di luar langkah sekarang hanya ukuran, bukan jarak, dan masing-masing
punya alasan: titik status `w-2.5 h-2.5` (bentuk, bukan ritme), bidang sentuh `w-11 h-11` dan
`min-h-11` (44px adalah ambang sentuh yang sekarang jadi aturan di bagian 7, bukan langkah
spacing), `border-l-[3px]` pada
kartu dashboard (bar aksi 3px, bagian 6), dan `text-[28px]` pada angka kartu dashboard (ukuran
yang ditulis pemilik di bagian 6; 24px terlalu kecil untuk jadi penanda, 30px adalah `text-3xl`
yang dilarang di atas). Tinggi kontrol sendiri sudah bukan pengecualian lagi: view yang
dirutekan pernah punya **76 kontrol** yang membuka tag-nya dengan `py-2` (36px) dan semuanya
sudah `min-h-11`. Rinciannya 59 dari penyisiran pertama dan 17 tambahan setelah pemindainya
dibetulkan, regex pemotong tag berhenti di tanda `>` dalam ekspresi Blade seperti
`$project->seo_requirements`, jadi 17 kontrol tersembunyi. Dua angka itu hasil pemindaian,
dan pemindainya masih kurang: ia cuma mengenali `py-2`, padahal kontrol bisa rapat lewat `py-1`
(24px), lewat `py-3` dengan `text-xs` (40px), lewat `h-4 w-4`, atau lewat checkbox. Sebelas
kontrol seperti itu ditutup dengan tangan, dan sebagian baru kelihatan setelah cabang Alpine yang
tersembunyi dipaksa tampil di peramban: dua tombol Upload dan dua input file di cabang
screenshot, tiga aksi proposal (`Download Proposal PDF`, `Open Live Demo`, `Buat WordPress siap
install`), tiga kotak centang layanan di `/clients/create` yang dinaikkan dari `h-4 w-4` ke
`h-5 w-5` (bidang sentuhnya kartu label, terukur 45px), dan tautan "Back to Clients" di
`/clients/{id}`. Total sekarang **145** `min-h-11` di seluruh view, dan pemindaian ulang
melaporkan nol kontrol di bawah 44px pada view yang dirutekan; enam sisanya ada di
`layouts/navigation.blade.php` dan `welcome.blade.php`. Kedua file itu dibuang 2026-10-16, dan
pemindaian ulang atas **seluruh** view (bukan hanya yang dirutekan) sekarang melaporkan **nol**
kontrol di bawah 44px dari total **152** `min-h-11`.

Rangka halaman sekarang benar-benar datang dari satu tempat. `layouts/app.blade.php` memakai
`px-6 py-6` dan `max-w-[1400px]`; di viewport 1440px konten mulai pada x = 280px (sidebar 256px
+ padding 24px), terukur di browser, dan tidak ada halaman yang horizontal overflow.
`pages/workshop.blade.php` dulu menambah `max-w-7xl mx-auto` sendiri; belakangan ternyata perbaikan
itu mendarat di view mati (`/workshop` cuma redirect ke `/project-workspace`), dan file itu ikut
dibuang 2026-10-16.

Viewport ponsel akhirnya diukur, bukan diasumsikan. Di lebar 522px (CSS), dashboard: tanpa
overflow horizontal, empat kartu statistik jadi satu kolom, dan nol elemen interaktif di bawah
44px. Angka 44px itu sebelumnya dilanggar tiga kali di shell dan di dashboard, dan semuanya
sudah ditutup: tombol "Menu" dan penutup sidebar `min-h-11 min-w-11`, avatar menu akun dibungkus
tombol `w-11 h-11` (gambarnya tetap 32px), item Logout `min-h-11`, dan tautan teks kecil
("Lihat semua", "Omzet bulan ini", aksi di state kosong) `min-h-11` dengan `px-1` agar bidang
sentuhnya melebar tanpa mengubah warna atau ukuran hurufnya. Kontrol form ikut dinaikkan ke
lantai yang sama: `x-text-input` dan ketiga tombol Breeze sekarang `min-h-11`, dan border input
pindah ke `border-slate-500` seperti yang diminta bagian 4 (diukur: `#64748b` di atas putih,
4,76:1, lolos ambang non-teks 3:1). Setelah itu penyisiran yang sama menaikkan 76 kontrol lain
di view yang dirutekan, lalu sebelas kontrol lagi ditutup tangan karena pemindainya tidak
melihatnya (paragraf di atas), jadi pemindaian statis sekarang melaporkan nol kontrol di bawah
44px.
Toolbar filter Finance, Projects, Request, dan Workspace ikut lebih tinggi di desktop; itu
konsekuensi yang dipilih pemilik, bukan hasil memampatkan.

Cara memeriksanya: semua view yang bisa dirutekan dipindai terhadap enam penolak, palet
terlarang, jarak di luar langkah, `rounded-2xl` dan `rounded` polos, teks `slate-400/300`,
gradien, serta keluarga warna asing (`rose`/`teal`/`violet`). Hasilnya nol untuk keenamnya di
jalur aplikasi, dan setelah kerangka mati dibuang (bagian 8 item 1) nol itu berlaku untuk seluruh
view: `dark:` 0, `indigo-` 0, `gray-` 0, `rose-`/`teal-`/`violet-` 0, `rounded-2xl` 0.

Satu temuan gerbang yang lebih besar dari urusan warna: enam halaman auth (`login`, `register`,
`forgot-password`, `reset-password`, `confirm-password`, `verify-email`) memakai
`<x-guest-layout>` yang memuat `@vite`, sementara `public/hot` masih menunjuk dev server Vite di
`[::1]:5173` yang tidak berjalan. Halaman-halaman itu datang ke browser tanpa CSS sama sekali,
jadi palet mana pun di dalamnya tidak pernah benar-benar terlihat. Kelimanya dipindah ke
`layouts/auth.blade.php` (kepala yang sama dengan aplikasi: Tailwind CDN, Inter, JetBrains Mono)
dan ditulis ulang dengan token bagian 4: kartu `rounded-xl border-slate-200` tanpa `shadow-xl`,
kontrol `rounded-md`, `rose-700` jadi `red-700`, `p-8`/`space-y-5`/`py-2.5`/`pl-3.5`/`h-14`
keluar, dan `opacity-90` pada teks putih di atas `brand-600` hilang. Hitungannya, `HEAD` ke
sekarang, atas enam berkas itu: `gray-*` 16 jadi 0, `dark:` 10 jadi 0, `indigo-*` 2 jadi 0,
`rounded-2xl` 2 jadi 0, `py-2.5` 2 jadi 0, `rose-*` 2 jadi 0, `font-medium` 4 jadi 0,
`space-y-5` 1 jadi 0. `rounded-xl` naik 3 jadi 6 dan itu radius kartu yang memang boleh. Copy Inggris Breeze diganti
Indonesia supaya cocok dengan sisa aplikasi. `/login` sekarang terukur 0 kegagalan kontras dan
tanpa overflow di 1440px, 522px, 390px, dan 320px (kartunya menyusut ke 257px tanpa memotong
apa pun); `x-guest-layout` dan kelas PHP `GuestLayout` tidak dipakai siapa pun dan ikut dibuang.

Satu efek samping yang menemukan bug lama: `/profile/edit` adalah satu-satunya halaman yang
memakai `<x-app-layout>` (komponen Breeze) alih-alih `@extends('layouts.app')`. Komponen itu
mengabaikan `<x-slot name="header">`, dan karena tidak ada `@section('title')`, judul tab
browser halaman itu selalu jatuh ke default `"Dashboard"` bahkan ketika pengguna sedang berada
di halaman Profile. Halaman itu dipindah ke `@extends` + `x-page-header` + `x-card`; slot header
yang mati tidak pernah dirender siapa pun. Copy Inggrisnya dibiarkan (bagian 8), tapi paletnya
sudah ikut: `gray-*`, `dark:` dan `indigo-*` di tiga partial profile habis.

## 6. Halaman Dashboard: bentuknya tetap, isinya yang bisa ditindak

Keputusan pemilik 2026-10-16: baris empat kartu statistik bertahan sebagai bentuk, karena
operator sudah hafal posisinya. Temuan 12 audit 003 tidak lagi dianggap utang bentuk, tapi
tetap utang isi, dan aturannya begini:

- Satu kartu hanya boleh ada kalau angkanya bisa diklik ke tempat angkanya diselesaikan.
  Kartu tanpa tujuan itu dihapus, bukan dirapikan.
- "Project Types" (tiga angka tanpa keputusan) masuk kategori dihapus.
- Urutan kartu ditentukan dari apa yang jatuh tempo hari ini, bukan dari jenis data.
- Isi tiap kartu: label 12px `slate-600`, angka 28px monospasi `slate-900`, satu baris 12px
  yang menyebut aksinya ("3 pengingat belum terkirim", bukan "Total reminder").
- Kartu yang menuntut tindakan memakai bar kiri 3px dari bagian 2; kartu yang hanya informasi
  tidak boleh memakainya.

Ini satu-satunya penyimpangan ritme yang diizinkan di dashboard (RHYTHM 2), dan penyimpangan
itu sendiri yang menjadi penanda "ada yang perlu dikerjakan".

Dijalankan 2026-10-16. Empat kartu yang tersisa, urut dari yang paling mendesak: Invoice Lewat
Jatuh Tempo (`/finance?tab=invoices&status=overdue`), Pengingat Gagal Terkirim
(`/finance?tab=reminders&status=failed`), Request Menunggu (`/projects?status=request`), Project
Berjalan (`/projects?status=in_progress`). Baris "Jenis Project" beserta tiga angkanya dihapus,
begitu juga kartu total proposal; `Proposal` tidak lagi diimpor `DashboardController`, dan
kolom omzet bulan ini pindah jadi satu baris di kartu Pipeline yang mengarah ke Reports. Kartu
"Project Berjalan" tidak pernah memakai bar kiri: jumlahnya informasi, bukan pekerjaan.

Yang dijaga bukan warnanya tapi dua aturannya, dan itu jadi tes: `tests/Feature/DashboardActionCardsTest.php`
menuntut keempat href kartu ada di HTML, menuntut `border-l-brand-600` muncul nol kali saat
semua nol dan dua kali saat ada invoice lewat jatuh tempo plus satu request, dan menuntut
"Jenis Project" serta "Backlink" tidak muncul lagi. Empat-empatnya terukur di browser juga:
tiap tautan mendarat di daftar yang sudah tersaring dan barisnya cocok dengan angka di kartu.
Angka tile pipeline sengaja tetap `text-2xl`; aturan 28px hanya untuk empat kartu paling atas,
kalau tidak, dua ukuran angka yang sama akan bersaing di layar yang sama.

## 7. Boleh dan jangan

Boleh:
- Warna yang lahir dari keadaan data: badge merah hanya kalau memang lewat jatuh tempo.
- Angka monospasi di semua kolom yang bisa dibandingkan.
- Satu aksen per layar, dan aksen itu harus bisa dijelaskan kenapa dia di situ.
- Mengubah dokumen ini lewat pemilik produk, dengan alasan tertulis.

Jangan:
- Bidang sentuh lebih kecil dari 44px pada kontrol yang bisa dipakai di ponsel. Tautan teks
  kecil boleh tetap kecil hurufnya, tapi pembungkusnya yang harus melebar (`min-h-11`), bukan
  hurufnya yang dibesarkan. Lantainya berlaku untuk semua kontrol yang bisa dirutekan, toolbar
  filter di desktop termasuk (diputuskan pemilik 2026-10-16).
- Gradien ungu-biru, cyan, pink, atau neon apa pun sebagai perlakuan warna utama. Sudah
  diperingatkan oleh filter R-01; sekarang juga dilarang oleh identitas produk.
- `gray-*`, `zinc-*`, `indigo-*`, dan kelas `dark:` di view aplikasi. Tema terang adalah
  keputusan, dan tidak ada pengalih tema, jadi jalur gelap adalah kode yang tidak terverifikasi.
- Teks `slate-400`/`slate-300`, `brand-500` untuk teks kecil, `slate-500` di atas latar halaman.
- Em dash di teks yang dilihat pengguna.
- Empat warna berbeda dalam satu baris kartu tanpa keadaan data yang memaksa.
- Mengimpor palet dari produk lain, termasuk yang ada di folder unduhan, tanpa menuliskan
  alasannya di sini lebih dulu.

## 8. Belum diputuskan (menunggu pemilik)

1. **Sudah dikerjakan 2026-10-16, keputusan pemilik.** Dua belas berkas yang tidak pernah dirender
   dibuang dari working tree (masih tercatat git, jadi bisa dipulihkan):
   `layouts/navigation.blade.php`, `layouts/guest.blade.php`, `components/nav-link`,
   `components/responsive-nav-link`, `components/dropdown`, `components/dropdown-link`,
   `components/auth-session-status`, `welcome.blade.php`, `dashboard.blade.php`,
   `pages/workshop.blade.php`, plus kelas PHP `GuestLayout` dan `AppLayout`. Sebelum dibuang,
   enam berkas pertama yang disebut di paragraf ini mengukur 61 `dark:`, 14 `indigo-`, dan
   59 `gray-`; angka lama dokumen (56 / 23 / 153) sudah tidak cocok dengan kode waktu itu.
   Yang **tidak** ikut dibuang: `components/*-button`, `text-input`, `input-label`,
   `input-error`, `modal` (masih dipakai `/profile/edit`) dan `profile/partials/*`.
   Rute `pages.workshop` dibiarkan: dia redirect yang masih melayani bookmark lama.
   Verifikasi setelah hapus: `view:cache` bersih, suite tetap 3 failed / 525 passed,
   `tests/Feature/Auth` 21 passed (bukti enam halaman auth tidak bergantung `layouts/guest`),
   `grep` tidak menemukan satu pun rujukan ke kelas yang dihapus, dan `/`, `/profile/edit`,
   `/workshop` dirender normal di peramban. Catatan penting: `/profile/edit` sudah lama pindah
   ke `@extends('layouts.app')`, jadi `AppLayout.php` benar-benar yatim sebelum dihapus.
   Sisa urusan item ini cuma bahasa, dan komponen yang bertahan sudah ikut disisir ke token
   bagian 4: primer `brand-600`, sekunder `border-slate-300`, tanpa `uppercase tracking-widest`,
   tanpa `dark:`. Halaman `/profile/edit` sendiri masih berbahasa Inggris; copy Inggris lain yang
   masih kelihatan datang dari framework, bukan dari view (`These credentials do not match our
   records.` di `/login`, dan pesan `session('status')` reset password), karena proyek ini tidak
   punya folder `lang/` sama sekali. Menerjemahkannya berarti menambah `lang/id.json`, keputusan
   terpisah.
2. Rute `service-packages` (resource, terdaftar di `routes/web.php:183`) mengembalikan 500:
   `ServicePackageController` memanggil `view('service-packages.index')` dan `...form`, tetapi
   folder `resources/views/service-packages/` tidak pernah ada di riwayat git. Rusak sebelum
   restyle ini, di luar bagiannya, dan belum disentuh.
3. Selesai diukur 2026-10-16. Dashboard, tiga halaman auth, dan delapan belas alamat internal
   (tujuh belas halaman berbeda: `/workshop` hanya redirect ke `/project-workspace`, lihat item 7)
   (Finance dengan keempat tabnya, Projects dan detailnya, Workspace, Workshop, Reports, CRM
   daftar dan detail, form request, Profile beserta edit, detail invoice, langganan tambah dan
   edit) dibuka di 1440px dan 375px lewat iframe, dan setiap cabang `hidden` / `x-show` dipaksa
   tampil lebih dulu supaya markup kondisional ikut kena. Hasil terukur di kedua lebar: nol teks
   di bawah ambang AA, nol kontrol di bawah 44px, nol overflow horizontal, dan peramban hanya
   melaporkan satu warning, `cdn.tailwindcss.com should not be used in production`. Yang masih
   belum: 375px diukur lewat iframe, bukan viewport nyata seperti dashboard dan `/login`, dan
   `service-packages` tidak ikut karena rutenya 500 (item 2).
4. Sudah dicek ulang lewat peramban (item 3) dan meninggalkan satu temuan bagian 4 yang sengaja
   belum dikerjakan: **10 kontrol toolbar filter** (empat `select` dan satu input pencarian di
   tab langganan Finance, tiga `select` di Reminders, `select` status di Projects, `select`
   pemilih project di Workspace) memakai `bg-slate-50` tanpa border sendiri, terukur
   `border-top-width: 0px`, dan pembungkus pencariannya cuma `border-slate-200` = 1,17:1
   terhadap putih. Bagian 4 minta `border-slate-500` (4,76:1) untuk bidang yang boundary-nya
   harus dikenali tanpa label, jadi ini kurang. Perbaikannya satu kelas per kontrol, tapi efeknya
   seluruh toolbar aplikasi jadi lebih berborder, jadi tidak dikerjakan diam-diam. Field di form
   yang tiap bidangnya sudah ada label (`finance/subscription-form`, `projects/form`) dibiarkan
   tanpa border: aturan bagian 4 itu spesifik untuk kontrol tanpa label.
5. Sudah diputuskan pemilik 2026-10-16: naikkan semua ke 44px. Bagian 4 menulis `px-4 py-2`
   (±36px) dan bagian 7 menuntut lantai 44px, dan pemilik memilih yang terakhir. `min-h-11`
   ditulis di 76 kontrol yang tersisa, jadi seluruh toolbar aplikasi lebih tinggi, desktop
   termasuk. Empat tempat yang sudah lebih dulu pindah: `x-text-input`, ketiga tombol Breeze,
   kontrol shell (Menu, penutup sidebar, avatar, Logout), dan seluruh form auth. Hitungan kontrol
   yang ditutup dan cara mengukurnya ada di bagian 5 dan item 3.
6. Sudah dijalankan tanpa keputusan baru, tinggal dirender: tombol "Analyze Now" di
   `projects/form` pindah dari `bg-brand-500 text-white` ke `bg-brand-600` dengan hover
   `bg-brand-700` (bagian 2 melarang `brand-500` untuk teks sekecil itu, dan versi
   `pages/request` sudah lama `brand-600`), dan tombol Logout di `/profile` pindah ke gaya
   sekunder `border-slate-300` karena bagian 4 melarang aksi memakai warna status. Copy Inggris
   di `pages/request` dan `projects/form` tidak disentuh: kedua form itu memang berbahasa
   Inggris seluruhnya, dan menerjemahkan satu tombol akan membuat satu layar dua bahasa.
7. Dua temuan dari judul tab, keduanya terukur di peramban dan tidak bisa diperbaiki sendiri.
   (a) `/workshop` mengembalikan "Workspace | SiteFlow" bukan karena judulnya salah, tapi karena
   `WebsiteBuilderController::proposalWorkshop` adalah redirect ke `pages.project-workspace`
   (`routes/web.php:61`). Konsekuensinya yang serius: `pages/workshop.blade.php` tidak dirujuk
   dari controller mana pun, jadi view itu mati dan sudah dibuang bersama item 1. Bagian 5 pernah
   mencatat perbaikan `max-w-7xl` di file itu, dan perbaikan itu nyata di file yang tidak pernah
   dirender. (b) `/profile/edit` berjudul "Profile | SiteFlow", sama persis dengan `/profile`,
   jadi dua tab tidak bisa dibedakan dari riwayat. Copy Inggris di judul ada di `/clients/create`
   ("Request Order") dan `/finance` ("Billing & Finance"); itu bagian keputusan `lang/` item 1.

## 9. Cara memakai dokumen ini

Agen yang menyentuh UI membaca bagian 0, 2, 3, 4, lalu menjalankan filter antislop sebagai
penolak, bukan sebagai sumber arah. Pasangan warna baru apa pun diukur dengan
`contrast-check.py` sebelum dikirim, dan angkanya ditulis ke dalam catatan perubahan.
Dokumen ini tidak boleh dipakai untuk membenarkan perubahan yang tidak diminta: kalau kode
bertentangan dengan bagian 2, 3, 4, atau 5, yang salah adalah kode, dan itu butuh izin
perubahan dari pemilik.
