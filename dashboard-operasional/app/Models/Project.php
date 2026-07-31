<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'client_name',
        'type',
        'status',
        'progress',
        'value',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'date',
        'progress' => 'integer',
        'value' => 'decimal:2',
    ];

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // Warna badge status untuk dipakai di view
    public function statusColor(): string
    {
        return match ($this->status) {
            'request' => 'slate',
            'proposal' => 'purple',
            'mockup' => 'blue',
            'development' => 'amber',
            'qa' => 'pink',
            'active', 'done' => 'emerald',
            default => 'slate',
        };
    }
}
