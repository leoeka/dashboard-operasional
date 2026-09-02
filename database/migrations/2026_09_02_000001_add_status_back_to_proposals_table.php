<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The status column was dropped in
 * 2026_08_22_000002_remove_status_from_proposals_table.php because it was
 * unused at the time. The mockup approval flow added later
 * (ProjectController::approveProposal / BundleBuilderService::build) relies
 * on proposals.status again ('pending' | 'approved' | 'rejected'), so it is
 * restored here instead of editing the old (already-run) migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('client_name');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
