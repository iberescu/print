<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductQuantity extends Model
{
    protected $guarded = [];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'is_default' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function totalPrice(): float
    {
        return round($this->quantity * (float) $this->unit_price, 2);
    }
}
