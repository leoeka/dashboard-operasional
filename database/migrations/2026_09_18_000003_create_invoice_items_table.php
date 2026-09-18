<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items, because a renewal invoice is rarely one thing.
 *
 * The existing invoices table carries a single `amount`, which was fine for a
 * project DP but cannot express "Hosting 1.500.000 + Domain 300.000 +
 * Maintenance 1.000.000". The invoice keeps `amount` as its authoritative
 * total; these rows are what that total is made of.
 *
 * Legacy project invoices simply have no items, and nothing forces them to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('description');
            // Decimal rather than integer so a half-month or pro-rata line is
            // possible later without another migration.
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total', 15, 2);

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
