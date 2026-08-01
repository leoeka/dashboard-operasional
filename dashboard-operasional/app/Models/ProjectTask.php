<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectTask extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'title', 'is_done', 'position'];

    protected $casts = ['is_done' => 'boolean'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}