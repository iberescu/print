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
        $logo = $kit ? $this->logoInput($kit) : null;
        if (! $kit || ! $logo) {
            return;
        }

        $summary = $kit->summary ?? [];
        $img = $gemini->generateImage(
            BrandKitSpec::productPrompt($this->spec, [
                'keywords' => $summary['keywords'] ?? [],
                'company'  => $kit->company ?: ($summary['company'] ?? ''),
                'colors'   => $summary['colors'] ?? [],
            ]),
            [$logo],
            config('shop.internal_engine.image_model'),
        );

        $path = "brandkits/{$this->key}/product-{$this->spec['key']}.webp";
        Storage::disk('public')->put($path, Img::webp($img['data'], 1000));

        $kit->appendItems('products', [[
            'key'          => $this->spec['key'],
            'label'        => $this->spec['label'],
            'img'          => Storage::disk('public')->url($path),
            'product_slug' => $this->spec['slug'],
        ]]);
    }
}
