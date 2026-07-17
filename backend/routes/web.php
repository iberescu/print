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
Route::post('/design/asset', [DesignController::class, 'asset'])->middleware('throttle:40,1,design-asset')->name('design.asset');
Route::post('/design/{product}/review', [DesignController::class, 'review'])->name('design.review.stash');
Route::post('/design/{product}/autosave', [DesignController::class, 'autosave'])->middleware('throttle:30,1,design-autosave')->name('design.autosave');
Route::get('/design/{product}/review', [DesignController::class, 'showReview'])->name('design.review');
Route::get('/design/{product}', [DesignController::class, 'show'])->name('design.start');
Route::get('/design/template/{template}/data', [DesignController::class, 'templateData'])->name('template.data');

// Cart + free shipping + upsell (req 7/11/15)
Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove/{lineId}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/qty/{lineId}', [CartController::class, 'updateQty'])->name('cart.qty');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:12,1,coupon')->name('cart.coupon');
Route::post('/cart/coupon/remove', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

// Newsletter / lead capture (footer + free tools) → welcome email with WELCOME20
Route::post('/subscribe', [\App\Http\Controllers\NewsletterController::class, 'store'])
    ->middleware('throttle:8,1')->name('subscribe');

// Embeddable "see your logo on products" widget (distribution: partner sites → our leads).
// Backend call returns an id; frontend renders by id. See WidgetController.
Route::post('/api/widget', [\App\Http\Controllers\WidgetController::class, 'create'])->middleware('throttle:30,1,widget-create');
Route::get('/api/widget/{id}', [\App\Http\Controllers\WidgetController::class, 'products'])->middleware('throttle:240,1,widget-feed');
Route::get('/widget.js', [\App\Http\Controllers\WidgetController::class, 'loader'])->name('widget.js');
Route::get('/widget/{id}', [\App\Http\Controllers\WidgetController::class, 'frame'])->name('widget.frame');
Route::get('/w/{id}', [\App\Http\Controllers\WidgetController::class, 'land'])->name('widget.land');

// Forced upsell steps before the cart (multi-step upsell + card-holder cross-sell)
Route::get('/upsell', [UpsellController::class, 'show'])->name('upsell.show');
Route::post('/upsell/add/{product}', [UpsellController::class, 'add'])->name('upsell.add');
Route::post('/upsell/finalize', [UpsellController::class, 'finalize'])->name('upsell.finalize');
Route::post('/upsell/next', [UpsellController::class, 'next'])->name('upsell.next');

// Search + sitemap
Route::get('/search', [StorefrontController::class, 'search'])->name('search');
Route::get('/sitemap.xml', [StorefrontController::class, 'sitemap'])->name('sitemap');

// Affiliate partner portal (key-based sign-in, no user accounts)
Route::get('/partner', [\App\Http\Controllers\PartnerController::class, 'show'])->name('partner');
Route::post('/partner', [\App\Http\Controllers\PartnerController::class, 'login'])->middleware('throttle:10,1,partner-login')->name('partner.login');
Route::get('/partner/dashboard', [\App\Http\Controllers\PartnerController::class, 'dashboard'])->name('partner.dashboard');
Route::post('/partner/logout', [\App\Http\Controllers\PartnerController::class, 'logout'])->name('partner.logout');

// Customer accounts — Google + email/password (req: login before checkout)
Route::get('/login', [CustomerAuth::class, 'showLogin'])->name('login');
Route::post('/login', [CustomerAuth::class, 'login'])->middleware('throttle:8,1,auth-login')->name('login.attempt');
Route::get('/register', [CustomerAuth::class, 'showRegister'])->name('register');
Route::post('/register', [CustomerAuth::class, 'register'])->middleware('throttle:8,1,auth-register')->name('register.store');
Route::get('/forgot-password', [CustomerAuth::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [CustomerAuth::class, 'sendResetLink'])->middleware('throttle:5,1,auth-forgot')->name('password.email');
Route::get('/reset-password/{token}', [CustomerAuth::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [CustomerAuth::class, 'resetPassword'])->middleware('throttle:5,1,auth-reset')->name('password.update');
Route::get('/auth/google', [CustomerAuth::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [CustomerAuth::class, 'handleGoogleCallback']);
Route::post('/logout', [CustomerAuth::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/account', [AccountController::class, 'show'])->middleware('auth')->name('account');
Route::post('/account/orders/{order}/reorder', [AccountController::class, 'reorder'])->middleware('auth')->name('account.reorder');
Route::get('/account/orders/{order}/invoice', [AccountController::class, 'invoice'])->middleware('auth')->name('account.invoice');

// Live URL reachability check for designer URL fields (green/red tick).
Route::get('/api/validate-url', \App\Http\Controllers\UrlCheckController::class)->middleware('throttle:30,1')->name('url.check');

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
Route::get('/pqsg/feed/{key}', [\App\Http\Controllers\PqsgController::class, 'feed'])->name('pqsg.feed');
Route::get('/pqsg/brand-profile/{key}', [\App\Http\Controllers\PqsgController::class, 'brandProfile'])->name('pqsg.brand-profile');

// AI logo maker (Replicate recraft SVG; finishing hands the logo to the upsell engine)
Route::get('/logo-maker', [\App\Http\Controllers\LogoController::class, 'show'])->name('logo.show');
// Inline throttles WITHOUT a prefix all share one per-IP bucket (the key is
// sha1(domain|ip) — the route is not part of it), so the status polling would
// starve finish/generate. The third parameter gives each route its own bucket.
Route::post('/logo-maker/generate', [\App\Http\Controllers\LogoController::class, 'generate'])->middleware('throttle:20,1,logo-gen')->name('logo.generate');
Route::post('/logo-maker/finish', [\App\Http\Controllers\LogoController::class, 'finish'])->middleware('throttle:10,1,logo-finish')->name('logo.finish');
Route::get('/logo-maker/download', [\App\Http\Controllers\LogoController::class, 'download'])->middleware('throttle:30,1,logo-dl')->name('logo.download');
Route::get('/logo-maker/png', [\App\Http\Controllers\LogoController::class, 'png'])->middleware('throttle:30,1,logo-png')->name('logo.png');
Route::get('/logo-maker/status/{id}', [\App\Http\Controllers\LogoController::class, 'status'])->middleware('throttle:120,1,logo-status')->name('logo.status');

// Free QR code generator (low-CPC entry tool; footer link only)
Route::get('/qr-code-generator', [\App\Http\Controllers\QrController::class, 'show'])->name('qr.show');
Route::get('/qr/image', [\App\Http\Controllers\QrController::class, 'image'])->middleware('throttle:120,1,qr-image')->name('qr.image');
Route::post('/qr/logo', [\App\Http\Controllers\QrController::class, 'logo'])->middleware('throttle:20,1,qr-logo')->name('qr.logo');
Route::post('/qr/capture', [\App\Http\Controllers\QrController::class, 'capture'])->middleware('throttle:10,1,qr-capture')->name('qr.capture');

// B2B affiliate program: landing + apply + the CORS-open widget API
Route::get('/affiliates', [\App\Http\Controllers\AffiliateController::class, 'show'])->name('affiliates');
Route::post('/affiliates/apply', [\App\Http\Controllers\AffiliateController::class, 'apply'])->middleware('throttle:6,1,affiliate-apply')->name('affiliates.apply');
Route::get('/affiliate/widget/capture', [\App\Http\Controllers\AffiliateController::class, 'widgetCapture'])->middleware('throttle:30,1,affiliate-capture')->name('affiliate.capture');
Route::get('/affiliate/widget/status', [\App\Http\Controllers\AffiliateController::class, 'widgetStatus'])->middleware('throttle:240,1,affiliate-status')->name('affiliate.status');
Route::match(['get', 'post'], '/affiliate/widget/track', [\App\Http\Controllers\AffiliateController::class, 'widgetTrack'])->middleware('throttle:120,1,affiliate-track')->name('affiliate.track');
Route::get('/affiliate/go', [\App\Http\Controllers\AffiliateController::class, 'widgetGo'])->middleware('throttle:60,1,affiliate-go')->name('affiliate.go');

// Support chat (bubble widget: AI-first, humans answer flagged tickets in admin)
// Inbound support email (contact@ → provider webhook → the support inbox); token-guarded.
Route::post('/hooks/inbound-email', \App\Http\Controllers\InboundEmailController::class)->middleware('throttle:120,1,inbound-email')->name('hooks.inbound-email');
Route::get('/support/messages', [\App\Http\Controllers\SupportController::class, 'messages'])->name('support.messages');
Route::post('/support', [\App\Http\Controllers\SupportController::class, 'send'])->middleware('throttle:20,1,support')->name('support.send');

// Legal / info pages
Route::get('/faq', fn () => Inertia::render('Legal/Faq'))->name('faq');
Route::get('/terms', fn () => Inertia::render('Legal/Terms'))->name('terms');
Route::get('/shipping', fn () => Inertia::render('Legal/Shipping', ['threshold' => (float) config('shop.free_shipping_threshold')]))->name('shipping');
Route::get('/returns', fn () => Inertia::render('Legal/Returns'))->name('returns');

// Admin dashboard + PIM
Route::get('/admin/login', [AdminAuth::class, 'show'])->name('admin.login.show');
Route::post('/admin/login', [AdminAuth::class, 'login'])->middleware('throttle:6,1,auth-admin')->name('admin.login');
Route::post('/admin/logout', [AdminAuth::class, 'logout'])->name('admin.logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');

    Route::get('/orders', [AdminOrders::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrders::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrders::class, 'updateStatus'])->name('orders.status');

    Route::get('/customers', [AdminCustomers::class, 'index'])->name('customers.index');

    Route::get('/experiments', [\App\Http\Controllers\Admin\ExperimentsController::class, 'index'])->name('experiments.index');

    Route::get('/support', [\App\Http\Controllers\Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('/support/{ticket}', [\App\Http\Controllers\Admin\SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [\App\Http\Controllers\Admin\SupportController::class, 'reply'])->name('support.reply');
    Route::post('/support/{ticket}/retry-ai', [\App\Http\Controllers\Admin\SupportController::class, 'retryAi'])->name('support.retry');

    Route::get('/products', [AdminProducts::class, 'index'])->name('products.index');
    Route::post('/products', [AdminProducts::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProducts::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProducts::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [AdminProducts::class, 'destroy'])->name('products.destroy');

    Route::get('/affiliates', [\App\Http\Controllers\Admin\AffiliateController::class, 'index'])->name('affiliates.index');
    Route::post('/affiliates', [\App\Http\Controllers\Admin\AffiliateController::class, 'store'])->name('affiliates.store');
    Route::patch('/affiliates/{affiliate}', [\App\Http\Controllers\Admin\AffiliateController::class, 'update'])->name('affiliates.update');
    Route::post('/affiliates/{affiliate}/payout', [\App\Http\Controllers\Admin\AffiliateController::class, 'payout'])->name('affiliates.payout');

    Route::get('/surfaces', [AdminSurfaces::class, 'index'])->name('surfaces.index');
    Route::post('/surfaces', [AdminSurfaces::class, 'store'])->name('surfaces.store');
    Route::get('/surfaces/{surface}/edit', [AdminSurfaces::class, 'edit'])->name('surfaces.edit');
    Route::put('/surfaces/{surface}', [AdminSurfaces::class, 'update'])->name('surfaces.update');
    Route::delete('/surfaces/{surface}', [AdminSurfaces::class, 'destroy'])->name('surfaces.destroy');
});
