<?php

namespace App\Http\Controllers;

use App\Jobs\BuildWpThemeJob;
use App\Models\ClientProposal;
use App\Models\Project;
use App\Models\WpBuild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kunci build WordPress: satu proses build per project, dan hanya untuk
 * kandidat yang sudah dicatat sebagai pilihan klien. Pembangunan temanya
 * sendiri (loop AI lewat antrean) menyusul di tahap berikutnya — baris
 * WpBuild yang lahir di sini adalah kuncinya: tanpa pilihan, build ditolak.
 */
class WpBuildController extends Controller
{
    public function store(Project $project): RedirectResponse
    {
        abort_unless(in_array('website', $project->serviceTypes()), 403);

        $proposal = ClientProposal::where('project_id', $project->id)->latest('id')->first();

        // Kunci utama tahap ini: build sebelum tim mencatat pilihan klien
        // selalu ditolak, sekalipun proposal PDF-nya sudah ada.
        if ($proposal?->chosen_candidate_id === null) {
            return back()->with('error', 'Build baru bisa dimulai setelah tim mencatat pilihan klien.');
        }

        $build = DB::transaction(function () use ($project, $proposal) {
            $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

            $existing = $locked->wpBuild;

            // Satu build per project: jika sedang berjalan/lolos, tolak duplikasi.
            if ($existing && $existing->status !== WpBuild::STATUS_INSPECTION_FAILED) {
                return null;
            }

            // Jika sebelumnya gagal, izinkan tim mencoba build ulang.
            if ($existing) {
                $existing->update([
                    'status' => WpBuild::STATUS_PENDING,
                    'inspection_results' => null,
                    'mockup_candidate_id' => $proposal->chosen_candidate_id,
                ]);

                return $existing;
            }

            return $locked->wpBuild()->create([
                'mockup_candidate_id' => $proposal->chosen_candidate_id,
            ]);
        });

        if ($build === null) {
            return back()->with('error', 'Build untuk project ini sudah ada.');
        }

        BuildWpThemeJob::dispatch($build);

        return back()->with(
            'success',
            'Build dibuat untuk Kandidat ' . $build->candidate->candidate_number . '. Pembangunan tema sedang diproses.'
        );
    }

    public function download(Project $project, WpBuild $build): BinaryFileResponse|RedirectResponse
    {
        abort_unless(in_array('website', $project->serviceTypes()), 403);
        abort_unless($build->project_id === $project->id, 404);

        if ($build->status !== WpBuild::STATUS_READY || empty($build->zip_path)) {
            return back()->with('error', 'Berkas ZIP tema belum siap diunduh.');
        }

        $fullPath = storage_path('app/' . ltrim($build->zip_path, '/'));

        if (!is_file($fullPath)) {
            return back()->with('error', 'Berkas ZIP tema tidak ditemukan pada server.');
        }

        $slug = Str::slug($project->name) ?: 'theme';
        $filename = $slug . '-theme-install.zip';

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }
}
