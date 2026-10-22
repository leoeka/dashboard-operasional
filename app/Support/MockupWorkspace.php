<?php

namespace App\Support;

use App\Models\MockupCandidate;
use App\Models\WpBuild;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Meja kerja berkas untuk loop mockup: satu folder per kandidat di
 * storage/app/mockup-work/{project}/candidate-{id}.
 *
 * Jalur disusun dengan storage_path(), bukan Storage::disk('local') - root
 * disk lokal Laravel 12 adalah storage/app/private, sedangkan artefak mockup
 * sengaja ditaruh langsung di storage/app mengikuti pola repo.
 *
 * Kunci folder adalah id baris, bukan nomor urut: nomor berputar 1-3 tiap
 * generasi, sedangkan jejak kandidat lama harus tetap utuh di disk.
 */
class MockupWorkspace
{
    public function basePath(): string
    {
        return storage_path('app/mockup-work');
    }

    public function candidatePath(MockupCandidate $candidate): string
    {
        $path = $this->basePath() . '/' . $candidate->project_id . '/candidate-' . $candidate->id;

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    /**
     * Nilai kolom `folder` (relatif terhadap storage/app) kembali menjadi
     * jalur lengkap di disk.
     */
    public function resolve(string $relativeFolder): string
    {
        return storage_path('app/' . ltrim($relativeFolder, '/'));
    }

    public function relativeFolder(MockupCandidate $candidate): string
    {
        return 'mockup-work/' . $candidate->project_id . '/candidate-' . $candidate->id;
    }

    public function buildPath(WpBuild $build): string
    {
        $path = $this->basePath() . '/' . $build->project_id . '/build-' . $build->id;

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    public function relativeBuildFolder(WpBuild $build): string
    {
        return 'mockup-work/' . $build->project_id . '/build-' . $build->id;
    }

    public function zipPath(WpBuild $build): string
    {
        $dir = $this->basePath() . '/' . $build->project_id;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/theme-install.zip';
    }

    public function relativeZipPath(WpBuild $build): string
    {
        return 'mockup-work/' . $build->project_id . '/theme-install.zip';
    }

    public function deleteProject(int $projectId): void
    {
        $path = $this->basePath() . '/' . $projectId;

        if (!is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
