<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Surface extends Model
{
    protected $guarded = [];

    protected $casts = [
        'width'          => 'decimal:2',
        'height'         => 'decimal:2',
        'bleed'          => 'decimal:2',
        'safety'         => 'decimal:2',
        'no_print_areas' => 'array',
        'fold_lines'     => 'array',
        'is_active'      => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
