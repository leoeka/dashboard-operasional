<?php

namespace App\Services;

use App\Models\Project;

/**
 * Perakit brief kandidat dari data proyek (form Request). Baris yang datanya
 * kosong tidak ditulis, supaya GPT tidak menebak-nebak dari ruang kosong.
 */
class MockupBrief
{
    public static function forRondeSatu(Project $project): string
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

        $baris[] = '';
        $baris[] = 'Tulis RENCANA DESAIN untuk satu kandidat mockup website proyek ini,'
            . ' mengikuti aturan (metode dua ronde, bentuk JSON, kontrak artefak).'
            . ' Balas HANYA dengan JSON rencana.';

        return implode("\n", $baris);
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
