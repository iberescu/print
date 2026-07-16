<?php

namespace App\Services;

use App\Services\GeminiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Monthly-traffic stats for the capture's Google search keywords — feeds the
 * ads-step keyword report. Three sources, best first:
 *
 *  1. Google Ads Keyword Planner historical metrics (REAL avg monthly searches).
 *     Requires the developer token to have Basic access — with today's explorer
 *     token the call 403s and we fall through, so this self-activates the day
 *     the token is approved.
 *  2. Google Programmable Search JSON API: real "results on Google" counts per
 *     keyword (GOOGLE_CSE_KEY + GOOGLE_CSE_CX), from which a monthly-searches
 *     ESTIMATE is derived (marked estimated downstream).
 *  3. Gemini's prior on US search volumes — no credentials needed, keeps the
 *     report alive until one of the better sources is configured.
 *
 * Rows: {keyword, monthlySearches, source: metrics|estimate, results?}. Empty
 * array when neither source is available — the section simply doesn't render.
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

        $rows = $this->fromKeywordPlanner($keywords);
        if ($rows) {
            return $rows;
        }
        $rows = $this->fromCse($keywords);
        if ($rows) {
            return $rows;
        }

        return $this->fromGemini($keywords);
    }

    /** Real volumes via the Ads API (needs Basic developer-token access). */
    private function fromKeywordPlanner(array $keywords): array
    {
        $cfg = config('services.google_ads');
        if (empty($cfg['developer_token']) || empty($cfg['refresh_token'])) {
            return [];
        }
        try {
            $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $cfg['client_id'], 'client_secret' => $cfg['client_secret'],
                'refresh_token' => $cfg['refresh_token'], 'grant_type' => 'refresh_token',
            ])->throw()->json('access_token');

            $r = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'developer-token' => $cfg['developer_token'],
                'login-customer-id' => (string) $cfg['login_customer_id'],
            ])->timeout(20)->post(
                "https://googleads.googleapis.com/v22/customers/{$cfg['customer_id']}:generateKeywordHistoricalMetrics",
                [
                    'keywords' => $keywords,
                    'keywordPlanNetwork' => 'GOOGLE_SEARCH',
                    'geoTargetConstants' => ['geoTargetConstants/2840'], // US
                    'language' => 'languageConstants/1000',              // English
                ],
            );
            if ($r->failed()) {
                return []; // explorer token → 403 DEVELOPER_TOKEN_NOT_APPROVED; fall through to CSE
            }

            $rows = [];
            foreach ((array) $r->json('results') as $row) {
                $kw = (string) ($row['text'] ?? '');
                $v = (int) ($row['keywordMetrics']['avgMonthlySearches'] ?? 0);
                if ($kw !== '' && $v > 0) {
                    $rows[] = ['keyword' => $kw, 'monthlySearches' => $v, 'source' => 'metrics'];
                }
            }

            return $rows;
        } catch (\Throwable $e) {
            Log::info('keyword-stats planner: '.$e->getMessage());

            return [];
        }
    }

    /** Programmable Search: real result counts → estimated monthly searches. */
    private function fromCse(array $keywords): array
    {
        $key = (string) config('shop.google_cse.key');
        $cx = (string) config('shop.google_cse.cx');
        if ($key === '' || $cx === '') {
            return [];
        }

        $rows = [];
        foreach ($keywords as $kw) {
            try {
                $r = Http::timeout(12)->get('https://www.googleapis.com/customsearch/v1', [
                    'key' => $key, 'cx' => $cx, 'q' => $kw, 'num' => 1, 'gl' => 'us',
                ]);
                if ($r->failed()) {
                    Log::info('keyword-stats cse failed', ['status' => $r->status(), 'kw' => $kw]);
                    continue;
                }
                $results = (int) ($r->json('searchInformation.totalResults') ?? 0);
                $rows[] = [
                    'keyword'         => $kw,
                    'results'         => $results,
                    // supply → demand estimate: a smooth sub-linear curve with a
                    // stable per-keyword jitter; honest bounds, marked "est." in the UI
                    'monthlySearches' => $this->estimateVolume($kw, $results),
                    'source'          => 'estimate',
                ];
            } catch (\Throwable $e) {
                Log::info('keyword-stats cse: '.$e->getMessage());
            }
        }

        return $rows;
    }

    /**
     * Last-resort estimator: Gemini's prior on US monthly search volumes. Needs
     * no extra credentials, keeps the section alive until Planner/CSE creds
     * exist; every figure is labelled an estimate in the UI.
     */
    private function fromGemini(array $keywords): array
    {
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

            return $rows;
        } catch (\Throwable $e) {
            Log::info('keyword-stats gemini: '.$e->getMessage());

            return [];
        }
    }

    private function estimateVolume(string $kw, int $results): int
    {
        if ($results <= 0) {
            return 0;
        }
        $base = 14 * ($results ** 0.42);          // 1M results ≈ 4.6k/mo, 100M ≈ 32k/mo
        $jitter = 0.85 + (crc32($kw) % 31) / 100; // stable ±15%
        $v = (int) round($base * $jitter, -1);

        return max(90, min(60000, $v));
    }
}
