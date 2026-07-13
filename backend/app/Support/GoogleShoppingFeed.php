<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the Google Shopping product feed (RSS 2.0 + g: namespace).
 *
 * Products listed in VERTICALS are expanded into one <item> per variant — the
 * cartesian product of their variant options (colour × size, …) — sharing an
 * item_group_id and tagged with the attributes Google requires for that
 * vertical (apparel needs gender/age_group/size/colour). Every other product is
 * emitted as a single "from" item. Adding a product to the feed's variant
 * handling is one row in VERTICALS — its own options drive the rest.
 */
class GoogleShoppingFeed
{
    /** Safety cap on generated variants per product (colour × size can be large). */
    private const MAX_VARIANTS = 300;

    /**
     * Per-product Shopping profiles. 'axes' maps the product's own option NAME to
     * the Google variant attribute (color|size|material); that option's values
     * become the variants. 'apparel' adds gender + age_group (+ size_system when a
     * size axis exists). 'gpc' is the google_product_category.
     */
    private const VERTICALS = [
        'gildan-softstyle-unisex-t-shirt'   => ['gpc' => 'Apparel & Accessories > Clothing > Shirts & Tops', 'apparel' => true, 'axes' => ['Color' => 'color', 'Size' => 'size']],
        'jerzees-nublend-hooded-sweatshirt' => ['gpc' => 'Apparel & Accessories > Clothing', 'apparel' => true, 'axes' => ['Color' => 'color', 'Size' => 'size']],
        'embroidered-polo-shirts'           => ['gpc' => 'Apparel & Accessories > Clothing > Shirts & Tops', 'apparel' => true, 'axes' => ['Colour' => 'color', 'Size' => 'size']],
        'embroidered-hats'                  => ['gpc' => 'Apparel & Accessories > Clothing Accessories > Hats', 'apparel' => true, 'axes' => ['Colour' => 'color']],
        'embroidered-beanies'               => ['gpc' => 'Apparel & Accessories > Clothing Accessories > Hats', 'apparel' => true, 'axes' => ['Colour' => 'color']],
        'custom-canvas-tote-bags'           => ['gpc' => 'Apparel & Accessories > Handbags, Wallets & Cases > Handbags', 'apparel' => true, 'axes' => ['Substrate Color' => 'color']],
        '20-oz-tumbler'                     => ['gpc' => 'Home & Garden > Kitchen & Dining > Tableware > Drinkware', 'axes' => ['Color' => 'color']],
        '40-oz-tumblers'                    => ['gpc' => 'Home & Garden > Kitchen & Dining > Tableware > Drinkware', 'axes' => ['Color' => 'color']],
        'custom-mugs'                       => ['gpc' => 'Home & Garden > Kitchen & Dining > Tableware > Drinkware > Mugs', 'axes' => ['Accent Colors' => 'color']],
    ];

    /** google_product_category for single-item (non-variant) products, by category name. */
    private const CATEGORY_GPC = [
        'Business Cards' => 'Office Supplies',
        'Stationery'     => 'Office Supplies',
    ];

