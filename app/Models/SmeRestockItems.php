<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmeRestockItems extends Model
{
    protected $fillable = [
        'sme_restock_id',
        'sme_item_id',
        'sme_item_variant_id',
        'quantity',
        'delivered_quantity',
        'remaining_quantity',
    ];

    protected $casts = [
        'quantity'           => 'integer',
        'delivered_quantity' => 'integer',
        'remaining_quantity' => 'integer',
    ];

    protected $attributes = [
        'delivered_quantity' => 0,
        'remaining_quantity' => 0,
    ];

    public function smeRestock(): BelongsTo
    {
        return $this->belongsTo(SmeRestocks::class, 'sme_restock_id', 'id');
    }

    public function smeItem(): BelongsTo
    {
        return $this->belongsTo(SmeItems::class, 'sme_item_id');
    }

    public function smeItemVariant(): BelongsTo
    {
        return $this->belongsTo(SmeItemVariants::class, 'sme_item_variant_id');
    }
}