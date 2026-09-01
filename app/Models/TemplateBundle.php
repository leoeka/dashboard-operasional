<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateBundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'preview_url',
        'description',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function bundles()
    {
        return $this->hasMany(ProjectBundle::class);
    }
}
