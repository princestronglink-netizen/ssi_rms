<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmePurchaseOrderItems extends Model
{
    protected $fillable = [
        'sme_purchase_order_id',
        'sme_item_id',
        'sme_item_variant_id',
        'quantity',
        'released_quantity',
        'remaining_quantity'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'released_quantity' => 'integer',
        'remaining_quantity' => 'integer',
    ];

    protected $attributes = [
        'released_quantity' => 0,
        'remaining_quantity' => 0,
    ];

    public function smeItem() : BelongsTo {
        return $this->belongsTo(SmeItems::class, 'sme_item_id', 'id');
    }

    public function smeItemVariant() : BelongsTo {
        return $this->belongsTo(SmeItemVariants::class, 'sme_item_variant_id', 'id');
    }

    public function smePurchaseOrder() : BelongsTo {
        return $this->belongsTo(SmePurchaseOrder::class, 'sme_purchase_order_id', 'id');
    }
}
