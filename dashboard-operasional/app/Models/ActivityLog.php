<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'client_name',
        'action',
        'status',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'  => 'bg-amber-100 text-amber-600',
            'approved' => 'bg-blue-100 text-blue-600',
            'success'  => 'bg-emerald-100 text-emerald-600',
            default    => 'bg-slate-100 text-slate-600',
        };
    }
}
