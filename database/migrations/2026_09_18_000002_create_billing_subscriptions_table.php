<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recurring service a client actually pays for: this hosting, this domain,
 * this SEO retainer — with its own price and its own next renewal date.
 *
 * Deliberately separate from service_packages. A package is the catalogue entry
 * ("SEO Basic, Rp3.500.000/month"); a subscription is one client's copy of it,
 * with a renewal date the scheduler reads. Keeping a renewal date on the
 * catalogue would mean every client sharing one date.
 *
 * project_id and service_package_id are both nullable on purpose. A domain is
 * billed to a client whether or not anyone opened a project for it, and a
 * one-off custom arrangement must be possible without first inventing a
 * catalogue entry for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('service_type', 30)->default('other'); // hosting|domain|website|maintenance|seo|other
            $table->text('description')->nullable();

            $table->string('billing_cycle', 20)->default('yearly'); // monthly|yearly
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');

            $table->date('start_date');
            $table->date('next_renewal_date');

            $table->string('status', 20)->default('active'); // active|paused|cancelled|expired

            // An overdue invoice never suspends anything by itself; these only
            // decide whether the scheduler acts for this subscription.
            $table->boolean('auto_invoice')->default(true);
            $table->boolean('auto_reminder')->default(true);

            $table->timestamps();

            // The scheduler's only scan: active subscriptions renewing soon.
            $table->index(['status', 'next_renewal_date']);
            $table->index('service_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
