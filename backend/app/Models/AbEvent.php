<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'has_url' => 'boolean',
        'meta'    => 'array',
        'amount'  => 'decimal:2',
    ];
}
