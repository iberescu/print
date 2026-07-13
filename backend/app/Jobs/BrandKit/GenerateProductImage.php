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

        // Composite the images this product's scene calls for (logo and/or QR). The
        // QR is passed to Gemini as a fixed asset like the logo — high-res (>500px)
        // so the model places it as-is instead of trying to "improve"/redraw it.
        $inputs = $this->spec['inputs'] ?? ['logo'];
        $imgs = [];
        if (in_array('logo', $inputs, true) && ($logo = $this->logoInput($kit))) {
            $imgs[] = $logo;
        }
        $hasQr = in_array('qr', $inputs, true);
        if ($hasQr && ($qr = $this->qrInput($kit))) {
            $imgs[] = $qr;
        }
        if (! $imgs) {
            return; // nothing to place
        }

        $summary = $kit->summary ?? [];
        $img = $gemini->generateImage(
            BrandKitSpec::productPrompt($this->spec, [
                'keywords' => $summary['keywords'] ?? [],
                'company'  => $kit->company ?: ($summary['company'] ?? ''),
                'url'      => $kit->website ?: ($summary['website'] ?? ''),
                'colors'   => $summary['colors'] ?? [],
            ]),
            $imgs,
            config('shop.internal_engine.image_model'),
        );

        $path = "brandkits/{$this->key}/product-{$this->spec['key']}.webp";
        Storage::disk('public')->put($path, Img::webp($img['data'], $hasQr ? 1200 : 1000));

        $kit->appendItems('products', [[
            'key'          => $this->spec['key'],
            'label'        => $this->spec['label'],
            'img'          => Storage::disk('public')->url($path),
            'product_slug' => $this->spec['slug'],
        ]]);
    }
}
