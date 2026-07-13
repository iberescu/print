<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /** Home "shop by product" tiles: display label, name patterns to find the
     *  product (works across seeded + crawled catalogues), optional category
     *  link when the label maps to a whole category. */
    private const SHOP_BY = [
        ['Business Cards', ['%business card%'], 'business-cards'],
        ['Flyers', ['%flyer%'], null],
        ['Postcards', ['%postcard%'], null],
        ['T-Shirts', ['%t-shirt%'], null], // NOT %tshirt% — it substring-matches "Sweatshirt"
        ['Signs', ['%yard sign%', '%sign%'], null],
        ['Stickers', ['%sticker%'], null],
        ['Banners', ['%banner%'], null],
        ['Posters', ['%poster%'], null],
        ['Brochures', ['%brochure%'], null],
        ['Mugs', ['%mug%'], null],
        ['Labels', ['%label%'], null],
        ['Tote Bags', ['%tote%'], null],
    ];

    public function home(): Response
    {
        $categories = Category::where('is_active', true)
            ->where('slug', '!=', 'services') // ad credit etc. — buyable via upsell, not browsable
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->get()
            ->map(fn (Category $c) => $this->categoryCard($c));

        // "Most Popular" = the curated featured products (one master per type);
        // fall back to badged/first products if nothing is featured yet.
        $featured = Product::with('category')->where('is_active', true)->where('featured', true)
            ->orderBy('sort_order')->take(8)->get();
        if ($featured->isEmpty()) {
            $featured = Product::with('category')->where('is_active', true)
                ->orderByRaw('badge IS NULL')->orderBy('sort_order')->take(8)->get();
        }
        $featured = $featured->map(fn (Product $p) => $this->productCard($p));

        return Inertia::render('Home', [
            'categories'            => $categories,
            'featured'              => $featured,
            'logoProducts'          => \App\Support\LogoOnProducts::forCurrentSession(),
            'shopBy'                => $this->shopByTiles(),
            'heroImage'             => \App\Support\Img::url('heroes/home'),
            'priceGuaranteeImage'   => \App\Support\Img::url('promos/price-guarantee'),
            'freeShippingThreshold' => (float) config('shop.free_shipping_threshold'),
            // Hero "business cards from $X" — the live flagship price, so it tracks the
            // catalogue/sale (never a hardcoded figure that drifts).
            'businessCardsFrom'     => (float) (Product::where('slug', 'standard-business-cards')->value('from_price')
                ?: Product::whereHas('category', fn ($q) => $q->where('slug', 'business-cards'))
                    ->where('is_active', true)->min('from_price')),
        ]);
    }

    /** One tile per popular product type, resolved by name so it survives
     *  catalogue reimports (cached briefly, same policy as the nav). */
    private function shopByTiles(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('home.shopby', 600, function () {
            return collect(self::SHOP_BY)->map(function ($tile) {
                [$label, $patterns, $category] = $tile;
                $product = Product::where('is_active', true)
                    ->where(function ($q) use ($patterns) {
                        foreach ($patterns as $p) {
                            $q->orWhere('name', 'like', $p);
                        }
                    })
                    ->orderByDesc('featured')->orderByRaw('badge IS NULL')->orderBy('sort_order')
                    ->first();

                return $product ? [
                    'label'     => $label,
                    'href'      => $category ? "/category/{$category}" : "/product/{$product->slug}",
                    'image'     => $this->img($product->image_path),
                    'fromPrice' => (float) $product->from_price,
                ] : null;
            })->filter()->values()->all();
        });
    }

    public function category(Category $category): Response
    {
        abort_unless($category->is_active, 404);

        $category->load([
            'products' => fn ($q) => $q->where('is_active', true),
            'subcategories' => fn ($q) => $q->where('is_active', true),
            'subcategories.products' => fn ($q) => $q->where('is_active', true),
        ]);

        // Grouped sections (one per non-empty subcategory) + tiles that jump to them.
        $sections = $category->subcategories
            ->map(fn ($s) => [
                'name'     => $s->name,
                'slug'     => $s->slug,
                'image'    => $this->img($s->image_path),
                'count'    => $s->products->count(),
                'products' => $s->products->map(fn (Product $p) => $this->productCard($p))->values(),
            ])
            ->filter(fn ($s) => $s['count'] > 0)
            ->values();

        // Anything not yet filed under a subcategory still shows, in a trailing group.
        $ungrouped = $category->products->whereNull('subcategory_id')
            ->map(fn (Product $p) => $this->productCard($p))->values();

        return Inertia::render('Category', [
            'category' => [
                'name'        => $category->name,
                'slug'        => $category->slug,
                'tagline'     => $category->tagline,
                'description' => $category->description,
                'image'       => $this->img($category->image_path),
            ],
            'sections'              => $sections,
            'ungrouped'             => $ungrouped,
            'products'              => $category->products->map(fn (Product $p) => $this->productCard($p))->values(),
            'categories'            => $this->nav(),
            'freeShippingThreshold' => (float) config('shop.free_shipping_threshold'),
        ]);
    }

    public function product(Product $product): Response
    {
        abort_unless($product->is_active, 404);
        $product->load(['category', 'options.values', 'quantities']);

        return Inertia::render('Product', [
            'product' => [
                'id'             => $product->id,
                'name'           => $product->name,
                'slug'           => $product->slug,
                'tagline'        => $product->tagline,
                'description'    => $product->description,
                'seo'            => $product->seo,
                'fromPrice'      => (float) $product->from_price,
                'badge'          => $product->badge,
                'image'          => $this->img($product->image_path),
                'supportsDesign' => $product->supports_design,
                'supportsUpload' => $product->supports_upload,
                'templateCount'  => \App\Models\Template::where('is_active', true)
                    ->where('category', $product->category->slug)->count(),
                'category'       => ['name' => $product->category->name, 'slug' => $product->category->slug],
                'options'        => $product->options->map(fn ($o) => [
                    'id'     => $o->id,
                    'name'   => $o->name,
                    'type'   => $o->type,
                    'values' => $o->values->map(fn ($v) => [
                        'id'          => $v->id,
                        'label'       => $v->label,
                        'priceDelta'  => (float) $v->price_delta,
                        'badge'       => $v->badge,
                        'description' => $v->description,
                        'swatch'      => $v->swatch,
                        'isDefault'   => $v->is_default,
                        'attributes'  => $v->attributes ?? [],
                    ]),
                ]),
                'quantities' => $product->quantities->map(fn ($q) => [
                    'id'             => $q->id,
                    'quantity'       => $q->quantity,
                    'unitPrice'      => (float) $q->unit_price,
                    'total'          => $q->totalPrice(),
                    'compareAtTotal' => $q->compare_at_total !== null ? (float) $q->compare_at_total : null,
                    'isDefault'      => $q->is_default,
                ]),
            ],
            'related'               => $this->related($product),
            'categories'            => $this->nav(),
            'freeShippingThreshold' => (float) config('shop.free_shipping_threshold'),
        ]);
    }

    /** Up to 4 related products: same category first, then top products from elsewhere. */
    private function related(Product $product)
    {
        $same = Product::with('category')
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->orderByRaw('badge IS NULL')
            ->orderBy('sort_order')
            ->take(4)->get();

        if ($same->count() < 4) {
            $fill = Product::with('category')
                ->where('is_active', true)
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $same->pluck('id'))
                ->orderByRaw('badge IS NULL')
                ->orderBy('sort_order')
                ->take(4 - $same->count())->get();
            $same = $same->concat($fill);
        }

        return $same->map(fn (Product $p) => $this->productCard($p));
    }

    private function nav()
    {
        // cached briefly (admin product saves forget this key; imports just wait out the TTL)
        return \Illuminate\Support\Facades\Cache::remember('nav.categories', 600, fn () => Category::where('is_active', true)
            ->where('slug', '!=', 'services') // upsell-only category, not browsable
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->get(['name', 'slug']));
    }

    private function categoryCard(Category $c): array
    {
        return [
            'name'    => $c->name,
            'slug'    => $c->slug,
            'tagline' => $c->tagline,
            'image'   => $this->img($c->image_path),
        ];
    }

    private function productCard(Product $p): array
    {
        return [
            'name'      => $p->name,
            'slug'      => $p->slug,
            'tagline'   => $p->tagline,
            'fromPrice' => (float) $p->from_price,
            'badge'     => $p->badge,
            'image'     => $this->img($p->image_path),
            'category'  => $p->category?->name,
        ];
    }

    private function img(?string $path): ?string
    {
        return \App\Support\Img::url($path);
    }

    /** Header search — name/tagline/category match over active products. */
    public function search(\Illuminate\Http\Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $products = collect();

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $products = Product::with('category')->where('is_active', true)
                ->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                        ->orWhere('tagline', 'like', $like)
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like));
                })
                ->orderBy('sort_order')->limit(48)->get()
                ->map(fn (Product $p) => $this->productCard($p));
        }

        return Inertia::render('Search', ['q' => $q, 'products' => $products]);
    }

    /** sitemap.xml — static pages + categories + active products, cached 1h. */
    public function sitemap()
    {
        $xml = \Illuminate\Support\Facades\Cache::remember('sitemap.xml', 3600, function () {
            $urls = [
                [url('/'), now()->toDateString(), 'daily'],
                [url('/logo-maker'), now()->toDateString(), 'weekly'],
                [url('/qr-code-generator'), now()->toDateString(), 'weekly'],
                [url('/affiliates'), now()->toDateString(), 'monthly'],
            ];
            foreach (\App\Models\Category::orderBy('name')->get() as $c) {
                $urls[] = [route('category.show', $c->slug), $c->updated_at?->toDateString(), 'weekly'];
            }
            foreach (Product::where('is_active', true)->orderBy('id')->get() as $p) {
                $urls[] = [route('product.show', $p->slug), $p->updated_at?->toDateString(), 'weekly'];
            }

            $body = '';
            foreach ($urls as [$loc, $mod, $freq]) {
                $body .= '<url><loc>'.e($loc).'</loc>'
                    .($mod ? '<lastmod>'.$mod.'</lastmod>' : '')
                    .'<changefreq>'.$freq.'</changefreq></url>';
            }

            return '<?xml version="1.0" encoding="UTF-8"?>'
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
