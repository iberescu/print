<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SpyFu competitors API — top SEO+PPC competitors for a domain, feeding the
 * ads-step market simulation. Auth is the API pair from the SpyFu account
 * (an API ID GUID + secret key — NOT the site login; the API also requires a
 * plan with API access). Missing credentials or any API failure degrade to []
 * so the upsell page simply doesn't show the section.
 */
class SpyfuClient
{
    private const URL = 'https://api.spyfu.com/apis/competitors_api/v2/combined/getCombinedTopCompetitors';

    /** @return array<int,array<string,mixed>> compact rows: {domain, ...numeric metrics} */
    public function topCompetitors(string $domain): array
    {
        $id = (string) config('shop.spyfu.api_id');
        $secret = (string) config('shop.spyfu.secret_key');
        if ($id === '' || $secret === '') {
            return [];
        }

        try {
            $r = Http::withBasicAuth($id, $secret)->timeout(20)->get(self::URL, [
                'domain'     => $domain,
                'pageSize'   => 8,
                'countryCode' => 'US',
            ]);
            if ($r->failed()) {
                Log::info('spyfu: request failed', ['status' => $r->status(), 'domain' => $domain]);

                return [];
            }
            $json = $r->json();
        } catch (\Throwable $e) {
            Log::info('spyfu: '.$e->getMessage());

            return [];
        }

        // Tolerant normalization — the row container and field names are mapped
        // loosely so a schema drift degrades gracefully instead of breaking.
        $rows = null;
        foreach (['results', 'competitors', 'topCompetitors', 'data'] as $k) {
            if (is_array($json[$k] ?? null)) {
                $rows = $json[$k];
                break;
            }
        }
        if ($rows === null && is_array($json) && array_is_list($json)) {
            $rows = $json;
        }
        if (! $rows) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = [];
            foreach ($row as $k => $v) {
                $lk = strtolower((string) $k);
                if ($item === [] && in_array($lk, ['domain', 'domainname', 'website', 'name'], true) && is_string($v)) {
                    $item['domain'] = strtolower(preg_replace('#^https?://(www\.)?#', '', trim($v)));
                }
                if (is_numeric($v)) {
                    if (str_contains($lk, 'organic') || str_contains($lk, 'seo')) {
                        $item['seoClicks'] = max($item['seoClicks'] ?? 0, (float) $v);
                    } elseif (str_contains($lk, 'paid') || str_contains($lk, 'ppc') || str_contains($lk, 'adclick')) {
                        $item['ppcClicks'] = max($item['ppcClicks'] ?? 0, (float) $v);
                    } elseif (str_contains($lk, 'keyword') || str_contains($lk, 'terms')) {
                        $item['keywords'] = max($item['keywords'] ?? 0, (float) $v);
                    } elseif (str_contains($lk, 'budget') || str_contains($lk, 'spend') || str_contains($lk, 'cost')) {
                        $item['budget'] = max($item['budget'] ?? 0, (float) $v);
                    }
                }
            }
            if (! empty($item['domain'])) {
                $out[] = $item;
            }
        }

        if ($out === []) {
            // shape unknown — log the first row's keys once so we can refine the mapping
            Log::info('spyfu: unrecognized row shape', ['keys' => array_keys((array) ($rows[0] ?? []))]);
        }

        return array_slice($out, 0, 8);
    }
}
