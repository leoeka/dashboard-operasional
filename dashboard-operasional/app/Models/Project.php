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
            'request'     => 'bg-slate-100 text-slate-600',
            'proposal'    => 'bg-purple-100 text-purple-600',
            'mockup'      => 'bg-blue-100 text-blue-600',
            'development' => 'bg-amber-100 text-amber-600',
            'qa'          => 'bg-pink-100 text-pink-600',
            'active'      => 'bg-emerald-100 text-emerald-600',
            'done'        => 'bg-emerald-100 text-emerald-600',
            default       => 'bg-slate-100 text-slate-600',
        };
    }
}
