<!-- antislop:start -->
## antislop
For UI, copy, people, mobile layout, or code comments work, read these installed skill files directly (use these paths even if a same-named global skill exists):
- Core filter, always on: `antislop`: `.codex/skills/antislop/SKILL.md`
- UI / visual: `antislop-ui`: `.codex/skills/antislop-ui/SKILL.md`
- Copy & text: `antislop-copywriting`: `.codex/skills/antislop-copywriting/SKILL.md`
- People: `antislop-human`: `.codex/skills/antislop-human/SKILL.md`
- Mobile / responsive: `antislop-layoutmobile`: `.codex/skills/antislop-layoutmobile/SKILL.md`
- Code comments: `antislop-code`: `.codex/skills/antislop-code/SKILL.md`
Before starting, follow the core's "Two Usage Modes" section in strict order: explicit session instruction first, then global preference, then ask. A session instruction always wins. For a resolved mode, say `antislop active: <mode> (session override).` or `antislop active: <mode> (global preference).` once before presenting findings or making edits, using the actual mode and source. Acknowledging the user's request without naming the source does not replace this notice.
Only an explicit choice of antislop during or after selects a session mode. A request to review, audit, or avoid file edits does not select a mode; read the global preference in that case. Another skill's mode does not select antislop's mode.
If the mode is unresolved, ask during/after and end the response; wait for the answer before any UI review, planning, or concept. For read-only tasks, put the active-mode notice only at the start of the final answer, never in progress messages. For editing tasks, announce before the first edit and omit it from the final answer.
To update antislop later: `npx antislop-ai --update`, or run `npx antislop-ai` and pick Overwrite them.
<!-- antislop:end -->

## Aturan kerja proyek

Sebelum membaca atau mengubah kode, baca `sebelum memulai project atau mengubah code agent suruh baca ini dahulu.md` sampai selesai. File itu adalah aturan utama proyek untuk semua agent.

Agent harus toleran terhadap brief yang belum lengkap, variasi data lama, dan perubahan arah dari pengguna. Kerjakan bagian yang aman dengan asumsi yang dinyatakan jelas. Tanyakan hanya jika jawabannya mengubah perilaku produk atau keputusan yang sulit dibatalkan. Jangan mengulang pertanyaan yang sudah dijawab, memaksa refactor di luar cakupan, atau menimpa perubahan pengguna.

Semua catatan handoff pekerjaan kode dicatat berurutan di `reports/HANDOFF.md`, bukan di `docs/`. Setiap entri mencatat tanggal, ringkasan perubahan/perilaku, file utama, keputusan penting, perintah pemeriksaan dan hasil sebenarnya, serta keterbatasan. Jangan menulis hasil tes yang belum dijalankan.

Lakukan review diff dan tes sesuai aturan proyek sebelum menyelesaikan pekerjaan. Laporan akhir harus menjelaskan file dan perilaku yang berubah serta hasil pemeriksaan.
