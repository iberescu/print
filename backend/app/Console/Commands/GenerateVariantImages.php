<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\GeminiClient;
use App\Support\Img;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Per-colour product images for the variant products (apparel, drinkware, bags).
 * Recolours each product's base photo to every value of its colour option via
 * Gemini — a blank garment/item in that exact colour, same framing — and stores
 * the result on the colour option value's image_path, which the Google Shopping
 * feed (GoogleShoppingFeed) then serves as the per-variant image_link.
 *
 * Idempotent: skips a colour whose image already exists unless --force.
 *   php artisan shop:variant-images [--product=slug] [--force] [--limit=N]
 */
class GenerateVariantImages extends Command
{
    protected $signature = 'shop:variant-images {--product=} {--force} {--limit=}';

    protected $description = 'Generate per-colour product images for apparel/variant products via Gemini';

    /** slug => [noun, kind (garment|drinkware|tote|headwear), colour option name]. */
    private const TARGETS = [
        'gildan-softstyle-unisex-t-shirt'   => ['a unisex crew-neck t-shirt', 'garment', 'Color'],
        'jerzees-nublend-hooded-sweatshirt' => ['a pullover hooded sweatshirt', 'garment', 'Color'],
        'embroidered-polo-shirts'           => ['a short-sleeve pique polo shirt', 'garment', 'Colour'],
        'embroidered-hats'                  => ['a structured baseball cap', 'headwear', 'Colour'],
        'embroidered-beanies'               => ['a cuffed knit beanie', 'headwear', 'Colour'],
        '20-oz-tumbler'                     => ['a 20 oz insulated tumbler with a clear lid', 'drinkware', 'Color'],
        '40-oz-tumblers'                    => ['a 40 oz insulated tumbler with a handle and straw', 'drinkware', 'Color'],
        'custom-canvas-tote-bags'           => ['a canvas tote bag with two handles', 'tote', 'Substrate Color'],
    ];

    public function handle(GeminiClient $gemini): int
    {
        $disk = Storage::disk('public');
        $only = $this->option('product');
        $limit = (int) ($this->option('limit') ?: 0);
        $done = 0;

        foreach (self::TARGETS as $slug => [$noun, $kind, $optName]) {
            if ($only && $only !== $slug) {
                continue;
            }
            $product = Product::with('options.values')->where('slug', $slug)->first();
            if (! $product) {
                $this->warn("skip {$slug} (product missing)");

                continue;
            }
            if (! ($product->image_path && $disk->exists($product->image_path))) {
                $this->warn("skip {$slug} (no base image)");

                continue;
            }
            $base = ['mime' => 'image/webp', 'data' => base64_encode($disk->get($product->image_path))];

            $option = $product->options->first(fn ($o) => strcasecmp($o->name, $optName) === 0);
            if (! $option) {
                $this->warn("skip {$slug} (no '{$optName}' option)");

                continue;
            }

            $this->line("== {$slug} ({$option->values->count()} colours) ==");
            foreach ($option->values as $value) {
                $path = sprintf('products/variants/%s-%s.webp', $slug, Str::slug($value->label));
                if (! $this->option('force') && $value->image_path === $path && $disk->exists($path)) {
                    $this->line("  exists: {$value->label}");

                    continue;
                }
                try {
                    $img = $gemini->generateImage($this->prompt($noun, $kind, $value), [$base]);
                    $disk->put($path, Img::webp($img['data'], 900));
                    $value->update(['image_path' => $path]);
                    $this->info("  ok: {$value->label}");
                    $done++;
                    if ($limit && $done >= $limit) {
                        $this->info("limit reached ({$limit})");

                        return self::SUCCESS;
                    }
                } catch (\Throwable $e) {
                    $this->error("  fail {$value->label}: ".$e->getMessage());
                }
            }
        }

        $this->info("generated {$done} variant image(s)");

        return self::SUCCESS;
    }

    private function prompt(string $noun, string $kind, $value): string
    {
        $colour = $value->label.($value->swatch ? " (approximately {$value->swatch})" : '');
        $blank = 'It must be COMPLETELY BLANK — remove any printed logo, graphic, text or embroidery that appears in the reference.';
        $common = 'Use the attached photo ONLY as the reference for shape, framing, camera angle, crop, fabric folds, soft shadows and the same clean off-white studio background. Photorealistic e-commerce product shot, centred, sharp, no watermark, no extra props, no people.';

        $subject = match ($kind) {
            'drinkware' => "Product photo of {$noun}, its body finished in solid {$colour}.",
            'tote'      => "Product photo of {$noun} made from {$colour} fabric.",
            'headwear'  => "Product photo of {$noun} in solid {$colour}.",
            default     => "Product photo of {$noun} in solid {$colour} fabric.",
        };

        return "{$subject} {$blank} {$common}";
    }
}
