<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Bulk quantity tiers for the printed/embroidered apparel (t-shirt, hoodie) — they
 * shipped with a single qty=1 tier, so buyers couldn't order in volume. Each tier
 * carries a per-unit and a total price with a normal bulk-discount curve; qty=1 stays
 * the default. Idempotent by (product, quantity) so re-running only tops up / corrects.
 */
class ApparelQuantitiesSeeder extends Seeder
{
    public function run(): void
    {
        // slug => [quantity => unit price]. First tier (qty 1) is the default.
        $sets = [
            'gildan-softstyle-unisex-t-shirt' => [
                1 => 7.99, 5 => 7.49, 10 => 6.99, 25 => 6.49, 50 => 5.99, 100 => 5.49, 250 => 4.99, 500 => 4.49,
            ],
            'jerzees-nublend-hooded-sweatshirt' => [
                1 => 27.99, 5 => 26.99, 10 => 25.49, 25 => 23.99, 50 => 22.49, 100 => 20.99, 250 => 19.49,
            ],
        ];

        foreach ($sets as $slug => $tiers) {
            $product = Product::where('slug', $slug)->first();
            if (! $product) {
                $this->command?->warn("ApparelQuantities: skip {$slug} (product missing)");
                continue;
            }

            $i = 0;
            foreach ($tiers as $qty => $unit) {
                $product->quantities()->updateOrCreate(
                    ['quantity' => $qty],
                    [
                        'unit_price'  => $unit,
                        'total_price' => round($qty * $unit, 2),
                        'is_default'  => $qty === 1,
                        'sort_order'  => $i,
                    ],
                );
                $i++;
            }
        }
    }
}
