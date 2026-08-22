<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = array_filter(
            ['progress', 'deadline'],
            fn (string $column): bool => Schema::hasColumn('projects', $column),
        );

        if ($columns !== []) {
            Schema::table('projects', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('deadline')->nullable();
        });
    }
};
