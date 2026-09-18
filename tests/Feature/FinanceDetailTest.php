<?php

use App\Models\BillingReminderLog;
use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Billing\ReminderPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4B: invoice detail, payments, reminder history and the derived timeline.
 *
 * Everything under test here reads. The detail page's buttons post to the
 * routes Phases 2–3 already own, and the reminder history is written only by
 * the scheduler and the queue worker — so what these tests protect is that the
 * page reports the truth, that it reports it cheaply, and that a schedule
 * change in ReminderPolicy reaches the screen without anyone editing a Blade
 * template.
 *
 * Helpers are named apart from FinanceWorkspaceTest's so both files can be
 * loaded in the same run.
 */
function fdUser(): User
{
    return User::factory()->create();
}

function fdClient(array $overrides = []): Client
{
    return Client::create(array_merge([
        'company_name' => 'PT Sumber Rejeki',
        'contact_name' => 'Budi',
        'email' => 'budi@sumber.test',
        'phone' => '08111111111',
    ], $overrides));
}

function fdProject(Client $client, string $name = 'Website Sumber'): Project
{
    return Project::create([
        'client_id' => $client->id,
        'name' => $name,
        'client_name' => $client->company_name,
        'status' => 'request',
    ]);
}

function fdSubscription(Client $client, array $overrides = []): BillingSubscription
{
    return BillingSubscription::create(array_merge([
        'client_id' => $client->id,
        'name' => 'Hosting + Domain',
        'service_type' => 'hosting',
        'billing_cycle' => BillingSubscription::CYCLE_YEARLY,
        'amount' => 2500000,
        'start_date' => '2025-06-01',
        'next_renewal_date' => '2026-06-01',
    ], $overrides));
}

function fdInvoice(array $attributes): Invoice
{
    return Invoice::create(array_merge([
        'invoice_number' => 'INV-D-' . fake()->unique()->numberBetween(10000, 99999),
        'type' => 'full',
        'amount' => 1000000,
        'due_date' => today(),
    ], $attributes));
}

/** A renewal invoice wired to its subscription, with the billing period set. */
function fdRenewalInvoice(BillingSubscription $subscription, string $renewalDate = '2026-06-01'): Invoice
{
    return fdInvoice([
        'client_id' => $subscription->client_id,
        'billing_subscription_id' => $subscription->id,
        'purpose' => Invoice::PURPOSE_RENEWAL,
        'issue_date' => Carbon::parse($renewalDate)->subDays(30)->toDateString(),
        'due_date' => $renewalDate,
        'billing_period_start' => $renewalDate,
        'billing_period_end' => Carbon::parse($renewalDate)->addYear()->subDay()->toDateString(),
        'amount' => $subscription->amount,
    ]);
}

function fdLog(Invoice $invoice, int $daysBefore, array $overrides = []): BillingReminderLog
{
    return BillingReminderLog::create(array_merge([
        'invoice_id' => $invoice->id,
        'billing_subscription_id' => $invoice->billing_subscription_id,
        'days_before' => $daysBefore,
        'scheduled_for' => $invoice->due_date->copy()->subDays($daysBefore)->toDateString(),
        'status' => BillingReminderLog::SENT,
        'sent_at' => now(),
        'recipient' => 'budi@sumber.test',
    ], $overrides));
}

function fdPayment(Invoice $invoice, array $overrides = []): Payment
{
    return Payment::create(array_merge([
        'invoice_id' => $invoice->id,
        'paid_on' => today(),
        'amount' => $invoice->amount,
        'method' => 'bank_transfer',
        'reference' => 'TRX-0001',
    ], $overrides));
}

afterEach(fn () => Carbon::setTestNow());

// --------------------------------------------------------- invoice detail ---

