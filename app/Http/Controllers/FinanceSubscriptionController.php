<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FinanceSubscription;
use App\Models\Project;
use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controller untuk pengelolaan langganan layanan berulang klien (Finance Subscription).
 */
class FinanceSubscriptionController extends Controller
{
    public function create()
    {
        return view('finance.subscription-form', [
            'subscription' => new FinanceSubscription(),
        ] + $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $subscription = FinanceSubscription::create($data + [
            'next_renewal_date' => $data['start_date'],
        ]);

        return redirect()
            ->route('pages.finance', ['tab' => 'subscriptions'])
            ->with('success', "Langganan \"{$subscription->name}\" dibuat. Invoice dan reminder akan berjalan otomatis sesuai jadwal.");
    }

    public function edit(FinanceSubscription $subscription)
    {
        return view('finance.subscription-form', [
            'subscription' => $subscription,
        ] + $this->formOptions());
    }

    public function update(Request $request, FinanceSubscription $subscription)
    {
        $subscription->update($this->validated($request));

        return redirect()
            ->route('pages.finance', ['tab' => 'subscriptions'])
            ->with('success', "Langganan \"{$subscription->name}\" diperbarui. Invoice yang sudah terbit tidak berubah.");
    }

    public function updateStatus(Request $request, FinanceSubscription $subscription)
    {
        $status = $request->validate([
            'status' => ['required', Rule::in(FinanceSubscription::STATUSES)],
        ])['status'];

        $subscription->update(['status' => $status]);

        $message = match ($status) {
            'active' => "Langganan \"{$subscription->name}\" diaktifkan kembali. Invoice dan reminder berjalan lagi.",
            'paused' => "Langganan \"{$subscription->name}\" dijeda. Tidak ada invoice atau reminder baru sampai diaktifkan.",
            'cancelled' => "Langganan \"{$subscription->name}\" dibatalkan. Invoice yang sudah terbit tetap tersimpan.",
            default => "Status langganan \"{$subscription->name}\" diperbarui.",
        };

        return back()->with('success', $message);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where(
                    fn ($query) => $query->where('client_id', $request->input('client_id'))
                ),
            ],
            'service_package_id' => ['nullable', 'exists:service_packages,id'],
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', Rule::in(FinanceSubscription::SERVICE_TYPES)],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'start_date' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:start_date'],
            'status' => ['required', Rule::in(FinanceSubscription::STATUSES)],
            'auto_reminder' => ['boolean'],
        ], [
            'project_id.exists' => 'Project yang dipilih bukan milik client ini.',
            'ends_on.after' => 'Tanggal berakhir langganan harus setelah tanggal mulai.',
        ]) + [
            'auto_reminder' => $request->boolean('auto_reminder'),
            'billing_cycle' => FinanceSubscription::cycleForServiceType($request->input('service_type')),
        ];
    }

    private function formOptions(): array
    {
        return [
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'client_id']),
            'packages' => ServicePackage::orderBy('name')->get(['id', 'name', 'category', 'price', 'unit']),
        ];
    }
}

