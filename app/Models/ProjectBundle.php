<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectBundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'template_bundle_id',
        'bundle_path',
        'zip_path',
        'status',
        'built_at',
        'exported_at',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function templateBundle()
    {
        return $this->belongsTo(TemplateBundle::class);
    }
}