it('shows a project invoice in full', function () {
    $client = fdClient();
    $project = fdProject($client);
    $invoice = fdInvoice([
        'project_id' => $project->id,
        'invoice_number' => 'INV-2026-0007',
        'type' => 'dp',
        'amount' => 5000000,
    ]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('INV-2026-0007')
        ->assertSee('PT Sumber Rejeki')
        ->assertSee('Website Sumber')
        ->assertSee('Belum Dibayar');
});

it('shows a renewal invoice with its subscription and billing period', function () {
    $client = fdClient();
    $subscription = fdSubscription($client);
    $invoice = fdRenewalInvoice($subscription);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Perpanjangan')
        ->assertSee('Hosting + Domain')
        ->assertSee('Tahunan')
        // The period the invoice covers, not just its due date. Formatted
        // through Carbon so the assertion follows the app locale rather than
        // pinning one.
        ->assertSee($invoice->billing_period_start->translatedFormat('d M Y'))
        ->assertSee($invoice->billing_period_end->translatedFormat('d M Y'));
});

it('prefers the billing contact over the general one', function () {
    $client = fdClient([
        'billing_name' => 'Finance Dept',
        'billing_email' => 'finance@sumber.test',
        'billing_phone' => '08222222222',
    ]);
    $invoice = fdInvoice(['client_id' => $client->id]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Finance Dept')
        ->assertSee('finance@sumber.test')
        ->assertSee('08222222222')
        ->assertDontSee('budi@sumber.test');
});

it('falls back to the general contact when no billing contact is set', function () {
    $client = fdClient();
    $invoice = fdInvoice(['client_id' => $client->id]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Budi')
        ->assertSee('budi@sumber.test');
});

it('shows a dash when the client has no contact details at all', function () {
    $client = Client::create(['company_name' => 'PT Tanpa Kontak']);
    $invoice = fdInvoice(['client_id' => $client->id]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('PT Tanpa Kontak')
        ->assertSee('—');
});

it('lists the invoice items with quantity, unit price and total', function () {
    $client = fdClient();
    $invoice = fdInvoice(['client_id' => $client->id, 'amount' => 3000000]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Perpanjangan hosting 1 tahun',
        'quantity' => 1,
        'unit_price' => 2500000,
        'total' => 2500000,
        'position' => 1,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Domain .co.id',
        'quantity' => 2,
        'unit_price' => 250000,
        'total' => 500000,
        'position' => 2,
    ]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Perpanjangan hosting 1 tahun')
        ->assertSee('Domain .co.id')
        ->assertSee('2.500.000')
        ->assertSee('500.000');
});

it('shows the money summary including discount and tax', function () {
    $client = fdClient();
    $invoice = fdInvoice([
        'client_id' => $client->id,
        'subtotal' => 3000000,
        'discount' => 200000,
        'tax' => 100000,
        'amount' => 2900000,
    ]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Subtotal')
        ->assertSee('3.000.000')
        ->assertSee('200.000')
        ->assertSee('100.000')
        ->assertSee('2.900.000');
});

it('lists the payments recorded against the invoice', function () {
    $client = fdClient();
    $recorder = User::factory()->create(['name' => 'Rina Kasir']);
    $invoice = fdInvoice(['client_id' => $client->id, 'status' => 'paid', 'paid_at' => today()]);

    fdPayment($invoice, [
        'method' => 'qris',
        'reference' => 'QR-99887',
        'recorded_by' => $recorder->id,
        'notes' => 'Masuk lewat QRIS merchant',
    ]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('QRIS')
        ->assertSee('QR-99887')
        ->assertSee('Rina Kasir')
        ->assertSee('Masuk lewat QRIS merchant');
});

it('says plainly when nothing has been paid yet', function () {
    $invoice = fdInvoice(['client_id' => fdClient()->id]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Belum ada pembayaran tercatat.');
});

it('keeps the invoice detail behind authentication', function () {
    $invoice = fdInvoice(['client_id' => fdClient()->id]);

    $this->get(route('pages.finance.invoices.show', $invoice))->assertRedirect(route('login'));
});

// ------------------------------------------------------------ detail actions ---

it('offers a manual reminder on an unpaid project invoice', function () {
    $client = fdClient();
    $invoice = fdInvoice(['project_id' => fdProject($client)->id]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee(route('pages.finance.remind', $invoice))
        ->assertSee(route('pages.finance.paid', $invoice));
});

it('offers no manual reminder on a renewal invoice', function () {
    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);

    // A renewal follows its thresholds; a hand-sent email would not be one.
    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee(route('pages.finance.remind', $invoice))
        ->assertSee(route('pages.finance.paid', $invoice));
});

it('offers no action at all once the invoice is paid', function () {
    $client = fdClient();
    $invoice = fdInvoice([
        'project_id' => fdProject($client)->id,
        'status' => 'paid',
        'paid_at' => today(),
    ]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee(route('pages.finance.remind', $invoice))
        ->assertDontSee(route('pages.finance.paid', $invoice))
        ->assertSee('Lunas');
});

it('marks an invoice paid from the detail page through the existing route', function () {
    $client = fdClient();
    $invoice = fdInvoice(['project_id' => fdProject($client)->id]);

    $this->actingAs(fdUser())
        ->patch(route('pages.finance.paid', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
});

// ----------------------------------------------------------- payments tab ---

it('lists received payments newest first', function () {
    $client = fdClient();
    $older = fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-OLD']);
    $newer = fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-NEW']);

    fdPayment($older, ['paid_on' => '2026-01-10']);
    fdPayment($newer, ['paid_on' => '2026-03-20']);

    $response = $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'payments']))
        ->assertOk()
        ->assertSee('INV-NEW')
        ->assertSee('INV-OLD');

    expect(strpos($response->getContent(), 'INV-NEW'))
        ->toBeLessThan(strpos($response->getContent(), 'INV-OLD'));
});

it('filters payments by method', function () {
    $client = fdClient();
    fdPayment(fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-BANK']), ['method' => 'bank_transfer']);
    fdPayment(fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-CASH']), ['method' => 'cash']);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'payments', 'method' => 'cash']))
        ->assertOk()
        ->assertSee('INV-CASH')
        ->assertDontSee('INV-BANK');
});

it('filters payments by date range', function () {
    $client = fdClient();
    fdPayment(fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-JAN']), ['paid_on' => '2026-01-15']);
    fdPayment(fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-MAR']), ['paid_on' => '2026-03-15']);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'payments', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertOk()
        ->assertSee('INV-MAR')
        ->assertDontSee('INV-JAN');
});

it('filters payments by client through both routes to one', function () {
    $wanted = fdClient(['company_name' => 'PT Dicari']);
    $other = fdClient(['company_name' => 'PT Lainnya', 'email' => 'lain@test.test']);

    // A renewal names its client directly…
    fdPayment(fdInvoice([
        'client_id' => $wanted->id,
        'invoice_number' => 'INV-DIRECT',
        'purpose' => Invoice::PURPOSE_RENEWAL,
    ]));

    // …a project invoice reaches one through its project. Both must match.
    fdPayment(fdInvoice([
        'project_id' => fdProject($wanted, 'Site Dicari')->id,
        'invoice_number' => 'INV-VIAPROJECT',
    ]));

    fdPayment(fdInvoice(['client_id' => $other->id, 'invoice_number' => 'INV-ASING']));

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'payments', 'client' => $wanted->id]))
        ->assertOk()
        ->assertSee('INV-DIRECT')
        ->assertSee('INV-VIAPROJECT')
        ->assertDontSee('INV-ASING');
});

it('searches payments by invoice number', function () {
    $client = fdClient();
    fdPayment(fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-2026-0042']));
    fdPayment(fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-2025-0099']));

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'payments', 'q' => '2026-0042']))
        ->assertOk()
        ->assertSee('INV-2026-0042')
        ->assertDontSee('INV-2025-0099');
});

it('keeps the payment filters attached to the pagination links', function () {
    $client = fdClient();

    foreach (range(1, 25) as $i) {
        fdPayment(fdInvoice(['client_id' => $client->id, 'invoice_number' => 'INV-P-' . $i]), ['method' => 'cash']);
    }

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'payments', 'method' => 'cash']))
        ->assertOk()
        ->assertSee('method=cash', false)
        ->assertSee('page=2', false);
});

it('explains an empty payments tab instead of showing a bare table', function () {
    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'payments']))
        ->assertOk()
        ->assertSee('Belum ada pembayaran tercatat');
});

it('does not query once per payment row', function () {
    $client = fdClient();
    $recorder = User::factory()->create();

    foreach (range(1, 20) as $i) {
        $invoice = fdInvoice([
            'project_id' => fdProject($client, 'Site ' . $i)->id,
            'invoice_number' => 'INV-N-' . $i,
        ]);
        fdPayment($invoice, ['recorded_by' => $recorder->id]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->actingAs(fdUser())->get(route('pages.finance', ['tab' => 'payments']))->assertOk();

    // Eager loading means the count stays flat whether there are two rows or
    // twenty; without it each row would fetch its invoice, project and recorder.
    expect($queries)->toBeLessThan(30);
});

// --------------------------------------------------- reminder history tab ---

it('lists the reminder log with its threshold and recipient', function () {
    $client = fdClient();
    $subscription = fdSubscription($client);
    $invoice = fdRenewalInvoice($subscription);
    fdLog($invoice, 30);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk()
        ->assertSee('PT Sumber Rejeki')
        ->assertSee($invoice->invoice_number)
        ->assertSee('Hosting + Domain')
        ->assertSee('H-30')
        ->assertSee('budi@sumber.test')
        ->assertSee('Terkirim');
});

it('filters the reminder log by status', function () {
    $client = fdClient();
    $subscription = fdSubscription($client);

    $sent = fdRenewalInvoice($subscription);
    $sent->update(['invoice_number' => 'INV-SENT']);
    fdLog($sent, 30);

    $failed = fdRenewalInvoice($subscription);
    $failed->update(['invoice_number' => 'INV-FAILED']);
    fdLog($failed, 30, [
        'status' => BillingReminderLog::FAILED,
        'sent_at' => null,
        'attempts' => 2,
        'error_message' => 'Alamat email tidak dapat dijangkau',
    ]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders', 'status' => BillingReminderLog::FAILED]))
        ->assertOk()
        ->assertSee('INV-FAILED')
        ->assertSee('Gagal')
        ->assertSee('2/' . BillingReminderLog::MAX_ATTEMPTS)
        ->assertDontSee('INV-SENT');
});

it('filters the reminder log by threshold', function () {
    $client = fdClient();
    $subscription = fdSubscription($client);
    $invoice = fdRenewalInvoice($subscription);
    $invoice->update(['invoice_number' => 'INV-THRESH']);

    fdLog($invoice, 30);
    fdLog($invoice, 7);

    $response = $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders', 'threshold' => 7]))
        ->assertOk()
        ->assertSee('H-7');

    // One row, not two: the H-30 claim for the same invoice is filtered out.
    expect(substr_count($response->getContent(), 'INV-THRESH'))->toBe(1);
});

it('filters the reminder log by client', function () {
    $wanted = fdClient(['company_name' => 'PT Dicari']);
    $other = fdClient(['company_name' => 'PT Lainnya', 'email' => 'lain@test.test']);

    $wantedInvoice = fdRenewalInvoice(fdSubscription($wanted));
    $wantedInvoice->update(['invoice_number' => 'INV-WANTED']);
    fdLog($wantedInvoice, 30);

    $otherInvoice = fdRenewalInvoice(fdSubscription($other));
    $otherInvoice->update(['invoice_number' => 'INV-OTHER']);
    fdLog($otherInvoice, 30);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders', 'client' => $wanted->id]))
        ->assertOk()
        ->assertSee('INV-WANTED')
        ->assertDontSee('INV-OTHER');
});

it('shows a pending claim as waiting rather than as sent', function () {
    $invoice = fdRenewalInvoice(fdSubscription(fdClient()));
    fdLog($invoice, 30, ['status' => BillingReminderLog::PENDING, 'sent_at' => null]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk()
        ->assertSee('Menunggu');
});

it('escapes a failure message instead of rendering it as markup', function () {
    $invoice = fdRenewalInvoice(fdSubscription(fdClient()));
    fdLog($invoice, 30, [
        'status' => BillingReminderLog::FAILED,
        'sent_at' => null,
        'attempts' => 1,
        'error_message' => '<script>alert(1)</script> koneksi ditolak',
    ]);

    $response = $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk();

    expect($response->getContent())
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});

it('truncates a long failure message rather than printing a wall of text', function () {
    $invoice = fdRenewalInvoice(fdSubscription(fdClient()));
    $long = str_repeat('kesalahan pengiriman ', 40) . 'EKORPESAN';

    fdLog($invoice, 30, [
        'status' => BillingReminderLog::FAILED,
        'sent_at' => null,
        'attempts' => 1,
        'error_message' => $long,
    ]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk()
        ->assertSee('kesalahan pengiriman')
        // The tail never reaches the page: a reader needs the reason, not the
        // whole message.
        ->assertDontSee('EKORPESAN');
});

it('offers no way to change a reminder log from the page', function () {
    $invoice = fdRenewalInvoice(fdSubscription(fdClient()));
    fdLog($invoice, 30);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk();

    // The history is written by the scheduler and the worker. Asserted against
    // the partial rather than the rendered page, because the page also carries
    // the legacy manual-invoice form on every tab.
    $markup = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(resource_path('views/finance/partials/reminders.blade.php')));

    expect($markup)->not->toContain('method="POST"')
        ->not->toContain('@method(')
        ->not->toContain('@csrf');
});

it('keeps the reminder filters attached to the pagination links', function () {
    $subscription = fdSubscription(fdClient());

    foreach (range(1, 25) as $i) {
        $invoice = fdRenewalInvoice($subscription);
        $invoice->update(['invoice_number' => 'INV-R-' . $i]);
        fdLog($invoice, 30);
    }

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders', 'status' => BillingReminderLog::SENT]))
        ->assertOk()
        ->assertSee('status=sent', false)
        ->assertSee('page=2', false);
});

it('explains an empty reminder history instead of showing a bare table', function () {
    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk()
        ->assertSee('Belum ada reminder terkirim');
});

it('does not query once per reminder row', function () {
    $client = fdClient();
    $subscription = fdSubscription($client);

    foreach (range(1, 20) as $i) {
        $invoice = fdRenewalInvoice($subscription);
        $invoice->update(['invoice_number' => 'INV-RN-' . $i]);
        fdLog($invoice, 30);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->actingAs(fdUser())->get(route('pages.finance', ['tab' => 'reminders']))->assertOk();

    expect($queries)->toBeLessThan(30);
});

// -------------------------------------------------------------- timeline ---

it('plots exactly the thresholds the policy defines, never a copy of them', function () {
    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);

    $thresholds = app(ReminderPolicy::class)->thresholdsFor($subscription);

    $response = $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk();

    foreach ($thresholds as $threshold) {
        $response->assertSee('H-' . $threshold);
    }

    // And nothing beyond them — a template carrying its own list would show a
    // threshold the engine never sends.
    foreach ([1, 3, 7, 14, 30, 60] as $candidate) {
        if (!in_array($candidate, $thresholds, true)) {
            expect($response->getContent())->not->toContain('>H-' . $candidate . '<');
        }
    }
});

it('follows the monthly schedule for a monthly subscription', function () {
    $subscription = fdSubscription(fdClient(), [
        'name' => 'SEO Bulanan',
        'service_type' => 'seo',
        'billing_cycle' => BillingSubscription::CYCLE_MONTHLY,
        'start_date' => '2026-05-01',
        'next_renewal_date' => '2026-06-01',
    ]);
    $invoice = fdRenewalInvoice($subscription);

    $policy = app(ReminderPolicy::class);

    $response = $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk();

    foreach ($policy->thresholdsFor($subscription) as $threshold) {
        $response->assertSee('H-' . $threshold);
    }

    // The monthly and yearly schedules differ, and this page must show the one
    // that actually applies to this subscription.
    expect($policy->thresholdsFor($subscription))
        ->not->toBe($policy->thresholdsFor(fdSubscription(fdClient(['company_name' => 'PT Tahunan', 'email' => 'th@test.test']))));
});

it('marks a threshold that was delivered as sent', function () {
    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);
    fdLog($invoice, 30);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Terkirim');
});

it('marks a failed threshold with how many attempts it has had', function () {
    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);
    fdLog($invoice, 30, [
        'status' => BillingReminderLog::FAILED,
        'sent_at' => null,
        'attempts' => 2,
        'error_message' => 'Host penerima menolak koneksi',
    ]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Gagal · percobaan 2/' . BillingReminderLog::MAX_ATTEMPTS)
        ->assertSee('Host penerima menolak koneksi');
});

it('marks a claimed but undelivered threshold as waiting', function () {
    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);
    fdLog($invoice, 30, ['status' => BillingReminderLog::PENDING, 'sent_at' => null]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Menunggu kirim');
});

it('marks a threshold whose day has not come as upcoming', function () {
    // Well before every threshold of a 2026-06-01 renewal.
    Carbon::setTestNow('2026-01-15 08:00:00');

    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Belum waktunya')
        ->assertDontSee('Tidak tercatat');
});

it('reports a past threshold with no log at all', function () {
    // After every threshold, with nothing ever recorded — which is exactly the
    // case the logs table cannot show, because the row is absent. The wording
    // stays neutral: the missing row proves no reminder was logged, and says
    // nothing about the cause.
    Carbon::setTestNow('2026-05-30 08:00:00');

    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Tidak tercatat');
});

it('plots no timeline for a project invoice', function () {
    $client = fdClient();
    $invoice = fdInvoice(['project_id' => fdProject($client)->id]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee('Jadwal Reminder');
});

it('stores nothing while deriving the timeline', function () {
    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);

    $before = BillingReminderLog::count();

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk();

    // Viewing the page must not claim a threshold — that would send an email
    // the scheduler never scheduled.
    expect(BillingReminderLog::count())->toBe($before);
});

// --------------------------------------------------------- compatibility ---

it('offers all five tabs', function () {
    $response = $this->actingAs(fdUser())->get(route('pages.finance'))->assertOk();

    foreach (['Overview', 'Langganan', 'Invoice', 'Pembayaran', 'Riwayat Reminder'] as $label) {
        $response->assertSee($label);
    }
});

it('falls back to the overview for a tab that does not exist', function () {
    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'tidak-ada']))
        ->assertOk()
        ->assertSee('Perpanjangan Mendatang');
});

it('links every invoice row to its detail page', function () {
    $client = fdClient();
    $invoice = fdInvoice(['client_id' => $client->id]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'invoices']))
        ->assertOk()
        ->assertSee(route('pages.finance.invoices.show', $invoice));
});

