<?php

namespace App\Console\Commands;

use App\Services\GeminiClient;
use App\Support\BrandKitSpec;
use App\Support\Img;
use Illuminate\Console\Command;

/**
 * Pre-generate the BLANK, unbranded product base photos (one per fixed-shape merch
 * product) that the internal upsell engine composites the customer's logo onto at
 * runtime. Generated once, visually validated, committed under resources/product-bases/
 * so every environment produces the exact same product — only the branding changes.
 */
class GenerateProductBases extends Command
{
    protected $signature = 'shop:product-bases {--only= : comma-separated product keys} {--force : overwrite existing bases}';

    protected $description = 'Generate the blank product base images used by the logo-composite flow';

    public function handle(GeminiClient $gemini): int
    {
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $dir = resource_path('product-bases');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $model = config('shop.internal_engine.image_model');
        $written = 0;

        foreach (BrandKitSpec::baseSpecs() as $p) {
            if ($only && ! in_array($p['key'], $only, true)) {
                continue;
            }
            $file = $dir.'/'.$p['key'].'.webp';
            if (is_file($file) && ! $this->option('force')) {
                $this->line("skip {$p['key']} (already exists — use --force to regenerate)");
                continue;
            }
            $this->info("generating base: {$p['key']} …");
            try {
                $img = $gemini->generateImage(BrandKitSpec::basePrompt($p), [], $model, '1:1');
                file_put_contents($file, Img::webp($img['data'], 1000));
                $this->info('  ✓ '.$p['key'].' → '.$file);
                $written++;
            } catch (\Throwable $e) {
                $this->error('  ✗ '.$p['key'].': '.$e->getMessage());
            }
        }

        $this->info("done — {$written} base(s) written to {$dir}");

        return self::SUCCESS;
    }
}
