<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePackage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePackage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServicePackage query()
 * @mixin \Eloquent
 */
class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'price', 'unit', 'features'];

    public function featureList(): array
    {
        return array_filter(explode("\n", $this->features));
    }
}
