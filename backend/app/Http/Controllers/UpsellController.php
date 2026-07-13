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

        $payload = match ($step) {
            'brand'    => $this->brandPayload(),
            'pqsg'     => $this->pqsgPayload(),
            'ads'      => $this->pqsgPayload() + ['promoImage' => $this->img('promos/layout-ai-offer-v4')],
            'finalize' => $this->finalizePayload(),
            default    => $this->relatedPayload(),
        };

        // The line being finalised vanished (or lost its ids) — skip the step.
        if ($payload === null) {
            $this->cart->advanceUpsell();

            return redirect()->route('upsell.show');
        }

        return Inertia::render('Upsell', [
            'step'      => $step,
            'stepIndex' => $this->cart->upsellIndex() + 1,
            'stepCount' => count($this->cart->upsellSteps()),
            'payload'   => $payload,
            'summary'   => [
                'subtotal'  => $this->cart->subtotal(),
                'threshold' => $this->cart->threshold(),
                'remaining' => $this->cart->remainingForFree(),
                'qualifies' => $this->cart->qualifiesFreeShipping(),
            ],
        ]);
    }

    /**
     * Final step: re-price the just-added line with a new quantity tier and/or
     * new values for the options that don't affect the design surface.
     */
    public function finalize(Request $request, Pricing $pricing)
    {
        $data = $request->validate([
            'quantityId'       => ['nullable', 'integer'],
            'optionValueIds'   => ['nullable', 'array'],
            'optionValueIds.*' => ['integer'],
        ]);

        $lineId = $this->cart->upsellLineId();
        $line = $lineId ? $this->cart->item($lineId) : null;
        $product = $line ? Product::with(['options.values', 'quantities'])->find($line['product_id']) : null;
        if (! $product) {
            return redirect()->route('upsell.show');
        }

        // Quantity must be one of the product's tiers; otherwise keep the current one.
        $quantityId = $product->quantities->firstWhere('id', (int) ($data['quantityId'] ?? 0))?->id
            ?? $line['quantity_id'] ?? null;

        // Surface-bound selections are locked to the approved design; changeable
        // groups take at most one submitted value each, falling back to what the
        // line already had. Anything not belonging to this product is dropped.
        $submitted = collect($data['optionValueIds'] ?? []);
        $current = collect($line['option_value_ids'] ?? []);
        $valueIds = $product->options->flatMap(function ($option) use ($submitted, $current) {
            $ids = $option->values->pluck('id');
            $pick = $option->affectsSurface()
                ? $current->first(fn ($id) => $ids->contains($id))
                : $submitted->first(fn ($id) => $ids->contains($id)) ?? $current->first(fn ($id) => $ids->contains($id));

            return $pick ? [$pick] : [];
        })->values()->all();

        $quote = $pricing->quote($product, $quantityId, $valueIds);

        $this->cart->update($lineId, [
            'quantity'         => $quote['quantity'],
            'quantity_id'      => $quote['quantity_id'],
            'unit_price'       => $quote['unit_price'],
            'line_total'       => $quote['line_total'],
            'options'          => $quote['options'],
            'option_value_ids' => $quote['option_value_ids'],
        ]);

        return redirect()->route('upsell.show');
    }

    public function add(Product $product, Request $request, Pricing $pricing)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate(['brand' => ['nullable', 'array']]);
        $quote = $pricing->quote($product, null, []);
        $brand = $data['brand'] ?? null;

        // Prefer the customer's "your logo on" mockup for this product (from the session
        // brand kit) so the cart shows their branded preview, not the stock product photo.
        $mockup = collect(\App\Support\LogoOnProducts::forCurrentSession())
            ->firstWhere('slug', $product->slug)['img'] ?? null;

        $this->cart->add([
            'product_id'       => $product->id,
            'name'             => $product->name,
            'slug'             => $product->slug,
            'image'            => $mockup ?: $this->img($product->image_path),
            'quantity'         => $quote['quantity'],
            'quantity_id'      => $quote['quantity_id'],
            'unit_price'       => $quote['unit_price'],
            'line_total'       => $quote['line_total'],
            'options'          => $quote['options'],
            'option_value_ids' => $quote['option_value_ids'],
            'design'           => $brand ? ['preview' => null, 'mode' => 'design'] : null,
            'brand'            => $brand ?: null,
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
        $key = session('pqsg.key');
        // Carry the resolved engine uuid so the client can pass it back in the feed
        // URL — a fallback if the Redis key→uuid mapping is ever gone (see
        // PqsgController::resolveUuid). Session copy survives cache flushes.
        $uuid = $key ? (\Illuminate\Support\Facades\Cache::get("pqsg:{$key}") ?: session("pqsg.uuid.{$key}")) : null;

        return [
            'key'       => $key,
            'uuid'      => $uuid,
            'apiBase'   => config('shop.pqsg.api_base'),
            'widgetSrc' => config('shop.pqsg.widget_src'),
        ];
    }

    /**
     * Final step: the just-added line plus everything the buyer may still change —
     * quantity tiers and option groups whose values don't alter the design surface.
     * Surface-bound picks (size, corners, …) come back as a locked summary.
     * Null when the line can't be finalised (skips the step).
     */
    private function finalizePayload(): ?array
    {
        $lineId = $this->cart->upsellLineId();
        $line = $lineId ? $this->cart->item($lineId) : null;
        if (! $line || ! isset($line['quantity_id'], $line['option_value_ids'])) {
            return null;
        }

        $product = Product::with(['options.values', 'quantities'])->find($line['product_id']);
        if (! $product) {
            return null;
        }

        $selected = collect($line['option_value_ids']);
        [$lockedOptions, $groups] = $product->options->partition->affectsSurface();

        $locked = $lockedOptions->map(function ($o) use ($selected) {
            $v = $o->values->first(fn ($v) => $selected->contains($v->id))
                ?? $o->values->firstWhere('is_default', true);

            return $v ? ['name' => $o->name, 'label' => $v->label] : null;
        })->filter()->values();

        // Deltas of the locked values — part of every displayed total, so the
        // client can price tiers/options instantly without a round trip.
        $lockedDelta = $lockedOptions->flatMap->values
            ->whereIn('id', $selected)->sum(fn ($v) => (float) $v->price_delta);

        return [
            'line' => [
                'id'        => $line['id'],
                'name'      => $line['name'],
                'slug'      => $line['slug'],
                'image'     => $line['image'],
                'preview'   => $line['design']['preview'] ?? null,
                'mode'      => $line['design']['mode'] ?? null,
                'quantity'  => $line['quantity'],
                'unitPrice' => (float) $line['unit_price'],
                'lineTotal' => (float) $line['line_total'],
                'options'   => $line['options'],
            ],
            'quantityId'     => $line['quantity_id'],
            'optionValueIds' => $groups->flatMap->values->whereIn('id', $selected)->pluck('id')->values(),
            'lockedDelta'    => round($lockedDelta, 2),
            'locked'         => $locked,
            'quantities'     => $product->quantities->map(fn ($q) => [
                'id'        => $q->id,
                'quantity'  => $q->quantity,
                'total'     => $q->totalPrice(),
                'isDefault' => $q->is_default,
            ])->values(),
            'groups' => $groups->filter(fn ($o) => $o->values->count() > 1)->map(fn ($o) => [
                'id'     => $o->id,
                'name'   => $o->name,
                'type'   => $o->type,
                'values' => $o->values->map(fn ($v) => [
                    'id'          => $v->id,
                    'label'       => $v->label,
                    'priceDelta'  => (float) $v->price_delta,
                    'description' => $v->description,
                    'badge'       => $v->badge,
                    'swatch'      => $v->swatch,
                    'attributes'  => $v->attributes ?? [],
                    'image'       => $this->img($v->image_path),
                ])->values(),
            ])->values(),
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
