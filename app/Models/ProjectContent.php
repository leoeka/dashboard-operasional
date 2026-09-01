<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'hero_title',
        'hero_subtitle',
        'cta_primary',
        'cta_secondary',
        'about_title',
        'about_content',
        'services_json',
        'faq_json',
        'footer_text',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'services_json' => 'array',
        'faq_json' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
