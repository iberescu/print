<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductQuantity;
use Illuminate\Database\Seeder;

/**
 * Business-card promotional pricing:
 *   • Every business-card product — 25% off all quantity tiers of 100+.
 *   • Affordable full-color line (standard/matte/glossy/uncoated) — cheapest-in-market
 *     headline prices at 50/100/500 that undercut GotPrint/HOTCARDS/360/Bizay/VistaPrint.
 *
 * The pre-discount price is captured once in compare_at_total, so the seeder is
 * idempotent (re-running recomputes from the original, never compounding) and the
 * storefront/feed show a strikethrough + the real "% off".
 */
class BusinessCardPricingSeeder extends Seeder
{
    private const BULK_MIN_QTY = 100;
    private const BULK_DISCOUNT = 0.25;

    /** The price-competitive full-color cards that carry the "cheapest" headline. */
    private const AFFORDABLE = [
        'standard-business-cards', 'matte-business-cards', 'glossy-business-cards', 'uncoated-business-cards',
    ];

    /** Cheapest-in-market selling price by quantity for the affordable line. */
    private const HEADLINE = [50 => 6.85, 100 => 9.49, 500 => 15.99];

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

        // Cheapest-in-market headline prices for the affordable full-color line at the
        // quantities buyers compare on (50/100/500). These run last so they win over
        // the bulk rule, and they undercut GotPrint/HOTCARDS/360/Bizay/VistaPrint.
        // setHeadline keeps the genuine prior price as compare_at (real "X% OFF").
        foreach (self::AFFORDABLE as $slug) {
            $p = Product::where('slug', $slug)->first();
            if (! $p) {
                continue;
            }
            foreach (self::HEADLINE as $qty => $price) {
                if ($tier = $p->quantities()->where('quantity', $qty)->first()) {
                    $this->setHeadline($tier, $price);
                    $count++;
                }
            }
            // "From" price = cheapest orderable tier, so storefront / JSON-LD / feed agree.
            $p->update(['from_price' => (float) $p->quantities()->min('total_price')]);
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

    /** Set an exact selling price, keeping the genuine established price as compare_at ("was"). */
    private function setHeadline(ProductQuantity $q, float $price): void
    {
        $regular = max((float) ($q->compare_at_total ?? 0), (float) $q->total_price, $price);
        $q->compare_at_total = $regular > $price + 0.001 ? $regular : null;
        $q->total_price = $price;
        $q->unit_price = $q->quantity ? round($price / $q->quantity, 4) : $q->unit_price;
        $q->save();
    }
}
