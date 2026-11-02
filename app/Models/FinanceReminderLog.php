<?php

namespace App\Models;

use App\Services\Finance\FinanceClock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catatan jejak pengiriman pengingat per invoice per threshold (H-30, H-7, H-3, H-1).
 * Disimpan di tabel billing_reminder_logs.
 */
class FinanceReminderLog extends Model
{
    use HasFactory;

    protected $table = 'billing_reminder_logs';

    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const FAILED = 'failed';

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
        'whatsapp_sent_at',
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
        'whatsapp_sent_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function subscription()
    {
        return $this->belongsTo(FinanceSubscription::class, 'billing_subscription_id');
    }

    public function wasSent(): bool
    {
        return $this->status === self::SENT;
    }

    public function canRetry(): bool
    {
        return $this->status === self::FAILED && $this->attempts < self::MAX_ATTEMPTS;
    }

    public function label(): string
    {
        return 'H-' . $this->days_before;
    }

    public function sentAtForDisplay(): ?string
    {
        if (!$this->sent_at) {
            return null;
        }

        $local = $this->sent_at->copy()->setTimezone(app(FinanceClock::class)->timezone());

        return $local->translatedFormat('d M Y H:i') . ' ' . $local->format('T');
    }
}
