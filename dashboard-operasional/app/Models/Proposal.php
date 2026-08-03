<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $project_id
 * @property string $client_name
 * @property string $status
 * @property string|null $summary
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Project|null $project
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal whereClientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal whereSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Proposal whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
