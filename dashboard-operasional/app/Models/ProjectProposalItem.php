<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectProposalItem extends Model
{
    public function proposalItems()
    {
        return $this->hasMany(ProjectProposalItem::class);
    }
}
