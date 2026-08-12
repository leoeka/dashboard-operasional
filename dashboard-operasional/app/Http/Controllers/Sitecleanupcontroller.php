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
            return back()->with('error', 'Project ini tidak punya site ZipWP yang bisa dihapus.');
        }

        if (!$project->mockupTemplate->isLiveOnZipWp()) {
            return back()->with('error', 'Site ini sudah pernah ditandai dihapus sebelumnya.');
        }

        // WAJIB konfirmasi eksplisit — checkbox "sudah baca & yakin" harus
        // dicentang di form, bukan cuma klik tombol biasa. Ini langkah
        // "harap baca dulu" yang diminta, supaya nggak ada yang kehapus
        // karena salah klik.
        $request->validate([
            'confirm_migrated' => 'required|accepted',
        ], [
            'confirm_migrated.accepted' => 'Kamu harus mencentang konfirmasi dulu sebelum menghapus.',
        ]);

        try {
            $zipWp->deleteSite($project->mockupTemplate->site_uuid);

            $project->mockupTemplate->update(['zipwp_deleted_at' => now()]);
            $project->update(['status' => 'done']);

            Log::info('Project ditandai done & site ZipWP dihapus', [
                'project_id' => $project->id,
                'site_uuid' => $project->mockupTemplate->site_uuid,
            ]);

            return back()->with('success', 'Project ditandai selesai & site berhasil dihapus dari ZipWP.');
        } catch (\Throwable $e) {
            Log::error('Gagal hapus site ZipWP: ' . $e->getMessage(), ['project_id' => $project->id]);

            return back()->with('error', 'Gagal menghapus site dari ZipWP: ' . $e->getMessage());
        }
    }
}