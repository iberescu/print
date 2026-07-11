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
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrates the in-house brand kit. Runs fast: extracts the logo/brand from an
 * uploaded image/PDF when needed, then fans out the heavy work as independent
 * parallel jobs — product mockups start immediately (logo only), while crawl +
 * summary + display ads run concurrently. Nothing waits on the crawl.
 *
 * Fallbacks: no logo but a source image/preview → isolate a logo from it; no
 * website → skip crawl, build a company-based summary; no logo → skip products.
 */
class BuildBrandKit implements ShouldQueue
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
        if (! $kit) {
            return;
        }
        $kit->update(['status' => 'processing']);

        // 1. Extraction — only for image/pdf captures that lack a clean logo.
        if (! $kit->logo_path && ! $kit->logo_url && $kit->source_file) {
            $this->extract($gemini, $kit);
            $kit->refresh();
        }

        // Upscale low-res logos + normalise aspect so mockups are crisp and square.
        if ($kit->logo_path || $kit->logo_url) {
            $this->enhanceLogo($gemini, $kit);
            $kit->refresh();
        }

        $hasLogo = (bool) ($kit->logo_path || $kit->logo_url);
        $hasUrl = (bool) $kit->website;

        // 2. Product mockups — need only the logo, start now (parallel).
        if ($hasLogo) {
            $kit->markStage('products', 'running');
            foreach (BrandKitSpec::products() as $p) {
                GenerateProductImage::dispatch($this->key, $p);
            }
        } else {
            $kit->markStage('products', 'skipped');
        }

        // 3. Crawl + summary + ads.
        if ($hasUrl) {
            CrawlAndSummarize::dispatch($this->key); // fans out the ad jobs itself
        } else {
            // No URL: company-based summary, and ads straight away if we have a logo.
            $company = (string) ($kit->company ?? '');
            $concepts = BrandKitSpec::ads();
            $kit->update(['summary' => [
                'company'                => $company,
                'description'            => $company ? "{$company} — custom print and promotional products." : '',
                'keywords'               => array_values(array_filter([$company])),
                'fonts'                  => [],
                'colors'                 => [],
                'google_search_keywords' => $this->fallbackKeywords($company),
                'ad_concepts'            => $concepts,
            ]]);
            $kit->markStage('summary', $company ? 'done' : 'skipped');

            if ($hasLogo) {
                $kit->markStage('ads', 'running');
                foreach (array_values($concepts) as $i => $concept) {
                    GenerateAdImage::dispatch($this->key, ['key' => 'ad'.$i] + $concept);
                }
            } else {
                $kit->markStage('ads', 'skipped');
            }
        }

        if (! $hasLogo && ! $hasUrl) {
            $kit->update(['status' => 'failed']);
        }
    }

    /** Pull company/website out of an uploaded image/PDF page and isolate a logo. */
    private function extract(GeminiClient $gemini, BrandKit $kit): void
    {
        $src = $this->imageInput($kit->source_file);
        if (! $src) {
            return;
        }
        $kit->markStage('extract', 'running');

        try {
            $info = $gemini->inspectImage(
                'This is a business card / brand artwork. Read ALL text on it carefully. Return JSON: '
                .'"company" (the brand/business name — usually the most prominent wordmark or the name shown '
                .'in the logo; extract it even if it is also a common English word), '
                .'"website" (ANY web address on the card — a www. or https:// string or a bare domain like '
                .'example.com or example.io; return just the host), '
                .'"tagline", "email", "phone". Use null ONLY when a field is genuinely absent.',
                $src,
            );
            $kit->update([
                'company' => $kit->company ?: ($info['company'] ?? null),
                'website' => $kit->website ?: ($this->normalizeUrl($info['website'] ?? null)),
                'extract' => $info,
            ]);
        } catch (\Throwable) {
            // non-fatal
        }

        try {
            $iso = $gemini->generateImage(
                'Extract and isolate ONLY the primary logo / brand mark from this artwork. Reproduce it '
                .'EXACTLY as it appears — same shapes, letters and colours — centred on a plain solid white '
                .'background, with nothing else around it (no card, no contact details, no extra text). '
                .'Tight crop with a little padding.',
                [$src],
                config('shop.internal_engine.image_model'),
            );
            $path = "brandkits/{$this->key}/logo.webp";
            Storage::disk('public')->put($path, Img::webp($iso['data'], 800));
            $kit->update(['logo_path' => $path, 'logo_url' => Storage::disk('public')->url($path)]);
        } catch (\Throwable) {
            // non-fatal — product stage will be skipped if still no logo
        }

        $kit->markStage('extract', 'done');
    }

    /**
     * Make the logo production-ready: upscale it via Gemini when it's low-res
     * (kept pixel-faithful — no restyle), then centre it on a square canvas so
     * downstream mockups don't inherit a wide wordmark's aspect ratio.
     */
    private function enhanceLogo(GeminiClient $gemini, BrandKit $kit): void
    {
        $input = $this->logoInput($kit);
        if (! $input) {
            return;
        }
        $bytes = base64_decode($input['data']);
        $dims = @getimagesizefromstring($bytes) ?: [0, 0];

        if ($dims[0] > 0 && max($dims[0], $dims[1]) < 512) {
            try {
                $up = $gemini->generateImage(
                    'Upscale this logo to a high-resolution version. Reproduce it PIXEL-FAITHFULLY — the exact '
                    .'same shapes, letters, colours, spacing and proportions — only sharper and larger. Do NOT '
                    .'restyle, recolour, add, remove, crop or redraw anything. Plain solid white background.',
                    [$input],
                    config('shop.internal_engine.image_model'),
                );
                $bytes = $up['data'];
            } catch (\Throwable) {
                // keep the original bytes
            }
        }

        $path = "brandkits/{$this->key}/logo-hd.webp";
        Storage::disk('public')->put($path, Img::square($bytes, 1024));
        $kit->update(['logo_path' => $path, 'logo_url' => Storage::disk('public')->url($path)]);
    }

    /** @return array<int,string> */
    private function fallbackKeywords(string $company): array
    {
        $base = trim($company) ?: 'custom print';

        return [$base, "$base near me", "$base online", "$base products"];
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || preg_match('/^(www\.)?yourcompany\.com$/i', $url)) {
            return null;
        }

        return preg_match('#^https?://#i', $url) ? $url : "https://{$url}";
    }
}
