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

/** One "your logo on a product" mockup (runs in parallel, one job per product). */
class GenerateProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReadsImages, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    /** @param array{key:string,label:string,slug:string,decoration:string,scene:string} $spec */
    public function __construct(public string $key, public array $spec)
    {
        $this->onQueue('brandkit');
    }

    public function handle(GeminiClient $gemini): void
    {
        $kit = BrandKit::where('key', $this->key)->first();
        if (! $kit) {
            return;
        }

        $summary = $kit->summary ?? [];
        $ctx = [
            'keywords'    => $summary['keywords'] ?? [],
            'company'     => $kit->company ?: ($summary['company'] ?? ''),
            'url'         => $kit->website ?: ($summary['website'] ?? ''),
            'description' => $summary['description'] ?? '',
            'colors'      => $summary['colors'] ?? [],
        ];

        // Gather the content assets this product composites: logo and/or QR, plus the
        // homepage screenshot for website-styled pieces. The QR is a fixed asset (>500px)
        // so the model reproduces it as-is instead of "improving" it.
        $inputs = $this->spec['inputs'] ?? ['logo'];
        $content = [];
        if (in_array('logo', $inputs, true) && ($logo = $this->logoGeminiInput($kit))) {
            $content[] = $logo;
        }
        $hasQr = in_array('qr', $inputs, true);
        if ($hasQr) {
            // A spec-provided QR (designer-flow QR business card) wins over the kit's capture QR.
            $qr = isset($this->spec['qr_asset']) ? $this->imageInput($this->spec['qr_asset']) : $this->qrInput($kit);
            if ($qr) {
                $content[] = $qr;
            }
        }
        $shot = ($this->spec['use_site_shot'] ?? false) && $kit->site_shot_path
            ? $this->imageInput($kit->site_shot_path) : null;
        if ($shot) {
            $content[] = $shot;
        }
        if (! $content) {
            return; // nothing to place
        }

        // Base + composite flow: place the content onto a PRE-GENERATED, pre-validated
        // blank base so the product/scene looks identical on every capture — only the
        // branding (and, when it suits the brand, the product colour) changes. Falls back
        // to direct one-shot generation for shape-derived pieces without a base.
        $baseInput = $this->baseInput();
        if ($baseInput) {
            $prompt = BrandKitSpec::placePromptFor($this->spec, $ctx);
            $imgs = array_merge([$baseInput], $content); // image 1 = base, then logo/QR/screenshot
        } else {
            $prompt = BrandKitSpec::productPrompt($this->spec, $ctx + ['has_site' => (bool) $shot]);
            $imgs = $content;
        }
        $big = $hasQr || (bool) $shot;

        // Model tier: the faster LITE model handles the simple "logo/QR composited onto a fixed
        // product base" mockups. Everything else — the website-styled pieces (brochure/flyer,
        // logo + screenshot) and the text-heavy direct layouts (letterhead, review sign) — uses
        // the fuller flash model. (Display ads run flash too, in GenerateAdImage.)
        $useLite = $baseInput && ! ($this->spec['use_site_shot'] ?? false);
        $model = $useLite
            ? config('shop.internal_engine.lite_image_model')
            : config('shop.internal_engine.image_model');

        // Keep every upload small (≤800px wide) so Gemini receives and processes them fast.
        $imgs = array_map(fn ($i) => $this->capForGemini($i), $imgs);
        // Always output a SQUARE 1:1 mockup so the product is never cropped in the gallery cards.
        $img = $gemini->generateImage($prompt, $imgs, $model, '1:1');

        $path = "brandkits/{$this->key}/product-{$this->spec['key']}.webp";
        Storage::disk('public')->put($path, Img::webp($img['data'], $big ? 1200 : 1000));

        $kit->appendItems('products', [[
            'key'          => $this->spec['key'],
            'label'        => $this->spec['label'],
            'img'          => Storage::disk('public')->url($path),
            'product_slug' => $this->spec['slug'],
        ]]);
    }

    /**
     * The base image sent to Gemini as image 1, if this product has one — either a
     * pre-generated blank product base (composited with the logo) or a style TEMPLATE
     * (e.g. the infinity mirror, where image 1 shows the effect to reproduce). Presence
     * of the file drives the base flow; the accompanying place_prompt says how to use it.
     */
    private function baseInput(): ?array
    {
        $file = resource_path('product-bases/'.$this->spec['key'].'.webp');
        if (! is_file($file)) {
            return null;
        }

        return ['mime' => 'image/webp', 'data' => base64_encode((string) file_get_contents($file))];
    }
}
