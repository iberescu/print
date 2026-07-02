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

    public function show()
    {
        // Forced upsell: can't reach the cart until the upsell steps are done.
        if ($this->cart->upsellPending()) {
            return redirect()->route('upsell.show');
        }

        return Inertia::render('Cart', [
            'items'       => $this->cart->items(),
            'summary'     => $this->summary(),
            'recommended' => $this->recommended(),
        ]);
    }

    public function add(Product $product, Request $request, Pricing $pricing): RedirectResponse
    {
        $data = $request->validate([
            'quantityId'       => ['nullable', 'integer'],
            'optionValueIds'   => ['nullable', 'array'],
            'optionValueIds.*' => ['integer'],
            'preview'          => ['nullable', 'string', 'max:4000000'],
            'mode'             => ['nullable', 'string', 'max:20'],
            'brand'            => ['nullable', 'array'],
            'brand.logo'       => ['nullable', 'string', 'max:4000000'],
        ]);

        // Normally the Review step already swapped these for stored URLs; convert
        // defensively so a raw data-URL can never reach the session/orders.
        $data['preview'] = \App\Support\PreviewStore::persist($data['preview'] ?? null);
        $brand = $request->input('brand') ?: null;
        if (is_array($brand) && isset($brand['logo'])) {
            $brand['logo'] = \App\Support\PreviewStore::persist($brand['logo']);
        }

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
            'brand'      => $brand,
        ]);

        $product->loadMissing('category');
        $steps = $this->upsellSteps($product, $data);
        $this->cart->setUpsell($steps);

        $flash = "“{$product->name}” added to your cart.";

        return $steps
            ? redirect()->route('upsell.show')->with('success', $flash)
            : redirect()->route('cart')->with('success', $flash);
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

    /** Which forced upsell steps apply to what was just added (req: multi-step upsell). */
    private function upsellSteps(Product $product, array $data): array
    {
        $steps = [];

        $brand = $data['brand'] ?? null;
        if (is_array($brand) && array_filter($brand)) {
            $steps[] = 'brand';   // lay the buyer's brand onto more products (req 11)
        }
        if (optional($product->category)->slug === 'business-cards') {
            $steps[] = 'related'; // non-personalised add-ons — card holders etc.
        }

        return $steps;
    }

    private function img(?string $path): ?string
    {
        return \App\Support\Img::url($path);
    }
}
