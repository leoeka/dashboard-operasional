<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'invoice_number',
        'type',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'last_reminder_sent_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'date',
        'last_reminder_sent_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
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

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->lt(today());
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