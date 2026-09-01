<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectBrand extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'company_name',
        'logo_path',
        'primary_color',
        'secondary_color',
        'font_primary',
        'font_secondary',
        'phone',
        'email',
        'address',
        'whatsapp',
        'slogan',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
