<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductQuantity;
use Illuminate\Database\Seeder;

/**
 * Business-card promotional pricing:
 *   • Standard Business Cards — 50 for $7.50 (backs the category banner).
 *   • Every business-card product — 25% off all quantity tiers of 100+.
 *
 * The pre-discount price is captured once in compare_at_total, so the seeder is
 * idempotent (re-running recomputes from the original, never compounding) and the
 * storefront can show a strikethrough + "save 25%".
 */
class BusinessCardPricingSeeder extends Seeder
{
    private const BULK_MIN_QTY = 100;
    private const BULK_DISCOUNT = 0.25;

    public function run(): void
    {
        $category = Category::where('slug', 'business-cards')->first();
        if (! $category) {
            $this->command?->warn('  business-cards category not found — skipping');

            return;
        }

        $productIds = Product::where('category_id', $category->id)->pluck('id');
        $count = 0;

        // 25% off every 100+ tier across all business-card products.
        foreach (ProductQuantity::whereIn('product_id', $productIds)->where('quantity', '>=', self::BULK_MIN_QTY)->get() as $q) {
            $original = $q->compare_at_total !== null ? (float) $q->compare_at_total : (float) $q->total_price;
            $this->reprice($q, $original, round($original * (1 - self::BULK_DISCOUNT), 2));
            $count++;
        }

        // Standard Business Cards headline prices — these override the bulk rule
        // above (run last, so they win): 50 for $7.50 (banner), 100 for $9.99.
        $standard = Product::where('slug', 'standard-business-cards')->first();
        foreach ([50 => 7.50, 100 => 9.99] as $qty => $price) {
            $tier = $standard?->quantities()->where('quantity', $qty)->first();
            if (! $tier) {
                continue;
            }
            $original = $tier->compare_at_total !== null ? (float) $tier->compare_at_total : (float) $tier->total_price;
            $this->reprice($tier, $original, $price);
            $count++;
        }

        $this->command?->info("  repriced {$count} business-card quantity tiers");
    }

    private function reprice(ProductQuantity $q, float $original, float $newTotal): void
    {
        $q->compare_at_total = $original;                 // capture once, then stable
        $q->total_price = $newTotal;
        $q->unit_price = $q->quantity ? round($newTotal / $q->quantity, 4) : $q->unit_price;
        $q->save();
    }
}
