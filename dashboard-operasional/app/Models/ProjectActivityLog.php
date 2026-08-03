<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Project|null $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectActivityLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectActivityLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectActivityLog query()
 * @mixin \Eloquent
 */
class ProjectActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'description'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}