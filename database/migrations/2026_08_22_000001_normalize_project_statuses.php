<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY status ENUM('request', 'proposal', 'mockup', 'development', 'qa', 'active', 'done', 'in_progress', 'completed') NOT NULL DEFAULT 'request'");
        }

        DB::table('projects')->whereIn('status', ['proposal', 'mockup', 'development', 'qa', 'active'])
            ->update(['status' => 'in_progress']);
        DB::table('projects')->where('status', 'done')->update(['status' => 'completed']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY status ENUM('request', 'in_progress', 'completed') NOT NULL DEFAULT 'request'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY status ENUM('request', 'in_progress', 'completed', 'proposal', 'mockup', 'development', 'qa', 'active', 'done') NOT NULL DEFAULT 'request'");
        }

        DB::table('projects')->where('status', 'in_progress')->update(['status' => 'proposal']);
        DB::table('projects')->where('status', 'completed')->update(['status' => 'done']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE projects MODIFY status ENUM('request', 'proposal', 'mockup', 'development', 'qa', 'active', 'done') NOT NULL DEFAULT 'request'");
        }
    }
};