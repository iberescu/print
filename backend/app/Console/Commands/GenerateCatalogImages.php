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

    private const STYLE = 'Professional e-commerce product photography, soft seamless light-grey studio background, '
        .'soft natural shadows, photorealistic, sharp focus, centered composition, no text, no logo, no watermark.';

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
                'prompt' => 'Bright, airy hero photograph of a modern small-business desk: a neat stack of business '
                    .'cards, a few flyers and a laptop showing a colourful design tool, lots of clean negative space '
                    .'on the right for headline text. Cinematic, professional, shallow depth of field. '.self::STYLE,
                'save'   => null,
            ];
        }

        if (in_array($only, ['all', 'categories'], true)) {
            foreach (Category::orderBy('sort_order')->get() as $cat) {
                $tasks[] = [
                    'path'   => "categories/{$cat->slug}",
                    'prompt' => "Clean modern flat-lay representing the \"{$cat->name}\" print category "
                        ."({$cat->tagline}). Assorted printed products tastefully arranged. ".self::STYLE,
                    'save'   => fn (string $p) => tap($cat)->update(['image_path' => $p]),
                ];
            }
        }

        if (in_array($only, ['all', 'products'], true)) {
            foreach (Product::with('category')->orderBy('sort_order')->get() as $product) {
                $tasks[] = [
                    'path'   => "products/{$product->slug}",
                    'prompt' => "{$product->name} ({$product->category->name}) — {$product->tagline} "
                        .'A single hero product shot of this printed item. '.self::STYLE,
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
                $ext  = str_contains($img['mime'], 'png') ? 'png' : 'jpg';
                $path = "{$task['path']}.{$ext}";
                $disk->put($path, $img['data']);
                if ($task['save']) {
                    ($task['save'])($path);
                }
                $count++;
                $this->info("ok    {$path}  (".number_format(strlen($img['data']) / 1024, 0)." KB)");
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
        return $disk->exists("{$base}.jpg") || $disk->exists("{$base}.png");
    }
}
