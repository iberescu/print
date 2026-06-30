<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function home(): Response
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn (Category $c) => $this->categoryCard($c));

        $featured = Product::with('category')
            ->where('is_active', true)
            ->orderByRaw('badge IS NULL')   // badged products first
            ->orderBy('sort_order')
            ->take(8)->get()
            ->map(fn (Product $p) => $this->productCard($p));

        return Inertia::render('Home', [
            'categories'            => $categories,
            'featured'              => $featured,
            'heroImage'             => \App\Support\Img::url('heroes/home'),
            'freeShippingThreshold' => (float) config('shop.free_shipping_threshold'),
        ]);
    }

    public function category(Category $category): Response
    {
        abort_unless($category->is_active, 404);
        $category->load(['products' => fn ($q) => $q->where('is_active', true)]);

        return Inertia::render('Category', [
            'category' => [
                'name'        => $category->name,
                'slug'        => $category->slug,
                'tagline'     => $category->tagline,
                'description' => $category->description,
                'image'       => $this->img($category->image_path),
            ],
            'products'              => $category->products->map(fn (Product $p) => $this->productCard($p)),
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
                'fromPrice'      => (float) $product->from_price,
                'badge'          => $product->badge,
                'image'          => $this->img($product->image_path),
                'supportsDesign' => $product->supports_design,
                'supportsUpload' => $product->supports_upload,
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
                    ]),
                ]),
                'quantities' => $product->quantities->map(fn ($q) => [
                    'id'        => $q->id,
                    'quantity'  => $q->quantity,
                    'unitPrice' => (float) $q->unit_price,
                    'total'     => $q->totalPrice(),
                    'isDefault' => $q->is_default,
                ]),
            ],
            'categories'            => $this->nav(),
            'freeShippingThreshold' => (float) config('shop.free_shipping_threshold'),
        ]);
    }

    private function nav()
    {
        return Category::where('is_active', true)->orderBy('sort_order')->get(['name', 'slug']);
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
}
