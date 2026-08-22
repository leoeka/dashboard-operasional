<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
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
}
