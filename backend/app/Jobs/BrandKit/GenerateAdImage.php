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
        $logo = $kit ? $this->logoGeminiInput($kit) : null;
        if (! $kit || ! $logo) {
            return;
        }

        $summary = $kit->summary ?? [];
        $company = (string) ($kit->company ?: ($summary['company'] ?? ''));
        $palette = ! empty($summary['colors']) ? implode(', ', (array) $summary['colors']) : null;
        $description = (string) ($summary['description'] ?? '');
        $headline = (string) ($this->ad['headline'] ?? '');
        $cta = (string) ($this->ad['cta'] ?? 'Learn more');

        // The 2nd ad mirrors the brand's real website: hand Gemini the homepage screenshot
        // as a style + imagery reference alongside the logo, and prompt it to design in that look.
        $siteShot = ($this->ad['use_site_shot'] ?? false) && $kit->site_shot_path
            ? $this->imageInput($kit->site_shot_path)
            : null;

        if ($siteShot) {
            $prompt = BrandKitSpec::adPromptFromSite($headline, $cta, $company, $description);
            $images = [$logo, $siteShot];
        } else {
            // Give each ad a distinct look (vibrant / professional / image-led / modern).
            $styleIndex = (int) preg_replace('/\D/', '', (string) ($this->ad['key'] ?? 'ad0'));
            $prompt = BrandKitSpec::adPrompt($headline, $cta, $company, $palette, $description, BrandKitSpec::adStyle($styleIndex), $styleIndex);
            $images = [$logo];
        }

        $images = array_map(fn ($i) => $this->capForGemini($i), $images);
        $img = $gemini->generateImage($prompt, $images, config('shop.internal_engine.ad_image_model'));

        $path = "brandkits/{$this->key}/ad-{$this->ad['key']}.webp";
        Storage::disk('public')->put($path, Img::webp($img['data'], 1200));

        $kit->appendItems('ads', [[
            'key' => $this->ad['key'],
            'img' => Storage::disk('public')->url($path),
        ]]);
    }
}
