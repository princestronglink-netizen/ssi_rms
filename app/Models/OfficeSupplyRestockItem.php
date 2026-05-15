<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeSupplyRestockItem extends Model
{
    protected $fillable = [
        'office_supply_restock_id',
        'office_supply_item_id',
        'office_supply_item_variant_id',
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

    public function officeSupplyRestock(): BelongsTo
    {
        return $this->belongsTo(OfficeSupplyRestock::class, 'office_supply_restock_id', 'id');
    }

    public function officeSupplyItem(): BelongsTo
    {
        return $this->belongsTo(OfficeSupplyItem::class, 'office_supply_item_id');
    }

    public function officeSupplyItemVariant(): BelongsTo
    {
        return $this->belongsTo(OfficeSupplyItemVariant::class, 'office_supply_item_variant_id');
    }
}