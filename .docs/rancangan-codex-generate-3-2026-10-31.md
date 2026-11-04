# Rancangan: tombol "buat 3 kandidat" lewat Codex CLI — 2026-10-31

Status: **rancangan untuk dicoret pemilik**. Nol berkas kode disunting.

> 2026-11-04: saklar `services.mockup.engine` dan seluruh jalur API sudah
> dicabut. Lihat `.docs/catatan-jalur-api-dipensiunkan-2026-11-04.md`.

Dasar:
- Trial Codex CLI 2026-10-31 di `C:\Program Codding\uji-codex\kandidat-1` (1 kandidat, model `gpt-6-luna`).
- Uji Apache Laragon 2026-10-31 (probe sementara, sudah dihapus).
- `.docs/catatan-ide-codex-ditutup-2026-10-21.md` — tiga tembok lama dan urutan membukanya.
- Cadangan jalur API: `storage/app/backup/api-mockup-sebelum-codex-20261031.zip` (48 berkas, commit `9c47212`).

## 1. Angka yang sudah terukur

| Hal | Angka | Sumber |
| --- | --- | --- |
| Satu kandidat (3 halaman + 2 foto) | **12 menit 46 detik**, 52.654 token | log trial 09:49:15 → 10:02:01 |
| Tiga kandidat berurutan | **± 40 menit** | hitungan, bukan ukur |
| PHP di Apache memanggil Codex | **berhasil**, 14,2 detik, 7.895 token, menulis berkas | probe `codex exec` lewat localhost |
| Akun proses Apache | `desktop-brqoass\dimas`, PATH memuat folder npm | probe |
| Satu jawaban sepele "ok" | **42.158 token masukan** (30.592 dari cache) | probe `--json` |
| Foto yang dibuat tapi gagal disalin | 2 dari 2; 9 gambar dibuat untuk 2 slot | log trial |
| Foto utuh di cache | 2,3–2,9 MB, 1.536×1.024, penutup berkas ada | `~/.codex/generated_images/<id-sesi>/` |
| Id sesi = nama folder cache | sama persis | trial dan probe |

Tiga tembok lama: (1) PATH — **tidak ada di Laragon** karena Apache berjalan sebagai akun pemilik; (2) status login di galat (stderr) — tetap berlaku, sudah ditangani; (3) butuh pekerja antrean — dihindari dengan proses latar terpisah.

## 2. Arah

Satu tombol di halaman Workspace proyek: **"Buat 3 kandidat"**. Tombol itu tidak menunggu. Ia menitipkan pesanan, lalu sebuah proses kecil di luar web mengerjakan tiga kandidat **berurutan** lewat Codex CLI. Hasilnya masuk ke gerbang tim yang sudah ada — tiga kartu kandidat, tombol setujui, proposal PDF, build tema. Semua yang sesudah gerbang tidak berubah.

Yang berubah hanya **siapa yang menulis desain**: dari panggilan API di dalam antrean menjadi Codex CLI di proses terpisah. Foto ikut dibuat Codex, dan disalin oleh kode kita — bukan oleh model.

Jalur API tetap utuh (cadangan ZIP sudah ada). **Bawaan sementara = `codex`** untuk uji tangan 2026-10-31; setelah uji selesai, kembalikan bawaan ke `api` sampai jalur Codex lolos tiga build nyata. Pemilihan mesin ada di satu kunci konfigurasi (`services.mockup.engine`, atau `MOCKUP_ENGINE` di `.env`), bukan di dua tombol. Jalan pulang = ubah satu nilai ke `api`, tanpa `git revert`.

## 3. Selisih hari ini vs yang dituju

