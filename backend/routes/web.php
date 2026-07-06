<?php

use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Admin\CustomerController as AdminCustomers;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrders;
use App\Http\Controllers\Admin\ProductController as AdminProducts;
use App\Http\Controllers\Admin\SurfaceController as AdminSurfaces;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController as CustomerAuth;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\UpsellController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/offer', fn () => view('offer'))->name('offer'); // standalone landing (own <head> tracking)
Route::get('/category/{category}', [StorefrontController::class, 'category'])->name('category.show');
Route::get('/product/{product}', [StorefrontController::class, 'product'])->name('product.show');

// Online designer (req 8/9/18)
Route::get('/design/{product}/templates', [DesignController::class, 'templates'])->name('design.templates');
Route::post('/design/{product}/review', [DesignController::class, 'review'])->name('design.review.stash');
Route::get('/design/{product}/review', [DesignController::class, 'showReview'])->name('design.review');
Route::get('/design/{product}', [DesignController::class, 'show'])->name('design.start');
Route::get('/design/template/{template}/data', [DesignController::class, 'templateData'])->name('template.data');

// Cart + free shipping + upsell (req 7/11/15)
Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove/{lineId}', [CartController::class, 'remove'])->name('cart.remove');

// Forced upsell steps before the cart (multi-step upsell + card-holder cross-sell)
Route::get('/upsell', [UpsellController::class, 'show'])->name('upsell.show');
Route::post('/upsell/add/{product}', [UpsellController::class, 'add'])->name('upsell.add');
Route::post('/upsell/finalize', [UpsellController::class, 'finalize'])->name('upsell.finalize');
Route::post('/upsell/next', [UpsellController::class, 'next'])->name('upsell.next');

// Customer accounts — Google + email/password (req: login before checkout)
Route::get('/login', [CustomerAuth::class, 'showLogin'])->name('login');
Route::post('/login', [CustomerAuth::class, 'login'])->name('login.attempt');
Route::get('/register', [CustomerAuth::class, 'showRegister'])->name('register');
Route::post('/register', [CustomerAuth::class, 'register'])->name('register.store');
Route::get('/auth/google', [CustomerAuth::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [CustomerAuth::class, 'handleGoogleCallback']);
Route::post('/logout', [CustomerAuth::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/account', [AccountController::class, 'show'])->middleware('auth')->name('account');

// Checkout + Stripe (req 14) — must be signed in to check out
Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
});
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::post('/stripe/webhook', [CheckoutController::class, 'webhook'])->name('stripe.webhook');

// Marketing feeds (req 20)
Route::get('/feed/google.xml', [FeedController::class, 'google'])->name('feed.google');
Route::get('/feed/rtbhouse.xml', [FeedController::class, 'rtbhouse'])->name('feed.rtbhouse');

// pqSmartGenerator upsell engine (async capture + widget status)
Route::post('/pqsg/upload', [\App\Http\Controllers\PqsgController::class, 'upload'])->name('pqsg.upload');
Route::get('/pqsg/status/{key}', [\App\Http\Controllers\PqsgController::class, 'status'])->name('pqsg.status');

// AI logo maker (Replicate recraft SVG; finishing hands the logo to the upsell engine)
Route::get('/logo-maker', [\App\Http\Controllers\LogoController::class, 'show'])->name('logo.show');
Route::post('/logo-maker/generate', [\App\Http\Controllers\LogoController::class, 'generate'])->middleware('throttle:10,1')->name('logo.generate');
Route::post('/logo-maker/finish', [\App\Http\Controllers\LogoController::class, 'finish'])->middleware('throttle:10,1')->name('logo.finish');

// Support chat (bubble widget: AI-first, humans answer flagged tickets in admin)
Route::get('/support/messages', [\App\Http\Controllers\SupportController::class, 'messages'])->name('support.messages');
Route::post('/support', [\App\Http\Controllers\SupportController::class, 'send'])->middleware('throttle:20,1')->name('support.send');

// Legal / info pages
Route::get('/faq', fn () => Inertia::render('Legal/Faq'))->name('faq');
Route::get('/terms', fn () => Inertia::render('Legal/Terms'))->name('terms');
Route::get('/shipping', fn () => Inertia::render('Legal/Shipping', ['threshold' => (float) config('shop.free_shipping_threshold')]))->name('shipping');
Route::get('/returns', fn () => Inertia::render('Legal/Returns'))->name('returns');

// Admin dashboard + PIM
Route::get('/admin/login', [AdminAuth::class, 'show'])->name('admin.login.show');
Route::post('/admin/login', [AdminAuth::class, 'login'])->name('admin.login');
Route::post('/admin/logout', [AdminAuth::class, 'logout'])->name('admin.logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');

    Route::get('/orders', [AdminOrders::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrders::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrders::class, 'updateStatus'])->name('orders.status');

    Route::get('/customers', [AdminCustomers::class, 'index'])->name('customers.index');

    Route::get('/support', [\App\Http\Controllers\Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('/support/{ticket}', [\App\Http\Controllers\Admin\SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [\App\Http\Controllers\Admin\SupportController::class, 'reply'])->name('support.reply');

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
