<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use App\Services\KeywordStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Monthly-traffic stats for the capture's crawl keywords — powers the ads-step
 * keyword report. Dispatched by CrawlAndSummarize once the summary (and thus
 * google_search_keywords) exists. Background; no data → section stays hidden.
 */
class FetchKeywordStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct(public string $key)
    {
        $this->onQueue('brandkit');
    }

    public function handle(KeywordStatsService $stats): void
    {
        $kit = BrandKit::where('key', $this->key)->first();
        if (! $kit || $kit->keyword_stats) {
            return;
        }
        $keywords = array_values(array_filter((array) ($kit->summary['google_search_keywords'] ?? [])));
        if (! $keywords) {
            return;
        }

        $rows = $stats->forKeywords($keywords);
        if ($rows) {
            $kit->update(['keyword_stats' => $rows]);

            return;
        }

        // A transient Gemini flake returns zero usable rows — retry instead of
        // silently leaving this capture without its traffic report.
        \Illuminate\Support\Facades\Log::warning("keyword-stats: empty result for kit {$kit->id} (attempt {$this->attempts()})");
        if ($this->attempts() < $this->tries) {
            $this->release(30);
        }
    }
}
