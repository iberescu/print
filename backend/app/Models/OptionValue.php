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
        'attributes'  => 'array',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    public function surface(): BelongsTo
    {
        return $this->belongsTo(Surface::class);
    }
}
