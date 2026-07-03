<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Cart;
use App\Services\Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UpsellController extends Controller
{
    /** Surfaces we can lay the buyer's brand onto (req 11). slug => BrandMockup variant. */
    private const BRAND_SURFACES = [
        'flyers' => 'flyer', 'posters' => 'poster', 'postcards' => 'postcard',
        'notepads' => 'notepad', 'tote-bags' => 'tote',
    ];

    public function __construct(private readonly Cart $cart)
    {
    }

    public function show()
    {
        $step = $this->cart->upsellCurrent();
        if ($step === null || $this->cart->count() === 0) {
            $this->cart->clearUpsell();

            return redirect()->route('cart');
        }

        return Inertia::render('Upsell', [
            'step'      => $step,
            'stepIndex' => $this->cart->upsellIndex() + 1,
            'stepCount' => count($this->cart->upsellSteps()),
            'payload'   => match ($step) {
                'brand' => $this->brandPayload(),
                'pqsg'  => $this->pqsgPayload(),
                default => $this->relatedPayload(),
            },
            'summary'   => [
                'subtotal'  => $this->cart->subtotal(),
                'threshold' => $this->cart->threshold(),
                'remaining' => $this->cart->remainingForFree(),
                'qualifies' => $this->cart->qualifiesFreeShipping(),
            ],
        ]);
    }

    public function add(Product $product, Request $request, Pricing $pricing)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate(['brand' => ['nullable', 'array']]);
        $quote = $pricing->quote($product, null, []);
        $brand = $data['brand'] ?? null;

        $this->cart->add([
            'product_id' => $product->id,
            'name'       => $product->name,
            'slug'       => $product->slug,
            'image'      => $this->img($product->image_path),
            'quantity'   => $quote['quantity'],
            'unit_price' => $quote['unit_price'],
            'line_total' => $quote['line_total'],
            'options'    => $quote['options'],
            'design'     => $brand ? ['preview' => null, 'mode' => 'design'] : null,
            'brand'      => $brand ?: null,
        ]);

        return redirect()->route('upsell.show')->with('success', "“{$product->name}” added.");
    }

    public function next()
    {
        $this->cart->advanceUpsell();

        return redirect()->route('upsell.show');
    }

    private function brandPayload(): array
    {
        $brand = collect($this->cart->items())->reverse()
            ->first(fn ($i) => ! empty($i['brand']))['brand'] ?? null;

        $surfaces = Product::with('category')->whereIn('slug', array_keys(self::BRAND_SURFACES))
            ->where('is_active', true)->orderBy('sort_order')->get();

        return [
            'brand'    => $brand,
            'products' => $surfaces->map(fn (Product $p) => [
                'name'      => $p->name,
                'slug'      => $p->slug,
                'fromPrice' => (float) $p->from_price,
                'mockup'    => self::BRAND_SURFACES[$p->slug] ?? 'flyer',
            ])->all(),
        ];
    }

    /** Third-party gallery step: the widget polls with the capture registered at Review. */
    private function pqsgPayload(): array
    {
        return [
            'key'       => session('pqsg.key'),
            'apiBase'   => config('shop.pqsg.api_base'),
            'widgetSrc' => config('shop.pqsg.widget_src'),
        ];
    }

    private function relatedPayload(): array
    {
        $accessories = Product::whereHas('category', fn ($q) => $q->where('slug', 'accessories'))
            ->where('is_active', true)->orderBy('sort_order')->get();

        return [
            'products' => $accessories->map(fn (Product $p) => [
                'name'      => $p->name,
                'slug'      => $p->slug,
                'tagline'   => $p->tagline,
                'fromPrice' => (float) $p->from_price,
                'image'     => $this->img($p->image_path),
            ])->all(),
        ];
    }

    private function img(?string $path): ?string
    {
        return \App\Support\Img::url($path);
    }
}
