<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the existing invoices table to also carry recurring renewals, rather
 * than standing up a second invoices table beside it.
 *
 * Three things change:
 *
 * 1. project_id becomes nullable, and its foreign key stops cascading.
 *    A domain renewal is billed to a client whether or not a project exists
 *    for it; and deleting a project must not take its invoices with it. An
 *    invoice is a finance record — it outlives the project it was raised for,
 *    keeping its client, its payments and its history, with project_id simply
 *    going null.
 *
 * 2. `purpose` is added as the real discriminator between a project payment and
 *    a renewal. Deliberately NOT inferred from `project_id === null`, because a
 *    renewal may well belong to a project (hosting for a site we built), and a
 *    reader should never have to guess an invoice's kind from a null.
 *
 * 3. `status` moves from a two-value ENUM to a short string. Renewals need
 *    `cancelled`, and drafts are coming; every future status would otherwise be
 *    another ALTER on this table. The stored values are unchanged, so existing
 *    'unpaid'/'paid' rows and every query against them keep working.
 *
 * `amount` remains the invoice's authoritative total — no separate `total`
 * column — because existing code, the reminder mail and the WhatsApp message
 * all read it. subtotal/discount/tax explain how that total was reached.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Deliberately the same code path on every driver. An earlier version
        // skipped the drop/re-add on SQLite and let change() rebuild the table,
        // which silently carried the ORIGINAL cascade over — so the tests ran
        // against cascade while production ran against nullOnDelete, and a test
        // proving invoices survive a deleted project passed for the wrong
        // reason. Laravel's SQLite driver supports dropping foreign keys.
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            // nullOnDelete, not cascade: deleting a project must not delete the
            // invoices raised against it.
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Financial records outlive the client record, so this nulls rather
            // than cascades.
            $table->foreignId('client_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('billing_subscription_id')->nullable()->after('client_id')->constrained()->nullOnDelete();

            $table->string('purpose', 30)->default('project_payment')->after('type'); // project_payment|renewal

            $table->date('issue_date')->nullable()->after('purpose');
            $table->decimal('subtotal', 15, 2)->nullable()->after('amount');
            $table->decimal('discount', 15, 2)->default(0)->after('subtotal');
            $table->decimal('tax', 15, 2)->default(0)->after('discount');
            $table->string('currency', 3)->default('IDR')->after('tax');

            $table->date('billing_period_start')->nullable()->after('due_date');
            $table->date('billing_period_end')->nullable()->after('billing_period_start');
            $table->text('notes')->nullable()->after('last_reminder_sent_at');

            $table->index(['purpose', 'status']);
            $table->index('due_date');
        });

        // status: ENUM('unpaid','paid') -> VARCHAR(20), same values.
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status', 20)->default('unpaid')->change();
        });

        // Existing rows predate these columns: give them an issue date (the day
        // they were created) and the purpose they have always had.
        // DATE() exists on both MySQL and SQLite; no driver branch needed.
        DB::table('invoices')->whereNull('issue_date')->update([
            'issue_date' => DB::raw('DATE(created_at)'),
        ]);
        DB::table('invoices')->whereNull('subtotal')->update(['subtotal' => DB::raw('amount')]);
    }

    public function down(): void
    {
        // A rollback must never quietly destroy finance history to make the
        // data fit an older column definition. Where the current data cannot be
        // represented by the legacy schema, this refuses and says what is in
        // the way — losing a rollback is recoverable, losing invoices is not.
        $renewals = DB::table('invoices')->whereNull('project_id')->count();
        if ($renewals > 0) {
            throw new RuntimeException(
                "Cannot rollback recurring billing schema because renewal invoices already exist: "
                . "{$renewals} invoice(s) have no project and the legacy schema requires one. "
                . "Reassign or archive them first, then roll back."
            );
        }

        $incompatible = DB::table('invoices')->whereNotIn('status', ['unpaid', 'paid'])->count();
        if ($incompatible > 0) {
            throw new RuntimeException(
                "Cannot rollback recurring billing schema because {$incompatible} invoice(s) carry a status "
                . "the legacy enum cannot hold (only 'unpaid' and 'paid' fit). "
                . "Resolve those invoices first, then roll back."
            );
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['purpose', 'status']);
            $table->dropIndex(['due_date']);

            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('billing_subscription_id');

            $table->dropColumn([
                'purpose',
                'issue_date',
                'subtotal',
                'discount',
                'tax',
                'currency',
                'billing_period_start',
                'billing_period_end',
                'notes',
            ]);
        });

        // Back to the original two-value enum, which the guard above has
        // already confirmed every row fits.
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid')->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        // Safe because the guard above proved every invoice still has a project.
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Restores the original cascade this migration replaced.
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
