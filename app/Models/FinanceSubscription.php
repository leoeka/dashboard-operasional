<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu layanan berulang yang dibayarkan klien (hosting, domain, website, maintenance).
 * Disimpan di tabel billing_subscriptions.
 */
class FinanceSubscription extends Model
{
    use HasFactory;

    protected $table = 'billing_subscriptions';

    public const CYCLE_MONTHLY = 'monthly';
    public const CYCLE_YEARLY = 'yearly';

    public const SERVICE_TYPES = ['hosting', 'domain', 'website', 'maintenance', 'seo', 'other'];
    public const STATUSES = ['active', 'paused', 'cancelled', 'expired'];

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
        return $this->hasMany(Invoice::class, 'billing_subscription_id');
    }

    public function reminderLogs()
    {
        return $this->hasMany(FinanceReminderLog::class, 'billing_subscription_id');
    }

    public function isYearly(): bool
    {
        return $this->billing_cycle === self::CYCLE_YEARLY;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function coversRenewalOn(CarbonInterface $date): bool
    {
        return !$this->ends_on || $date->lt($this->ends_on->copy()->startOfDay());
    }

    public function daysUntilRenewal(): int
    {
        return (int) today()->diffInDays($this->next_renewal_date, false);
    }

    public function periodEndFrom(CarbonInterface $start): CarbonInterface
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

