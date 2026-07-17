<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Services\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()
                    ? $request->user()->only('id', 'name', 'email', 'is_admin')
                    : null,
            ],
            'navCategories' => Cache::remember(
                'nav.categories',
                600,
                // toArray() — caching an Eloquent Collection in Redis deserializes to a
                // broken __PHP_Incomplete_Class on cache hits, blanking the nav.
                fn () => Category::where('is_active', true)->orderBy('sort_order')->get(['name', 'slug'])->toArray()
            ),
            'shop' => [
                'freeShippingThreshold' => (float) config('shop.free_shipping_threshold'),
                'company'               => config('shop.company'),
            ],
            // Private brand store context (subdomain hosts only): drives the lock
            // banner and the customer-brand color overrides in StoreLayout.
            'brandStore' => fn () => app()->bound('brandStore') ? [
                'company'  => app('brandStore')->company,
                'logo'     => app('brandStore')->logoUrl(),
                'hero'     => app('brandStore')->heroUrl(),
                'colors'   => app('brandStore')->colors,
                'mainShop' => config('app.url'),
            ] : null,
            // RTB House offer-id prefix for this context: the store's alias on
            // store hosts, the session capture's store alias on the main shop
            // (so "added on the main page" events hit pre-provisioned feed ids).
            'rtbAlias' => fn () => $this->rtbAlias(),
            'cart' => fn () => [
                'count'    => app(Cart::class)->count(),
                'subtotal' => app(Cart::class)->subtotal(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /** The RTB House alias for this request's brand context (null = no events). */
    private function rtbAlias(): ?string
    {
        if (! config('shop.rtbhouse.tag')) {
            return null;
        }
        if (app()->bound('brandStore')) {
            return app('brandStore')->alias?->alias;
        }
        $key = session('pqsg.key');
        if (! $key) {
            return null;
        }
        $kit = \App\Models\BrandKit::where('key', $key)->first();
        $store = $kit ? \App\Models\BrandStore::with('alias')->where('brand_kit_id', $kit->id)->first() : null;

        return $store?->alias?->alias;
    }
}
