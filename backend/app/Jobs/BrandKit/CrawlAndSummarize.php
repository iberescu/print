<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use App\Services\GeminiClient;
use App\Support\BrandKitSpec;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
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
    public int $timeout = 120;

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

        $text = $this->crawl($kit->website);
        $kit->update(['crawl_text' => Str::limit($text, 18000, '')]);

        $summary = [];
        try {
            $summary = $gemini->generateJson($this->summaryPrompt($kit, $text));
        } catch (\Throwable) {
            // fall through to a minimal summary below
        }
        $summary = $this->normalize($summary, $kit);

        $kit->update([
            'summary' => $summary,
            'company' => $kit->company ?: ($summary['company'] ?? null),
        ]);
        $kit->markStage('summary', 'done');

        // now that keywords/company exist, generate the display ads (need the logo)
        if ($this->logoInput($kit)) {
            $kit->markStage('ads', 'running');
            foreach (BrandKitSpec::ads() as $ad) {
                GenerateAdImage::dispatch($this->key, $ad);
            }
        } else {
            $kit->markStage('ads', 'skipped');
        }
    }

    /** Crawl the site: Cloudflare Browser Rendering (JS/SPA + bot-walls) first, plain fetch as fallback. */
    private function crawl(string $url): string
    {
        $markdown = $this->cloudflareMarkdown($url);
        if (mb_strlen(trim((string) $markdown)) >= 200) {
            return trim($markdown);
        }

        return $this->plainCrawl($url);
    }

    /** Render a URL to clean markdown via Cloudflare Browser Rendering (or null). */
    private function cloudflareMarkdown(string $url): ?string
    {
        $account = config('shop.cloudflare.account_id');
        $token = config('shop.cloudflare.browser_token');
        if (! $account || ! $token) {
            return null;
        }

        try {
            $r = Http::withToken($token)->timeout(35)->post(
                "https://api.cloudflare.com/client/v4/accounts/{$account}/browser-rendering/markdown",
                ['url' => $url],
            );
            if ($r->successful() && $r->json('success') && is_string($r->json('result'))) {
                return $r->json('result');
            }
        } catch (\Throwable) {
            // fall back to a plain fetch
        }

        return null;
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
            .'"keywords" (array of 6-10 relevant descriptive keywords), '
            .'"fonts" (array of 1-3 font families the site appears to use — best guess), '
            .'"colors" (array of 2-4 brand colours as hex codes or names), '
            .'"google_search_keywords" (array of EXACTLY 4 high-intent Google Search ad keyword phrases a '
            ."customer would type to find this business). Website text:\n\n".Str::limit($text, 12000, '');
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

        return [
            'company'                => $company,
            'description'            => (string) ($s['description'] ?? ''),
            'keywords'               => array_values(array_filter(array_map('trim', (array) ($s['keywords'] ?? [])))),
            'fonts'                  => array_values(array_filter(array_map('trim', (array) ($s['fonts'] ?? [])))),
            'colors'                 => array_values(array_filter(array_map('trim', (array) ($s['colors'] ?? [])))),
            'google_search_keywords' => array_slice($kw, 0, 4),
        ];
    }
}
