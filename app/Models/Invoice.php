<?php

namespace App\Models;

use App\Services\Billing\BillingClock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    public const PURPOSE_PROJECT = 'project_payment';
    public const PURPOSE_RENEWAL = 'renewal';

    protected $fillable = [
        'project_id',
        'client_id',
        'billing_subscription_id',
        'invoice_number',
        'type',
        'purpose',
        'issue_date',
        'amount',
        'subtotal',
        'discount',
        'tax',
        'currency',
        'due_date',
        'billing_period_start',
        'billing_period_end',
        'status',
        'paid_at',
        'last_reminder_sent_at',
        'notes',
    ];

    /**
     * Mirrors the column defaults. Without these, a just-created invoice reads
     * back a null status and purpose until it is refreshed, which is a trap for
     * anything acting on the object it just made.
     */
    protected $attributes = [
        'type' => 'full',
        'purpose' => self::PURPOSE_PROJECT,
        'status' => 'unpaid',
        'currency' => 'IDR',
        'discount' => 0,
        'tax' => 0,
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'paid_at' => 'date',
        'last_reminder_sent_at' => 'datetime',
        'amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function subscription()
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function reminderLogs()
    {
        return $this->hasMany(BillingReminderLog::class);
    }

    /**
     * A renewal is identified by its purpose, never by project_id being null —
     * a renewal can belong to a project (hosting for a site we built), and a
     * reader should not have to infer an invoice's kind from a missing value.
     */
    public function isRenewal(): bool
    {
        return $this->purpose === self::PURPOSE_RENEWAL;
    }

    /**
     * The client to bill, whether the invoice reaches them through a project or
     * directly through a subscription.
     */
    public function billableClient(): ?Client
    {
        return $this->client ?? $this->project?->client;
    }

    /** What the line items add up to. The stored `amount` stays authoritative. */
    public function itemsTotal(): string
    {
        return number_format((float) $this->items->sum('total'), 2, '.', '');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'dp' => 'DP',
            'pelunasan' => 'Pelunasan',
            'full' => 'Full Payment',
            default => ucfirst($this->type),
        };
    }

    /**
     * Unpaid, and its due date is behind the business date.
     *
     * Asked of BillingClock rather than of today(), because the application
     * deliberately runs on UTC while the business runs on config('billing.
     * timezone'). For the eight hours those two disagree, today() would call an
     * invoice current that the Finance summary — which has always used the
     * clock — already counts as overdue, and the badge on the row would
     * contradict the number at the top of the same page.
     *
     * Compared as plain calendar dates: due_date is a business date, not an
     * instant, so an offset must not be able to shift it across a day boundary.
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'paid') {
            return false;
        }

        $clock = app(BillingClock::class);

        return $clock->businessDate($this->due_date)->lessThan($clock->businessDate($clock->today()));
    }

    public function statusColor(): string
    {
        if ($this->status === 'paid')
            return 'emerald';
        if ($this->isOverdue())
            return 'red';
        return 'amber';
    }

    public function statusLabel(): string
    {
        if ($this->status === 'paid')
            return 'Lunas';
        if ($this->isOverdue())
            return 'Terlambat';
        return 'Belum Dibayar';
    }
}