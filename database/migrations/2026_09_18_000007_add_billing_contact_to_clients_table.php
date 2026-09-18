<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A separate address for invoices.
 *
 * The person we talk to about a website is often not the person who pays for
 * it. These are optional: when billing_email is empty the client's main email
 * is used, so every existing client keeps working with no data entry at all
 * (see Client::billingEmail()).
 *
 * This is also the LAST billing migration, which makes it the FIRST one a
 * rollback executes — so it carries the guard for the whole Billing & Finance
 * set. Guarding only inside the invoices migration was not enough: by the time
 * that one refused, this one had already dropped its columns, leaving the
 * schema half-rolled-back. Refusing here means a rollback that cannot safely
 * proceed changes nothing at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('billing_name')->nullable()->after('contact_name');
            $table->string('billing_email')->nullable()->after('email');
            $table->string('billing_phone', 50)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        $this->guardAgainstBillingData();

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['billing_name', 'billing_email', 'billing_phone']);
        });
    }

    /**
     * Refuses the rollback while the billing feature holds real data.
     *
     * Every table checked here is created by this migration set, so any row in
     * one of them is data that a rollback would destroy — including a payment
     * recorded against an ordinary project invoice, which lives in the same new
     * table. Each check is guarded by hasTable()/hasColumn() so a partially
     * applied migration state can still be rolled back.
     */
    private function guardAgainstBillingData(): void
    {
        $found = [];

        if ($this->tableHasRows('billing_subscriptions')) {
            $found[] = 'billing subscriptions';
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'purpose')
            && DB::table('invoices')->where('purpose', 'renewal')->exists()) {
            $found[] = 'renewal invoices';
        }

        if ($this->tableHasRows('invoice_items')) {
            $found[] = 'invoice items';
        }

        if ($this->tableHasRows('payments')) {
            $found[] = 'recorded payments';
        }

        if ($this->tableHasRows('billing_reminder_logs')) {
            $found[] = 'reminder logs';
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'billing_email')) {
            $hasContact = DB::table('clients')
                ->where(function ($query) {
                    $query->whereNotNull('billing_name')
                        ->orWhereNotNull('billing_email')
                        ->orWhereNotNull('billing_phone');
                })
                ->exists();

            if ($hasContact) {
                $found[] = 'client billing contacts';
            }
        }

        if ($found) {
            throw new RuntimeException(
                'Cannot rollback Billing & Finance schema because billing data already exists ('
                . implode(', ', $found) . '). '
                . 'Archive or migrate the billing data before rolling back.'
            );
        }
    }

    private function tableHasRows(string $table): bool
    {
        return Schema::hasTable($table) && DB::table($table)->exists();
    }
};