| Bagian | Hari ini | Yang dituju |
| --- | --- | --- |
| Pemicu | `store()` membuat 3 baris lalu mengirim 3 pekerjaan antrean | `store()` membuat 3 baris lalu melepas **satu** runner |
| Penulis desain | `MockupCandidateRunner` → API OpenAI (`gpt-5-mini`) | `codex exec` dengan brief yang sama |
| Foto | `cari_gambar` (Google Custom Search) + `unduh_gambar` | dibuat Codex, disalin runner dari cache |
| Progres | kartu membaca status baris kandidat | **tetap sama**, ditambah denyut runner |
| Gerbang tim, PDF, build tema, importer | — | **tidak disentuh** |
| Buat ulang satu kandidat | `regenerate` → antrean | tahap 4 rancangan ini, setelah tiga kandidat terbukti |

## 4. Alur baru

1. Pemilik menekan tombol. `store()` tetap membuat tiga baris kandidat dalam satu transaksi (pola yang sudah ada, termasuk penolakan klik kedua).
2. Kalau mesin = `codex`: tulis `pesanan.json` di meja kerja proyek, lepas runner, balas "tiga kandidat sedang dibuat" lalu kembali. Tidak ada pekerjaan berat di dalam permintaan web.
3. Runner (`php artisan mockup:codex {project}`) mengambil kunci berkas. Kalau kunci sudah dipegang, ia berhenti dan mencatat "sudah ada yang berjalan".
4. Untuk kandidat 1, 2, 3 berurutan:
   - siapkan folder `candidate-<id>`, tulis `brief.txt` dari bahan yang sama dengan jalur API (rencana desain, palet, aturan K-9, penanda gambar dan tautan);
   - jalankan `codex.cmd exec --json` dengan folder kerja kandidat itu;
   - baca baris `--json` untuk denyut dan pemakaian token;
   - setelah proses selesai: salin foto (bagian 6), periksa HTML dan penanda, potret dengan Chromium yang sudah dipakai sekarang;
   - perbarui baris kandidat: `awaiting_review` bila lolos, `failed` bila tidak.
5. Halaman membaca `mockup-status` yang sudah ada. Isinya ditambah: kandidat ke berapa, tahap, dan **detik sejak denyut terakhir**.
6. Setelah ketiga baris siap, gerbang tim berjalan seperti biasa.

## 5. Berkas dan status

```
folder temp sistem (di luar repo)/
  <project>-<candidate>-<acak>/
    brief.txt         brief yang dibaca Codex
    index.html, pages/…, foto-slot.txt
        (disalin ke candidate-<id>/ setelah Codex selesai)

storage/app/mockup-work/<project>/
  codex/
    pesanan.json      proyek, jumlah kandidat, waktu pesan
    status.json       keadaan runner (lihat di bawah)
    kunci.lock        kunci berkas, berisi pid
    runner.log        keluaran galat runner
  candidate-<id>/     satu folder per baris kandidat (id, bukan nomor urut)
    brief.txt         arsip brief
    events.jsonl      keluaran --json apa adanya
    codex.log         galat Codex apa adanya
    foto-slot.txt     catatan jalur foto dari model (bila ada)
    index.html, pages/…, hero.png, galeri-1.png, preview.png
```

Catatan: folder kandidat memakai id baris (sama dengan jalur API, lewat `MockupWorkspace`), jadi artefak lama tetap bisa dibaca. Pesanan/status/kunci tetap di `codex/` karena berlaku untuk seluruh proyek. **Folder kerja Codex sengaja di luar repo**: kalau dijalankan di dalam `dashboard-operasional`, Codex ikut membaca `AGENTS.md`/`DESIGN.md` milik aplikasi ini lalu berhenti minta pilihan mode desain (kejadian 2026-10-31 11:26: 30 detik, 0 token, tanpa satu berkas pun).

`status.json` minimal:

```json
{
  "keadaan": "jalan",
  "kandidat_ke": 2,
  "jumlah": 3,
  "tahap": "menulis halaman",
  "denyut": "2026-10-31T11:20:14+07:00",
  "token": 48120,
  "pesan": ""
}
```