it('links an upcoming renewal to its subscription and offers no invoice button', function () {
    $subscription = fdSubscription(fdClient(), ['next_renewal_date' => today()->addDays(10)->toDateString()]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance'))
        ->assertOk()
        ->assertSee(route('pages.finance.subscriptions.edit', $subscription))
        // Invoices are issued by the daily run, which owns the numbering and
        // the idempotency. A button here could duplicate a period.
        ->assertDontSee('Generate Invoice');
});

it('keeps the summary cards on every tab', function () {
    foreach (['overview', 'subscriptions', 'invoices', 'payments', 'reminders'] as $tab) {
        $this->actingAs(fdUser())
            ->get(route('pages.finance', ['tab' => $tab]))
            ->assertOk()
            ->assertSee('Belum Dibayar');
    }
});

it('only uses Alpine directives the runtime actually loads', function () {
    // Same guard as Phase 4A, extended to the views this phase added: the
    // layout pulls Alpine core from a CDN with no plugins.
    foreach ([
        'finance/invoice-detail.blade.php',
        'finance/partials/payments.blade.php',
        'finance/partials/reminders.blade.php',
        'finance/partials/overview.blade.php',
    ] as $view) {
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents(resource_path('views/' . $view)));

        expect($markup)->not->toContain('x-collapse')
            ->not->toContain('x-intersect')
            ->not->toContain('x-mask');
    }
});

