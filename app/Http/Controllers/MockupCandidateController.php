<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateMockupCandidateJob;
use App\Models\ClientProposal;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Support\MockupGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gerbang tim untuk kandidat mockup: memicu tiga kandidat pertama,
 * membuat ulang satu kandidat, menyetujui yang siap, dan menyajikan gambar
 * pratinjau. Semua aksi tipis - baris kandidat mencatat status, dan loop AI
 * berat selalu lewat antrean (GenerateMockupCandidateJob), tidak pernah
 * di dalam request web.
 */
class MockupCandidateController extends Controller
{
    public function __construct(private MockupGate $gate)
    {
    }

    public function store(Project $project): RedirectResponse
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
            return back()->with('error', 'Kandidat untuk project ini sudah ada. Gunakan tombol buat ulang pada kartu.');
        }

        $batch->each(fn ($candidate) => GenerateMockupCandidateJob::dispatch($candidate));

        return back()->with('success', 'Tiga kandidat sedang dibuat. Muat ulang halaman ini beberapa saat lagi.');
    }

    public function regenerate(Project $project, MockupCandidate $candidate): RedirectResponse
    {
        abort_unless($this->isWebsiteProject($project), 403);
        abort_unless($candidate->project_id === $project->id, 404);

        // Pilihan klien mengunci set ini — baris yang dibangun adalah baris yang
        // klien pilih, jadi tidak boleh diganti lagi.
        $latestProposal = ClientProposal::where('project_id', $project->id)->latest('id')->first();
        if ($latestProposal?->chosen_candidate_id !== null) {
            return back()->with('error', 'Pilihan klien sudah terkunci. Kandidat tidak bisa dibuat ulang.');
        }

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
            return back()->with('error', 'Kandidat ini masih sedang dibuat.');
        }

        GenerateMockupCandidateJob::dispatch($replacement);

        return back()->with('success', 'Kandidat ' . $candidate->candidate_number . ' sedang dibuat ulang. Muat ulang halaman ini beberapa saat lagi.');
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

    private function isWebsiteProject(Project $project): bool
    {
        return in_array('website', $project->serviceTypes());
    }
}
