<?php

use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Admin\CustomerController as AdminCustomers;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrders;
use App\Http\Controllers\Admin\ProductController as AdminProducts;
use App\Http\Controllers\Admin\SurfaceController as AdminSurfaces;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\UpsellController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/offer', fn () => view('offer'))->name('offer'); // standalone landing (own <head> tracking)
Route::get('/category/{category}', [StorefrontController::class, 'category'])->name('category.show');
Route::get('/product/{product}', [StorefrontController::class, 'product'])->name('product.show');

// Online designer (req 8/9/18)
Route::get('/design/{product}/templates', [DesignController::class, 'templates'])->name('design.templates');
Route::get('/design/{product}', [DesignController::class, 'show'])->name('design.start');
Route::get('/design/template/{template}/data', [DesignController::class, 'templateData'])->name('template.data');

// Cart + free shipping + upsell (req 7/11/15)
Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove/{lineId}', [CartController::class, 'remove'])->name('cart.remove');

// Forced upsell steps before the cart (multi-step upsell + card-holder cross-sell)
Route::get('/upsell', [UpsellController::class, 'show'])->name('upsell.show');
Route::post('/upsell/add/{product}', [UpsellController::class, 'add'])->name('upsell.add');
Route::post('/upsell/next', [UpsellController::class, 'next'])->name('upsell.next');

// Checkout + Stripe (req 14)
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('/stripe/webhook', [CheckoutController::class, 'webhook'])->name('stripe.webhook');

// Marketing feeds (req 20)
Route::get('/feed/google.xml', [FeedController::class, 'google'])->name('feed.google');
Route::get('/feed/rtbhouse.xml', [FeedController::class, 'rtbhouse'])->name('feed.rtbhouse');

// Admin dashboard + PIM
Route::get('/admin/login', [AdminAuth::class, 'show'])->name('login');
Route::post('/admin/login', [AdminAuth::class, 'login'])->name('admin.login');
Route::post('/admin/logout', [AdminAuth::class, 'logout'])->name('admin.logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');

    Route::get('/orders', [AdminOrders::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrders::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrders::class, 'updateStatus'])->name('orders.status');

    Route::get('/customers', [AdminCustomers::class, 'index'])->name('customers.index');

    Route::get('/products', [AdminProducts::class, 'index'])->name('products.index');
    Route::post('/products', [AdminProducts::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProducts::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProducts::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProducts::class, 'destroy'])->name('products.destroy');

    Route::get('/surfaces', [AdminSurfaces::class, 'index'])->name('surfaces.index');
    Route::post('/surfaces', [AdminSurfaces::class, 'store'])->name('surfaces.store');
    Route::get('/surfaces/{surface}/edit', [AdminSurfaces::class, 'edit'])->name('surfaces.edit');
    Route::put('/surfaces/{surface}', [AdminSurfaces::class, 'update'])->name('surfaces.update');
    Route::delete('/surfaces/{surface}', [AdminSurfaces::class, 'destroy'])->name('surfaces.destroy');
});
