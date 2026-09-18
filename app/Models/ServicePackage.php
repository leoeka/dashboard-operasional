<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'price', 'unit', 'features'];

    protected $casts = ['price' => 'decimal:2'];

    /**
     * A catalogue entry, not a subscription: this is the price list, while
     * BillingSubscription is one client's copy of an entry with its own renewal
     * date. Nothing here carries a date.
     */
    public function billingSubscriptions()
    {
        return $this->hasMany(BillingSubscription::class);
    }

    public function featureList(): array
    {
        return array_filter(explode("\n", $this->features));
    }
}
