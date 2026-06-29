<?php

use App\Http\Controllers\DesignController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/category/{category}', [StorefrontController::class, 'category'])->name('category.show');
Route::get('/product/{product}', [StorefrontController::class, 'product'])->name('product.show');

// Online designer (req 8/9/18)
Route::get('/design/{product}', [DesignController::class, 'show'])->name('design.start');
Route::post('/design/{product}', [DesignController::class, 'store'])->name('design.store');

// Cart stub (Phase 5) — shows the saved design for now
Route::get('/cart', function (Request $request) {
    return Inertia::render('ComingSoon', [
        'title'         => 'Your Cart',
        'message'       => 'Full cart, free-shipping nudges and design-on-product upsells arrive in Phase 5. Your saved design is below.',
        'pendingDesign' => $request->session()->get('pending_design'),
    ]);
})->name('cart');
