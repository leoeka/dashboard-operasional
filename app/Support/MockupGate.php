<?php

namespace App\Support;

use App\Models\MockupCandidate;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Aturan main gerbang tinjau: apa pun "set kandidat sekarang" untuk satu
 * project. Regenerasi menambah baris baru dengan nomor yang sama dan baris
 * lama tetap hidup sebagai jejak, jadi kandidat nomor N yang berlaku selalu
 * berarti baris nomor N dengan id terbesar.
 */
class MockupGate
{
    /**
     * @return Collection<int, MockupCandidate> kunci = candidate_number
     */
    public function currentSet(Project $project): Collection
    {
        return $project->mockupCandidates()
            ->orderByDesc('id')
            ->get()
            ->groupBy('candidate_number')
            ->map->first()
            ->sortKeys();
    }
}
