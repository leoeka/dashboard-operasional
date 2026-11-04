<?php

namespace App\Http\Controllers;

use App\Models\ClientProposal;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Support\MockupCodexLauncher;
use App\Support\MockupCodexState;
use App\Support\MockupGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gerbang tim untuk kandidat mockup: memicu tiga kandidat pertama,
 * membuat ulang satu atau beberapa kandidat, menyetujui yang siap, dan
 * menyajikan gambar pratinjau. Pembuatan kandidat selalu dilepas sebagai
 * proses Codex di latar, tidak pernah dikerjakan di dalam request web.
 */
class MockupCandidateController extends Controller
{
    public function __construct(
        private MockupGate $gate,
        private MockupCodexLauncher $codex,
        private MockupCodexState $codexState,
    ) {
    }

    public function store(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless($this->isWebsiteProject($project), 403);

        // Batch hanya untuk set pertama. Cek dan buat digembok di satu
        // transaksi supaya dua klik berbarengan tidak sama-sama lolos dan
        // membuat dua batch; job dititipkan sesudah transaksi berhasil.
        [$batch, $alreadyCreated] = DB::transaction(function () use ($project) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            if ($locked->mockupCandidates()->exists()) {
                return [collect(), true];
            }

            $batch = collect([1, 2, 3])->map(
                fn ($number) => $locked->mockupCandidates()->create(['candidate_number' => $number])
            );

            return [$batch, false];
        });

