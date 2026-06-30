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
        {--only=all : all|products|categories|hero}
        {--limit=0 : max items to generate (0 = no limit)}
        {--force : regenerate even if an image already exists}';

    protected $description = 'Generate catalog imagery (hero, categories, products) with Gemini';

    private const STYLE = 'Vibrant modern commercial product photography for a premium print brand. Bright even studio '
        .'lighting, crisp focus, rich saturated colour, clean soft white-to-light-grey gradient background, subtle '
        .'realistic shadows, generous negative space, editorial e-commerce style. No text, no logo, no watermark.';

    public function handle(GeminiClient $gemini): int
    {
        $only  = $this->option('only');
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $disk  = Storage::disk('public');
        $count = 0;

        $tasks = [];

        if (in_array($only, ['all', 'hero'], true)) {
            $tasks[] = [
                'path'   => 'heroes/home',
                'maxw'   => 1800,
                'prompt' => 'Premium wide flat-lay hero photo, slightly angled top-down, of an array of custom-branded '
                    .'business products beautifully arranged on the RIGHT side of the frame: a neatly folded forest-green '
                    .'polo shirt, two ceramic mugs (one navy, one white), several navy ballpoint pens, a brushed metal '
                    .'water bottle with a green grip band, a natural canvas tote bag, a stack of business cards in a clear '
                    .'acrylic holder, a navy spiral notebook and a scatter of round sticker badges — a cohesive forest-green, '
                    .'navy and white brand palette. The products rest on a rich royal-blue surface printed with a subtle '
                    .'blueprint graph-paper grid of faint lighter-blue lines. Soft realistic drop shadows ground every item. '
                    .'The LEFT 40 percent of the frame is clean, empty, evenly-lit blue gridded surface with generous '
                    .'negative space reserved for headline text. Bright even studio lighting, crisp focus, rich saturated '
                    .'colour, premium editorial e-commerce product photography. No text, no logo, no watermark.',
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
                $tasks[] = [
                    'path'   => "products/{$product->slug}",
                    'maxw'   => 1000,
                    'prompt' => "{$product->name} ({$product->category->name}) — {$product->tagline} "
                        .'A single vibrant hero product shot of this printed item, styled and colourful, '
                        .'filling the frame. '.self::STYLE,
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
                $webp = $this->toWebp($img['data'], $task['maxw'] ?? 1000);
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

    /** Resize (cap width) and re-encode to web-ready webp. */
    private function toWebp(string $data, int $maxW): string
    {
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            return $data;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w > $maxW) {
            $scaled = imagescale($im, $maxW, (int) round($h * $maxW / $w));
            if ($scaled !== false) {
                imagedestroy($im);
                $im = $scaled;
            }
        }
        ob_start();
        imagewebp($im, null, 82);
        $out = ob_get_clean();
        imagedestroy($im);

        return $out !== false && $out !== '' ? $out : $data;
    }
}
