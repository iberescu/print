<?php

use App\Http\Controllers\StorefrontController;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/category/{category}', [StorefrontController::class, 'category'])->name('category.show');
Route::get('/product/{product}', [StorefrontController::class, 'product'])->name('product.show');

// --- Stubs for the next phases (designer/cart) so the funnel stays navigable ---
Route::get('/design/{product}', function (Product $product, Request $request) {
    return Inertia::render('ComingSoon', [
        'title'   => 'Online Designer',
        'message' => "The fabric.js designer arrives in the next phase — you'll design front & back here, pick a template, or upload your own artwork.",
        'product' => ['name' => $product->name, 'slug' => $product->slug],
        'mode'    => $request->query('mode'),
    ]);
})->name('design.start');

Route::get('/cart', fn () => Inertia::render('ComingSoon', [
    'title'   => 'Your Cart',
    'message' => 'Cart, free-shipping nudges and design-on-product upsells are coming in a later phase.',
]))->name('cart');
