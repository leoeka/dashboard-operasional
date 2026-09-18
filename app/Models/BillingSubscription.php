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
        'status',
        'auto_invoice',
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
        'auto_invoice' => true,
        'auto_reminder' => true,
    ];

    protected $casts = [
        'start_date' => 'date',
        'next_renewal_date' => 'date',
        'amount' => 'decimal:2',
        'auto_invoice' => 'boolean',
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
