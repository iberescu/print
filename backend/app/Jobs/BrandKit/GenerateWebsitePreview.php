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
use Illuminate\Support\Facades\Storage;

/**
 * Homepage design for the "$10 website" upsell — generated only for captures
 * WITHOUT a website (they see this offer where URL-captures see the Layout.ai
 * ads). One flash-model image built from the logo (brand colours read straight
 * from it) and the company name when we have one; the upsell step shows it
 * inside a MacBook frame.
 */
class GenerateWebsitePreview implements ShouldQueue
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
        if (! $kit || $kit->website || $kit->website_preview_path) {
            return; // URL captures get the ads offer instead; don't regenerate
        }
        $logo = $this->logoGeminiInput($kit);
        if (! $logo) {
            return; // nothing to design from
        }

        $company = trim((string) ($kit->company ?: ($kit->summary['company'] ?? '')));
        $named = $company !== ''
            ? "The brand is called \"{$company}\" — the header wordmark and the hero headline use that exact name, spelled correctly."
            : 'No company name is known — brand the site with the logo alone and use short generic headline copy (no invented company name).';

        $prompt = 'A pixel-perfect FLAT SCREENSHOT of a modern, professional small-business website '
            .'homepage, desktop layout, filling the entire frame edge-to-edge (no browser window, no '
            .'chrome, no device, no drop shadow — just the web page itself). Design it FROM the provided '
            .'logo: build the colour scheme from the logo\'s own colours (dominant brand colour for '
            .'accents/buttons, plenty of clean white/light space), and place the logo AS-IS in the top-left '
            ."of a clean navigation bar (reproduce it exactly — never redraw it). {$named} "
            .'Layout, top to bottom: slim nav bar (logo left, 4 short menu links right); a strong hero '
            .'section with a large headline, one sentence of supporting copy, a prominent brand-coloured '
            .'call-to-action button and a tasteful hero image or abstract brand-coloured graphic; below it a '
            .'row of three simple feature/service cards with small icons; then a short about/testimonial band; '
            .'and a dark footer strip with small contact placeholders. Modern typography (clean geometric '
            .'sans-serif), generous spacing, crisp edges — the polished look of a premium website template. '
            .'All visible text is real, correctly spelled English (short generic marketing copy is fine); '
            .'absolutely no gibberish, lorem-ipsum garble or misspelled words. No watermark.';

        $img = $gemini->generateImage(
            $prompt,
            [$this->capForGemini($logo)],
            config('shop.internal_engine.image_model'),
            '16:9',
        );

        $path = "brandkits/{$this->key}/website-preview.webp";
        Storage::disk('public')->put($path, Img::webp($img['data'], 1400));
        $kit->update(['website_preview_path' => $path]);
    }
}
