<?php

namespace App\Services;

use App\Models\MockupCandidate;
use App\Models\Project;
use App\Support\MockupRules;
use App\Support\MockupWorkspace;
use App\Support\PageSnapshot;
use RuntimeException;

/**
 * Menjalankan empat babak pembuatan satu kandidat: rencana desain, kritik
 * diri atas rencana itu, markah HTML, lalu kritik diri atas hasil render-
 * an (mata sendiri lewat potret, maks 2 ronde). Setiap babak melanjutkan
 * percakapan yang sama di loop alat, jadi otak bisa lihat jejak babak
 * sebelumnya. Artefak akhir: index.html + preview.png di folder kandidat,
 * kalimat ide tersimpan di baris, status naik ke awaiting_review.
 */
class MockupCandidateRunner
{
    public function __construct(
        private MockupToolLoop $loop,
        private MockupToolBox $tools,
        private MockupWorkspace $workspace,
    ) {}

    public static function make(MockupCandidate $candidate): self
    {
        $workspace = new MockupWorkspace();
        $client = new MockupOpenAiClient();
        $tools = new MockupToolBox($workspace->candidatePath($candidate));

        return new self(
            MockupToolLoop::make($client, $tools),
            $tools,
            $workspace,
        );
    }

    public function run(Project $project, MockupCandidate $candidate): MockupCandidate
    {
        $candidate->update(['folder' => $this->workspace->relativeFolder($candidate)]);

        $instructions = MockupRules::load();
        $input = $this->satuPesan(MockupBrief::forRondeSatu($project));

        [$rencanaMentah, $input] = $this->loop->act($instructions, $input);
        $rencana = $this->rencanaSah($rencanaMentah);

        $input[] = $this->pesan($this->tugasKritik($rencanaMentah));
        [$rencanaAkhir, $input] = $this->loop->act($instructions, $input);
        $rencana = $this->rencanaSah($rencanaAkhir);

        $candidate->update(['idea_sentence' => $rencana['kalimat_ide']]);

        $input[] = $this->pesan($this->tugasMarkah($rencanaAkhir));
        $this->loop->act($instructions, $input);

        // Babak 4: nilai hasil render-an sendiri. Ronde berikutnya hanya
        // jalan kalau ronde sebelumnya masih mengubah berkas — itu tanda
        // ada yang diperbaiki dan perlu dipotret ulang. Maksimal 2 ronde.
        $input[] = $this->pesan($this->tugasKritikPotret());
        $rondeKritik = 0;

        do {
            if ($rondeKritik > 0) {
                $input[] = $this->pesan($this->tugasKritikPotretUlang());
            }

            $tulisSebelum = $this->tools->jumlahTulis();
            [, $input] = $this->loop->act($instructions, $input);
            $rondeKritik++;
        } while ($rondeKritik < 2 && $this->tools->jumlahTulis() > $tulisSebelum);

        $candidate->update(['critique_rounds' => $rondeKritik]);

        $index = $this->workspace->resolve($candidate->folder) . '/index.html';

        if (!is_file($index)) {
            throw new RuntimeException('Kandidat ' . $candidate->candidate_number . ' selesai tanpa index.html.');
        }

        $preview = $this->workspace->resolve($candidate->folder) . '/preview.png';
        (new PageSnapshot())->captureFile($index, $preview);

        $candidate->update([
            'png_path' => $candidate->folder . '/preview.png',
            'status' => MockupCandidate::STATUS_AWAITING_REVIEW,
        ]);

        return $candidate;
    }

    /**
     * @return array{kalimat_ide: string, palet: list<string>, pasangan_huruf: string, konsep_tata_letak: string, elemen_tanda_tangan: string}
     */
    private function rencanaSah(string $teks): array
    {
        $awal = strpos($teks, '{');
        $akhir = strrpos($teks, '}');

        if ($awal === false || $akhir === false || $akhir <= $awal) {
            throw new RuntimeException('Rencana desain tidak sah: bukan JSON.');
        }

        $data = json_decode(substr($teks, $awal, $akhir - $awal + 1), true);

        if (!is_array($data) || !is_string($data['kalimat_ide'] ?? null) || trim($data['kalimat_ide']) === '') {
            throw new RuntimeException('Rencana desain tidak sah: kalimat_ide kosong.');
        }

        $palet = $data['palet'] ?? null;
        $paletJahat = is_array($palet)
            ? array_filter($palet, fn ($warna) => !is_string($warna) || !preg_match('/^#[0-9a-fA-F]{6}$/', $warna))
            : [1];

        if (!is_array($palet) || count($palet) < 4 || count($palet) > 6 || $paletJahat !== []) {
            throw new RuntimeException('Rencana desain tidak sah: palet harus 4-6 warna #hex.');
        }

        foreach (['pasangan_huruf', 'konsep_tata_letak', 'elemen_tanda_tangan'] as $kunci) {
            if (!is_string($data[$kunci] ?? null) || trim($data[$kunci]) === '') {
                throw new RuntimeException('Rencana desain tidak sah: ' . $kunci . ' kosong.');
            }
        }

        return $data;
    }

    private function tugasKritik(string $rencanaMentah): string
    {
        return "Ini rencana desainku:\n\n" . $rencanaMentah
            . "\n\nKritik diri: kalau brief-nya proyek website lain, apakah rencana ini masih kelihatan sama?"
            . " Perbaiki yang generik. Balas HANYA dengan JSON rencana final, tanpa teks lain.";
    }

    private function tugasMarkah(string $rencanaAkhir): string
    {
        return "Ini rencana desain finalku:\n\n" . $rencanaAkhir
            . "\n\nSekarang tulis markahnya berdasarkan rencana final itu."
            . " Pakai tulis_berkas untuk membuat index.html di folder kerja ini,"
            . " mengikuti seluruh aturan markah (penanda gambar __EXITO_IMAGE:slot,"
            . " penanda tautan __EXITO_PAGE_URL:telepon|email|whatsapp, variabel CSS di kepala)."
            . " Kalau perlu gambar, cari lalu unduh dulu. Setelah index.html tertulis, balas: selesai";
    }

    private function tugasKritikPotret(): string
    {
        return "Sekarang nilai hasilnya sendiri. Pakai lihat_potret untuk melihat render index.html,"
            . " bandingkan dengan aturan desain (larangan K dan terukur M),"
            . " dan perbaiki markah lewat tulis_berkas kalau ada yang melanggar."
            . " Kalau sudah bersih, balas: selesai";
    }

    private function tugasKritikPotretUlang(): string
    {
        return "Kamu baru mengubah berkas. Potret ulang (lihat_potret) dan pastikan perbaikanmu sendiri"
            . " tidak melanggar aturan. Kalau sudah bersih, balas: selesai";
    }

    /**
     * @return array{type: string, role: string, content: list<array{type: string, text: string}>}
     */
    private function pesan(string $teks): array
    {
        return [
            'type' => 'message',
            'role' => 'user',
            'content' => [['type' => 'input_text', 'text' => $teks]],
        ];
    }

    /**
     * @return list<array{type: string, role: string, content: list<array{type: string, text: string}>}>
     */
    private function satuPesan(string $teks): array
    {
        return [$this->pesan($teks)];
    }
}