it('never prints a class name assembled at runtime for the timeline', function () {
    // Tailwind's scanner cannot see "bg-{$tone}-400", so the dot would come out
    // unstyled in a compiled build even though it works under the CDN.
    $markup = file_get_contents(resource_path('views/finance/invoice-detail.blade.php'));

    expect($markup)->not->toMatch('/bg-\{\{/');
});

it('leaves the legacy finance actions working', function () {
    $client = fdClient();
    $project = fdProject($client);

    $this->actingAs(fdUser())
        ->post(route('pages.finance.store'), [
            'project_id' => $project->id,
            'type' => 'dp',
            'amount' => 1500000,
            'due_date' => today()->addDays(14)->toDateString(),
        ])
        ->assertRedirect();

    expect(Invoice::where('project_id', $project->id)->count())->toBe(1);
});

// ------------------------------------------------- timeline day semantics ---

it('does not call a threshold missed on the very day it falls due', function () {
    // H-30 of a 2026-06-01 renewal is 2026-05-02. The daily run fires at 09:00
    // in the billing zone, so at any hour of that day the threshold has either
    // not run yet or has only just run — neither is a miss.
    Carbon::setTestNow(Carbon::parse('2026-05-02 00:30:00', config('billing.timezone'))->utc());

    $invoice = fdRenewalInvoice(fdSubscription(fdClient()));

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Dijadwalkan hari ini')
        ->assertDontSee('Tidak tercatat');
});

