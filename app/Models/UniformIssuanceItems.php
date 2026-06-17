<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UniformIssuanceItems extends Model
{
    protected $fillable = [
        'uniform_issuance_recipient_id',
        'uniform_item_id',
        'uniform_item_variant_id',
        'quantity',
        'released_quantity',
        'remaining_quantity',
        'uniform_issuance_type_id'
    ];

    protected $casts = [
        'quantity'                  => 'integer',
        'released_quantity'         => 'integer',
        'remaining_quantity'        => 'integer',
    ];

    public function uniformIssuanceRecipient() : BelongsTo {
        return $this->belongsTo(UniformIssuanceRecipients::class, 'uniform_issuance_recipient_id', 'id');
    }

    public function uniformItem() : BelongsTo {
        return $this->belongsTo(UniformItems::class, 'uniform_item_id', 'id');
    }

    public function uniformItemVariant() : BelongsTo {
        return $this->belongsTo(UniformItemVariants::class, 'uniform_item_variant_id', 'id');
    }

    public function uniformIssuanceType()
    {
        return $this->belongsTo(\App\Models\UniformIssuanceType::class, 'uniform_issuance_type_id');
    }
}
