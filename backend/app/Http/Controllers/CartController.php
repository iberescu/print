<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Cart;
use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly Cart $cart)
    {
    }

    public function show(): Response
    {
        $items = $this->cart->items();

        return Inertia::render('Cart', [
            'items'         => $items,
            'summary'       => $this->summary(),
            'recommended'   => $this->recommended(),
            'designMockups' => $this->designMockups($items),
        ]);
    }

    public function add(Product $product, Request $request, Pricing $pricing): RedirectResponse
    {
        $data = $request->validate([
            'quantityId'       => ['nullable', 'integer'],
            'optionValueIds'   => ['nullable', 'array'],
            'optionValueIds.*' => ['integer'],
            'preview'          => ['nullable', 'string'],
            'mode'             => ['nullable', 'string'],
            'brand'            => ['nullable', 'array'],
        ]);

        $quote = $pricing->quote($product, $data['quantityId'] ?? null, $data['optionValueIds'] ?? []);

        $this->cart->add([
            'product_id' => $product->id,
            'name'       => $product->name,
            'slug'       => $product->slug,
            'image'      => $this->img($product->image_path),
            'quantity'   => $quote['quantity'],
            'unit_price' => $quote['unit_price'],
            'line_total' => $quote['line_total'],
            'options'    => $quote['options'],
            'design'     => ! empty($data['preview'])
                ? ['preview' => $data['preview'], 'mode' => $data['mode'] ?? 'design']
                : null,
            'brand'      => $request->input('brand') ?: null,
        ]);

        return redirect()->route('cart')->with('success', "“{$product->name}” added to your cart.");
    }

    public function remove(string $lineId): RedirectResponse
    {
        $this->cart->remove($lineId);

        return back();
    }

    private function summary(): array
    {
        return [
            'subtotal'  => $this->cart->subtotal(),
            'shipping'  => $this->cart->shipping(),
            'total'     => $this->cart->total(),
            'count'     => $this->cart->count(),
            'threshold' => $this->cart->threshold(),
            'qualifies' => $this->cart->qualifiesFreeShipping(),
            'remaining' => $this->cart->remainingForFree(),
        ];
    }

    /** Cheapest products not already in the cart — to nudge toward free shipping (req 15). */
    private function recommended(): array
    {
        $inCart = collect($this->cart->items())->pluck('product_id')->all();

        return Product::with('category')->where('is_active', true)->whereNotIn('id', $inCart)
            ->orderBy('from_price')->take(4)->get()
            ->map(fn (Product $p) => [
                'name'      => $p->name,
                'slug'      => $p->slug,
                'fromPrice' => (float) $p->from_price,
                'image'     => $this->img($p->image_path),
                'category'  => $p->category?->name,
            ])->all();
    }

    /** req 11: the user's brand elements (logo, name, url, contact) laid into per-product SVG mockups. */
    private function designMockups(array $items): array
    {
        $withBrand = collect($items)->reverse()->first(fn ($i) => ! empty($i['brand']));
        $brand = $withBrand['brand'] ?? null;
        if (! $brand) {
            return [];
        }

        $map = ['flyers' => 'flyer', 'posters' => 'poster', 'postcards' => 'postcard', 'notepads' => 'notepad', 'tote-bags' => 'tote'];
        $surfaces = Product::whereIn('slug', array_keys($map))->orderBy('sort_order')->get();

        return [
            'brand'    => $brand,
            'products' => $surfaces->map(fn (Product $p) => [
                'name'      => $p->name,
                'slug'      => $p->slug,
                'fromPrice' => (float) $p->from_price,
                'mockup'    => $map[$p->slug] ?? 'flyer',
            ])->all(),
        ];
    }

    private function img(?string $path): ?string
    {
        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }
}