Aturan status:
- `denyut` diperbarui setiap baris `--json` dan paling lambat tiap 10 detik.
- Keadaan: `menunggu`, `jalan`, `selesai`, `gagal`, `berhenti`.
- `gagal` dan `berhenti` wajib membawa `pesan` yang bisa dibaca pemilik (bukan kode galat mentah).

## 6. Foto: jangan lewat model

**Yang gagal kemarin, tepatnya.** Model menulis gambar sebagai teks base64 sepanjang ±3,5 juta karakter di dalam satu perintah shell. Windows menolak: `CreateProcessAsUserW … 206` (nama/argumen terlalu panjang) lalu `Set-Content: PathTooLongException`. Hasilnya satu berkas terpotong (tanpa penutup) dan satu berkas 0 byte. Ini bukan sandbox, dan bukan model yang tidak bisa menggambar — gambarnya bagus dan utuh di cache.

**Aturan rancangan: model tidak pernah menyalin isi gambar.** Dua lapis:

1. **Manifest dari model.** Brief meminta, setelah tiap gambar dibuat, satu perintah salin pendek (`Copy-Item <jalur> <slot>.png`) — bukan isi base64 — lalu menulis `foto-slot.txt` berisi `slot: nama-berkas`. Runner memeriksa setiap berkas bernama: tanda awal PNG dan penutup `IEND` harus ada, ukuran di atas 50 KB.
2. **Pemulihan oleh runner.** Kalau manifest tidak ada atau tidak lengkap, runner menyalin dari `~/.codex/generated_images/<id-sesi>/`, dengan id dari baris pertama `--json` (`thread.started`). Urutan berkas = urutan slot di brief; hanya berkas yang utuh dipakai; jumlah yang kurang dicatat sebagai "foto tidak lengkap".

Kalau setelah dua lapis masih ada slot tanpa foto sah, kandidat ditandai `failed` dengan pesan "foto tidak lengkap (n dari m)", bukan ditampilkan setengah jadi. Kartu yang setengah jadi membuat orang berhenti percaya layar.

**Penanda foto vs potret (temuan 2026-10-31 11:43-11:50).** Kandidat 22 dan 23 selesai dengan dua foto sah (2,5 MB dan 2,3 MB), tetapi `preview.png` kartunya kosong: potret diambil dari `index.html` mentah yang `src`-nya masih `__EXITO_IMAGE:hero`. Kandidat 24 menulis penanda itu sebagai teks telanjang, jadi kalimat `__EXITO_IMAGE:hero` ikut tercetak di halaman. Aturannya sekarang:

- Berkas arsip tetap memuat penanda; pembangun tema (`WpThemeBuilder`) yang menggantinya saat build.
- `PageSnapshot` memotret **salinan sementara** di folder yang sama, dengan tiap penanda ditukar nama berkas nyata (`hero.png`), lalu salinan itu dihapus. Salinan di folder yang sama menjaga rujukan relatif.
- Penanda wajib berada di dalam `src="..."`, `srcset`, atau `url(...)`. Runner menolak penanda yang berdiri sendiri sebagai teks ("ditulis sebagai teks, bukan pada src").
- Brief Codex mencontohkan `<img src="__EXITO_IMAGE:hero" alt="...">`; `resources/ai/aturan-mockup.md` menyebut aturan yang sama untuk jalur API.

**Yang belum terbukti:** apakah model menerima jalur berkas gambar dari alat bawaannya. Di trial kemarin ia menerima base64 dan tidak pernah menyebut jalur. Lapis 1 diuji lebih dulu di tahap 2; kalau model memang tidak bisa menyalin dengan perintah pendek, lapis 2 yang menjadi andalan dan urutan slot harus diperjelas di brief ("buat foto hero lebih dulu, lalu galeri-1").

## 7. Aturan proses

