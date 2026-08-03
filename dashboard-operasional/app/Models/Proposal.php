<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $guarded = [];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function mockupTemplate()
    {
        return $this->belongsTo(MockupTemplate::class);
    }
}
