<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use App\Services\GeminiClient;
use App\Support\Img;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The Private Brand Store hero: a wide 16:9 banner of print products on a table,
 * every piece carrying THE customer's logo, the scene styled after their website
 * (logo + homepage screenshot go in as fixed references; gemini-3.1-flash-image).
 * Deterministic output path — the store page picks it up whenever it lands.
 */
class GenerateStoreHero implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReadsImages, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(public string $key)
    {
        $this->onQueue('brandkit');
    }

    /** Where the hero lives for a kit (existence = it's ready). */
    public static function path(string $key): string
    {
        return "brandkits/{$key}/store-hero.webp";
    }

    public function handle(GeminiClient $gemini): void
    {
        $kit = BrandKit::where('key', $this->key)->first();
        if (! $kit || Storage::disk('public')->exists(self::path($this->key))) {
            return;
        }
        $logo = $this->logoGeminiInput($kit) ?? $this->logoInput($kit);
        if (! $logo) {
            return;
        }
        $shot = $kit->site_shot_path ? $this->imageInput($kit->site_shot_path) : null;

        $company = trim((string) ($kit->company ?: ($kit->summary['company'] ?? ''))) ?: 'the company';
        $colors = implode(', ', array_slice((array) ($kit->summary['colors'] ?? []), 0, 3));

        $prompt = 'A premium WIDE e-commerce hero photograph, exactly 16:9, photorealistic. '
            .'An elegant assortment of printed merchandise arranged on a light wooden studio table, '
            .'shot from a slight 3/4 angle with soft natural light and shallow depth of field: '
            .'a neat stack of business cards, a flyer, a ceramic mug, a canvas tote bag and a pen. '
            .'EVERY item carries the EXACT logo from image 1 — reproduce it verbatim, unmodified, '
            .'correct colors and proportions; never redraw or restyle it. '
            .($shot
                ? 'Image 2 is the company\'s website: match the scene\'s accent props, background tone and mood '
                  .'to that site\'s visual identity so the photo feels like the same brand. '
                : '')
            .($colors !== '' ? "Brand colors to echo in the props and backdrop: {$colors}. " : '')
            .'Composition: products grouped on the RIGHT two-thirds; the LEFT third stays calm, softly '
            .'out-of-focus background with clean negative space (a headline will be overlaid there). '
            ."No text, no words, no lettering anywhere in the image except the logo itself. This is the hero banner for {$company}'s private merchandise store.";

        $images = array_values(array_filter([$this->capForGemini($logo), $this->capForGemini($shot)]));

        try {
            $img = $gemini->generateImage($prompt, $images, config('shop.internal_engine.image_model'), '16:9');
            Storage::disk('public')->put(self::path($this->key), Img::webp($img['data'], 1920));
            Log::info("brandstore: hero generated for kit {$kit->id}");
        } catch (\Throwable $e) {
            Log::warning("brandstore: hero failed for kit {$kit->id}: {$e->getMessage()}");
        }
    }
}
