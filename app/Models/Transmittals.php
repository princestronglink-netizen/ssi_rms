<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Transmittals extends Model
{
    protected $fillable = [
        'uniform_issuance_id',
        'transmittal_number',
        'transmitted_by',
        'transmitted_to',
        'items_summary',
        'purpose',
        'instructions',
        'transmitted_at',
        'status',
        'received_from_office',
        'date_received_from_office',
        'received_from_site',
        'date_received_from_site',
        'remarks',
        'returned_by',
        'date_returned',
    ];

    protected $casts = [
        'items_summary'             => 'array',
        'transmitted_at'            => 'date',
        'date_received_from_office' => 'date',
        'date_received_from_site'   => 'date',
        'date_returned'             => 'date',
    ];
 
    public function uniformIssuance(): BelongsTo
    {
        return $this->belongsTo(UniformIssuances::class, 'uniform_issuance_id');
    }

    public function issuances(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\UniformIssuances::class,
            'transmittal_issuances',
            'transmittal_id',
            'uniform_issuance_id'
        );
    }
    
    public function logs(): HasMany
    {
        return $this->hasMany(TransmittalLog::class, 'transmittal_id')->latest();
    }
    
 
    /**
     * Auto-generate transmittal number: TXN-YYYYMMDD-XXXX
     */
    public static function generateNumber(): string
    {
        $prefix = 'TXN-' . now()->format('Ymd') . '-';
        $last   = static::where('transmittal_number', 'like', $prefix . '%')
            ->orderByDesc('transmittal_number')
            ->value('transmittal_number');
 
        $next = $last
            ? (int) substr($last, -4) + 1
            : 1;
 
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }


}
