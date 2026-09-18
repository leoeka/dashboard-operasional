<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Money actually received against an invoice.
 *
 * Kept separate from invoices.paid_at, which only records THAT an invoice was
 * settled. This records how much, when, by which method and against which
 * reference — the part a finance person needs when reconciling a bank
 * statement.
 */
class Payment extends Model
{
    use HasFactory;

    public const METHODS = ['bank_transfer', 'cash', 'qris', 'other'];

    protected $fillable = [
        'invoice_id',
        'paid_on',
        'amount',
        'currency',
        'method',
        'reference',
        'notes',
        'recorded_by',
    ];

    protected $attributes = [
        'currency' => 'IDR',
        'method' => 'bank_transfer',
    ];

    protected $casts = [
        'paid_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'bank_transfer' => 'Bank Transfer',
            'cash' => 'Cash',
            'qris' => 'QRIS',
            default => 'Other',
        };
    }
}
