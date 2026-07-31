<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'client_name',
        'status',
        'summary',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
