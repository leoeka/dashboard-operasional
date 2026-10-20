# Penghematan biaya dan token satu proposal

Status 2026-10-20: **rancangan, nol baris kode berubah.** Angka gambar diambil dari tabel harga resmi
OpenAI pada hari dokumen ini ditulis; harga bisa bergeser, jadi cek ulang sebelum dipakai memutuskan.

Besok lanjut dari sini: **A dulu** (satu baris `.env`, uji 3 foto), lalu **F** (pencatat pemakaian,
menunggu izin pemilik), baru C dan B. Belum ada satu pun yang diizinkan dikerjakan. Tidak ada yang
di-commit sejak `e6ca58f`, dan dokumen ini sendiri belum di-commit.

## 1. Inventory: yang benar-benar dibayar satu kali proposal

| Tahap | Panggilan | Model | Yang dibawa |
|---|---|---|---|
| target_market | 1 | Gemini | user story |
| competitor_research | 0 AI | - | 3 halamanCompetitor, tiap halaman dipotong 6000 karakter |
| website_content | 1 | gpt-5.6 | instruksi 11.890 byte + teks pesaing s/d 18.000 karakter, plafon keluaran **32.768 token** |
| design_profile (bila ada referensi) | 1 | gpt-5-mini | 2 tangkaran, `detail: low` |
| design_blueprint | **3** | gpt-5-mini | instruksi 9.425 byte + sitemap penuh + konteks brand + **2 tangkaran `detail: high`** |
| mockup_assets - generate | **s/d 27** | gpt-image-1 | foto, 1536x1024 atau 1024x1536, kualitas medium |
| mockup_assets - review | **s/d 27** | gpt-4.1-mini | foto yang baru dibuat |
| proposal_document, PNG, demo, ZIP | 0 | - | render lokal |

Yang penting: **theme WordPress sudah 0 token.** Sisa biaya ada di tiga baris terakhir.

## 2. Harga resmi yang dipakai menghitung

`gpt-image-1` (gpt-image-1-mini di kurung siku):

| Kualitas | 1024x1024 | 1024x1536 / 1536x1024 |
|---|---|---|
| low | $0,011 [$0,005] | $0,016 [$0,006] |
| medium | $0,042 [$0,011] | **$0,063 [$0,015]** |
| high | $0,167 [$0,036] | $0,25 [$0,052] |

Semua rasio komposisi yang ada di `CompositionSpec` (16:9, 3:2, 4:3, 5:4, 4:5, 3:4) dipetakan
`photoSize()` ke sisi panjang 1536, jadi **setiap foto hari ini jatuh di tier $0,063**, tidak ada yang
di tier kotak.

## 3. Tagihan foto hari ini

27 foto x $0,063 = **$1,70 per proposal**, dan slot yang ditolak review diulang sampai 2 percobaan, jadi
yang buruk mendekati **$3,40**. Panggilan teks (3 blueprint + 1 profil) di gpt-5-mini hanya sen-sean;
penulis konten belum bisa dihitung karena harga gpt-5.6 tidak saya temukan di tabel resmi dan aplikasinya
tidak pernah mencatat pemakaian.

Kesimpulan yang harus diingat: **yang bikin cepat habis itu foto, bukan blueprint.**

## 4. Tuas, urut dari yang paling murah diusahakan

### A. Ganti model gambar. Nol baris kode.

Isi `.env`: `OPENAI_IMAGE_MODEL=gpt-image-1-mini`. Biaya per foto $0,063 ke $0,015, **hemat 76%**
($1,70 ke $0,41) tanpa mengubah apa pun yang lain. Yang diuji lebih dulu: render 3 foto subjek yang
sama di kedua model, lihat sebagai satu halaman mockup. Kalau mini lolos, ini penghematan terbesar yang
paling murah dikerjakan.

### B. Generate hanya hero saat draf, sisanya baru dibayar waktu klien memilih. SUDAH DIKERJAKAN.

27 foto jadi 3 untuk draf, dan penuh hanya untuk satu kandidat yang dipilih: 3 + maksimal 8 = 11.
Ikut memperbaiki dua hal lain:
- Waktu. Anggaran 300 detik dipakai bersama untuk ketiga kandidat, sementara 27 foto pada 5 gambar/menit
  butuh 5,4 menit. Hari ini **proposal pertama selalu pulang dengan foto bolong**. 3 foto makan 36 detik,
  dan top-up 8 foto makan 96 detik - keduanya di dalam anggaran.
- Wajib-foto. Slot bagian halaman tidak ikut dibuat saat draf, jadi kandidat tidak mungkin berstatus
  `degraded` hanya karena bagian tengahnya belum difoto.

Bentuk yang dikerjakan (beda dari rencana awal, karena satu jaminan lama tidak boleh kalah):
- `MockupAssetService::generateForCandidate(..., bool $heroOnly = false)` - hanya hero yang jadi slot.
- `GenerateMockupGptService::renderCandidates()` memanggilnya dengan `heroOnly: true`.
- `GenerateMockupGptService::completeCandidate()` dipanggil `WebsiteBuilderController::approveProposal()`
  sebelum manifest dibangun: membeli sisa foto kandidat terpilih lalu **merender ulang PNG-nya**, supaya
  gambar yang disetujui tetap gambar yang dikirim. `reusePersisted()` membuat hero draf tidak dibayar dua kali.
