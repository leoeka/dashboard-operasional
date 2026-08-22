<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ZipWpMcpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller BARU, terpisah dari ProjectController — khusus buat proses
 * hapus site ZipWP setelah project selesai & sudah dimigrasi ke hosting
 * client. Sengaja dipisah biar tidak menyentuh ProjectController yang
 * sudah ada.
 */
class SiteCleanupController extends Controller
{
    public function destroy(Request $request, Project $project, ZipWpMcpService $zipWp)
    {
        if (!$project->mockupTemplate || !$project->mockupTemplate->site_uuid) {
            return back()->with('error', 'This project does not have a ZipWP site that can be deleted.');
        }

        if (!$project->mockupTemplate->isLiveOnZipWp()) {
            return back()->with('error', 'This site has already been marked as deleted previously.');
        }

        // WAJIB konfirmasi eksplisit — checkbox "sudah baca & yakin" harus
        // dicentang di form, bukan cuma klik tombol biasa. Ini langkah
        // "harap baca dulu" yang diminta, supaya nggak ada yang kehapus
        // karena salah klik.
        $request->validate([
            'confirm_migrated' => 'required|accepted',
        ], [
            'confirm_migrated.accepted' => 'You must check the confirmation box before deleting.',
        ]);

        try {
            $zipWp->deleteSite($project->mockupTemplate->site_uuid);

            $project->mockupTemplate->update(['zipwp_deleted_at' => now()]);
            $project->update(['status' => 'completed']);

            Log::info('Project marked as done & ZipWP site deleted', [
                'project_id' => $project->id,
                'site_uuid' => $project->mockupTemplate->site_uuid,
            ]);

            return back()->with('success', 'Project marked as completed & site successfully deleted from ZipWP.');
        } catch (\Throwable $e) {
            Log::error('Failed to delete ZipWP site: ' . $e->getMessage(), ['project_id' => $project->id]);

            return back()->with('error', 'Failed to delete site from ZipWP: ' . $e->getMessage());
        }
    }
}