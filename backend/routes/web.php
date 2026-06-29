<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/category/{category}', [StorefrontController::class, 'category'])->name('category.show');
Route::get('/product/{product}', [StorefrontController::class, 'product'])->name('product.show');

// Online designer (req 8/9/18)
Route::get('/design/{product}', [DesignController::class, 'show'])->name('design.start');
Route::get('/design/template/{template}/data', [DesignController::class, 'templateData'])->name('template.data');

// Cart + free shipping + upsell (req 7/11/15)
Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove/{lineId}', [CartController::class, 'remove'])->name('cart.remove');

// Checkout (Stripe) — Phase 6 stub for now
Route::get('/checkout', fn () => Inertia::render('ComingSoon', [
    'title'   => 'Checkout',
    'message' => 'Stripe checkout arrives in Phase 6. Your cart, shipping and totals are ready to wire up.',
]))->name('checkout');
