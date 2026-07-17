<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandStoreToken extends Model
{
    protected $guarded = [];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function store()
    {
        return $this->belongsTo(BrandStore::class, 'brand_store_id');
    }
}
