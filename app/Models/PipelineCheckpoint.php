<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineCheckpoint extends Model
{
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    protected $guarded = [];

    protected $casts = ['payload' => 'array'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