    public function xml(): string
    {
        $items = '';
        foreach ($this->products() as $p) {
            foreach ($this->itemsFor($p) as $fields) {
                $items .= '<item>';
                foreach ($fields as $k => $v) {
                    $items .= $this->tag($k, $v);
                }
                $items .= '</item>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>'
            .$this->tag('title', 'RunMyPrint')
            .$this->tag('link', url('/'))
            .$this->tag('description', 'Custom printing for business')
            .$items
            .'</channel></rss>';
    }

    private function products()
    {
        // services (ad credit etc.) are not shippable goods — keep them out of shopping feeds
        return Product::with(['category', 'options.values', 'quantities'])
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('slug', '!=', 'services'))
            ->orderBy('sort_order')->get();
    }

    /** One or more feed items for a product (variants when it has a Shopping profile). */
    private function itemsFor(Product $p): array
    {
        $profile = self::VERTICALS[$p->slug] ?? null;

        return $profile
            ? $this->variants($p, $profile)
            : [$this->single($p)];
    }

    /** A single "from" item — business cards, marketing, signage, etc. */
    private function single(Product $p): array
    {
        $fields = [
            'g:id'          => (string) $p->id,
            'g:title'       => $p->name,
            'g:description' => $this->description($p),
            'link'          => route('product.show', $p->slug),
            'g:image_link'  => $this->img($p),
            'g:price'       => $this->price((float) $p->from_price),
            'g:availability' => 'in_stock',
            'g:condition'   => 'new',
            'g:brand'       => 'RunMyPrint',
            'g:product_type' => $p->category->name ?? '',
        ];
        if ($gpc = self::CATEGORY_GPC[$p->category->name ?? ''] ?? null) {
            $fields['g:google_product_category'] = $gpc;
        }

        // Per-unit price ("$0.15/ct") for pack products — the per-card figure every
        // competitor shows. The price is for the cheapest tier's pack, so the unit
        // measure is that pack's count.
        $tier = $p->quantities->first(fn ($q) => abs((float) $q->totalPrice() - (float) $p->from_price) < 0.01)
            ?? $p->quantities->sortBy('total_price')->first();
        if ($tier && (int) $tier->quantity > 1) {
            $fields['g:unit_pricing_measure'] = (int) $tier->quantity.'ct';
            $fields['g:unit_pricing_base_measure'] = '1ct';
        }

        $fields['g:identifier_exists'] = 'no';

        return $fields;
    }

    /** Expand a product into variant items over its configured axes. */
    private function variants(Product $p, array $profile): array
    {
        // resolve each configured axis to the product's option values
        $axes = [];
        foreach ($profile['axes'] as $optName => $attr) {
            $opt = $p->options->first(fn ($o) => strcasecmp($o->name, $optName) === 0);
            $vals = $opt ? $opt->values->sortBy('sort_order')->values() : collect();
            if ($vals->isNotEmpty()) {
                $axes[] = ['attr' => $attr, 'values' => $vals];
            }
        }

        // cartesian product of axis values (capped)
        $combos = [[]];
        foreach ($axes as $axis) {
            $next = [];
            foreach ($combos as $combo) {
                foreach ($axis['values'] as $v) {
                    $next[] = array_merge($combo, [['attr' => $axis['attr'], 'v' => $v]]);
                }
            }
            $combos = array_slice($next, 0, self::MAX_VARIANTS);
        }

        $apparel = $profile['apparel'] ?? false;
        $hasSize = collect($axes)->contains(fn ($a) => $a['attr'] === 'size');
        $groupId = count($combos) > 1 ? (string) $p->id : null;
        $productImg = $this->img($p);

        $items = [];
        foreach ($combos as $combo) {
            $ids = [$p->id];
            $labels = [];
            $attrs = [];
            $delta = 0.0;
            $img = null;
            foreach ($combo as $c) {
                $ids[] = $c['v']->id;
                $labels[] = $c['v']->label;
                $attrs['g:'.$c['attr']] = $c['v']->label;
                $delta += (float) $c['v']->price_delta;
                if ($c['attr'] === 'color' && $c['v']->image_path) {
                    $img = url(Storage::disk('public')->url($c['v']->image_path));
                }
            }

            $fields = ['g:id' => implode('-', $ids)];
            if ($groupId) {
                $fields['g:item_group_id'] = $groupId;
            }
            $fields['g:title'] = $labels ? $p->name.' - '.implode(', ', $labels) : $p->name;
            $fields['g:description'] = $this->description($p);
            $fields['link'] = route('product.show', $p->slug);
            $fields['g:image_link'] = $img ?: $productImg;
            $fields['g:price'] = $this->price((float) $p->from_price + $delta);
            $fields['g:availability'] = 'in_stock';
            $fields['g:condition'] = 'new';
            $fields['g:brand'] = 'RunMyPrint';
            $fields['g:product_type'] = $p->category->name ?? '';
            if ($profile['gpc'] ?? null) {
                $fields['g:google_product_category'] = $profile['gpc'];
            }
            foreach ($attrs as $k => $v) {
                $fields[$k] = $v; // g:color, g:size, …
            }
            if ($apparel) {
                $fields['g:gender'] = 'unisex';
                $fields['g:age_group'] = 'adult';
                if ($hasSize) {
                    $fields['g:size_system'] = 'US';
                }
            }
            $fields['g:identifier_exists'] = 'no';

            $items[] = $fields;
        }

        return $items;
    }

    private function price(float $v): string
    {
        return number_format($v, 2, '.', '').' USD';
    }

    /** A real product description — the PIM/SEO copy if present, else a clean, honest fallback. */
    private function description(Product $p): string
    {
        $seo = is_array($p->seo) ? ($p->seo['description'] ?? null) : null;
        $text = $p->description ?: ($seo ?: null);
        if (! $text) {
            $cat = strtolower($p->category->name ?? 'print product');
            $text = "{$p->name} — custom {$cat} printed by RunMyPrint. Premium quality, fast turnaround and a 100% satisfaction guarantee.";
        }
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        return mb_strlen($text) > 4900 ? mb_substr($text, 0, 4900) : $text; // Google caps description at 5000
    }

    private function img(Product $p): string
    {
        if ($p->image_path && Storage::disk('public')->exists($p->image_path)) {
            return url(Storage::disk('public')->url($p->image_path));
        }

        return url('/');
    }

    private function tag(string $name, $value): string
    {
        return "<{$name}>".htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</{$name}>";
    }
}
