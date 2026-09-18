<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What was actually received against an invoice.
 *
 * invoices.paid_at is a date and nothing more — it cannot record how much came
 * in, by which method, or against which reference number. These rows are the
 * finance record; paid_at stays as the invoice's own summary flag so existing
 * screens keep working.
 *
 * recorded_by is nullable so a payment survives the admin who entered it being
 * removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->date('paid_on');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('method', 30)->default('bank_transfer'); // bank_transfer|cash|qris|other
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
