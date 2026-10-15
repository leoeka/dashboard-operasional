<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How long a subscription runs for.
 *
 * Nullable on purpose: a subscription with no end date keeps renewing until an
 * operator stops it, which is what every row written before this column existed
 * does. Filling it in is what makes the reminder schedule stop by itself.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->date('ends_on')->nullable()->after('next_renewal_date');
        });
    }

    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->dropColumn('ends_on');
        });
    }
};
