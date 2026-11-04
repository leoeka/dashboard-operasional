<?php

namespace App\Services;

use App\Models\Project;

/**
 * Perakit ringkas data proyek (form Request) untuk brief kandidat. Baris yang
 * datanya kosong tidak ditulis, supaya model tidak menebak dari ruang kosong.
 */
class MockupBrief
{
    /**
     * Baris data proyek yang dibaca brief Codex.
     *
     * @return list<string>
     */
    public static function dataProyek(Project $project): array
    {
        $baris = [
            'Proyek: ' . $project->name . ' (jenis: ' . ($project->type ?: 'website') . ')',
            'Klien: ' . $project->client_name,
        ];

        foreach (self::barisOpsional($project) as $label => $nilai) {
            if (is_string($nilai) && trim($nilai) !== '') {
                $baris[] = $label . ': ' . $nilai;
            }
        }

        return $baris;
    }

    private static function barisOpsional(Project $project): array
    {
        return [
            'Nama website' => $project->website_name,
            'Target pasar' => $project->target_market,
            'Fitur yang diminta' => $project->website_features,
            'Cerita klien' => $project->description,
        ];
    }
}
