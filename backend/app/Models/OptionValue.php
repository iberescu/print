<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionValue extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'is_default'  => 'boolean',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }
}
