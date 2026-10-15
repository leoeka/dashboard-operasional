<?php

namespace App\Http\Controllers;

use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\Project;
use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Creating and editing the recurring services a client pays for.
 *
 * Kept out of InvoiceController: that one owns invoice actions, and
 * subscriptions are a different thing with a different lifecycle.
 *
 * Nothing here touches the renewal engine automatically. The form records what
 * was agreed — what it costs, when it started, and when the term ends. The
 * calendar belongs to BillingRenewalService, which works out on its daily run
 * which period is current and rolls it forward until `ends_on`.
 */
class BillingSubscriptionController extends Controller
{
    public function create()
    {
        return view('finance.subscription-form', [
            'subscription' => new BillingSubscription(),
        ] + $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $subscription = BillingSubscription::create($data + [
            // The first period opens on the day the client signs up. From there
            // the daily run owns the date entirely, so no form field asks for
            // it — an operator sets the term, not the calendar.
            'next_renewal_date' => $data['start_date'],
        ]);

        return redirect()
            ->route('pages.finance', ['tab' => 'subscriptions'])
            ->with('success', "Langganan \"{$subscription->name}\" dibuat. Invoice dan reminder akan berjalan otomatis sesuai jadwal.");
    }

    public function edit(BillingSubscription $subscription)
    {
        return view('finance.subscription-form', [
            'subscription' => $subscription,
        ] + $this->formOptions());
    }

    public function update(Request $request, BillingSubscription $subscription)
    {
        $subscription->update($this->validated($request));

        return redirect()
            ->route('pages.finance', ['tab' => 'subscriptions'])
            // Said out loud because it is the question an operator will have:
            // changing the price here does not rewrite what was already billed.
            ->with('success', "Langganan \"{$subscription->name}\" diperbarui. Invoice yang sudah terbit tidak berubah.");
    }

    /**
     * Pause, resume or cancel — never delete.
     *
     * A subscription is what explains why an invoice exists. Removing it would
     * leave paid invoices pointing at nothing, so the UI only ever changes
     * status, and a cancelled subscription simply stops being picked up by the
     * daily run.
     */
    public function updateStatus(Request $request, BillingSubscription $subscription)
    {
        $status = $request->validate([
            'status' => ['required', Rule::in(BillingSubscription::STATUSES)],
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
            // Both optional: a domain can be billed without a project, and a
            // custom arrangement without a catalogue entry.
            //
            // But a project, when given, must belong to THIS client. Checking
            // only that the id exists would let a subscription bill PT A for
            // work recorded under PT B — an inconsistency no later screen could
            // make sense of. The dropdown filters by client too, but that is a
            // convenience; this is the rule.
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where(
                    fn ($query) => $query->where('client_id', $request->input('client_id'))
                ),
            ],
            'service_package_id' => ['nullable', 'exists:service_packages,id'],
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', Rule::in(BillingSubscription::SERVICE_TYPES)],
            'description' => ['nullable', 'string'],
            // Not asked for: the service type decides it. An operator who picks
            // "hosting" is not also choosing a cadence, and the two disagreeing
            // is a state nothing downstream can make sense of.
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'start_date' => ['required', 'date'],
            // Not asked for: the first period opens on start_date, and after
            // that the renewal engine owns the date. Two knobs for the same
            // fact is how a subscription ends up billing the wrong day.
            // Empty means no term — it renews until an operator stops it.
            'ends_on' => ['nullable', 'date', 'after:start_date'],
            'status' => ['required', Rule::in(BillingSubscription::STATUSES)],
            'auto_reminder' => ['boolean'],
        ], [
            'project_id.exists' => 'Project yang dipilih bukan milik client ini.',
            'ends_on.after' => 'Tanggal berakhir langganan harus setelah tanggal mulai.',
        ]) + [
            // Unchecked boxes are simply absent from the request.
            'auto_reminder' => $request->boolean('auto_reminder'),
            'billing_cycle' => BillingSubscription::cycleForServiceType($request->input('service_type')),
        ];
    }

    private function formOptions(): array
    {
        return [
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'client_id']),
            // Optional templates. Selecting one prefills the form; it never
            // becomes a dependency of the subscription.
            'packages' => ServicePackage::orderBy('name')->get(['id', 'name', 'category', 'price', 'unit']),
        ];
    }
}
