<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Run (or end) a REAL percentage sale on a category. It genuinely lowers every
 * quantity tier's price — so customers actually pay less at checkout — while
 * capturing the pre-sale price in compare_at_total. The Google Shopping feed
 * (GoogleShoppingFeed) then advertises price = regular, sale_price = discounted,
 * i.e. an honest "X% OFF" backed by a real markdown.
 *
 *   php artisan shop:sale --category=business-cards --percent=25   # start/refresh
 *   php artisan shop:sale --category=business-cards --off          # end, restore prices
 *
 * Idempotent: the regular price is always taken from compare_at_total when set,
 * so re-running never compounds the discount.
 */
class RunSale extends Command
{
    protected $signature = 'shop:sale {--category=} {--percent=25} {--off}';

    protected $description = 'Run or end a real % sale on a category (discounts every tier, records regular price in compare_at)';

    public function handle(): int
    {
        $cat = (string) $this->option('category');
        if ($cat === '') {
            $this->error('--category is required (e.g. --category=business-cards)');

            return self::FAILURE;
        }
        $off = (bool) $this->option('off');
        $pct = (float) $this->option('percent');
        if (! $off && ($pct <= 0 || $pct >= 100)) {
            $this->error('--percent must be between 0 and 100');

            return self::FAILURE;
        }

        $products = Product::with('quantities')
            ->whereHas('category', fn ($q) => $q->where('slug', $cat))
            ->where('is_active', true)->get();
        if ($products->isEmpty()) {
            $this->warn("No active products in category '{$cat}'.");

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($products as $p) {
            foreach ($p->quantities as $q) {
                if ($off) {
                    // restore the regular price, drop the sale marker
                    if ($q->compare_at_total !== null) {
                        $regular = (float) $q->compare_at_total;
                        $q->total_price = $regular;
                        $q->unit_price = $q->quantity ? round($regular / $q->quantity, 4) : $q->unit_price;
                        $q->compare_at_total = null;
                        $q->save();
                    }

                    continue;
                }

                // regular = the genuine pre-sale price (compare_at if already on sale, else current)
                $regular = $q->compare_at_total !== null ? (float) $q->compare_at_total : (float) $q->total_price;
                $sale = round($regular * (1 - $pct / 100), 2);
                $q->compare_at_total = $regular;
                $q->total_price = $sale;
                $q->unit_price = $q->quantity ? round($sale / $q->quantity, 4) : $q->unit_price;
                $q->save();
            }

            // "From" price follows the cheapest tier a buyer can order (the sale price)
            $p->update(['from_price' => (float) $p->quantities()->min('total_price')]);
            $n++;
        }

        $this->info($off
            ? "Ended the sale on {$n} products in '{$cat}' (prices restored)."
            : "Applied {$pct}% off {$n} products in '{$cat}'.");

        return self::SUCCESS;
    }
}
