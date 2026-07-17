<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use App\Services\GeminiClient;
use App\Support\BrandKitSpec;
use App\Support\Img;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Crawl 1–2 pages of the buyer's website, store the text, and ask Gemini for a
 * brand summary (description, keywords, fonts, colours, 4 Google search-ad
 * keywords). Then fans out the display-ad jobs. Runs in parallel with the
 * product-image jobs — it never blocks them.
 */
class CrawlAndSummarize implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReadsImages, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public string $key)
    {
        $this->onQueue('brandkit');
    }

    public function handle(GeminiClient $gemini): void
    {
        $kit = BrandKit::where('key', $this->key)->first();
        if (! $kit || ! $kit->website) {
            return;
        }

        $kit->markStage('summary', 'running');

        // ONE Cloudflare Browser Rendering call renders the page WITH css/images/js and
        // returns BOTH the page content and a full-page screenshot — a single call avoids
        // the browser-rendering rate limit that two back-to-back calls tripped.
        [$text, $shot] = $this->snapshot($kit->website);
        if (trim($text) === '') {
            $text = $this->plainCrawl($kit->website); // snapshot unavailable → server-side fetch
        }
        $kit->update(['crawl_text' => Str::limit($text, 18000, '')]);

        if ($shot) {
            $shotPath = "brandkits/{$this->key}/site-shot.webp";
            Storage::disk('public')->put($shotPath, Img::webp($shot, 1280));
            $kit->update(['site_shot_path' => $shotPath]);
            $kit->refresh();
        }

        // Summarise the crawl into a description + tailored ad concepts. Retry a few
        // times when the model returns nothing usable: a single transient/empty response
        // would otherwise drop the whole kit to generic ad copy ("Trusted by
        // professionals") even though the crawl itself succeeded.
        $summary = [];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $summary = $gemini->generateJson($this->summaryPrompt($kit, $text));
            } catch (\Throwable) {
                $summary = [];
            }
            if (trim((string) ($summary['description'] ?? '')) !== '' || ! empty($summary['ad_concepts'])) {
                break;
            }
        }
        $summary = $this->normalize($summary, $kit);

        $kit->update([
            'summary' => $summary,
            'company' => $kit->company ?: ($summary['company'] ?? null),
        ]);
        $kit->markStage('summary', 'done');

        // keyword traffic stats for the ads-step report (needs the keywords above)
        FetchKeywordStats::dispatch($this->key);

        // crawl done → private brand store (if the mockups already finished first)
        CreateBrandStore::consider($this->key);

        // now that the brand summary exists, generate display ads from its tailored
        // concepts (need the logo). Each ad job also reads the summary for context.
        if ($this->logoInput($kit)) {
            $kit->markStage('ads', 'running');
            foreach (array_values($summary['ad_concepts'] ?? []) as $i => $concept) {
                $spec = ['key' => 'ad'.$i] + $concept;
                // the 2nd and 4th ads mirror the brand's real website — hand them the screenshot
                if (in_array($i, [1, 3], true) && $kit->site_shot_path) {
                    $spec['use_site_shot'] = true;
                }
                GenerateAdImage::dispatch($this->key, $spec);
            }
            // Products whose scene needs the crawl keywords (word-cloud) — dispatch now
            // that the summary exists, so they don't fall back to the company name.
            foreach (BrandKitSpec::products() as $p) {
                if (BrandKitSpec::needsSummary($p)) {
                    GenerateProductImage::dispatch($this->key, $p);
                }
            }
        } else {
            $kit->markStage('ads', 'skipped');
        }
    }

    /**
     * ONE Cloudflare Browser Rendering call: render the page WITH css/images/js and return
     * BOTH the visible text and a full-PAGE (scrolled) screenshot. A single call keeps us
     * under the browser-rendering rate limit that two back-to-back calls (markdown then
     * screenshot) tripped. The screenshot is the style/imagery reference for the ad.
     *
     * @return array{0:string,1:?string} [visible text, PNG bytes|null]
     */
    private function snapshot(string $url): array
    {
        $account = config('shop.cloudflare.account_id');
        $token = config('shop.cloudflare.browser_token');
        if (! $account || ! $token) {
            return ['', null];
        }

        try {
            $r = Http::withToken($token)->timeout(70)->post(
                "https://api.cloudflare.com/client/v4/accounts/{$account}/browser-rendering/snapshot",
                [
                    'url'               => $url,
                    'viewport'          => ['width' => 1280, 'height' => 900],
                    'screenshotOptions' => ['type' => 'png', 'fullPage' => true],
                    'gotoOptions'       => ['waitUntil' => 'networkidle2', 'timeout' => 30000],
                ],
            );
            if ($r->successful() && $r->json('success')) {
                $html = (string) $r->json('result.content');
                $b64 = (string) $r->json('result.screenshot');
                $shot = $b64 !== '' ? base64_decode($b64) : null;

                return [$this->htmlToText($html), $shot ?: null];
            }
        } catch (\Throwable) {
            // best effort — fall back to a plain server-side fetch for text, no screenshot
        }

        return ['', null];
    }

    /** Fallback: fetch the homepage + one internal page server-side, return collapsed visible text. */
    private function plainCrawl(string $url): string
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36';
        $get = fn (string $u) => rescue(fn () => Http::withHeaders(['User-Agent' => $ua, 'Accept' => 'text/html'])
            ->timeout(12)->get($u), null);

        $home = $get($url);
        $html = ($home && $home->successful()) ? $home->body() : '';
        $text = $this->htmlToText($html);

        // one more page: first internal link that looks like about/services/products
        foreach ($this->internalLinks($html, $url) as $link) {
            $more = $get($link);
            if ($more && $more->successful()) {
                $text .= "\n\n".$this->htmlToText($more->body());
            }
            break;
        }

        return trim($text);
    }

    /** @return array<int,string> */
    private function internalLinks(string $html, string $base): array
    {
        $host = parse_url($base, PHP_URL_HOST);
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $m);
        $wanted = [];
        foreach ($m[1] ?? [] as $href) {
            if (! preg_match('/(about|service|product|shop|company|work)/i', $href)) {
                continue;
            }
            $abs = str_starts_with($href, 'http') ? $href : rtrim($base, '/').'/'.ltrim($href, '/');
            if (parse_url($abs, PHP_URL_HOST) === $host) {
                $wanted[] = $abs;
            }
        }

        return array_values(array_unique($wanted));
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|svg)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function summaryPrompt(BrandKit $kit, string $text): string
    {
        $company = $kit->company ? "The company appears to be \"{$kit->company}\". " : '';
        $domain = parse_url($kit->website, PHP_URL_HOST) ?: $kit->website;
        $adCount = (int) config('shop.internal_engine.max_ads', 0) ?: 6;
        $thin = strlen(trim($text)) < 200
            ? 'The crawled text is sparse (likely a JS-rendered or protected site), so INFER what the '
                ."business does from its name and its domain \"{$domain}\" and produce your best-guess "
                .'keywords and description anyway — never leave them blank. '
            : '';

        return "You are a brand strategist. {$company}Below is text crawled from the website "
            ."{$kit->website} (domain {$domain}). {$thin}Produce a JSON object describing the brand for an "
            .'advertising campaign, with EXACTLY these keys: '
            .'"company" (string, the business name), '
            .'"description" (2-3 sentence plain-English summary of what they do), '
            .'"keywords" (array of 20-30 short keywords or phrases describing the business, its products, '
            .'services and industry — these fill a brand word-cloud, so make them specific and varied), '
            .'"fonts" (array of 1-3 font families the site appears to use — best guess), '
            .'"colors" (array of 2-4 brand colours as hex codes or names), '
            .'"google_search_keywords" (array of EXACTLY 4 high-intent Google Search ad keyword phrases a '
            .'customer would type to find this business), '
            ."\"ad_concepts\" (array of {$adCount} Google DISPLAY ad concepts tailored to THIS specific business — each "
            .'an object with "headline" (max 6 words, benefit-driven and specific to what they actually do; '
            .'NOT generic retail copy like "shop the collection") and "cta" (a 2-3 word button label that '
            .'fits the business, e.g. "Get started", "Book a demo", "Try it free", "Get a quote", "Learn '
            .'more"; use "Shop now" ONLY for an actual online shop that sells products). '
            ."Website text:\n\n".Str::limit($text, 12000, '');
    }

    /** Guarantee the shape/counts the storefront relies on, with sensible fallbacks. */
    private function normalize(array $s, BrandKit $kit): array
    {
        $company = trim((string) ($s['company'] ?? $kit->company ?? ''));
        $kw = array_values(array_filter(array_map('trim', (array) ($s['google_search_keywords'] ?? []))));
        if (count($kw) < 4) {
            $base = $company ?: 'custom print';
            foreach (["$base", "$base near me", "$base online", "$base services"] as $fallback) {
                if (count($kw) >= 4) {
                    break;
                }
                if (! in_array($fallback, $kw, true)) {
                    $kw[] = $fallback;
                }
            }
        }

        // Tailored display-ad concepts (headline + cta); fall back to neutral ones.
        $concepts = [];
        foreach ((array) ($s['ad_concepts'] ?? []) as $c) {
            $h = trim((string) ($c['headline'] ?? ''));
            if ($h !== '') {
                $concepts[] = ['headline' => $h, 'cta' => trim((string) ($c['cta'] ?? '')) ?: 'Learn more'];
            }
        }
        if (count($concepts) < 2) {
            $concepts = BrandKitSpec::ads();
        }
        $adCap = (int) config('shop.internal_engine.max_ads', 0);
        if ($adCap > 0) {
            $concepts = array_slice($concepts, 0, $adCap);
        }

        return [
            'company'                => $company,
            'description'            => (string) ($s['description'] ?? ''),
            'keywords'               => array_values(array_filter(array_map('trim', (array) ($s['keywords'] ?? [])))),
            'fonts'                  => array_values(array_filter(array_map('trim', (array) ($s['fonts'] ?? [])))),
            'colors'                 => array_values(array_filter(array_map('trim', (array) ($s['colors'] ?? [])))),
            'google_search_keywords' => array_slice($kw, 0, 4),
            'ad_concepts'            => array_values($concepts),
        ];
    }
}
