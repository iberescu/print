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
        {--only=all : all|products|categories|hero|logo|promo|logo-maker}
        {--product= : only this product slug (implies --only=products)}
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

    /** Accessories (card holders/cases/stands): tight, product-focused catalog shot,
     *  Vistaprint-style — the product fills the frame on pure white, plain & unbranded. */
    private const ACCESSORY_STYLE = 'Clean e-commerce catalog product photograph. The single product is CENTERED and '
        .'FILLS most of the frame — tight crop, minimal empty space, product-focused — shot straight-on or at a gentle '
        .'three-quarter angle. Pure seamless white background (#ffffff), bright even soft studio lighting, one soft '
        .'natural contact shadow directly beneath, crisp sharp focus showing the material, finish and craftsmanship. '
        .'The product is completely PLAIN and UNBRANDED — no logo, no printed design, no engraving, no text anywhere. '
        .'If it holds business cards, show a small neat stack of plain blank white cards. Realistic premium product '
        .'photography, no extra props, no text, no watermark, no UI overlay.';

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
        $slug  = $this->option('product');
        if ($slug) {
            $only = 'products';
        }
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

        if (in_array($only, ['all', 'logo-maker'], true)) {
            $tasks[] = [
                'path'   => 'heroes/logo-maker',
                'maxw'   => 1100,
                'prompt' => 'Elegant brand-identity presentation photographed from a slight angle on a light desk: '
                    .'a stack of business cards, a letterhead sheet and an embossed notebook, all carrying the SAME '
                    .'simple abstract geometric emblem in deep navy (#2b3b55) and vivid blue (#398aff) — a clean '
                    .'circular mark, no readable text. Beside them a fine liner pen and a small colour swatch card '
                    .'with two blue chips. Soft daylight, shallow depth of field, premium design-studio aesthetic, '
                    .'no logos of real brands, no watermark.',
                'save'   => null,
            ];
            $tasks[] = [
                'path'   => 'promos/logo-maker-showcase',
                'maxw'   => 1100,
                'prompt' => 'Clean flat-lay of printed merchandise all carrying the SAME simple abstract geometric '
                    .'navy-and-blue emblem (no readable text): a white ceramic mug, folded t-shirt, business cards, '
                    .'a canvas tote bag and a round sticker sheet, arranged with generous spacing on a soft light-grey '
                    .'background. The one mark repeats consistently across every item. Bright even studio light, '
                    .'editorial e-commerce style, no real brands, no watermark.',
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
            $products = Product::with('category')
                ->when($slug, fn ($q) => $q->where('slug', $slug))
                ->orderBy('sort_order')->get();

            foreach ($products as $product) {
                // Accessories (card holders/cases/stands) — a tight product-focused
                // catalog shot on white, always PLAIN (holders aren't printed), even
                // though the crawl left some flagged supports_design.
                if ($product->category->slug === 'accessories') {
                    $tasks[] = [
                        'path'   => "products/{$product->slug}",
                        'maxw'   => 1000,
                        'prompt' => "{$product->name} — {$product->tagline} ".self::ACCESSORY_STYLE,
                        'save'   => fn (string $p) => tap($product)->update(['image_path' => $p]),
                    ];
                    continue;
                }

                // Other non-personalisable items ship as-is — neutral, no brand design.
                if (! $product->supports_design && ! $product->supports_upload) {
                    $tasks[] = [
                        'path'   => "products/{$product->slug}",
                        'maxw'   => 1000,
                        'prompt' => "{$product->name} ({$product->category->name}) — {$product->tagline} "
                            .'A single elegant hero product shot of this item, styled and filling the frame. '
                            .'The product is completely PLAIN and UNBRANDED — no logo, no printed design, no '
                            .'engraving, no text anywhere on it. Showcase its material, finish and craftsmanship. '
                            .'If it holds business cards, show a small neat stack of plain blank white cards. '
                            .self::STYLE,
                        'save'   => fn (string $p) => tap($product)->update(['image_path' => $p]),
                    ];
                    continue;
                }

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