it('reports a threshold that passed yesterday unrecorded, while it is still unpaid', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-03 10:00:00', config('billing.timezone'))->utc());

    $invoice = fdRenewalInvoice(fdSubscription(fdClient()));

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        // Neutral wording on purpose: the absent row proves no reminder was
        // logged, not why it was not.
        ->assertSee('Tidak tercatat')
        ->assertDontSee('Tidak diperlukan');
});

it('keeps a threshold still to come as upcoming', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00', config('billing.timezone'))->utc());

    $invoice = fdRenewalInvoice(fdSubscription(fdClient()));

    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Belum waktunya')
        ->assertDontSee('Tidak tercatat');
});

it('does not flag the thresholds an early payment made unnecessary', function () {
    // The case from the spec: renewal 2026-06-01, H-30 sent, client pays on
    // 2026-05-15, so H-7 (25 May) and H-3 (29 May) are never claimed.
    Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00', config('billing.timezone'))->utc());

    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);
    fdLog($invoice, 30);

    $invoice->update(['status' => 'paid', 'paid_at' => '2026-05-15']);

    $response = $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Terkirim')
        ->assertDontSee('Tidak tercatat');

    // Both remaining thresholds, and only those two.
    expect(substr_count($response->getContent(), 'Tidak diperlukan'))->toBe(2);
});