- **Jalur penuh** ke `codex.js` (`%APPDATA%\npm\node_modules\@openai\codex\bin\codex.js`), dijalankan lewat `node`; jangan bergantung pada PATH meski sekarang ada. Windows tidak bisa `CreateProcess` berkas `.cmd` langsung, karena itu yang dipanggil `node`.
- `-s workspace-write`, `--skip-git-repo-check`, `-C` folder kandidat, `--json`, dan **stdin `NUL`** supaya tidak ada `Reading additional input from stdin...`.
- Dilepas dari permintaan web lewat `proc_open` PHP (`bypass_shell`), keluaran dialihkan ke berkas, **pid disimpan di kunci**. Itu memberi dua hal: halaman tidak menunggu, dan proses bisa dimatikan.
- **Batas keras 20 menit per kandidat.** Lewat itu: `taskkill /PID <pid> /T /F`, status `gagal` dengan alasan "melewati batas waktu".
- **Denyut basi = berhenti.** Kalau `denyut` lebih tua dari 5 menit sementara keadaan masih `jalan`, layar menulis "berhenti" dan menawarkan tombol coba lagi. Ini pelajaran kejadian 03:48: layar tidak boleh diam-diam menunggu selamanya.
- **Satu kunci berkas** per proyek; klik kedua dijawab "sedang berjalan" tanpa membuat proses baru.
- **Folder kerja di luar repo.** `-C` menunjuk folder temp sistem, bukan `storage/`. Hasil disalin ke folder kandidat setelah selesai. Ini mencegah Codex membaca `AGENTS.md`/`DESIGN.md` aplikasi dan berhenti minta mode.
- **Tombol "Buat ulang N kandidat"** di bawah grid membuat ulang semua kandidat yang gagal/berhenti dalam satu klik; kandidat yang sudah siap ditinjau tidak disentuh. Tetap satu runner dan satu kunci.
- Token dicatat per kandidat dari `turn.completed`; ditampilkan supaya pemakaian kuota terlihat, bukan ditebak.

## 8. Layar

Keadaan yang wajib ada, semuanya nyata (tidak ada kontrol mati):

| Keadaan | Yang terlihat |
| --- | --- |
| Belum ada pesanan | tombol "Buat 3 kandidat" |
| Baru dilepas | "menyiapkan kandidat 1 dari 3" |
| Berjalan | kandidat ke-n, tahap dalam bahasa biasa, "bergerak 4 detik lalu", token terpakai |
| Denyut basi | "berhenti sejak 6 menit lalu" + tombol coba lagi |
| Selesai | tiga kartu kandidat seperti sekarang |
| Gagal satu | kartu kandidat itu kosong dengan alasan; dua lain tetap tampil |
| Gagal semua | pesan penyebab + tombol coba lagi |

Tidak ada bilah kemajuan palsu. Angka "detik sejak denyut" dipakai karena ia jujur: ia membedakan "lambat" dari "mati".

## 9. Pengamanan dan jalan pulang

- Jalur API tidak disentuh. Saklar `services.mockup.engine` (`api` bawaan, `codex` bila diaktifkan). Rollback = ubah satu nilai, tanpa `git revert`.
- Cadangan zip tetap disimpan; cara pulih ada di `MANIFEST.txt` di dalamnya.
- Brief berisi data klien dan dikirim ke layanan ChatGPT lewat langganan. Ini sama sifatnya dengan jalur API, tetapi harus disebut di layar sekali: "desain dibuat di luar server, memakai akun ChatGPT mesin ini".
- Tidak ada kredensial yang dibaca, ditampilkan, atau disalin oleh rancangan ini. Folder `~/.codex` hanya dibaca untuk gambar.

## 10. Yang tidak diubah

Gerbang tim dan aturan setuju/tolak; proposal PDF; penjaga jalur PNG; build tema WordPress; importer halaman/menu/media; pemeriksa aturan (K-7, K-9, M-1/M-2, M-11); tes yang sudah ada. Kandidat dari Codex harus lolos pemeriksa yang sama — kalau tidak lolos, kandidat gagal, bukan aturannya yang dilonggarkan.

## 11. Biaya dan waktu

