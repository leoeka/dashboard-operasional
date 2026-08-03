<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property bool $is_done
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Project $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask whereIsDone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectTask whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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