it('still reports a threshold that passed before the payment arrived', function () {
    // H-30 fell on 2026-05-02 with nothing recorded; the invoice was not paid
    // until 2026-05-15. The payment cannot explain the earlier silence.
    Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00', config('billing.timezone'))->utc());

    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);
    $invoice->update(['status' => 'paid', 'paid_at' => '2026-05-15']);

    $response = $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Tidak tercatat');

    // H-7 and H-3 fall after the payment, so only H-30 is left unexplained.
    expect(substr_count($response->getContent(), 'Tidak tercatat'))->toBe(1)
        ->and(substr_count($response->getContent(), 'Tidak diperlukan'))->toBe(2);
});

it('treats a payment landing exactly on a threshold as settling it', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00', config('billing.timezone'))->utc());

    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);
    // 2026-05-25 is H-7 exactly.
    $invoice->update(['status' => 'paid', 'paid_at' => '2026-05-25']);

    $response = $this->actingAs(fdUser())->get(route('pages.finance.invoices.show', $invoice))->assertOk();

    // H-7 and H-3 settled; H-30 passed long before the payment.
    expect(substr_count($response->getContent(), 'Tidak diperlukan'))->toBe(2)
        ->and(substr_count($response->getContent(), 'Tidak tercatat'))->toBe(1);
});