- Tidak ada tagihan API: pemakaian masuk kuota langganan ChatGPT. Batasnya tidak terlihat dari aplikasi; token per kandidat dicatat supaya bisa dibandingkan antar-run.
- Tiap kandidat membawa ±40 ribu token muatan tetap (dokumen bawaan Codex), jadi tiga kandidat ≈ 150 ribu token meski hasilnya pendek.
- Di PC lokal: PC harus menyala dan tidak tidur selama ±40 menit. Kalau PC mati di tengah, status menjadi "berhenti" dan kandidat bisa diulang. Di server: mesin menyala terus, tetapi antrean, CPU, dan kuota menjadi urusan bersama semua proyek (bagian 12).
- CLI bisa berubah sewaktu-waktu; versi dicatat di `status.json` supaya kegagalan aneh bisa dilacak.

## 12. Kalau aplikasi dipindah ke server

Pertanyaan pemilik 2026-10-31: "kalau sudah di deploy ke server bagaimana". Jawabannya dibagi tiga: yang hilang, yang tetap, yang baru.

**Yang hilang (khas Windows):**

- Batas panjang baris perintah. Di Linux argumen tidak dibatasi 32 ribu karakter, jadi kegagalan base64 kemarin tidak akan terulang — meski aturan "model tidak menyalin isi gambar" tetap dipakai, karena menyalin piksel lewat argumen itu boros di mana pun.
- `start /B` dan `Start-Process`. Diganti `systemd` atau `nohup`.
- Tembok PATH. Di server, PHP berjalan sebagai `www-data` yang tidak punya folder npm — tetapi rancangan ini memang selalu memakai jalur penuh, jadi tidak terpengaruh.

**Yang tetap sama:**

- Codex harus **terpasang dan login di server** dengan akun ChatGPT. Satu kali login lewat kode perangkat (browser di mesin lain), hasilnya tersimpan di folder akun yang menjalankan Codex.
- Sesi bisa kedaluwarsa atau dicabut. Layar wajib membedakan "belum masuk" dari "sedang bekerja"; tombol tidak boleh berbohong.
- Brief klien tetap dikirim ke layanan ChatGPT di bawah akun itu.

**Yang baru dan harus diputuskan:**

1. **Akun proses.** PHP (`www-data`) tidak boleh memakai folder login milik akun lain. Rancangan: runner dijalankan `systemd` sebagai akun khusus (mis. `siteflow`) yang memegang login Codex; PHP hanya menulis `pesanan.json`. Ini justru **menghapus** tembok lama nomor 3: `systemd` menyalakan ulang proses sendiri, berbeda dengan `php artisan queue:work` yang dulu harus dinyalakan tangan setiap kali.
2. **Banyak pesanan sekaligus.** Diukur 2026-10-31: dua `codex exec` dijalankan bersamaan, keduanya berhasil — 8,3 detik dan 8,9 detik, tanpa galat batas laju. Jadi **dua klien bisa dikerjakan bersamaan**; pertanyaannya bukan "bisa atau tidak", melainkan berapa banyak sebelum kuota dan CPU terganggu. **Satuan antrean = satu proyek (tiga kandidat), bukan satu kandidat** — tiga kandidat di dalam satu proyek selalu berurutan, jadi satu pesanan memakan mesin ±40 menit. Rancangan: batas jumlah runner bersamaan ada di konfigurasi — **1 di PC lokal, 2 di VPS setelah diukur**; pesanan sisanya antre berurutan dengan posisi tampil di layar. Kandidat di dalam satu proyek tetap berurutan (bagian 4): tiga kandidat sekaligus berarti tiga kali lonjakan kuota dan tiga pekerjaan yang harus diawasi.
3. **Bersih-bersih cache gambar.** Satu kandidat meninggalkan ±9 gambar × 2,5 MB ≈ 22 MB di folder cache. Tiga kandidat ≈ 65 MB per proyek. Runner wajib menghapus gambar yang tidak dipakai setelah foto tersalin dan diperiksa.
4. **Batas hosting.** Shared hosting tidak bisa: tidak ada Node, tidak ada proses latar, tidak ada akses keluar bebas. Minimum **VPS Linux** dengan Node 20+, Codex CLI, Chromium (sudah dipakai untuk potret), dan ruang disk beberapa GB.
5. **Risiko langganan jadi risiko produksi.** Di PC ini, langganan mati berarti fitur berhenti sementara. Di server, ia berarti fitur berhenti untuk semua klien sekaligus, dan satu akun pribadi menjadi mesin produksi untuk banyak klien. Ini alasan keputusan nomor 4 (jalur API disimpan) menjadi penting, bukan sekadar sopan.

