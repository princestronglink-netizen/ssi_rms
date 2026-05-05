<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\belongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Clients extends Model
{
    protected $fillable = [
        'client_name',
        'contact_person',
        'email',
        'contact_number',
        'address',
        'contract_start_date',
        'contract_renewal_date',
        'contract_end_date',
        'status',
    ];

    protected $casts = [
        'contract_start_date'       => 'date',
        'contract_renewal_date'     => 'date',
        'contract_end_date'         => 'date',
    ];

    public function site() : HasMany {
        return $this->hasMany(Sites::class, 'client_id', 'id');
    }

    public function billing() : HasMany {
        return $this->hasMany(Billing::class, 'client_id', 'id');
    }

    public function getSitesAttribute() {
        return $this->site()->pluck('id');
    }

    public function getSiteNamesAttibute() {
        return $this->sites()->pluck('site_name')->toArray;
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'client_user',  
            'client_id',    
            'user_id'       
        );
    }
}
