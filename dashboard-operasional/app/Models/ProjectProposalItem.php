<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProjectProposalItem> $proposalItems
 * @property-read int|null $proposal_items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectProposalItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectProposalItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectProposalItem query()
 * @mixin \Eloquent
 */
class ProjectProposalItem extends Model
{
    public function proposalItems()
    {
        return $this->hasMany(ProjectProposalItem::class);
    }
}
