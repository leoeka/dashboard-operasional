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

    // A rollback walks the ledger newest-first, so anything merged after the
    // billing group would be counted as part of it. Roll those off first.
    $onTop = trailingMigrations();
    if ($onTop !== []) {
        Artisan::call('migrate:rollback', [
            '--database' => 'billing_rollback',
            '--step' => count($onTop),
            '--force' => true,
        ]);
    }

    return 'billing_rollback';
}

/**
 * The migrations this phase added, i.e. what "roll back billing" means.
 * Listed by name so an unrelated migration merged later cannot move the window
 * away from the group the guard protects.
 */
const BILLING_MIGRATIONS = [
    '2026_09_18_000001_create_service_packages_table',
    '2026_09_18_000002_create_billing_subscriptions_table',
    '2026_09_18_000003_create_invoice_items_table',
    '2026_09_18_000004_create_payments_table',
    '2026_09_18_000005_create_billing_reminder_logs_table',
    '2026_09_18_000006_extend_invoices_for_recurring_billing',
    '2026_09_18_000007_add_billing_contact_to_clients_table',
    '2026_10_12_063107_add_whatsapp_sent_at_to_billing_reminder_logs_table',
    '2026_10_14_000001_add_ends_on_to_billing_subscriptions_table',
];

/** Migration files merged after the newest member of the billing group. */
function trailingMigrations(): array
{
    $newestGrouped = max(BILLING_MIGRATIONS);

    $files = array_map(
        static fn (string $path): string => basename($path, '.php'),
        glob(database_path('migrations/*.php'))
    );

    return array_values(array_filter(
        $files,
        static fn (string $name): bool => $name > $newestGrouped
    ));
}

/**
 * How many steps reach the whole group: the group itself plus anything unrelated
 * merged inside its date range, since a rollback cannot skip a file.
 */
function billingRollbackSteps(string $connection): int
{
    $ledger = DB::connection($connection)
        ->table('migrations')
        ->orderByDesc('migration')
        ->pluck('migration')
        ->all();

    $deepest = 0;

    foreach (BILLING_MIGRATIONS as $name) {
        $position = array_search($name, $ledger, true);
        if ($position === false) {
            throw new RuntimeException("Billing migration {$name} is not in the test ledger; update BILLING_MIGRATIONS.");
        }
        $deepest = max($deepest, $position);
    }

    return $deepest + 1;
}

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

    $steps = billingRollbackSteps($connection);
    $migrationsBefore = DB::connection($connection)->table('migrations')->count();

    $failure = null;

    try {
        Artisan::call('migrate:rollback', [
            '--database' => $connection,
            '--step' => $steps,
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

    $steps = billingRollbackSteps($connection);
    $migrationsBefore = $db->table('migrations')->count();

    Artisan::call('migrate:rollback', [
        '--database' => $connection,
        '--step' => $steps,
        '--force' => true,
    ]);

    expect($db->table('migrations')->count())->toBe($migrationsBefore - $steps)
        ->and($schema->hasTable('billing_subscriptions'))->toBeFalse()
        ->and($schema->hasTable('payments'))->toBeFalse()
        ->and($schema->hasColumn('clients', 'billing_email'))->toBeFalse()
        ->and($schema->hasColumn('invoices', 'purpose'))->toBeFalse()
        // the client survives the rollback, only the billing columns go
        ->and($db->table('clients')->count())->toBe(1);

    Artisan::call('migrate', ['--database' => $connection, '--force' => true]);

    // Migrating again reapplies the whole group plus the unrelated file that was
    // merged inside its date range, and the ones rolled off before the test.
    expect($db->table('migrations')->count())->toBe($migrationsBefore + count(trailingMigrations()))
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
            '--step' => billingRollbackSteps($connection),
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
