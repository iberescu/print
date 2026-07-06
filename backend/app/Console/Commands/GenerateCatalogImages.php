<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\GeminiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateCatalogImages extends Command
{
    protected $signature = 'images:generate
        {--only=all : all|products|categories|hero|logo|promo}
        {--limit=0 : max items to generate (0 = no limit)}
        {--force : regenerate even if an image already exists}';

    protected $description = 'Generate catalog imagery (hero, categories, products) with Gemini';

    private const STYLE = 'Vibrant modern commercial product photography for a premium print brand. Bright even studio '
        .'lighting, crisp focus, rich saturated colour, clean soft white-to-light-grey gradient background, subtle '
        .'realistic shadows, generous negative space, editorial e-commerce style. No text, no logo, no watermark.';

    /** Product shots show a realistic B2B design ON the printed piece (unlike hero/category shots). */
    private const PRODUCT_STYLE = 'Vibrant modern commercial product photography for a premium print brand. Bright even '
        .'studio lighting, crisp focus, clean soft white-to-light-grey gradient background, subtle realistic shadows, '
        .'generous negative space, editorial e-commerce style. The photo itself has NO watermarks, captions or UI '
        .'overlays — but the printed product faces MUST display the described brand design with a clearly visible '
        .'logo and readable text.';

    /** Fictional B2B brands / palettes / motifs — picked deterministically per product slug. */
    private const BRANDS = [
        ['Northwind Consulting', 'navy blue and lime green on white'],
        ['Vertex Studio', 'deep teal and warm sand'],
        ['Harbor & Co.', 'forest green and cream'],
        ['Atlas Advisory', 'cobalt blue and amber yellow'],
        ['Summit Legal', 'charcoal and burgundy with gold accents'],
        ['Bloom Dental', 'soft aqua and coral on white'],
        ['Forge Engineering', 'ink black and safety orange'],
        ['Solstice Café', 'terracotta and olive with cream'],
        ['Meridian Realty', 'slate blue and copper'],
        ['Pixel & Pine', 'emerald and off-white with black type'],
    ];

    private const MOTIFS = [
        'a bold geometric line pattern',
        'subtle topographic contour lines',
        'sweeping abstract colour arcs',
        'a clean dot-grid texture',
        'strong diagonal colour blocks',
        'a duotone architectural photo accent',
        'minimal leaf/nature line illustrations',
        'layered rounded shapes',
    ];

    public function handle(GeminiClient $gemini): int
    {
        $only  = $this->option('only');
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $disk  = Storage::disk('public');
        $count = 0;

        $tasks = [];

        if (in_array($only, ['all', 'logo'], true)) {
            $tasks[] = [
                'path'   => 'brand/logo-placeholder',
                'maxw'   => 512,
                'prompt' => 'A clean, neutral LOGO PLACEHOLDER graphic for an online design tool, on a pure white background. '
                    .'Centered: a soft rounded square outline in light grey with a simple abstract emblem inside — a stylised '
                    .'mountain-and-circle mark in medium slate grey (#8a97ad) — signalling "your logo here". Minimal, flat, '
                    .'lots of padding, subtle, professional. No real brand, no photorealism, no colourful gradients.',
                'save'   => null,
            ];
        }

        if (in_array($only, ['all', 'promo'], true)) {
            $tasks[] = [
                'path'   => 'promos/layout-ai-offer',
                'maxw'   => 1200,
                'prompt' => 'Premium campaign visual for a print brand × ad-tech partnership, landscape 16:10. Deep navy '
                    .'(#16233b) scene with sophisticated depth: two or three overlapping translucent glass panels in '
                    .'blues (#398aff, #2b3b55) set at a slight angle with soft reflections, a fine halftone dot '
                    .'gradient, thin concentric arc lines, and one elegant thin rising curve with small glowing nodes '
                    .'suggesting growing traffic. Centre-left, clear of the panels: a large refined pure-white "$250" '
                    .'in an elegant editorial typeface, beneath it "AD CREDIT" in wide letterspaced light-blue '
                    .'(#9cc6ff) capitals with a short lime-green (#c7f23d) underline. A subtle lime edge-glow on one '
                    .'glass panel is the only other warm accent. Cinematic soft lighting, luxury fintech-agency '
                    .'aesthetic, balanced negative space on the right, no cartoon objects, no icons, no other text, '
                    .'no logos, no watermark.',
                'save'   => null,
            ];
        }

        if (in_array($only, ['all', 'hero'], true)) {
            $tasks[] = [
                'path'   => 'heroes/home',
                'maxw'   => 1600,
                'prompt' => 'Premium wide flat-lay hero photo, slightly angled top-down, of an array of custom-branded '
                    .'business products beautifully arranged on a clean light surface: a folded polo shirt, two ceramic '
                    .'mugs, ballpoint pens, a metal water bottle, a canvas tote bag, a stack of business cards in a holder, '
                    .'a notebook and some stickers — cohesive green, navy and white brand palette. Bright airy lifestyle '
                    .'commercial photography, lots of clean empty space on the left third for headline text. '.self::STYLE,
                'save'   => null,
            ];
        }

        if (in_array($only, ['all', 'categories'], true)) {
            foreach (Category::orderBy('sort_order')->get() as $cat) {
                $tasks[] = [
                    'path'   => "categories/{$cat->slug}",
                    'maxw'   => 1000,
                    'prompt' => "Vibrant modern flat-lay representing the \"{$cat->name}\" print category "
                        ."({$cat->tagline}). A bright, colourful arrangement of assorted printed products. ".self::STYLE,
                    'save'   => fn (string $p) => tap($cat)->update(['image_path' => $p]),
                ];
            }
        }

        if (in_array($only, ['all', 'products'], true)) {
            foreach (Product::with('category')->orderBy('sort_order')->get() as $product) {
                // deterministic per-slug pick → every product looks different, regens stay stable
                $seed = crc32($product->slug);
                [$brand, $palette] = self::BRANDS[$seed % count(self::BRANDS)];
                $motif = self::MOTIFS[($seed >> 3) % count(self::MOTIFS)];
                $stitched = ($product->decoration ?? 'print') === 'embroidery';

                $tasks[] = [
                    'path'   => "products/{$product->slug}",
                    'maxw'   => 1000,
                    'prompt' => "{$product->name} ({$product->category->name}) — {$product->tagline} "
                        .'A single vibrant hero product shot of this item, styled and filling the frame. '
                        .'The product carries a professional B2B brand design for the fictional company '
                        ."\"{$brand}\": a simple geometric logo mark, the company name in clean modern type, "
                        .'a short supporting text line (tagline or contact details), in a '.$palette
                        .' colour scheme, decorated with '.$motif.' appropriate to this product type. '
                        .($stitched ? 'The design is EMBROIDERED — visible stitched threads, not printed. ' : '')
                        .self::PRODUCT_STYLE,
                    'save'   => fn (string $p) => tap($product)->update(['image_path' => $p]),
                ];
            }
        }

        foreach ($tasks as $task) {
            if ($limit > 0 && $count >= $limit) {
                break;
            }
            if (! $force && $this->existing($disk, $task['path'])) {
                $this->line("skip  {$task['path']} (exists)");
                continue;
            }

            try {
                $img  = $gemini->generateImage($task['prompt']);
                $webp = \App\Support\Img::webp($img['data'], $task['maxw'] ?? 1000);
                $path = "{$task['path']}.webp";
                $disk->put($path, $webp);
                if ($task['save']) {
                    ($task['save'])($path);
                }
                $count++;
                $this->info("ok    {$path}  (".number_format(strlen($webp) / 1024, 0)." KB, web-ready)");
            } catch (Throwable $e) {
                $this->error("fail  {$task['path']}: ".$e->getMessage());
            }

            usleep(800_000); // be gentle on rate limits
        }

        $this->newLine();
        $this->info("Done. Generated {$count} image(s).");

        return self::SUCCESS;
    }

    private function existing($disk, string $base): bool
    {
        return $disk->exists("{$base}.webp") || $disk->exists("{$base}.jpg") || $disk->exists("{$base}.png");
    }
}
