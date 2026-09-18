<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table ServicePackage has always assumed but never had.
 *
 * The model, the controller and the `service-packages` resource routes were all
 * shipped, but no migration ever created the table — so opening that page
 * returns a 500 on any environment built from migrations. Columns and types
 * follow what the existing model and controller already expect (see
 * ServicePackageController::validated()), so nothing else has to change.
 *
 * Guarded with hasTable(): an environment where somebody created this table by
 * hand must not fail the migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_packages')) {
            return;
        }

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // 'paket_utama' | 'tambahan' — the two values the controller validates.
            $table->string('category', 50);
            // Money is decimal, never float: a price is an exact amount.
            $table->decimal('price', 15, 2)->default(0);
            // e.g. "per bulan", "per tahun". Optional in the existing form.
            $table->string('unit', 50)->nullable();
            // Newline-separated list; ServicePackage::featureList() splits it.
            $table->text('features')->nullable();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_packages');
    }
};