- Top-upnya **best effort**, dan ini bukan jalan pintas: approval dulu dibuat mati saat OpenAI bermasalah
  (dulu harus kirim PNG ke GPT vision), dan jaminan "approval jalan tanpa panggilan AI apa pun" tidak
  boleh dikorbankan demi foto. Kalau kuota habis, kandidat dikembalikan persis seperti yang dilihat klien -
  manifest draf dan PNG draf, yang memang versi tanpa foto yang dirender ZIP. Foto yang terlanjur dibayar
  tetap di disk dan dipakai lagi pada approval berikutnya. Tidak pernah menyimpan setengah (6 dari 9):
  itu desain ketiga yang tidak pernah disetujui siapa pun.

Sisa bagian halaman tidak menjadi kotak kosong: ia sudah merender versi tanpa fotonya sendiri (monogram,
keping warna) dan hasilnya sama di PNG, demo, dan ZIP. Yang hilang hanya stok foto di bagian tengah.

### C. Tangkaran referensi jangan dibayar tiga kali. Sekitar 2 baris.

Tiga panggilan blueprint masing-masing menempelkan 2 screenshot dengan `detail: high`, padahal
`extractDesignProfile()` sudah **membaca screenshot yang sama sekali** lalu mengubahnya jadi teks
(komposisi hero, karakter tipografi, kadar whitespace, dan 9 sifat lain) yang juga ikut dikirim.
Jadi deskripsinya sudah sampai ke desainer; gambarnya redundan. Buang gambar dari panggilan desainer,
atau turunkan ke `low`. Tidak mengubah hasil desain, hanya berhenti membayar untuk hal yang sudah
dibayar.

### D. Turunkan plafon keluaran penulis konten. SEBAGIAN: plafon sudah, `reasoning effort` belum.

`website_content_max_output_tokens` 32.768 -> **16.384** di `config/services.php` + fallback di
`GenerateMockupGptService`, dengan tes Feature yang membaca permintaan sebenarnya. Alasannya biaya, bukan
panjang tulisan: pada gpt-5.6 plafon itu adalah batas tagihan satu panggilan, jadi satu panggilan tidak
boleh lebih dari ± $0,49 padahal bisa sampai ± $0,98.
Catatan koreksi: angka "± 3,5 kB keluaran" yang saya pakai waktu mengusulkan ini adalah **blueprint desain**,
bukan keluaran penulis konten - dan belum ada satu pun instance terukur, karena `pipeline_checkpoints`
stage `website_content` payload-nya 0 baris di DB dev. Karena itu plafonnya dipotong setengah, bukan
diturunkan ke 4.096. Sisanya (`reasoning effort low`) baru aman sesudah F mengukur panjang jawaban nyata.

### E. Dua opsi, bukan tiga. Nol baris kode.

`OPENAI_MOCKUP_CANDIDATE_COUNT=2` memangkas 33% dari panggilan blueprint dan 9 foto. Ini keputusan
produk, bukan teknis: klien memilih dari dua, bukan tiga.

### F. Pencatat pemakaian. 5 baris, dan ini yang pertama.

Tidak ada satu panggilan pun yang menyimpan `usage`, DB dev `pipeline_checkpoints` 0 baris, `proposals`
kosong. Tanpa itu, A sampai E di atas adalah aritmetika dari tabel harga, bukan pengukuran, dan tidak ada
cara membuktikan sebuah perubahan berhasil.

## 5. Yang tidak boleh disentuh saat mengejar hemat

- `reusePersisted()` - penahan supaya retry tidak membayar foto yang sudah jadi.
- Pembekuan blueprint sebelum satu foto pun dibayar.
- Paritas PNG = demo = ZIP.
- Review visual untuk hero (gerbang yang menahan foto cacat sampai ke klien).

## 6. Urutan kerja yang saya usulkan

1. F (pencatat) lalu jalankan satu proposal nyata -> kita punya angka asli, bukan taksiran.
2. A (ganti model gambar, uji visual 1 render).
3. C (buang gambar redundan).
4. B (hero saja) + tes bahwa kandidat tidak lagi dianggap cacat.
5. E dan D hanya kalau kamu memang mau.

Urutan yang benar-benar diambil 2026-10-20, karena saldo akun nol dan A/C/F hanya bisa dibuktikan dengan
memakai uang: **D (plafon) -> B (hero-only) -> F (pencatat) -> lalu C -> A**. D dan B selesai dan terbukti
lewat tes yang me-fake API gambar, nol token. F adalah syarat supaya angka-angka di dokumen ini berhenti
jadi aritmetika tabel harga.

Sumber harga: [gpt-image-1](https://developers.openai.com/api/docs/models/gpt-image-1),
[gpt-image-1-mini](https://developers.openai.com/api/docs/models/gpt-image-1-mini),
[OpenAI pricing](https://developers.openai.com/api/docs/pricing).
