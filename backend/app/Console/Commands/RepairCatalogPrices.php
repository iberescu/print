<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Repair crawled tier pricing where the import's per-unit heuristic misfired
 * (a junk trailing tier like "10000 → $1.00" made a rising TOTAL sequence look
 * falling, so every total got multiplied by its quantity: letterhead 25 sheets
 * = $749.75 instead of $29.99). Detection: unit prices that RISE strongly with
 * quantity — real unit prices fall. Repair: the stored unit IS the true tier
 * total (that's what the crawler captured); junk tiers that undercut the
 * running total are dropped.
 */
class RepairCatalogPrices extends Command
{
    protected $signature = 'catalog:repair-prices {--dry : report only}';

    protected $description = 'Fix crawled products whose tier totals were stored as unit prices';

    public function handle(): int
    {
        $fixed = 0;
        foreach (Product::with('quantities')->get() as $p) {
            $tiers = $p->quantities->sortBy('quantity')->values();
            if ($tiers->count() < 2 || ! $this->looksSwapped($tiers)) {
                continue;
            }

            $this->line(($this->option('dry') ? '[dry] ' : '')."{$p->slug}:");
            $maxTotal = 0.0;
            foreach ($tiers as $q) {
                $trueTotal = (float) $q->unit_price; // the crawler's captured tier TOTAL
                if ($trueTotal <= $maxTotal * 0.5) {
                    // junk tier (the "$1.00" placeholders that poisoned the import)
                    $this->line("  drop {$q->quantity} (\${$trueTotal})");
                    if (! $this->option('dry')) {
                        $q->delete();
                    }

                    continue;
                }
                $maxTotal = max($maxTotal, $trueTotal);
                $this->line("  {$q->quantity} units: \${$q->total_price} → \${$trueTotal}");
                if (! $this->option('dry')) {
                    $q->update(['total_price' => round($trueTotal, 2), 'unit_price' => round($trueTotal / max(1, $q->quantity), 4)]);
                }
            }

            if (! $this->option('dry')) {
                $p->load('quantities');
                if (! $p->quantities->firstWhere('is_default', true)) {
                    $p->quantities->sortBy('quantity')->first()?->update(['is_default' => true]);
                }
                $def = $p->quantities->firstWhere('is_default', true) ?? $p->quantities->sortBy('quantity')->first();
                $p->update(['from_price' => (float) ($def?->total_price ?? $p->from_price)]);
                $this->info("  from_price → \${$p->from_price}");
            }
            $fixed++;
        }

        $this->info(($this->option('dry') ? 'would fix ' : 'fixed ')."{$fixed} products");

        return self::SUCCESS;
    }

    /** Real unit prices FALL as quantity rises; a strongly rising sequence means
     *  totals were stored in the unit column. Majority vote across adjacent
     *  pairs, ignoring junk values ≤ $1 (the poison that fooled the import). */
    private function looksSwapped($tiers): bool
    {
        $units = $tiers->pluck('unit_price')->map(fn ($v) => (float) $v)
            ->filter(fn ($v) => $v > 1.0)->values();
        if ($units->count() < 2) {
            return false;
        }
        $rises = 0;
        $falls = 0;
        for ($i = 1; $i < $units->count(); $i++) {
            $units[$i] > $units[$i - 1] ? $rises++ : $falls++;
        }

        // max (not last): the junk tail that caused the corruption sits at the end
        return $rises > $falls && $units->max() >= $units->first() * 2;
    }
}
