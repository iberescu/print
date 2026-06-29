<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

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

// Checkout + Stripe (req 14)
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('/stripe/webhook', [CheckoutController::class, 'webhook'])->name('stripe.webhook');