it('claims nothing about old thresholds when auto reminder is switched off', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00', config('billing.timezone'))->utc());

    $subscription = fdSubscription(fdClient(), ['auto_reminder' => false]);
    $invoice = fdRenewalInvoice($subscription);

    // Nothing is expected for days still to come, and the page says that
    // rather than implying a failure. There is no history of when the flag was
    // turned off, so it never speaks for the past.
    $this->actingAs(fdUser())
        ->get(route('pages.finance.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Reminder nonaktif')
        ->assertDontSee('Tidak tercatat');
});

it('derives every day-semantics state without writing a row', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-02 10:00:00', config('billing.timezone'))->utc());

    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);
    $invoice->update(['status' => 'paid', 'paid_at' => '2026-05-20']);

    $before = BillingReminderLog::count();

    $this->actingAs(fdUser())->get(route('pages.finance.invoices.show', $invoice))->assertOk();

    expect(BillingReminderLog::count())->toBe($before)
        ->and($invoice->fresh()->status)->toBe('paid');
});

// ----------------------------------------------- overdue across timezones ---

it('agrees on overdue across the UTC and billing day boundary', function () {
    // 16:30 UTC is already 00:30 the next day in Asia/Makassar, so an invoice
    // due 22 Oct is overdue to the business while UTC still calls it today.
    $instant = Carbon::parse('2026-10-22 16:30:00', 'UTC');
    Carbon::setTestNow($instant);

    expect($instant->copy()->setTimezone(config('billing.timezone'))->toDateString())->toBe('2026-10-23');

    $invoice = fdInvoice([
        'client_id' => fdClient()->id,
        'invoice_number' => 'INV-TZ-0001',
        'due_date' => '2026-10-22',
        'status' => 'unpaid',
    ]);

    // The model, the badge and the summary must say the same thing.
    expect($invoice->isOverdue())->toBeTrue()
        ->and($invoice->statusLabel())->toBe('Terlambat')
        ->and($invoice->statusColor())->toBe('red');

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'invoices', 'status' => 'overdue']))
        ->assertOk()
        ->assertSee('INV-TZ-0001');
});

