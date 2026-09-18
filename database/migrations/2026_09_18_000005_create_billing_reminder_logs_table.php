<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The source of truth for "has this renewal reminder already gone out".
 *
 * invoices.last_reminder_sent_at cannot answer that question: it is one
 * timestamp, so it can say "something was sent today" but never "H-30 was sent,
 * H-7 was not". A yearly renewal needs all three of H-30, H-7 and H-3 to be
 * tracked independently, and the scheduler may run more than once a day.
 *
 * The unique key on (invoice_id, days_before) is what actually prevents a
 * duplicate email — not a time window, not a flag the sender remembers to set.
 * Inserting the row is the claim on that reminder; sending happens after.
 *
 * last_reminder_sent_at stays untouched for the legacy project-invoice reminder
 * flow, which still uses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_subscription_id')->nullable()->constrained()->nullOnDelete();

            // 30, 7, 3 for yearly; 7, 3, 1 for monthly. Stored as the number of
            // days before the renewal date, which is how the policy expresses it.
            $table->unsignedSmallInteger('days_before');
            $table->date('scheduled_for');

            $table->string('status', 20)->default('pending'); // pending|sent|failed
            $table->timestamp('sent_at')->nullable();
            $table->string('recipient')->nullable();
            $table->text('error_message')->nullable();
            // Bounded so a permanently broken address cannot be retried forever.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();

            $table->unique(['invoice_id', 'days_before'], 'billing_reminder_once_per_threshold');
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_reminder_logs');
    }
};
