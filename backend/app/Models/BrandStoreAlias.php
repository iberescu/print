<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandStoreAlias extends Model
{
    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo(BrandStore::class, 'brand_store_id');
    }
}
