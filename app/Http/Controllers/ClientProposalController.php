<?php

namespace App\Http\Controllers;

use App\Models\ClientProposal;
use App\Models\MockupCandidate;
use App\Models\Project;
use App\Services\MockupProposalPdf;
use App\Support\MockupGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Proposal PDF untuk klien: dibuat setelah gerbang tim menyetujui ketiga
 * kandidat, diunduh tim lalu dikirim ke klien. Pilihan klien sendiri
 * dicatat tim lewat chosen_candidate_id (tahap 7), bukan lewat PDF ini.
 */
class ClientProposalController extends Controller
{
    public function __construct(private MockupGate $gate, private MockupProposalPdf $pdf)
    {
    }

    public function store(Project $project): RedirectResponse
    {
        abort_unless(in_array('website', $project->serviceTypes()), 403);

        $set = $this->gate->currentSet($project);        // Kandidat yang boleh masuk proposal: disetujui, atau sudah pernah
        // dikirim (buat-ulang proposal setelah kirim tetap boleh).
        $eligible = $set->filter(
            fn ($candidate) => in_array($candidate->status, [
                MockupCandidate::STATUS_TEAM_APPROVED,
                MockupCandidate::STATUS_SENT,
                MockupCandidate::STATUS_CHOSEN,
            ], true)
        );

        if ($set->count() !== 3 || $eligible->count() !== 3) {
            return back()->with('error', 'Proposal PDF baru bisa dibuat setelah ketiga kandidat disetujui tim.');
        }

        // Pilihan klien yang sudah terkunci tidak boleh dijadikan yatim oleh
        // proposal baru — build WordPress sedang menunjuk kandidat dari
        // proposal yang sudah dikembalikan klien ini.
        $latest = ClientProposal::where('project_id', $project->id)->latest('id')->first();
        if ($latest?->chosen_candidate_id !== null) {
            return back()->with('error', 'Pilihan klien sudah terkunci. Proposal tidak bisa dibuat ulang.');
        }

        try {
            $proposal = $this->pdf->create($project, $eligible);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Proposal PDF gagal dibuat — berkas tidak bisa ditulis ke penyimpanan.');
        }

        $eligible
            ->filter(fn ($candidate) => $candidate->status === MockupCandidate::STATUS_TEAM_APPROVED)
            ->each->update(['status' => MockupCandidate::STATUS_SENT]);

        return back()->with(
            'success',
            'Proposal PDF dibuat. Unduh berkasnya dan kirimkan ke klien — klien mencentang pilihannya di PDF.'
        );
    }

    /**
     * Tim mencatat kandidat yang dicentang klien di PDF yang dikembalikan.
     * Sekali dicatat, pilihan terkunci: kandidat terpilih jadi 'chosen',
     * dua lainnya di set sekarang jadi 'rejected', dan build WordPress hanya
     * boleh dari kandidat terpilih ini.
     */
    public function choose(Project $project, ClientProposal $proposal, MockupCandidate $candidate): RedirectResponse
    {
        abort_unless(in_array('website', $project->serviceTypes()), 403);
        abort_unless($proposal->project_id === $project->id, 404);

        // Gerbang lain (buat ulang proposal, buat ulang kandidat, build) hanya
        // membaca proposal terbaru. Mencatat pilihan di baris lama mengunci
        // baris yang tak dibaca siapa-siapa — build jadi macet selamanya.
        $latestId = ClientProposal::where('project_id', $project->id)->latest('id')->value('id');
        abort_unless($proposal->id === $latestId, 409);

        $set = $this->gate->currentSet($project);
        abort_unless($set->contains('id', $candidate->id), 404);

        // Kalau ada hasil buat-ulang yang belum disetujui tim, PDF yang klien
        // pegang tidak cocok lagi dengan kartu di layar — paksa tim menyelesaikan
        // tinjau + buat ulang proposal dulu, supaya "yang dicentang klien =
        // yang dibangun" tidak bocor.
        $stillInReview = $set->filter(
            fn ($member) => in_array($member->status, [
                MockupCandidate::STATUS_DRAFT,
                MockupCandidate::STATUS_AWAITING_REVIEW,
            ], true)
        );
        if ($stillInReview->isNotEmpty()) {
            return back()->with(
                'error',
                'Masih ada kandidat hasil buat-ulang yang belum disetujui tim. Setujui dulu, buat ulang proposal, baru catat pilihan klien.'
            );
        }

        // Dua klik berbarengan digembok di satu transaksi: yang pertama menang,
        // yang kedua membaca chosen_candidate_id yang sudah terisi.
        $recorded = DB::transaction(function () use ($proposal, $candidate, $set) {
            $row = ClientProposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            if ($row->chosen_candidate_id !== null) {
                return false;
            }

            // 'sent' berarti baris persis ini pernah dicetak di PDF klien
            // (store() menandainya begitu PDF dibangun). Kandidat pengganti yang
            // disetujui setelah PDF dibuat belum pernah dilihat klien — wajib
            // buat ulang proposal dulu, supaya "yang dicentang klien = yang
            // dibangun" tidak bocor. Diperiksa di dalam gembok supaya pesan
            // "pilihan terkunci" tetap yang pertama keluar untuk permintaan ganda.
            abort_unless($candidate->status === MockupCandidate::STATUS_SENT, 422);

            $row->chosen_candidate_id = $candidate->id;
            $row->chosen_at = now();
            $row->save();

            // Kunci set sekarang — baris jejak tergantikan tidak ikut karena
            // bukan anggota currentSet.
            $set->each(function ($member) use ($candidate) {
                $member->status = $member->id === $candidate->id
                    ? MockupCandidate::STATUS_CHOSEN
                    : MockupCandidate::STATUS_REJECTED;
                $member->save();
            });

            return true;
        });

        if (! $recorded) {
            return back()->with('error', 'Pilihan klien sudah terkunci. Tidak bisa diganti.');
        }

        return back()->with(
            'success',
            'Pilihan klien dicatat: Kandidat ' . $candidate->candidate_number . '. Build WordPress terkunci pada kandidat ini.'
        );
    }

    public function download(Project $project, ClientProposal $proposal): BinaryFileResponse
    {
        abort_unless($proposal->project_id === $project->id, 404);

        $path = storage_path('app/'.$proposal->pdf_path);
        abort_unless($proposal->pdf_path && is_file($path), 404);

        if ($proposal->sent_at === null) {
            $proposal->sent_at = now();
            $proposal->save();
        }

        return response()->download($path, 'Proposal-'.$project->code.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