        if ($alreadyCreated) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Kandidat untuk project ini sudah ada.'], 422);
            }
            return back()->with('error', 'Kandidat untuk project ini sudah ada. Gunakan tombol buat ulang pada kartu.');
        }

        $this->tulisPesanan($project, null);

        if (!$this->codex->launch($project)) {
            $pesan = $this->codexState->masalahMesin() ?: 'Runner Codex tidak bisa dilepas.';
            $batch->each(fn (MockupCandidate $candidate) => $candidate->update(['failure_reason' => $pesan]));

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            return back()->with('error', $pesan);
        }

        $pesan = 'Tiga kandidat sedang dibuat lewat Codex. Proses berjalan di latar, halaman ini boleh ditutup.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $pesan]);
        }

        return back()->with('success', $pesan);
    }

    public function regenerate(Request $request, Project $project, MockupCandidate $candidate): RedirectResponse|JsonResponse
    {
        abort_unless($this->isWebsiteProject($project), 403);
        abort_unless($candidate->project_id === $project->id, 404);

        // Pilihan klien mengunci set ini — baris yang dibangun adalah baris yang
        // klien pilih, jadi tidak boleh diganti lagi.
        $latestProposal = ClientProposal::where('project_id', $project->id)->latest('id')->first();
        if ($latestProposal?->chosen_candidate_id !== null) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Pilihan klien sudah terkunci.'], 422);
            }
            return back()->with('error', 'Pilihan klien sudah terkunci. Kandidat tidak bisa dibuat ulang.');
        }

        if ($this->codexState->terkunci($project)) {
            $pesan = 'Masih ada proses pembuatan kandidat lain. Coba lagi setelah proses itu selesai.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            return back()->with('error', $pesan);
        }

        // Runner mati meninggalkan draf tanpa alasan. Klaim basi untuk
        // nomor ini dilepas supaya baris pengganti bisa dibuat.
        $project->mockupCandidates()
            ->where('candidate_number', $candidate->candidate_number)
            ->where('status', MockupCandidate::STATUS_DRAFT)
            ->whereNull('failure_reason')
            ->update(['failure_reason' => 'Proses berhenti sebelum selesai.']);

        // Timbang baris TERBARU untuk nomor slot ini (bukan baris yang
        // diposting: kartu lama yang sudah digantikan masih bisa diposting),
        // digembok di satu transaksi supaya dua klik berbarengan tidak
        // membuat dua pengganti untuk slot yang sama.
        [$replacement, $stillRunning] = DB::transaction(function () use ($project, $candidate) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $current = $this->gate->currentSet($locked)->get($candidate->candidate_number);

            if ($current?->isRunning()) {
                return [null, true];
            }

            return [$locked->mockupCandidates()->create([
                'candidate_number' => $candidate->candidate_number,
            ]), false];
        });

        if ($stillRunning) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Kandidat ini masih sedang dibuat.'], 422);
            }
            return back()->with('error', 'Kandidat ini masih sedang dibuat.');
        }

        $this->tulisPesanan($project, $candidate->candidate_number);

        if (!$this->codex->launch($project, $candidate->candidate_number)) {
            $pesan = $this->codexState->masalahMesin() ?: 'Runner Codex tidak bisa dilepas.';
            $replacement->update(['failure_reason' => $pesan]);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            return back()->with('error', $pesan);
        }

        $pesan = 'Kandidat ' . $candidate->candidate_number . ' sedang dibuat ulang lewat Codex. Proses berjalan di latar.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $pesan]);
        }

        return back()->with('success', $pesan);
    }

    /**
     * Satu tombol untuk kandidat yang gagal atau berhenti. Kandidat yang
     * sudah siap ditinjau tidak disentuh supaya hasil bagus tidak hilang.
     */
    public function regenerateAll(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless($this->isWebsiteProject($project), 403);

        $latestProposal = ClientProposal::where('project_id', $project->id)->latest('id')->first();
        if ($latestProposal?->chosen_candidate_id !== null) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Pilihan klien sudah terkunci.'], 422);
            }
            return back()->with('error', 'Pilihan klien sudah terkunci. Kandidat tidak bisa dibuat ulang.');
        }

        $set = $this->gate->currentSet($project);
        $perlu = $set->filter(fn (MockupCandidate $c) => $c->hasFailed());
        $draf = $set->filter(fn (MockupCandidate $c) => $c->isRunning());

        if ($this->codexState->terkunci($project)) {
            $pesan = 'Masih ada proses pembuatan kandidat lain. Coba lagi setelah proses itu selesai.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            return back()->with('error', $pesan);
        }

        if ($draf->isNotEmpty()) {
            // Tidak terkunci berarti sisa draf ini milik runner yang mati.
            $project->mockupCandidates()
                ->whereIn('candidate_number', $draf->pluck('candidate_number'))
                ->where('status', MockupCandidate::STATUS_DRAFT)
                ->whereNull('failure_reason')
                ->update(['failure_reason' => 'Proses berhenti sebelum selesai.']);

            $perlu = $perlu->merge($draf);
        }

        if ($perlu->isEmpty()) {
            $pesan = 'Tidak ada kandidat yang perlu dibuat ulang.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            return back()->with('error', $pesan);
        }

        $nomor = $perlu->pluck('candidate_number')->unique()->sort()->values();

        $pengganti = DB::transaction(function () use ($project, $nomor) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            return $nomor->map(
                fn (int $number) => $locked->mockupCandidates()->create(['candidate_number' => $number])
            );
        });

        $this->tulisPesanan($project, null);

        if (!$this->codex->launch($project)) {
            $pesan = $this->codexState->masalahMesin() ?: 'Runner Codex tidak bisa dilepas.';
            $pengganti->each(fn (MockupCandidate $c) => $c->update(['failure_reason' => $pesan]));

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            return back()->with('error', $pesan);
        }

        $pesan = $nomor->count() . ' kandidat sedang dibuat ulang lewat Codex. Kandidat yang sudah siap tidak disentuh.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $pesan]);
        }

        return back()->with('success', $pesan);
    }

    public function approve(Project $project): RedirectResponse
    {
        abort_unless($this->isWebsiteProject($project), 403);

        // Cuma set sekarang yang disetujui; baris tergantikan yang kebetulan
        // masih menunggu tinjau tidak ikut berubah status.
        $toApprove = $this->gate->currentSet($project)
            ->filter(fn ($candidate) => $candidate->status === MockupCandidate::STATUS_AWAITING_REVIEW);

        if ($toApprove->isEmpty()) {
            return back()->with('error', 'Tidak ada kandidat yang siap disetujui.');
        }

        $project->mockupCandidates()
            ->whereIn('id', $toApprove->pluck('id'))
            ->update(['status' => MockupCandidate::STATUS_TEAM_APPROVED]);

        return back()->with('success', $toApprove->count() . ' kandidat disetujui tim. Langkah berikutnya: proposal PDF.');
    }

    public function preview(MockupCandidate $candidate): BinaryFileResponse
    {
        abort_unless($candidate->png_path !== null, 404);

        $path = storage_path('app/' . $candidate->png_path);

        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'image/png']);
    }

    /**
     * Endpoint status asinkron untuk polling progress bar mockup tanpa refresh browser.
     */
    public function status(Project $project)
    {
        abort_unless($this->isWebsiteProject($project), 403);

        $set = $this->gate->currentSet($project);
        $hasRunning = $set->contains(fn ($c) => $c->isRunning());
        $runner = null;
        $runnerStopped = false;

        $status = $this->codexState->visible($project);

        if ($status === null && $hasRunning) {
            $umur = $this->umurPesanan($project);
            $belumMulai = $umur === null || $umur <= 120;

            $status = [
                'keadaan' => $belumMulai ? MockupCodexState::KEADAAN_MENUNGGU : MockupCodexState::KEADAAN_BERHENTI,
                'kandidat_ke' => 0,
                'jumlah' => 3,
                'tahap' => $belumMulai ? 'menunggu runner' : 'berhenti',
                'token' => 0,
                'pesan' => $belumMulai ? '' : 'Runner tidak pernah mulai. Klik coba lagi pada kandidat yang kosong.',
            ];
        }

        if ($status !== null) {
            $runnerStopped = $hasRunning && in_array($status['keadaan'], [
                MockupCodexState::KEADAAN_BERHENTI,
                MockupCodexState::KEADAAN_GAGAL,
                MockupCodexState::KEADAAN_SELESAI,
            ], true);

            $runner = [
                'state' => $status['keadaan'],
                'candidate_index' => $status['kandidat_ke'] ?? null,
                'total' => $status['jumlah'] ?? null,
                'stage' => $status['tahap'] ?? '',
                'heartbeat_seconds' => $this->codexState->heartbeatAgeSeconds($project),
                'tokens' => $status['token'] ?? 0,
                'message' => $status['pesan'] ?? '',
                'alive' => $this->codexState->terkunci($project),
            ];

            if ($runnerStopped) {
                $hasRunning = false;
            }
        }

        $wpBuild = $project->wpBuild;
        $isBuilding = $wpBuild && in_array($wpBuild->status, [
            \App\Models\WpBuild::STATUS_PENDING,
            \App\Models\WpBuild::STATUS_BUILDING,
            \App\Models\WpBuild::STATUS_INSPECTION,
        ], true);

        $candidates = $set->map(function ($candidate) use ($runnerStopped) {
            $progress = $candidate->progress();
            $stopped = $runnerStopped && $candidate->isRunning();

            return [
                'id' => $candidate->id,
                'candidate_number' => $candidate->candidate_number,
                'status' => $candidate->status,
                'is_running' => $candidate->isRunning() && !$stopped,
                'stopped' => $stopped,
                'has_failed' => $candidate->hasFailed(),
                'failure_reason' => $candidate->failure_reason,
                'percent' => $progress['percent'],
                'message' => $progress['message'],
                'preview_url' => $candidate->png_path ? route('pages.projects.mockup.preview', $candidate) : null,
                'idea_sentence' => $candidate->idea_sentence,
            ];
        })->values();

        return response()->json([
            'has_running' => $hasRunning,
            'is_building' => $isBuilding,
            'runner' => $runner,
            'candidates' => $candidates,
        ]);
    }

    private function tulisPesanan(Project $project, ?int $candidateNumber): void
    {
        @file_put_contents(
            $this->codexState->orderPath($project),
            json_encode([
                'project_id' => $project->id,
                'kandidat' => $candidateNumber,
                'waktu' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function umurPesanan(Project $project): ?int
    {
        $path = $this->codexState->orderPath($project);

        if (!is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (!is_array($data) || !is_string($data['waktu'] ?? null)) {
            return null;
        }

        try {
            return max(0, now()->getTimestamp() - \Illuminate\Support\Carbon::parse($data['waktu'])->getTimestamp());
        } catch (\Throwable) {
            return null;
        }
    }

    private function isWebsiteProject(Project $project): bool
    {
        return in_array('website', $project->serviceTypes());
    }
}
