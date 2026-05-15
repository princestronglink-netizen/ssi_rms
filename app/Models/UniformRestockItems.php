<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformRestockItems extends Model
{
    protected $fillable = [
        'uniform_restock_id',
        'uniform_item_id',
        'uniform_item_variant_id',
        'quantity',
        'delivered_quantity',
        'remaining_quantity',
    ];

    protected $casts = [
        'quantity'                  => 'integer',
        'delivered_quantity'        => 'integer',
        'remaining_quantity'        => 'integer',
    ];

    protected $attributes = [
        'delivered_quantity' => 0,
        'remaining_quantity' => 0,
    ];

    public function uniformRestock() : BelongsTo {
        return $this->belongsTo(UniformRestocks::class, 'uniform_restock_id', 'id');
    }

    public function uniformItem()
    {
        return $this->belongsTo(UniformItems::class, 'uniform_item_id');
    }
 
    public function uniformItemVariant()
    {
        return $this->belongsTo(UniformItemVariants::class, 'uniform_item_variant_id');
    }
}
