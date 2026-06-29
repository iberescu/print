<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', [
        'freeShippingThreshold' => (float) config('shop.free_shipping_threshold', 50),
    ]);
});
