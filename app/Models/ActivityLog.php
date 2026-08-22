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
            'pending' => 'amber',
            'approved' => 'blue',
            'success' => 'emerald',
            default => 'slate',
        };
    }
}
