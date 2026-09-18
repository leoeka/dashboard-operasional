<?php

namespace App\Http\Controllers;

use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\BillingReminderLog;
use App\Models\Payment;
use App\Models\Project;
use App\Services\Billing\BillingClock;
use App\Services\Billing\BillingCycle;
use Illuminate\Http\Request;

/**
 * The Billing & Finance workspace: everything /finance shows.
 *
 * Read-only on purpose. Creating an invoice, marking one paid and sending a
 * reminder all still go through InvoiceController, and subscriptions through
 * BillingSubscriptionController — this only assembles what the page displays,
 * so the actions keep the behaviour they were tested with.
 *
 * Every date question is asked of BillingClock/BillingCycle rather than
 * recomputed here. "Overdue" and "7 days from now" have to mean the same thing
 * on this page as they do in the scheduler that sends the reminders, and the
 * only way to guarantee that is to use the same clock.
 */
class FinanceController extends Controller
{
    public const TABS = ['overview', 'subscriptions', 'invoices', 'payments', 'reminders'];

    public function __construct(
        private BillingClock $clock,
        private BillingCycle $cycle,
    ) {
    }

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';

        $data = [
            'tab' => $tab,
            'summary' => $this->summary(),
        ];

        $data += match ($tab) {
            'subscriptions' => $this->subscriptionsTab($request),
            'invoices' => $this->invoicesTab($request),
            'payments' => $this->paymentsTab($request),
            'reminders' => $this->remindersTab($request),
            default => $this->overviewTab(),
        };

        // The legacy manual-invoice form lives on every tab's page, so its
        // project list is always needed.
        $data['projects'] = Project::orderBy('name')->get(['id', 'name', 'client_name']);