it('does not call an invoice overdue before the business day has turned', function () {
    // 15:30 UTC is still 23:30 on the 22nd in Makassar.
    Carbon::setTestNow(Carbon::parse('2026-10-22 15:30:00', 'UTC'));

    $invoice = fdInvoice([
        'client_id' => fdClient()->id,
        'invoice_number' => 'INV-TZ-0002',
        'due_date' => '2026-10-22',
        'status' => 'unpaid',
    ]);

    expect($invoice->isOverdue())->toBeFalse()
        ->and($invoice->statusLabel())->toBe('Belum Dibayar');

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'invoices', 'status' => 'overdue']))
        ->assertOk()
        ->assertDontSee('INV-TZ-0002');
});

it('reads overdue from the configured billing zone rather than a fixed one', function () {
    // Pinned to Asia/Makassar nowhere: point the config at a zone on the other
    // side of UTC and the same instant must stop being overdue.
    config(['billing.timezone' => 'America/New_York']);
    app()->forgetInstance(\App\Services\Billing\BillingClock::class);

    Carbon::setTestNow(Carbon::parse('2026-10-23 01:00:00', 'UTC'));

    $invoice = fdInvoice([
        'client_id' => fdClient()->id,
        'due_date' => '2026-10-22',
        'status' => 'unpaid',
    ]);

    // 01:00 UTC on the 23rd is still 21:00 on the 22nd in New York.
    expect($invoice->isOverdue())->toBeFalse();
});

it('never calls a paid invoice overdue', function () {
    Carbon::setTestNow(Carbon::parse('2026-12-01 10:00:00', 'UTC'));

    $invoice = fdInvoice([
        'client_id' => fdClient()->id,
        'due_date' => '2026-10-22',
        'status' => 'paid',
        'paid_at' => '2026-10-20',
    ]);

    expect($invoice->isOverdue())->toBeFalse()
        ->and($invoice->statusLabel())->toBe('Lunas');
});

// -------------------------------------------------------- sent_at display ---

it('shows the delivery time in the billing zone without moving the stored one', function () {
    $subscription = fdSubscription(fdClient());
    $invoice = fdRenewalInvoice($subscription);

    // 23:30 UTC is 07:30 the next morning in Makassar.
    $log = fdLog($invoice, 30, ['sent_at' => Carbon::parse('2026-10-22 23:30:00', 'UTC')]);

    $this->actingAs(fdUser())
        ->get(route('pages.finance', ['tab' => 'reminders']))
        ->assertOk()
        ->assertSee('23 Oct 2026 07:30 WITA');

    // Storage is untouched: still the same UTC instant.
    expect($log->fresh()->sent_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-22 23:30');
});
