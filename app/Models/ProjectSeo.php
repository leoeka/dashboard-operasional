<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectSeo extends Model
{
    use HasFactory;

    protected $table = 'project_seos';

    protected $fillable = [
        'project_id',
        'target_url',
        'location',
        'keywords',
        'competitors',
        'cms_platform',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}