        return view('pages.finance', $data);
    }

    /**
     * The numbers across the top.
     *
     * Aggregates only — counts and sums computed by the database. Loading the
     * invoices to add them up in PHP would mean fetching every row the business
     * has ever issued to render six figures.
     */
    private function summary(): array
    {
        $today = $this->clock->today()->toDateString();
        $in7 = $this->clock->today()->addDays(7)->toDateString();
        $in30 = $this->clock->today()->addDays(30)->toDateString();
        $monthStart = $this->clock->today()->startOfMonth()->toDateString();
        $monthEnd = $this->clock->today()->endOfMonth()->toDateString();

        $unpaid = Invoice::where('status', 'unpaid');

        // Overdue stays derived — unpaid AND past its due date — exactly as the
        // model's isOverdue() defines it. Nothing stores an "overdue" status, so
        // nothing can drift out of step with the date.
        $overdue = Invoice::where('status', 'unpaid')->whereDate('due_date', '<', $today);

        $dueSoon = Invoice::where('status', 'unpaid')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $in7);

        return [
            'outstanding_total' => (float) (clone $unpaid)->sum('amount'),
            'outstanding_count' => (clone $unpaid)->count(),
            'overdue_total' => (float) (clone $overdue)->sum('amount'),
            'overdue_count' => (clone $overdue)->count(),
            'due_soon_total' => (float) (clone $dueSoon)->sum('amount'),
            'due_soon_count' => (clone $dueSoon)->count(),
            'paid_this_month' => (float) Payment::whereBetween('paid_on', [$monthStart, $monthEnd])->sum('amount'),
            'paid_this_month_count' => Payment::whereBetween('paid_on', [$monthStart, $monthEnd])->count(),
            'active_subscriptions' => BillingSubscription::where('status', 'active')->count(),
            'upcoming_renewals' => BillingSubscription::where('status', 'active')
                ->whereDate('next_renewal_date', '>=', $today)
                ->whereDate('next_renewal_date', '<=', $in30)
                ->count(),
        ];
    }

    private function overviewTab(): array
    {
        $renewals = BillingSubscription::where('status', 'active')
            ->whereNotNull('next_renewal_date')
            ->whereDate('next_renewal_date', '<=', $this->clock->today()->addDays(45)->toDateString())
            ->with(['client:id,company_name', 'project:id,name'])
            ->orderBy('next_renewal_date')
            ->limit(12)
            ->get();

        return [
            'renewals' => $renewals->map(fn (BillingSubscription $s) => [
                'model' => $s,
                'days' => $days = $this->cycle->daysUntil($s->next_renewal_date),
                'countdown' => $this->countdownLabel($days),
                'tone' => $this->countdownTone($days),
            ]),
        ];
    }

    private function subscriptionsTab(Request $request): array
    {
        $query = BillingSubscription::with(['client:id,company_name', 'project:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('service_type'), fn ($q) => $q->where('service_type', $request->query('service_type')))
            ->when($request->filled('cycle'), fn ($q) => $q->where('billing_cycle', $request->query('cycle')))
            ->when($request->filled('client'), fn ($q) => $q->where('client_id', $request->query('client')))
            ->when($request->query('due') === 'upcoming', fn ($q) => $q
                ->whereDate('next_renewal_date', '>=', $this->clock->today()->toDateString())
                ->whereDate('next_renewal_date', '<=', $this->clock->today()->addDays(30)->toDateString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->query('q') . '%';

                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhereHas('client', fn ($c) => $c->where('company_name', 'like', $term));
                });
            });

        $subscriptions = $query->orderBy('next_renewal_date')->paginate(15)->withQueryString();

        // Attached after pagination so the countdown is computed for the page
        // being shown, not for every subscription in the database.
        $subscriptions->getCollection()->each(function (BillingSubscription $s) {
            $days = $this->cycle->daysUntil($s->next_renewal_date);
            $s->setAttribute('days_until', $days);
            $s->setAttribute('countdown', $this->countdownLabel($days));
            $s->setAttribute('countdown_tone', $this->countdownTone($days));
        });

        return [
            'subscriptions' => $subscriptions,
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
        ];
    }

    private function invoicesTab(Request $request): array
    {
        $today = $this->clock->today()->toDateString();
        $status = $request->query('status');

        $invoices = Invoice::query()
            ->when($status === 'unpaid', fn ($q) => $q->where('status', 'unpaid'))
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            // Same derivation as the summary card and the model.
            ->when($status === 'overdue', fn ($q) => $q->where('status', 'unpaid')->whereDate('due_date', '<', $today))
            ->when($request->filled('purpose'), fn ($q) => $q->where('purpose', $request->query('purpose')))
            ->when($request->filled('client'), function ($q) use ($request) {
                $clientId = $request->query('client');

                // A renewal names its client directly; a project invoice reaches
                // one through its project. Both have to match.
                $q->where(fn ($inner) => $inner->where('client_id', $clientId)
                    ->orWhereHas('project', fn ($p) => $p->where('client_id', $clientId)));
            })
            ->with(['project:id,name,client_id', 'project.client:id,company_name', 'client:id,company_name', 'subscription:id,name,service_type'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return [
            'invoices' => $invoices,
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
        ];
    }

    /**
     * Money received, newest first.
     *
     * Eager-loads both routes to a client: a renewal payment reaches one
     * directly, a project payment through its project.
     */
    private function paymentsTab(Request $request): array
    {
        $payments = Payment::query()
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->query('method')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_on', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_on', '<=', $request->query('to')))
            ->when($request->filled('client'), function ($q) use ($request) {
                $clientId = $request->query('client');

                $q->whereHas('invoice', fn ($i) => $i->where('client_id', $clientId)
                    ->orWhereHas('project', fn ($p) => $p->where('client_id', $clientId)));
            })
            ->when($request->filled('q'), fn ($q) => $q->whereHas(
                'invoice',
                fn ($i) => $i->where('invoice_number', 'like', '%' . $request->query('q') . '%')
            ))
            ->with([
                'invoice:id,invoice_number,purpose,project_id,client_id,billing_subscription_id',
                'invoice.client:id,company_name',
                'invoice.project:id,name,client_id',
                'invoice.project.client:id,company_name',
                'invoice.subscription:id,name',
                'recorder:id,name',
            ])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'payments' => $payments,
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
        ];
    }

    /**
     * Every reminder the engine has claimed, with what became of it.
     *
     * Read-only by nature — the rows are written by the scheduler and the queue
     * worker, and nothing on this page may change them.
     */
    private function remindersTab(Request $request): array
    {
        $logs = BillingReminderLog::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('threshold'), fn ($q) => $q->where('days_before', $request->query('threshold')))
            ->when($request->filled('client'), function ($q) use ($request) {
                $clientId = $request->query('client');

                $q->whereHas('invoice', fn ($i) => $i->where('client_id', $clientId)
                    ->orWhereHas('project', fn ($p) => $p->where('client_id', $clientId)));
            })
            ->with([
                'invoice:id,invoice_number,purpose,project_id,client_id,billing_subscription_id',
                'invoice.client:id,company_name',
                'invoice.project:id,name,client_id',
                'invoice.project.client:id,company_name',
                'subscription:id,name,service_type',
            ])
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'reminderLogs' => $logs,
            'clients' => Client::orderBy('company_name')->get(['id', 'company_name']),
            // Offered as filter options rather than typed out in the template,
            // so the list follows the policy rather than a copy of it.
            'thresholdOptions' => collect(BillingReminderLog::query()->distinct()->orderByDesc('days_before')->pluck('days_before')),
        ];
    }

    /** Reads the way a person would say it, rather than "-3 days". */
    private function countdownLabel(int $days): string
    {
        return match (true) {
            $days < 0 => abs($days) . ' hari lewat',
            $days === 0 => 'Hari ini',
            $days === 1 => 'Besok',
            default => $days . ' hari lagi',
        };
    }

    /**
     * Urgency shown through colour, following the reminder schedule so the page
     * and the emails agree about what counts as close.
     */
    private function countdownTone(int $days): string
    {
        return match (true) {
            $days < 0 => 'red',
            $days <= 1 => 'red',
            $days <= 3 => 'amber',
            $days <= 7 => 'amber',
            default => 'slate',
        };
    }
}
