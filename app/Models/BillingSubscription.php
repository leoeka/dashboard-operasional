<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One recurring service a client pays for, with its own next renewal date.
 *
 * The renewal date lives here rather than on ServicePackage because a package
 * is a catalogue entry shared by every client, while this is one client's copy
 * of it. A subscription does not need a package at all — a custom arrangement
 * is created by filling in name and amount directly.
 */
class BillingSubscription extends Model
{
    use HasFactory;

    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_YEARLY = 'yearly';

    public const SERVICE_TYPES = ['hosting', 'domain', 'website', 'maintenance', 'seo', 'other'];
    public const STATUSES = ['active', 'paused', 'cancelled', 'expired'];

    /**
     * What an operator pays for decides how often they are chased, and nobody
     * should have to remember that mapping per client: hosting and domain are
     * bought a year at a time, everything else is a monthly retainer.
     *
     * This is the only place the rule lives. The form no longer asks for a
     * cycle; it derives one from the service type chosen here.
     */
    public const YEARLY_SERVICE_TYPES = ['hosting', 'domain'];

    public static function cycleForServiceType(string $serviceType): string
    {
        return in_array($serviceType, self::YEARLY_SERVICE_TYPES, true)
            ? self::CYCLE_YEARLY
            : self::CYCLE_MONTHLY;
    }

    protected $fillable = [
        'client_id',
        'project_id',
        'service_package_id',
        'name',
        'service_type',
        'description',
        'billing_cycle',
        'amount',
        'currency',
        'start_date',
        'next_renewal_date',
        'ends_on',
        'status',
        'auto_reminder',
    ];

    /**
     * Mirrors the column defaults, so a newly built model already knows what it
     * is before it has been round-tripped through the database.
     */
    protected $attributes = [
        'service_type' => 'other',
        'billing_cycle' => self::CYCLE_YEARLY,
        'currency' => 'IDR',
        'status' => 'active',
        'auto_reminder' => true,
    ];

    protected $casts = [
        'start_date' => 'date',
        'next_renewal_date' => 'date',
        'ends_on' => 'date',
        'amount' => 'decimal:2',
        'auto_reminder' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function servicePackage()
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function reminderLogs()
    {
        return $this->hasMany(BillingReminderLog::class);
    }

    public function isYearly(): bool
    {
        return $this->billing_cycle === self::CYCLE_YEARLY;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Whether a renewal date still falls inside what the client subscribed to.
     *
     * A renewal date opens the period that is about to be billed, so a date
     * landing on or after the end of the term would bill for service nobody
     * bought. A null ends_on means there is no term: it runs until an operator
     * stops it, which is every subscription written before terms existed.
     */
    public function coversRenewalOn(\Carbon\CarbonInterface $date): bool
    {
        return !$this->ends_on || $date->lt($this->ends_on->copy()->startOfDay());
    }

    /** Negative once the renewal date has passed. */
    public function daysUntilRenewal(): int
    {
        return (int) today()->diffInDays($this->next_renewal_date, false);
    }

    /**
     * Where the next billing period would end if this renewed today. Used to
     * fill an invoice's billing_period_* columns; it does not move anything.
     */
    public function periodEndFrom(\Carbon\CarbonInterface $start): \Carbon\CarbonInterface
    {
        return $this->isYearly()
            ? $start->copy()->addYear()->subDay()
            : $start->copy()->addMonth()->subDay();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
