<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gallery'          => 'array',
        'seo'              => 'array',
        'from_price'       => 'decimal:2',
        'supports_design'  => 'boolean',
        'supports_upload'  => 'boolean',
        'is_active'        => 'boolean',
        'featured'         => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function surface(): BelongsTo
    {
        return $this->belongsTo(Surface::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    public function quantities(): HasMany
    {
        return $this->hasMany(ProductQuantity::class)->orderBy('sort_order');
    }
}
