<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $orders = Order::where('email', $request->user()->email)
            ->latest()->take(25)->get()
            ->map(fn (Order $o) => [
                'number' => $o->number,
                'total'  => (float) $o->total,
                'status' => $o->status,
                'items'  => count($o->items ?? []),
                'date'   => $o->created_at?->toDayDateTimeString(),
            ]);

        // "My designs": projects this account owns (claimed at login or made
        // while signed in) — each edits via /design/{slug}?project={id}.
        $designs = \App\Models\DesignProject::where('user_id', $request->user()->id)
            ->latest('updated_at')->take(24)->get()
            ->map(fn ($p) => [
                'id'      => $p->id,
                'slug'    => $p->product_slug,
                'product' => $p->product_name,
                'preview' => $p->preview,
                'date'    => $p->updated_at?->diffForHumans(),
            ]);

        return Inertia::render('Account', ['orders' => $orders, 'designs' => $designs]);
    }

    /** Rebuild the cart from a past order at CURRENT prices (print = repeat business). */
    public function reorder(\App\Models\Order $order, Request $request, \App\Services\Cart $cart, \App\Services\Pricing $pricing)
    {
        abort_unless($order->email === $request->user()->email, 403);

        $added = 0;
        foreach (($order->items ?? []) as $item) {
            $product = \App\Models\Product::with(['options.values', 'quantities'])
                ->where('id', $item['product_id'] ?? 0)->where('is_active', true)->first();
            if (! $product) {
                continue; // product retired since — skip it, report below
            }
            $quote = $pricing->quote($product, $item['quantity_id'] ?? null, $item['option_value_ids'] ?? []);
            $cart->add([
                'product_id'       => $product->id,
                'name'             => $product->name,
                'slug'             => $product->slug,
                'image'            => \App\Support\Img::url($product->image_path),
                'quantity'         => $quote['quantity'],
                'quantity_id'      => $quote['quantity_id'],
                'unit_price'       => $quote['unit_price'],
                'line_total'       => $quote['line_total'],
                'options'          => $quote['options'],
                'option_value_ids' => $quote['option_value_ids'],
                'design'           => $item['design'] ?? null,
                'brand'            => $item['brand'] ?? null,
            ]);
            $added++;
        }

        if (! $added) {
            return back()->with('error', 'Those products are no longer available.');
        }

        $skipped = count($order->items ?? []) - $added;

        return redirect()->route('cart')->with('success',
            "Order {$order->number} is back in your cart at current prices.".($skipped ? " ({$skipped} discontinued item(s) skipped.)" : ''));
    }
}