**Yang berubah pada rencana bertahap:** tahap 1–4 tetap sama di kedua tempat; yang berbeda hanya cara melepas proses (tahap 3) — `Start-Process` di Windows lokal, unit `systemd` di server. Rancangan tidak mengikat ke salah satu.

## 13. Rencana bertahap

1. **Runner tanpa layar.** `mockup:codex` menjalankan satu kandidat, menulis `status.json`, mematikan diri saat lewat batas. Diuji dari terminal, bukan dari web.
2. **Pemulihan foto.** Manifest + cache, verifikasi PNG, uji dengan satu kandidat sungguhan. Ini tahap penentu; kalau gagal, rancangan berhenti di sini dan jalur API tetap.
3. **Tombol dan polling.** `store()` melepas runner, status menampilkan denyut, kunci bekerja, kill bekerja. Diuji dengan proses palsu (tanpa biaya).
4. **Tiga kandidat penuh dari satu klik**, lalu masuk gerbang. Ukur waktu dan token sebenarnya; bandingkan dengan jalur API.
5. **Buat ulang satu kandidat** lewat Codex (kalau tahap 4 memuaskan), lalu keputusan apakah jalur API dipensiunkan.

Setiap tahap: tes Pest memakai sqlite `:memory:` (jangan pernah MySQL), review CodeRabbit untuk berkas baru sebelum lanjut.

## 14. Yang belum terbukti

- Apakah model bisa menyalin gambar dengan perintah pendek (lapis 1) — diuji di tahap 2.
- Apakah proses latar 13 menit bertahan stabil saat halaman ditutup dan dibuka lagi — diuji di tahap 3.
- Apakah tiga kandidat berurutan benar-benar ±40 menit di mesin ini — baru ada hitungan.
- Batas kuota langganan per hari — tidak terbaca dari aplikasi.
- Login Codex di server Linux tanpa layar (kode perangkat) — belum diuji di mesin mana pun.
- Apakah `systemd` + akun khusus di server bisa menjalankan Codex dengan sandbox penuh — belum diuji.
- Batas aman jumlah runner bersamaan untuk **generate nyata** — yang terukur baru dua tugas sepele; generate 13 menit dengan 9 gambar per kandidat belum dicoba berbarengan.

## 15. Keputusan pemilik

Diperbarui 2026-10-31 setelah pemilik membaca rancangan:

| # | Pertanyaan | Keputusan |
| --- | --- | --- |
| 1 | 40 menit per klik, PC harus nyala | **diterima** — menunggu giliran di antrean dianggap wajar |
| 2 | Kandidat dengan foto tidak lengkap | **ditolak** dengan alasan, tidak ditampilkan setengah jadi |
| 3 | Batas keras 20 menit per kandidat | **ya** |
| 4 | Jalur API setelah tiga build nyata lolos | **disimpan** sebagai cadangan |
| 5 | Antrean: batasi atau lepas semua | **antrean dipertahankan** (2026-10-31). Batas 1 di PC lokal; di server dinaikkan ke 2 hanya setelah satu uji generate nyata berjalan bersamaan |

Dua akibat dari keputusan 2: kartu kandidat yang fotonya kurang tampil kosong dengan alasan "foto tidak lengkap (n dari m)", dan tombol buat ulang berlaku untuk kandidat itu saja — bukan mengulang ketiganya.
