## Aturan kerja proyek

Sebelum membaca atau mengubah kode, baca `sebelum memulai project atau mengubah code agent suruh baca ini dahulu.md` sampai selesai. File itu adalah aturan utama proyek untuk semua agent.

Agent harus toleran terhadap brief yang belum lengkap, variasi data lama, dan perubahan arah dari pengguna. Kerjakan bagian yang aman dengan asumsi yang dinyatakan jelas. Tanyakan hanya jika jawabannya mengubah perilaku produk atau keputusan yang sulit dibatalkan. Jangan mengulang pertanyaan yang sudah dijawab, memaksa refactor di luar cakupan, atau menimpa perubahan pengguna.

Semua catatan handoff pekerjaan kode dicatat berurutan di `reports/HANDOFF.md`, bukan di `docs/`. Setiap entri mencatat tanggal, ringkasan perubahan/perilaku, file utama, keputusan penting, perintah pemeriksaan dan hasil sebenarnya, serta keterbatasan. Jangan menulis hasil tes yang belum dijalankan.

Lakukan review diff dan tes sesuai aturan proyek sebelum menyelesaikan pekerjaan. Laporan akhir harus menjelaskan file dan perilaku yang berubah serta hasil pemeriksaan.
