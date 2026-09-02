<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ai_reasoning now stores the full analysis plus all 3 mockup candidates
 * (each with a full page/section blueprint), which regularly exceeds the
 * ~64KB limit of a plain TEXT column ("Data too long for column
 * 'ai_reasoning'"). Widen it to LONGTEXT (up to 4GB). Uses a raw statement
 * instead of Schema::table(...)->change() so it doesn't require
 * doctrine/dbal, which isn't installed in this project.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `proposals` MODIFY `ai_reasoning` LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `proposals` MODIFY `ai_reasoning` TEXT NULL');
        }
    }
};
