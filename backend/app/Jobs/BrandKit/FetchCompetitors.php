<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use App\Services\SpyfuClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Top competitors for a URL capture (SpyFu) — powers the "your market, live"
 * simulation on the Layout.ai ads step. Background like everything else in the
 * upsell; no data (or no API plan) just means the section stays hidden.
 */
class FetchCompetitors implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public string $key)
    {
        $this->onQueue('brandkit');
    }

    public function handle(SpyfuClient $spyfu): void
    {
        $kit = BrandKit::where('key', $this->key)->first();
        if (! $kit || ! $kit->website || $kit->competitors) {
            return;
        }
        $domain = strtolower(parse_url($kit->website, PHP_URL_HOST) ?: $kit->website);
        $domain = preg_replace('/^www\./', '', $domain);
        if (! $domain || ! str_contains($domain, '.')) {
            return;
        }

        $rows = $spyfu->topCompetitors($domain);
        if ($rows) {
            $kit->update(['competitors' => $rows]);
        }
    }
}
