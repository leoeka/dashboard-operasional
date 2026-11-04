<?php

namespace App\Services;

use App\Models\MockupCandidate;
use App\Models\Project;

/**
 * Brief untuk satu kandidat saat mesin = codex.
 *
 * Versi ramping dengan anggaran waktu (batas runner 20 menit, rata-rata
 * proses 13 menit). Foto tidak pernah melewati model: model hanya mencatat
 * path asli hasil alat gambar, lalu runner (MockupCodexPhotos) menyalin dan
 * memeriksanya.
 *
 * Jika $referensiPath diisi (mis. 'referensi.png' yang disalin ke folder
 * kerja), Codex meniru gambar itu dan tidak membuat mockup sendiri.
 */
class MockupCodexBrief
{
    /**
     * @param  list<string>  $ideSebelumnya  kalimat ide kandidat lain yang sudah jadi
     */
    public static function build(
        Project $project,
        MockupCandidate $candidate,
        int $jumlah,
        array $ideSebelumnya = [],
        ?string $referensiPath = null
    ): string {
        $ref = $referensiPath !== null && $referensiPath !== '';
        $b = [];

        $b[] = '# DESIGN BRIEF — WEBSITE MOCKUP';
        $b[] = 'Kandidat ' . $candidate->candidate_number . ' dari ' . $jumlah;
        $b[] = '';
        $b[] = 'Kamu adalah Senior UI Designer dan Frontend Developer yang teliti pada detail visual.';
        $b[] = 'Kerjakan mandiri sampai selesai. Jangan bertanya balik.';
        $b[] = '';

        // 00. WAKTU
        $b[] = '## 00 — Anggaran waktu dan urutan prioritas';
        $b[] = 'Batas keras proses ini 20 menit. Targetkan selesai dalam 14 menit. Kerjakan berurutan dan jangan melompat:';
        $b[] = '1. Rencana singkat dan design tokens (maks 2 menit).';
        $b[] = '2. Foto: buat `hero` lebih dulu, lalu foto lain yang benar-benar dipakai. Total maksimal 5 foto.';
        $b[] = '3. `index.html` lengkap dengan semua foto terpasang.';
        $b[] = '4. Berkas wajib (langkah 07).';
        $b[] = '5. QA visual (langkah 06) hanya jika sisa waktu cukup.';
        $b[] = 'Jika waktu hampir habis, lengkapi berkas wajib dulu. Hasil lengkap yang sederhana lebih baik daripada hasil bagus yang tidak selesai.';
        $b[] = '';

        // 01. ATURAN
        $b[] = '## 01 — Aturan mutlak';
        $b[] = '1. `<head>` wajib berisi `<meta charset="utf-8">` dan `<meta name="viewport" content="width=device-width, initial-scale=1">`. Simpan file sebagai UTF-8.';
        $b[] = '2. Boleh memakai Google Fonts lewat `<link>`: satu font heading dan satu font body, dengan fallback stack.';
        $b[] = '3. Tidak boleh ada elemen yang saling menimpa, teks terpotong, atau horizontal overflow.';
        $b[] = '4. Hanya gunakan data dari proyek. Harga, alamat, statistik, testimoni, dan penghargaan yang tidak ada di data proyek jangan ditampilkan.';
        $b[] = '5. Warna dan font hanya dari design tokens yang kamu tetapkan.';
        $b[] = '';

        // 02. BISNIS
        $b[] = '## 02 — Data proyek';
        $b[] = implode("\n", MockupBrief::dataProyek($project));
        $b[] = '';
        $b[] = 'Tentukan singkat: bisnis apa, target pelanggan, CTA utama, dan satu pembeda bisnis.';
        $b[] = '';

        // 03. REFERENSI / TOKENS
        if ($ref) {
            $b[] = '## 03 — Desain referensi (acuan mengikat)';
            $b[] = 'Gambar referensi sudah tersedia di: `' . $referensiPath . '`';
            $b[] = 'Buka dan lihat gambarnya. Jangan membuat konsep atau mockup baru, dan jangan mengubah warna, font, atau komposisi.';
            $b[] = 'Tulis `design-tokens.json` dari gambar itu: hex warna (background, teks, aksen, tombol), font heading dan body (Google Fonts terdekat), ukuran heading desktop dan mobile, tinggi hero, lebar container, padding section, radius tombol, gaya kartu.';
            $b[] = 'Urutan section di `rencana.json` harus sama dengan urutan di gambar. Buat foto dengan subjek dan crop yang mirip foto di referensi.';
            $b[] = 'Tulis ke `desain-referensi.txt`:';
            $b[] = 'design: ' . $referensiPath;
            $b[] = 'Gambar referensi tidak boleh dipasang sebagai foto atau background website.';
        } else {
            $b[] = '## 03 — Arah desain dan design tokens';
            $b[] = 'Buat SATU gambar mockup halaman utama (16:9) dengan alat gambar bawaan sebagai acuan visual: navigasi, hero full-bleed dengan overlay gradien dari sisi teks, strip fitur 3-4 kolom tepat di bawah hero, section konten dengan komposisi bervariasi, portofolio atau layanan, CTA, footer. Foto, tipografi, dan spacing harus menyatu seperti presentasi studio desain.';
            if (!empty($ideSebelumnya)) {
                $b[] = 'Kandidat lain sudah memakai ide berikut. Pilih arah yang jelas berbeda (warna, tipografi, komposisi hero):';
                foreach ($ideSebelumnya as $ide) {
                    $b[] = '- ' . $ide;
                }
            }
            $b[] = 'Lalu tulis `design-tokens.json` dari mockup itu: hex warna, font Google Fonts, ukuran heading, tinggi hero, lebar container, padding section, radius tombol, gaya kartu.';
            $b[] = 'Catat lokasi mockup ke `desain-referensi.txt`:';
            $b[] = 'design: <jalur-lengkap-hasil-alat>';
            $b[] = 'Mockup ini hanya acuan, bukan foto yang dipasang ke website.';
        }
        $b[] = '';
        $b[] = 'Buat `rencana.json` (JSON valid) dengan kunci: nama_proyek, kalimat_ide, creative_direction, konsep_visual, konsep_hero, palet_warna, tipografi, daftar_bagian (tujuan tiap section), daftar_aset (slot dan fungsinya). Isi palet_warna dan tipografi dari design-tokens.json.';
        $b[] = '';

        // 04. FOTO
        $b[] = '## 04 — Foto';
        $b[] = 'Tulis satu paragraf STYLE BIBLE (pencahayaan, lensa, color grade, suasana) dan sisipkan persis sama di setiap prompt gambar agar foto seragam. Contoh: "Photorealistic, warm natural daylight, 35mm lens, shallow depth of field, consistent warm color grade, no text, no logo, no watermark."';
        $b[] = '';
        $b[] = 'Aturan:';
        $b[] = '- Slot `hero` wajib. Nama slot lain bebas (misalnya `tentang`, `layanan-1`, `galeri-1`), total maksimal 5. Jangan membuat foto yang tidak dipakai.';
        $b[] = '- Setiap foto punya subjek berbeda dan berkas berbeda.';
        $b[] = '- Hero: sisakan area lapang atau gelap di sisi teks.';
        $b[] = '- Tidak ada teks, logo, atau watermark di dalam foto. Jangan memakai screenshot desain atau gambar dari internet.';
        $b[] = '- Gambar rancangan/mockup BUKAN foto website. Path-nya hanya boleh ada di `desain-referensi.txt`, tidak pernah di `foto-slot.txt`.';
        $b[] = '';
        $b[] = 'Prosedur untuk SETIAP foto, segera setelah dibuat:';
        $b[] = '1. Catat path ASLI hasil alat untuk foto itu. Jangan menyalin, memindahkan, atau mengganti nama berkasnya, karena runner memulihkan foto dari path asli tersebut.';
        $b[] = '2. Setiap slot harus punya path yang berbeda dari slot lain. Jangan menulis path foto sebelumnya untuk slot baru. Jika alat tidak memberi path baru, buat ulang fotonya.';
        $b[] = '3. Tulis ke `foto-slot.txt` dengan format `<nama-slot>: <jalur-hasil-alat>`. Nama slot huruf kecil, angka, tanda hubung, dan unik. Jika tidak ada path, tulis `-`.';
        $b[] = 'Jangan memasukkan isi gambar (base64, hex, piksel) ke perintah shell.';
        $b[] = '';

        // 05. WEBSITE
        $b[] = '## 05 — Implementasi `index.html`';
        $b[] = 'Buat `index.html` lengkap: HTML5 semantik, CSS di `<style>` pada `<head>`, JavaScript seperlunya. Salin design tokens ke CSS variables di `:root`. Bangun section demi section sesuai urutan di desain acuan dan pertahankan komposisi, warna, serta hierarkinya.';
        $b[] = '';
        $b[] = 'Resep teknis:';
        $b[] = '- Container: `max-width` sesuai tokens, `margin-inline: auto`, padding horizontal `clamp(20px, 5vw, 80px)`.';
        $b[] = '- Hero: `<img>` dengan `object-fit: cover` dan `object-position` diatur, overlay gradien dari sisi teks, tinggi 560-720px. Kolom teks `max-width` sekitar 560px agar headline tidak patah jadi baris pendek.';
        $b[] = '- Heading memakai `clamp()` dan `text-wrap: balance`. Judul tidak boleh menyentuh tepi layar.';
        $b[] = '- Strip fitur berada di alur normal tepat di bawah hero, bukan `position: absolute` yang bisa menimpa konten.';
        $b[] = '- Kartu portofolio atau layanan: CSS Grid dengan `aspect-ratio` sama, tinggi baris seragam, caption konsisten dan cocok dengan isi foto.';
        $b[] = '- Ikon memakai SVG inline bergaya garis tipis.';
        $b[] = '- Mobile (390px): hero ditumpuk, crop gambar disesuaikan, grid jadi 1-2 kolom, tombol tinggi minimal 44px, menu mobile berfungsi.';
        $b[] = '- Konten spesifik untuk bisnis ini, headline maksimal 3 baris, tanpa teks generik.';
        $b[] = '';
        $b[] = 'Pasang setiap foto dengan token: `<img src="__EXITO_IMAGE:nama-slot" alt="Deskripsi gambar">`. Nama token sama persis dengan nama slot. Jangan menebak path atau memakai URL eksternal.';
        $b[] = '';
        $b[] = 'WAJIB sebelum lanjut: setiap slot di `foto-slot.txt` harus muncul minimal sekali di `index.html` sebagai `<img src="__EXITO_IMAGE:slot">` atau `url(__EXITO_IMAGE:slot)`, bukan sebagai teks biasa. Verifikasi dengan pencarian teks (`Select-String` atau `grep`). Slot yang belum terpasang harus dipasang, atau dihapus dari manifest bila memang tidak dipakai. `hero` wajib terpasang.';
        $b[] = 'Untuk setiap tautan navigasi internal, buat `pages/<slug>.html` dengan head, tokens, header, dan footer yang sama. Tidak boleh ada tautan ke halaman kosong.';
        $b[] = '';

        // 06. QA
        $b[] = '## 06 — QA visual (jika waktu cukup)';
        $b[] = 'Jika browser headless (misalnya Playwright) sudah tersedia, ambil screenshot 1440px dan 390px lalu bandingkan dengan desain acuan. Jangan menginstal tool berat jika persiapannya lebih dari 2 menit.';
        $b[] = 'Periksa dan perbaiki: teks rusak (`â†’`, `Â·`), elemen saling menimpa, teks terpotong, headline patah jadi baris pendek, foto duplikat, caption tidak cocok dengan foto, font jatuh ke fallback, overflow horizontal di mobile.';
        $b[] = 'Satu putaran sudah cukup. Putaran kedua hanya jika waktu tersisa. Jika tidak bisa menjalankan browser, lakukan tinjauan kode terhadap daftar itu dan jangan mengklaim sudah menguji.';
        $b[] = '';

        // 07. BERKAS
        $b[] = '## 07 — Berkas akhir';
        $b[] = 'Tulis `ide.txt` berisi satu kalimat yang sama dengan `kalimat_ide` di rencana.json.';
        $b[] = 'Pastikan tersedia: index.html, rencana.json, design-tokens.json, ide.txt, desain-referensi.txt, foto-slot.txt, semua foto yang dipakai, dan pages/ bila ada halaman tambahan.';
        $b[] = 'Jangan menghapus file milik runner. Jangan menyatakan selesai jika berkas wajib belum ada atau `hero` belum terpasang di halaman.';
        $b[] = '';
        $b[] = 'Setelah seluruh pekerjaan selesai, balas satu kata: `selesai`.';

        return implode("\n", $b);
    }
}