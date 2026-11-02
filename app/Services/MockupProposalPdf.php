<?php

namespace App\Services;

use App\Models\ClientProposal;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Merakit proposal PDF untuk klien dari tiga kandidat yang sudah disetujui tim.
 * Perakitannya murni mekanis, tanpa panggilan AI: kalimat tiap kandidat sudah
 * ditulis GPT saat tahap 3 (idea_sentence), dan gambarnya adalah PNG persis
 * yang ditinjau tim di gerbang — jadi "yang klien centang = yang tim lihat".
 */
class MockupProposalPdf
{
    /** @param Collection<int, \App\Models\MockupCandidate> $candidates */
    public function render(Project $project, Collection $candidates): string
    {
        return Pdf::loadView('pdf.proposal', [
            'project' => $project,
            'candidates' => $candidates,
            'date' => now()->format('n/j/Y'),
        ])->setPaper('a4')->setOption('defaultFont', 'DejaVu Sans')->output();
    }

    /** @param Collection<int, \App\Models\MockupCandidate> $candidates */
    public function create(Project $project, Collection $candidates): ClientProposal
    {
        $path = 'proposals/'.$project->id.'/proposal-'.now()->format('Ymd-His-v').'-'.Str::lower(Str::random(4)).'.pdf';
        $absolute = storage_path('app/'.$path);

        // Ditulis langsung ke storage/app, bukan Storage::disk('local') -
        // root disk lokal Laravel 12 adalah storage/app/private, sedangkan
        // artefak pipeline sengaja ditaruh langsung di storage/app (sama
        // seperti MockupWorkspace dan penjaga jalur PNG di gerbang).
        if (! is_dir(dirname($absolute)) && ! mkdir($dirname = dirname($absolute), 0775, true) && ! is_dir($dirname)) {
            throw new \RuntimeException('Folder proposal tidak bisa dibuat: '.$dirname);
        }
        if (file_put_contents($absolute, $this->render($project, $candidates)) === false) {
            if (is_file($absolute)) {
                unlink($absolute);
            }
            throw new \RuntimeException('Berkas proposal tidak bisa ditulis: '.$absolute);
        }

        try {
            return $project->clientProposals()->create(['pdf_path' => $path]);
        } catch (\Throwable $e) {
            unlink($absolute);
            throw $e;
        }
    }
}
