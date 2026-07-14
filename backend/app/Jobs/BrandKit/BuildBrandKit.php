<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use App\Services\GeminiClient;
use App\Services\ReplicateClient;
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

    public function handle(GeminiClient $gemini, ReplicateClient $replicate): void
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

        // Keep the original, size the working logo sanely, super-resolve only if tiny.
        if ($kit->logo_path || $kit->logo_url) {
            $this->enhanceLogo($replicate, $kit);
            $kit->refresh();
        }

        $hasLogo = (bool) ($kit->logo_path || $kit->logo_url);
        $hasUrl = (bool) $kit->website;

        // QR builder flow: "your [logo +] QR on products". Products only — the paper
        // set carries the QR (plus the logo when there is one), merch carries the
        // logo. No crawl/summary/ads (the gallery only shows the product mockups).
        if ($kit->qr_path) {
            $kit->markStage('products', 'running');
            foreach (BrandKitSpec::qrProducts($hasLogo) as $p) {
                GenerateProductImage::dispatch($this->key, $p);
            }
            $kit->markStage('summary', 'skipped');
            $kit->markStage('ads', 'skipped');

            return;
        }

        // 2. Product mockups — need only the logo, start now (parallel).
        if ($hasLogo) {
            $kit->markStage('products', 'running');
            foreach (BrandKitSpec::products() as $p) {
                if (BrandKitSpec::needsSummary($p)) {
                    continue; // word-cloud etc. — dispatched after the summary/keywords exist
                }
                GenerateProductImage::dispatch($this->key, $p);
            }
        } else {
            $kit->markStage('products', 'skipped');
        }

        // 2b. QR-code business card (designer/upload flows with a logo + website): build
        // the QR ourselves (website, rounded, black, logo in the centre) and let Gemini
        // place it, unmodified, on the card back. Skipped for the QR-maker flow (qr_path).
        if ($hasLogo && $hasUrl && ! $kit->qr_path) {
            $this->buildQrBusinessCard($kit);
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
                // keyword-dependent products (word-cloud) now that the summary exists
                foreach (BrandKitSpec::products() as $p) {
                    if (BrandKitSpec::needsSummary($p)) {
                        GenerateProductImage::dispatch($this->key, $p);
                    }
                }
            } else {
                $kit->markStage('ads', 'skipped');
            }
        }

        if (! $hasLogo && ! $hasUrl) {
            $kit->update(['status' => 'failed']);
        }
    }

    /** Build the QR (website + centre logo, rounded/black) and queue the QR business card. */
    private function buildQrBusinessCard(BrandKit $kit): void
    {
        try {
            $disk = Storage::disk('public');
            $logoFile = $kit->logo_path && $disk->exists($kit->logo_path) ? $disk->path($kit->logo_path) : null;
            $png = app(\App\Services\QrRenderer::class)->png((string) $kit->website, 'rounded', '000000', $logoFile, 1000);
            $path = "brandkits/{$kit->key}/qr-card.png";
            $disk->put($path, $png);
            GenerateProductImage::dispatch($kit->key, BrandKitSpec::qrBusinessCard() + ['qr_asset' => $path]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Identify the brand from an uploaded image / PDF page: ask Gemini for the company,
     * the website URL and the logo (presence + bounding box). The URL feeds the normal
     * crawl (handle()), and the logo is cropped STRAIGHT from the document (no redraw) so
     * enhanceLogo() can super-resolve it when it's low-res.
     */
    private function extract(GeminiClient $gemini, BrandKit $kit): void
    {
        $src = $this->imageInput($kit->source_file);
        if (! $src) {
            return;
        }
        $kit->markStage('extract', 'running');

        $info = [];
        try {
            $info = $gemini->inspectImage(
                'This is uploaded brand artwork — a business card, flyer, letterhead or a PDF page. Read ALL '
                .'text carefully AND look at the graphics. Return JSON with EXACTLY these keys: '
                .'"company" (the brand/business name — usually the most prominent wordmark or the name in the '
                .'logo; extract it even if it is also a common English word), '
                .'"website" (ANY web address present — a www. or https:// string or a bare domain like '
                .'example.com or example.io; return just the host), '
                .'"tagline", "email", "phone", '
                .'"has_logo" (true ONLY if a distinct logo, brand mark, emblem or graphic wordmark is present; '
                .'false for plain text with no mark), '
                .'"logo_box" (the bounding box of the ENTIRE primary logo as fractions of the whole image — '
                .'{"x":left,"y":top,"w":width,"h":height}, each 0..1. It MUST enclose the COMPLETE logo lockup '
                .'together — BOTH the icon/symbol AND the full company-name wordmark/text — from its leftmost to '
                .'rightmost and topmost to bottommost extent; do NOT box only the icon or a single word. Null when '
                .'has_logo is false), '
                .'"logo_quality" ("high" if the logo looks crisp, sharp and high-resolution with clean edges; '
                .'"low" if it looks blurry, soft, pixelated, jpeg-artifacted or clearly low-resolution; otherwise '
                .'"medium"; null when has_logo is false). '
                .'Use null for any text field that is genuinely absent.',
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

        // Logo: only when one is actually present. Prefer the REAL logo cropped straight
        // from the document (no redraw) so enhanceLogo() can super-resolve it if it's
        // low-res; fall back to a Gemini isolation only when we can't crop a clean box.
        if ($info['has_logo'] ?? true) {
            $logo = $this->cropLogo($src, $info['logo_box'] ?? null) ?? $this->isolateLogo($gemini, $src);
            if ($logo !== null) {
                $path = "brandkits/{$this->key}/logo.webp";
                Storage::disk('public')->put($path, Img::webp($logo, 1200));
                $kit->update(['logo_path' => $path, 'logo_url' => Storage::disk('public')->url($path)]);
            }
        }

        $kit->markStage('extract', 'done');
    }

    /** Crop the real logo region out of the source image using a 0..1 bounding box (+ padding). */
    private function cropLogo(array $src, mixed $box): ?string
    {
        if (! is_array($box)) {
            return null;
        }
        $x = (float) ($box['x'] ?? -1);
        $y = (float) ($box['y'] ?? -1);
        $w = (float) ($box['w'] ?? 0);
        $h = (float) ($box['h'] ?? 0);
        if ($x < 0 || $y < 0 || $w <= 0.01 || $h <= 0.01 || ($x + $w) > 1.2 || ($y + $h) > 1.2 || ($w * $h) < 0.006) {
            return null; // missing, implausible, or too small to be the whole logo → isolate instead
        }

        $im = @imagecreatefromstring(base64_decode($src['data']));
        if (! $im) {
            return null;
        }
        $iw = imagesx($im);
        $ih = imagesy($im);
        $padX = 0.08 * $w;
        $padY = 0.08 * $h;
        $x0 = max(0, (int) round(($x - $padX) * $iw));
        $y0 = max(0, (int) round(($y - $padY) * $ih));
        $x1 = min($iw, (int) round(($x + $w + $padX) * $iw));
        $y1 = min($ih, (int) round(($y + $h + $padY) * $ih));
        $cw = $x1 - $x0;
        $ch = $y1 - $y0;
        if ($cw < 8 || $ch < 8) {
            imagedestroy($im);

            return null;
        }
        $crop = imagecrop($im, ['x' => $x0, 'y' => $y0, 'width' => $cw, 'height' => $ch]);
        imagedestroy($im);
        if (! $crop) {
            return null;
        }
        ob_start();
        imagepng($crop);
        $bytes = ob_get_clean();
        imagedestroy($crop);

        return $bytes ?: null;
    }

    /** Fallback: ask Gemini to isolate the logo on white (a redraw — only when a crop isn't possible). */
    private function isolateLogo(GeminiClient $gemini, array $src): ?string
    {
        try {
            $iso = $gemini->generateImage(
                'Extract and isolate ONLY the primary logo / brand mark from this artwork. Reproduce it '
                .'EXACTLY as it appears — same shapes, letters and colours — centred on a plain solid white '
                .'background, with nothing else around it (no card, no contact details, no extra text). '
                .'Tight crop with a little padding.',
                [$src],
                config('shop.internal_engine.image_model'),
            );

            return $iso['data'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Make the logo production-ready, faithfully:
     *   1. keep the pristine original untouched (logo_original_path),
     *   2. downscale an over-large working copy to a sane max side,
     *   3. upscale ONLY genuinely tiny logos, via Replicate real-esrgan (a true
     *      super-resolution model — not a generative redraw), capped to a max side,
     *   4. pad to a square at the working resolution (no upscaling) for mockups.
     * A logo in the normal range is used exactly as supplied.
     */
    private function enhanceLogo(ReplicateClient $replicate, BrandKit $kit): void
    {
        $input = $this->logoInput($kit);
        if (! $input) {
            return;
        }
        $disk = Storage::disk('public');
        $orig = base64_decode($input['data']);

        // 1. keep the original, exactly as supplied
        $ext = strtolower(explode('/', $input['mime'])[1] ?? 'png');
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'png';
        $origPath = "brandkits/{$this->key}/logo-original.{$ext}";
        $disk->put($origPath, $orig);

        $dims = @getimagesizefromstring($orig) ?: [0, 0];
        $maxSide = max($dims[0], $dims[1]);
        $below = (int) config('shop.internal_engine.logo_upscale_below_px', 125);
        $to = (int) config('shop.internal_engine.logo_upscale_to_px', 512);
        $resize = (int) config('shop.internal_engine.logo_resize_px', 800);

        // Logos extracted from an uploaded document are usually small, low-res crops —
        // super-resolve them up to the target size, not just the tiny-logo floor.
        if (in_array($kit->source, ['pdf', 'image'], true)) {
            $below = max($below, $to);
        }

        // Super-resolve when the logo is too SMALL, or when the extract inspection judged
        // it LOW QUALITY (blurry / pixelated / low-res) even at an adequate pixel size.
        $tooSmall = $maxSide > 0 && $maxSide < $below;
        $lowQuality = ($kit->extract['logo_quality'] ?? null) === 'low' && $maxSide > 0 && $maxSide <= $resize;

        $bytes = $orig;
        if ($tooSmall || $lowQuality) {
            // real-esrgan — true super-resolution + denoise (never a generative redraw)
            try {
                $up = $replicate->runImage(
                    (string) config('shop.internal_engine.esrgan_model', 'nightmareai/real-esrgan'),
                    [
                        'image'        => 'data:'.$input['mime'].';base64,'.$input['data'],
                        'scale'        => (int) config('shop.internal_engine.esrgan_scale', 4),
                        'face_enhance' => false,
                    ],
                    90,
                );
                if ($up) {
                    // tiny → cap to the upscale target; low-quality-but-sized → keep the working size
                    $bytes = Img::cap($up, $tooSmall ? $to : $resize);
                }
            } catch (\Throwable) {
                // real-esrgan unavailable — fall back to the original bytes
            }
        } elseif ($maxSide > $resize) {
            // too big — faithful downscale to the working max side
            $bytes = Img::cap($orig, $resize);
        }
        // else: normal range — used exactly as supplied

        $path = "brandkits/{$this->key}/logo-hd.webp";
        $disk->put($path, Img::squarePad($bytes));
        $kit->update([
            'logo_path'          => $path,
            'logo_url'           => $disk->url($path),
            'logo_original_path' => $origPath,
        ]);
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
