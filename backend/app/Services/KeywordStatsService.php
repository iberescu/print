<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Monthly-traffic stats for the capture's Google search keywords — feeds the
 * ads-step keyword report. Gemini estimates US monthly search volumes (its
 * prior is in line with what SEO tools report); every figure is labelled
 * ESTIMATED US traffic in the UI. Empty array on failure — the section simply
 * doesn't render.
 */
class KeywordStatsService
{
    /** @param string[] $keywords @return array<int,array<string,mixed>> */
    public function forKeywords(array $keywords): array
    {
        $keywords = array_values(array_filter(array_map('trim', $keywords), fn ($k) => $k !== ''));
        $keywords = array_slice($keywords, 0, 6);
        if (! $keywords) {
            return [];
        }

        try {
            $list = implode('", "', array_map(fn ($k) => str_replace('"', '', $k), $keywords));
            $r = app(GeminiClient::class)->generateJson(
                'Estimate the average MONTHLY Google search volume in the UNITED STATES for each of these '
                .'keywords, the way an SEO tool would report them. Be realistic: niche long-tail terms are '
                .'often 50-500/mo, mainstream commercial terms 1k-50k/mo. Respond ONLY JSON: '
                .'{"volumes": [{"keyword": "...", "monthly": 1234}, ...]} — one entry per keyword, same order. '
                ."Keywords: \"{$list}\"",
            );
            $rows = [];
            foreach ((array) ($r['volumes'] ?? []) as $row) {
                $kw = trim((string) ($row['keyword'] ?? ''));
                $v = (int) ($row['monthly'] ?? 0);
                if ($kw !== '' && $v > 0) {
                    $rows[] = ['keyword' => $kw, 'monthlySearches' => max(60, min(60000, $v)), 'source' => 'estimate'];
                }
            }
            if (! $rows) {
                Log::warning('keyword-stats: 0 usable rows; raw: '.substr(json_encode($r), 0, 300));
            }

            return $rows;
        } catch (\Throwable $e) {
            Log::info('keyword-stats gemini: '.$e->getMessage());

            return [];
        }
    }
}
