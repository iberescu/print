<?php

namespace App\Services;

use App\Models\OptionValue;
use App\Models\Product;

class Pricing
{
    /**
     * Authoritative server-side price for a product + selected quantity tier + option values.
     *
     * The resolved ids come back too, so a cart line can be re-quoted later
     * (the final-step page changes quantity/material after the design is approved).
     *
     * @param  array<int>  $optionValueIds
     * @return array{quantity:int,quantity_id:int|null,unit_price:float,line_total:float,options:array<string,string>,option_value_ids:array<int>}
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
        $valueIds = [];
        if (! empty($optionValueIds)) {
            foreach (OptionValue::with('option')->whereIn('id', $optionValueIds)->get() as $v) {
                $deltas += (float) $v->price_delta;
                $labels[$v->option->name ?? 'Option'] = $v->label;
                $valueIds[] = $v->id;
            }
        }

        $lineTotal = round($base + $deltas, 2);
        $quantity = $qty?->quantity ?? 1;

        return [
            'quantity'         => $quantity,
            'quantity_id'      => $qty?->id,
            'unit_price'       => $quantity ? round($lineTotal / $quantity, 2) : $lineTotal,
            'line_total'       => $lineTotal,
            'options'          => $labels,
            'option_value_ids' => $valueIds,
        ];
    }
}
