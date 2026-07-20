<?php

namespace App\Support;

use App\Models\BrandStoreAlias;
use App\Models\Product;

/**
 * The RTB House BRAND-STORE product feed (CSV, read ~once per day): every
 * alias in the pool × the personalized product set. Unmapped aliases carry the
 * default product photos; once a brand store claims an alias its rows switch
 * to the customer's logo mockups (and the company name joins the title) on the
 * next feed read — while basketadd/conversion events fire immediately against
 * these pre-provisioned ids. Offer id = "{alias}-{product-slug}".
 */
class RtbStoreFeed
{
    /** TSV, CRLF, columns mirroring the proven viteprint RTB feed:
     *  id  title  price  condition  link  availability  image_link  product_type  category */
    public function csv(): string
    {
        $products = $this->feedProducts();
        $aliases = BrandStoreAlias::with('store.brandKit')->orderBy('id')->get();

        $rows = [['id', 'title', 'price', 'condition', 'link', 'availability', 'image_link', 'product_type', 'category']];

        foreach ($aliases as $alias) {
            $store = $alias->store;
            $mockups = $store?->brandKit
                ? collect((array) $store->brandKit->products)->filter(fn ($p) => ! empty($p['img']))->keyBy('product_slug')
                : collect();

            foreach ($products as $p) {
                $mockup = $mockups->get($p->slug);
                $rows[] = [
                    "{$alias->alias}-{$p->slug}",
                    $store ? "{$store->company} {$p->name}" : $p->name,
                    number_format((float) $p->from_price, 2, '.', '').' USD',
                    'new',
                    $this->url($alias->alias, "/product/{$p->slug}"),
                    'in_stock',
                    $mockup['img'] ?? Img::url($p->image_path),
                    $alias->alias,
                    $alias->alias,
                ];
            }
        }

        return implode("\r\n", array_map(
            fn ($r) => implode("\t", array_map(fn ($v) => preg_replace('/[\t\r\n]+/', ' ', (string) $v), $r)),
            $rows,
        ))."\r\n";
    }

    /** The personalized set: every brand-kit mockup spec that maps to a live product. */
    private function feedProducts()
    {
        $slugs = collect(BrandKitSpec::products())->pluck('slug')->filter()->unique()->values();

        return Product::where('is_active', true)->whereIn('slug', $slugs)
            ->orderBy('sort_order')->get(['id', 'slug', 'name', 'tagline', 'image_path', 'from_price']);
    }

    private function url(string $sub, string $path): string
    {
        $base = config('shop.brand_store_base') ?: parse_url((string) config('app.url'), PHP_URL_HOST);
        $base = preg_replace('/^www\./i', '', (string) $base);
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return "{$scheme}://{$sub}.{$base}".($port ? ":{$port}" : '').$path;
    }
}
