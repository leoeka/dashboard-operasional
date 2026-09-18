<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'company_name',
        'contact_name',
        'billing_name',
        'email',
        'billing_email',
        'phone',
        'billing_phone',
        'whatsapp',
        'address',
        'logo_path',
        'website',
        'instagram',
        'notes',
        'created_by',
    ];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function billingSubscriptions()
    {
        return $this->hasMany(BillingSubscription::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Where invoices go. Finance is often a different person from the contact
     * we discuss the website with, but most clients never fill this in — so an
     * empty billing address falls back to the main one rather than silently
     * sending nothing.
     */
    public function billingEmail(): ?string
    {
        return filled($this->billing_email) ? $this->billing_email : $this->email;
    }

    public function billingName(): ?string
    {
        return filled($this->billing_name) ? $this->billing_name : $this->contact_name;
    }

    public function billingPhone(): ?string
    {
        return filled($this->billing_phone) ? $this->billing_phone : $this->phone;
    }
}
