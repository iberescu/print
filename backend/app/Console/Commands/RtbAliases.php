<?php

namespace App\Console\Commands;

use App\Models\BrandStore;
use App\Models\BrandStoreAlias;
use Illuminate\Console\Command;

/**
 * Top the RTB House alias pool up to N random shop subdomains. The names go
 * into the store product feed (read once per day), so the pool must be
 * provisioned BEFORE stores need it — run once after deploy, re-run any time
 * (idempotent; only adds what's missing).
 */
class RtbAliases extends Command
{
    protected $signature = 'rtb:aliases {--count=100 : pool size to top up to}';

    protected $description = 'Provision the RTB House brand-store alias pool';

    private const ADJ = [
        'amber', 'aqua', 'bold', 'bright', 'brisk', 'calm', 'cedar', 'clear', 'coral', 'crisp',
        'dawn', 'dusk', 'eager', 'fable', 'fern', 'flint', 'gold', 'hazel', 'ivory', 'jade',
        'keen', 'lively', 'lunar', 'maple', 'mellow', 'noble', 'north', 'ocean', 'olive', 'opal',
        'pearl', 'pine', 'plume', 'prime', 'quiet', 'rapid', 'royal', 'sage', 'solar', 'sunny',
        'swift', 'terra', 'tidal', 'topaz', 'umber', 'vivid', 'warm', 'wild', 'winter', 'zesty',
    ];

    private const NOUN = [
        'anchor', 'aspen', 'badger', 'beacon', 'birch', 'bison', 'breeze', 'brook', 'canyon', 'cliff',
        'comet', 'cove', 'crane', 'creek', 'delta', 'ember', 'falcon', 'fjord', 'garnet', 'glade',
        'grove', 'harbor', 'hawk', 'heron', 'lark', 'lynx', 'meadow', 'mesa', 'otter', 'peak',
        'pond', 'prairie', 'quartz', 'raven', 'reef', 'ridge', 'river', 'sparrow', 'summit', 'trail',
    ];

    public function handle(): int
    {
        $target = max(1, (int) $this->option('count'));
        $have = BrandStoreAlias::count();

        $made = 0;
        while ($have + $made < $target) {
            $alias = self::ADJ[random_int(0, count(self::ADJ) - 1)]
                .'-'.self::NOUN[random_int(0, count(self::NOUN) - 1)]
                .'-'.random_int(10, 99);
            if (BrandStoreAlias::where('alias', $alias)->exists() || BrandStore::where('subdomain', $alias)->exists()) {
                continue; // collision — roll again
            }
            BrandStoreAlias::create(['alias' => $alias]);
            $made++;
        }

        $free = BrandStoreAlias::whereNull('brand_store_id')->count();
        $this->info("pool: ".($have + $made)." aliases ({$made} new, {$free} free)");

        return self::SUCCESS;
    }
}
