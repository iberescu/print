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
            'items'         => $this->items(),
            'summary'       => $this->summary(),
            'recommended'   => $this->recommended(),
            'brandProducts' => \App\Support\LogoOnProducts::forCurrentSession(),
            // free500 A/B arm: the cart shows the $100-threshold credit status
            'adsOffer'      => \App\Support\AdsOffer::current() === \App\Support\AdsOffer::FREE500 ? [
                'variant'   => 'free500',
                'credit'    => \App\Support\AdsOffer::CREDIT,
                'qualifyAt' => \App\Support\AdsOffer::QUALIFY_AT,
            ] : null,
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
            'project'          => ['nullable', 'uuid'],
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

        $line = [
            'product_id'       => $product->id,
            'name'             => $product->name,
            'slug'             => $product->slug,
            'image'            => $this->img($product->image_path),
            'quantity'         => $quote['quantity'],
            'quantity_id'      => $quote['quantity_id'],
            'unit_price'       => $quote['unit_price'],
            'line_total'       => $quote['line_total'],
            'options'          => $quote['options'],
            'option_value_ids' => $quote['option_value_ids'],
            'design'           => ! empty($data['preview'])
                ? ['preview' => $data['preview'], 'mode' => $data['mode'] ?? 'design', 'project' => $data['project'] ?? null]
                : null,
            'brand'            => $brand,
        ];

        // Edit-from-cart round trip: the same project is the same design —
        // replace that line instead of piling up duplicates.
        $existing = ! empty($data['project'])
            ? collect($this->cart->items())->first(fn ($i) => ($i['design']['project'] ?? null) === $data['project'])
            : null;
        if ($existing) {
            $this->cart->update($existing['id'], $line);
            $lineId = $existing['id'];
        } else {
            $lineId = $this->cart->add($line);
        }

        $product->loadMissing('category');
        $steps = $this->upsellSteps($product, $data);
        $this->cart->setUpsell($steps, $lineId);

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

    /** In-cart quantity switch: re-price the line at another of the product's tiers. */
    public function updateQty(string $lineId, Request $request, Pricing $pricing): RedirectResponse
    {
        $data = $request->validate(['quantityId' => ['required', 'integer']]);

        $line = $this->cart->item($lineId);
        $product = $line ? Product::with('quantities')->find($line['product_id']) : null;
        if (! $product) {
            return back();
        }

        // Only accept one of this product's tiers; otherwise keep the current one.
        $quantityId = $product->quantities->firstWhere('id', (int) $data['quantityId'])?->id
            ?? $line['quantity_id'] ?? null;

        // Keep the line's existing options — re-price against the new tier only.
        $quote = $pricing->quote($product, $quantityId, $line['option_value_ids'] ?? []);

        $this->cart->update($lineId, [
            'quantity'    => $quote['quantity'],
            'quantity_id' => $quote['quantity_id'],
            'unit_price'  => $quote['unit_price'],
            'line_total'  => $quote['line_total'],
        ]);

        return back();
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $error = $this->cart->applyCoupon($data['code']);

        return back()->with($error ? 'error' : 'success', $error ?: 'Code applied.');
    }

    public function removeCoupon()
    {
        $this->cart->removeCoupon();

        return back();
    }

    /** Cart lines enriched with each product's quantity tiers (for the in-cart qty switch). */
    private function items(): array
    {
        $items = $this->cart->items();
        $products = Product::with('quantities')
            ->whereIn('id', collect($items)->pluck('product_id')->filter()->unique()->all())
            ->get()->keyBy('id');

        return collect($items)->map(function ($it) use ($products) {
            $tiers = $products->get($it['product_id'] ?? null)?->quantities;
            $it['quantities'] = $tiers
                ? $tiers->sortBy('sort_order')->values()->map(fn ($q) => [
                    'id'       => $q->id,
                    'quantity' => $q->quantity,
                    'total'    => (float) $q->totalPrice(),
                ])->all()
                : [];

            return $it;
        })->all();
    }

    private function summary(): array
    {
        return [
            'subtotal'  => $this->cart->subtotal(),
            'coupon'    => $this->cart->coupon()?->code,
            'discount'  => $this->cart->discount(),
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


    /** Which forced upsell steps apply to what was just added (req: multi-step upsell).
     *  Funnel order: review → final step (qty/material) → accessories → brand gallery → cart. */
    private function upsellSteps(Product $product, array $data): array
    {
        $steps = [];
        $hasPqsg = session('pqsg.key') && config('shop.pqsg.enabled');

        // Designed items (came through the review page) get a final step first:
        // adjust quantity and the options that don't touch the approved design
        // surface (paper stock, finish, …) — with nothing to change it is skipped.
        if ((! empty($data['preview']) || ! empty($data['mode'])) && $this->hasFinalizeChoices($product)) {
            $steps[] = 'finalize';
        }

        // accessories are business-card add-ons only (no accessories for other
        // products yet); when both steps apply, accessories come first
        if (optional($product->category)->slug === 'business-cards') {
            $steps[] = 'related'; // non-personalised add-ons — card holders etc.
        }

        $brand = $data['brand'] ?? null;
        if ($hasPqsg) {
            $steps[] = 'pqsg';    // pqSmartGenerator: the buyer's logo on more products
            $steps[] = 'ads';     // Layout.ai ad-credit offer — facebook-ad mockup from the same capture
        } elseif (! config('shop.pqsg.enabled') && is_array($brand) && array_filter($brand)) {
            $steps[] = 'brand';   // internal brand mockups — only when the engine is off
        }

        return $steps;
    }

    /** More than one quantity tier, or at least one option safe to change after design. */
    private function hasFinalizeChoices(Product $product): bool
    {
        $product->loadMissing(['options.values', 'quantities']);

        return $product->quantities->count() > 1
            || $product->options->contains(fn ($o) => ! $o->affectsSurface() && $o->values->count() > 1);
    }

    private function img(?string $path): ?string
    {
        return \App\Support\Img::url($path);
    }
}
