<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectBacklink extends Model
{
    use HasFactory;

    protected $table = 'project_backlinks';

    protected $fillable = [
        'project_id',
        'target_url',
        'quantity',
        'anchor_text',
        'niche',
        'anchor_type',
        'priority',
        'data',
    ];

    protected $casts = [
        'anchor_type' => 'array',
        'data' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}

