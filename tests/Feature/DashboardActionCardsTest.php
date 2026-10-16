<?php

/**
 * Dashboard cards must be actionable, not decorative.
 *
 * DESIGN bagian 6: satu kartu hanya boleh ada kalau angkanya bisa diklik ke tempat
 * angkanya diselesaikan, dan bar kiri 3px hanya muncul ketika memang ada yang menunggu.
 * Yang dijaga di sini jadi bukan warnanya, tapi dua aturan itu.
 */

use App\Models\BillingReminderLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;

function dbUser(): User
{
    return User::factory()->create();
}

function dbInvoice(array $attributes = []): Invoice
{
    return Invoice::create(array_merge([
        'invoice_number' => 'INV-DB-' . fake()->unique()->numberBetween(10000, 99999),
        'type' => 'full',
        'amount' => 1500000,
        'status' => 'unpaid',
        'due_date' => today()->subDays(4)->toDateString(),
    ], $attributes));
}

function dbProject(string $status = 'request'): Project
{
    return Project::create([
        'name' => 'Website ' . fake()->word(),
        'client_name' => 'PT Contoh',
        'status' => $status,
    ]);
}

it('links every statistic card to the page that resolves its number', function () {
    $response = $this->actingAs(dbUser())->get('/');

    $html = $response->getContent();

    expect($html)->toContain('/finance?tab=invoices&amp;status=overdue')
        ->and($html)->toContain('/finance?tab=reminders&amp;status=failed')
        ->and($html)->toContain('/projects?status=request')
        ->and($html)->toContain('/projects?status=in_progress');
});

it('shows the overdue amount instead of a count of all unpaid invoices', function () {
    dbInvoice(['amount' => 2500000]);
    dbInvoice(['amount' => 1000000, 'due_date' => today()->addDays(9)->toDateString()]);

    $this->actingAs(dbUser())->get('/')
        ->assertSee('Invoice Lewat Jatuh Tempo')
        ->assertSee('Rp 2.500.000');
});

it('marks only the cards that demand action today with the left accent', function () {
    $html = $this->actingAs(dbUser())->get('/')->getContent();
    expect(substr_count($html, 'border-l-brand-600'))->toBe(0);

    dbInvoice();
    dbProject('request');

    $html = $this->actingAs(dbUser())->get('/')->getContent();
    // Dua kartu aktif hari ini: invoice lewat jatuh tempo dan request menunggu.
    expect(substr_count($html, 'border-l-brand-600'))->toBe(2)
        ->and($html)->toContain('border-l-[3px] border-l-brand-600');
});

it('drops the project-type card and its three numbers', function () {
    $this->actingAs(dbUser())->get('/')
        ->assertDontSee('Jenis Project')
        ->assertDontSee('Backlink');
});

it('points the empty attention list at the next step', function () {
    $this->actingAs(dbUser())->get('/')
        ->assertSee('Tidak ada yang menunggu hari ini')
        ->assertSee('Buat request baru');
});

it('counts reminders whose delivery failed', function () {
    $invoice = dbInvoice();
    BillingReminderLog::create([
        'invoice_id' => $invoice->id,
        'days_before' => 7,
        'scheduled_for' => today()->toDateString(),
        'status' => BillingReminderLog::FAILED,
        'attempts' => 1,
    ]);

    $this->actingAs(dbUser())->get('/')
        ->assertSee('Pengingat Gagal Terkirim')
        ->assertSee('Perlu dikirim ulang dari Finance');
});
