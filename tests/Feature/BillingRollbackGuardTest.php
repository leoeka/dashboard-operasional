<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rolling back the Billing & Finance migrations must be all-or-nothing.
 *
 * The invoices migration already refused to delete finance data, but it is not
 * the first migration a rollback runs — the clients one is, and by the time the
 * refusal came the billing contact columns had already been dropped. Nothing
 * was lost, but the schema was left half-rolled-back until someone re-ran
 * migrate. The guard now lives in the migration that executes first, so a
 * rollback that cannot proceed safely changes nothing.
 *
 * These tests run against their own sqlite file rather than the shared test
 * database: rolling back the schema underneath the suite would break every test
 * after them.
 */
function billingRollbackConnection(): string
{
    $file = tempnam(sys_get_temp_dir(), 'billing-rollback-') . '.sqlite';
    touch($file);

    config(['database.connections.billing_rollback' => [
        'driver' => 'sqlite',
        'database' => $file,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    Artisan::call('migrate', ['--database' => 'billing_rollback', '--force' => true]);

    return 'billing_rollback';
}

/** The seven migrations this phase added, i.e. what a "roll back billing" means. */
const BILLING_MIGRATION_STEPS = 7;

function seedBillingData(string $connection): void
{
    $db = DB::connection($connection);

    $clientId = $db->table('clients')->insertGetId([
        'company_name' => 'PT ABC',
        'contact_name' => 'Budi',
        'email' => 'budi@abc.test',
        'billing_email' => 'finance@abc.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $subscriptionId = $db->table('billing_subscriptions')->insertGetId([
        'client_id' => $clientId,
        'name' => 'Hosting + Domain',
        'service_type' => 'hosting',
        'billing_cycle' => 'yearly',
        'amount' => 2500000,
        'currency' => 'IDR',
        'start_date' => '2025-10-30',
        'next_renewal_date' => '2026-10-30',
        'status' => 'active',
        'auto_invoice' => true,
        'auto_reminder' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $invoiceId = $db->table('invoices')->insertGetId([
        'client_id' => $clientId,
        'billing_subscription_id' => $subscriptionId,
        'invoice_number' => 'INV-2026-000001',
        'type' => 'full',
        'purpose' => 'renewal',
        'issue_date' => '2026-09-30',
        'amount' => 2500000,
        'subtotal' => 2500000,
        'due_date' => '2026-10-30',
        'status' => 'unpaid',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $db->table('invoice_items')->insert([
        'invoice_id' => $invoiceId,
        'description' => 'Hosting Business',
        'quantity' => 1,
        'unit_price' => 2500000,
        'total' => 2500000,
        'position' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $db->table('payments')->insert([
        'invoice_id' => $invoiceId,
        'paid_on' => '2026-10-15',
        'amount' => 2500000,
        'currency' => 'IDR',
        'method' => 'bank_transfer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $db->table('billing_reminder_logs')->insert([
        'invoice_id' => $invoiceId,
        'billing_subscription_id' => $subscriptionId,
        'days_before' => 30,
        'scheduled_for' => '2026-09-30',
        'status' => 'sent',
        'sent_at' => now(),
        'recipient' => 'finance@abc.test',
        'attempts' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('refuses to roll back billing while billing data exists, and changes nothing', function () {
    $connection = billingRollbackConnection();
    seedBillingData($connection);

    $migrationsBefore = DB::connection($connection)->table('migrations')->count();

    $failure = null;

    try {
        Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--step' => BILLING_MIGRATION_STEPS,
            '--force' => true,
        ]);
    } catch (Throwable $e) {
        $failure = $e;
    }

    expect($failure)->not->toBeNull()
        ->and($failure->getMessage())->toContain('Cannot rollback Billing & Finance schema')
        // and it names what is actually in the way
        ->and($failure->getMessage())->toContain('billing subscriptions')
        ->and($failure->getMessage())->toContain('renewal invoices');

    $schema = Schema::connection($connection);
    $db = DB::connection($connection);

    // Not one billing table or column was touched — this is the difference from
    // guarding inside the invoices migration alone.
    expect($schema->hasColumn('clients', 'billing_email'))->toBeTrue()
        ->and($schema->hasColumn('clients', 'billing_name'))->toBeTrue()
        ->and($schema->hasColumn('clients', 'billing_phone'))->toBeTrue();

    foreach (['service_packages', 'billing_subscriptions', 'invoice_items', 'payments', 'billing_reminder_logs'] as $table) {
        expect($schema->hasTable($table))->toBeTrue();
    }

    foreach (['purpose', 'client_id', 'billing_subscription_id', 'subtotal', 'billing_period_start'] as $column) {
        expect($schema->hasColumn('invoices', $column))->toBeTrue();
    }

    // The data itself is intact.
    expect($db->table('invoices')->where('purpose', 'renewal')->count())->toBe(1)
        ->and($db->table('payments')->count())->toBe(1)
        ->and($db->table('invoice_items')->count())->toBe(1)
        ->and($db->table('billing_reminder_logs')->count())->toBe(1)
        ->and($db->table('billing_subscriptions')->count())->toBe(1)
        ->and($db->table('clients')->value('billing_email'))->toBe('finance@abc.test');

    // And the migration ledger is exactly where it was — nothing half-applied.
    expect($db->table('migrations')->count())->toBe($migrationsBefore);
});

it('rolls back cleanly when no billing data exists, and migrates again', function () {
    $connection = billingRollbackConnection();
    $db = DB::connection($connection);
    $schema = Schema::connection($connection);

    // A client with no billing contact must not be mistaken for billing data.
    $db->table('clients')->insert([
        'company_name' => 'PT Tanpa Billing',
        'contact_name' => 'Sari',
        'email' => 'sari@tanpa.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migrationsBefore = $db->table('migrations')->count();

    Artisan::call('migrate:rollback', [
        '--database' => $connection,
        '--step' => BILLING_MIGRATION_STEPS,
        '--force' => true,
    ]);

    expect($db->table('migrations')->count())->toBe($migrationsBefore - BILLING_MIGRATION_STEPS)
        ->and($schema->hasTable('billing_subscriptions'))->toBeFalse()
        ->and($schema->hasTable('payments'))->toBeFalse()
        ->and($schema->hasColumn('clients', 'billing_email'))->toBeFalse()
        ->and($schema->hasColumn('invoices', 'purpose'))->toBeFalse()
        // the client survives the rollback, only the billing columns go
        ->and($db->table('clients')->count())->toBe(1);

    Artisan::call('migrate', ['--database' => $connection, '--force' => true]);

    expect($db->table('migrations')->count())->toBe($migrationsBefore)
        ->and($schema->hasTable('billing_subscriptions'))->toBeTrue()
        ->and($schema->hasColumn('clients', 'billing_email'))->toBeTrue()
        ->and($schema->hasColumn('invoices', 'purpose'))->toBeTrue();
});

it('treats a filled-in billing contact alone as data worth protecting', function () {
    $connection = billingRollbackConnection();

    DB::connection($connection)->table('clients')->insert([
        'company_name' => 'PT Kontak Saja',
        'contact_name' => 'Rina',
        'email' => 'rina@kontak.test',
        'billing_phone' => '08123456789',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $failure = null;

    try {
        Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--step' => BILLING_MIGRATION_STEPS,
            '--force' => true,
        ]);
    } catch (Throwable $e) {
        $failure = $e;
    }

    // Somebody typed that number in; dropping the column would throw it away.
    expect($failure)->not->toBeNull()
        ->and($failure->getMessage())->toContain('client billing contacts')
        ->and(Schema::connection($connection)->hasColumn('clients', 'billing_phone'))->toBeTrue();
});
