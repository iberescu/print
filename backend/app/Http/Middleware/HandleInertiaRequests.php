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
            'navCategories' => Cache::remember(
                'nav.categories',
                600,
                fn () => Category::where('is_active', true)->orderBy('sort_order')->get(['name', 'slug'])
            ),
            'shop' => [
                'freeShippingThreshold' => (float) config('shop.free_shipping_threshold'),
            ],
            'cart' => fn () => [
                'count'    => app(Cart::class)->count(),
                'subtotal' => app(Cart::class)->subtotal(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
