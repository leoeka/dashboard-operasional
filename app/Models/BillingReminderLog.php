<?php

namespace App\Models;

use App\Services\Billing\BillingClock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One reminder threshold for one invoice — H-30, H-7, H-3, H-1.
 *
 * This is the idempotency record for renewal reminders, not merely a history
 * table. The unique key on (invoice_id, days_before) is what stops a second
 * email: claiming a threshold means inserting this row, and a duplicate insert
 * fails at the database rather than relying on the sender checking a timestamp.
 *
 * `status` therefore separates "claimed but not yet delivered" (pending) from
 * delivered (sent) and from a delivery that failed (failed, with the reason).
 * A failed row keeps its claim so a retry updates it instead of creating a
 * second one, and `attempts` caps how often that retry may happen.
 */
class BillingReminderLog extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const FAILED = 'failed';

    /** A permanently bad address must not be retried forever. */
    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'invoice_id',
        'billing_subscription_id',
        'days_before',
        'scheduled_for',
        'status',
        'sent_at',
        'recipient',
        'error_message',
        'attempts',
    ];

    protected $attributes = [
        'status' => self::PENDING,
        'attempts' => 0,
    ];

    protected $casts = [
        'scheduled_for' => 'date',
        'sent_at' => 'datetime',
        'days_before' => 'integer',
        'attempts' => 'integer',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription()
    {
        return $this->belongsTo(BillingSubscription::class, 'billing_subscription_id');
    }

    public function wasSent(): bool
    {
        return $this->status === self::SENT;
    }

    public function canRetry(): bool
    {
        return $this->status === self::FAILED && $this->attempts < self::MAX_ATTEMPTS;
    }

    /** "H-30", the way the schedule is written and read. */
    public function label(): string
    {
        return 'H-' . $this->days_before;
    }

    /**
     * When it went out, as an operator in the billing timezone reads a clock.
     *
     * Display only, and deliberately so: sent_at is stored in UTC and stays
     * that way, because it records an instant and instants must stay comparable
     * across every row in the table. This converts a copy for reading and
     * writes nothing back. The zone is spelled out in the returned string so a
     * time on screen can never be mistaken for the stored UTC one.
     */
    public function sentAtForDisplay(): ?string
    {
        if (!$this->sent_at) {
            return null;
        }

        $local = $this->sent_at->copy()->setTimezone(app(BillingClock::class)->timezone());

        return $local->translatedFormat('d M Y H:i') . ' ' . $local->format('T');
    }
}
