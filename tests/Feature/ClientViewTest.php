<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\FinanceSubscription;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_client_detail_page_with_projects_subscriptions_and_invoices(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'company_name' => 'PT Sinar Bamboo',
            'contact_name' => 'Dimas',
            'email' => 'pradanadimas534@gmail.com',
            'billing_email' => 'finance@sinarbamboo.com',
            'phone' => '085704717410',
            'whatsapp' => '085704717410',
            'address' => 'Purwoharjo, Banyuwangi',
            'website' => 'sinarbamboo.com',
            'instagram' => '@sinarbamboo',
            'notes' => 'Klien prioritas untuk website dan retainer bulanan.',
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'client_name' => $client->contact_name,
            'name' => 'Website Company Profile PT Sinar Bamboo',
            'type' => 'company',
            'status' => 'in_progress',
        ]);

        $subscription = FinanceSubscription::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'Cloud Hosting & Maintenance',
            'service_type' => 'hosting',
            'billing_cycle' => FinanceSubscription::CYCLE_YEARLY,
            'amount' => 1500000,
            'start_date' => now()->subMonths(6),
            'next_renewal_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'invoice_number' => 'INV-2026-0001',
            'type' => 'full',
            'purpose' => Invoice::PURPOSE_PROJECT,
            'amount' => 5000000,
            'due_date' => now()->addDays(7),
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get(route('pages.crm-view', $client));

        $response->assertOk();
        $response->assertSee('PT Sinar Bamboo');
        $response->assertSee('Dimas');
        $response->assertSee('pradanadimas534@gmail.com');
        $response->assertSee('finance@sinarbamboo.com');
        $response->assertSee('Purwoharjo, Banyuwangi');
        $response->assertSee('sinarbamboo.com');
        $response->assertSee('@sinarbamboo');
        $response->assertSee('Website Company Profile PT Sinar Bamboo');
        $response->assertSee('Cloud Hosting & Maintenance');
        $response->assertSee('INV-2026-0001');
        $response->assertSee('Rp 5.000.000');
        $response->assertSee('Rp 1.500.000');
        $response->assertSee('wa.me/6285704717410');
        $response->assertSee('Klien prioritas untuk website dan retainer bulanan.');
    }

    public function test_it_requires_authentication(): void
    {
        $client = Client::create([
            'company_name' => 'PT Test Client',
            'contact_name' => 'Contact Person',
        ]);

        $response = $this->get(route('pages.crm-view', $client));
        $response->assertRedirect(route('login'));
    }
}
