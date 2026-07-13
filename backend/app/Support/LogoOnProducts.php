<?php

namespace App\Support;

use App\Models\BrandKit;
use App\Models\Product;

/**
 * The customer's "your logo on products" mockups (the internal-engine / Layout.ai gallery)
 * for the CURRENT session — the already-generated CACHED images, mapped to real products
 * where they exist. Surfaced in the cart and on the home page; returns [] when there's no
 * brand kit or no generated products yet. Never regenerates.
 */
class LogoOnProducts
{
    /** @return array<int,array<string,mixed>> */
    public static function forCurrentSession(): array
    {
        $key = session('pqsg.key');
        if (! $key) {
            return [];
        }
        $kit = BrandKit::where('key', $key)->first();
        if (! $kit || empty($kit->products)) {
            return [];
        }

        $slugs = collect($kit->products)->pluck('product_slug')->filter()->all();
        $shop = Product::with('category')->whereIn('slug', $slugs)->where('is_active', true)->get()->keyBy('slug');

        $first = ['brochure' => 0, 'flyer' => 1]; // website-styled print pieces lead the list (when generated)

        return collect($kit->products)
            ->map(function ($p) use ($shop) {
                if (empty($p['img'])) {
                    return null; // need the cached image
                }
                $prod = $shop->get($p['product_slug'] ?? '');

                return [
                    'key'       => $p['key'] ?? '',
                    'label'     => $p['label'] ?? ($prod?->name ?? 'Your logo'),
                    'img'       => $p['img'], // cached, already-generated — shown as-is, no regeneration
                    'slug'      => $prod?->slug,        // null → image-only card (no buy link)
                    'name'      => $prod?->name,
                    'fromPrice' => $prod ? (float) $prod->from_price : null,
                    'category'  => $prod?->category?->name,
                ];
            })
            ->filter()
            ->unique(fn ($p) => $p['slug'] ?: $p['label'])
            ->sortBy(fn ($p) => $first[$p['key']] ?? 9)  // brochure & flyer first, rest keep their order
            ->values()
            ->all();
    }
}
