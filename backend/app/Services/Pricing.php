<?php

namespace App\Services;

use App\Models\OptionValue;
use App\Models\Product;

class Pricing
{
    /**
     * Authoritative server-side price for a product + selected quantity tier + option values.
     *
     * @param  array<int>  $optionValueIds
     * @return array{quantity:int,unit_price:float,line_total:float,options:array<string,string>}
     */
    public function quote(Product $product, ?int $quantityId, array $optionValueIds): array
    {
        $product->loadMissing('quantities');

        $qty = ($quantityId ? $product->quantities->firstWhere('id', $quantityId) : null)
            ?? $product->quantities->firstWhere('is_default', true)
            ?? $product->quantities->first();

        $base = $qty ? $qty->totalPrice() : (float) $product->from_price;

        $deltas = 0.0;
        $labels = [];
        if (! empty($optionValueIds)) {
            foreach (OptionValue::with('option')->whereIn('id', $optionValueIds)->get() as $v) {
                $deltas += (float) $v->price_delta;
                $labels[$v->option->name ?? 'Option'] = $v->label;
            }
        }

        $lineTotal = round($base + $deltas, 2);
        $quantity = $qty?->quantity ?? 1;

        return [
            'quantity'   => $quantity,
            'unit_price' => $quantity ? round($lineTotal / $quantity, 2) : $lineTotal,
            'line_total' => $lineTotal,
            'options'    => $labels,
        ];
    }
}
