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

/** One Google Display ad banner (runs in parallel, one job per ad angle). */
class GenerateAdImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReadsImages, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    /** @param array{key:string,headline:string} $ad */
    public function __construct(public string $key, public array $ad)
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
        $company = $kit->company ?: ($summary['company'] ?? '');
        $palette = ! empty($summary['colors']) ? implode(', ', (array) $summary['colors']) : null;

        $img = $gemini->generateImage(
            BrandKitSpec::adPrompt(
                (string) ($this->ad['headline'] ?? ''),
                (string) ($this->ad['cta'] ?? 'Learn more'),
                (string) $company,
                $palette,
                (string) ($summary['description'] ?? ''),
            ),
            [$logo],
            config('shop.internal_engine.image_model'),
        );

        $path = "brandkits/{$this->key}/ad-{$this->ad['key']}.webp";
        Storage::disk('public')->put($path, Img::webp($img['data'], 1200));

        $kit->appendItems('ads', [[
            'key' => $this->ad['key'],
            'img' => Storage::disk('public')->url($path),
        ]]);
    }
}
