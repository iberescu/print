<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\GeminiClient;
use Illuminate\Console\Command;

/**
 * Original per-product SEO copy (description + spec details + FAQ).
 *
 *   php artisan products:seo              # import the committed bundle into the DB (deploy)
 *   php artisan products:seo --generate   # (dev) generate via Gemini, write the bundle + DB
 *   php artisan products:seo --generate --force --only=flyers
 *
 * Generated copy is stored in database/seed/product-seo.json (committed) so it is
 * reproducible on every environment WITHOUT calling the API in production.
 */
class GenerateProductSeo extends Command
{
    protected $signature = 'products:seo {--generate} {--force} {--only=}';

    protected $description = 'Import (or --generate via Gemini) original SEO copy for products';

    public function handle(GeminiClient $gemini): int
    {
        $file = base_path('database/seed/product-seo.json');
        $store = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

        if ($this->option('generate')) {
            $products = Product::with(['category', 'surface', 'options.values'])
                ->where('is_active', true)
                ->when($this->option('only'), fn ($q, $s) => $q->where('slug', $s))
                ->orderBy('id')->get();

            foreach ($products as $p) {
                if (! $this->option('force') && ! empty($store[$p->slug]['description'])) {
                    $this->line("  skip {$p->slug} (already has copy)");

                    continue;
                }
                $this->line("  generating {$p->slug} …");
                try {
                    $seo = $this->generateFor($gemini, $p);
                    if ($seo) {
                        $store[$p->slug] = $seo;
                    } else {
                        $this->warn("    empty result for {$p->slug}");
                    }
                } catch (\Throwable $e) {
                    $this->warn("    failed {$p->slug}: {$e->getMessage()}");
                }
            }

            ksort($store);
            @mkdir(dirname($file), 0777, true);
            file_put_contents($file, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->info('Wrote '.$file);
        }

        $n = 0;
        foreach ($store as $slug => $seo) {
            $p = Product::where('slug', $slug)->first();
            if ($p) {
                $p->seo = $seo;
                $p->save();
                $n++;
            }
        }
        $this->info("Applied SEO copy to {$n} products.");

        return self::SUCCESS;
    }

    /** @return array{description:string,details:array<int,string>,faq:array<int,array{q:string,a:string}>}|null */
    private function generateFor(GeminiClient $gemini, Product $p): ?array
    {
        $options = $p->options->map(
            fn ($o) => $o->name.': '.$o->values->pluck('label')->filter()->implode(', ')
        )->filter()->values()->all();

        $size = $p->surface && $p->surface->width
            ? rtrim(rtrim((string) $p->surface->width, '0'), '.').'×'.rtrim(rtrim((string) $p->surface->height, '0'), '.').' '.$p->surface->unit
            : null;

        $ctx = array_filter([
            'product'    => $p->name,
            'category'   => $p->category->name,
            'tagline'    => $p->tagline,
            'summary'    => $p->description,
            'size'       => $size,
            'options'    => $options ?: null,
            'from_price' => '$'.$p->from_price,
        ]);

        $brand = config('shop.company.brand', 'RunMyPrint');

        $prompt = <<<PROMPT
        You are an expert e-commerce SEO copywriter for a custom online print shop called {$brand}.
        Write ORIGINAL, unique marketing copy for the product below. Never copy another company's wording.
        Be factual: only use specifications present in the data — never invent sizes, materials, stock weights or prices.
        Tone: professional, benefit-led, trustworthy US English. No keyword stuffing, no hype clichés, no competitor names.

        PRODUCT DATA (JSON):
        {$this->json($ctx)}

        Return STRICT JSON only, with exactly this shape:
        {
          "description": "Two short paragraphs (~120-160 words total). What the product is, its key benefits and real-world use cases, and why it's worth ordering from {$brand}. Use the product name naturally 1-2 times for SEO.",
          "details": ["6 to 8 concise product-detail bullets grounded in the data: sizes, stock/paper or material choices, finishes, customization, minimum quantities, turnaround. Each 4-10 words, no trailing period."],
          "faq": [
            {"q": "A specific buyer question about THIS product", "a": "A helpful 1-3 sentence answer."}
          ]
        }
        Provide exactly 5 FAQ entries covering practical concerns (artwork/file formats, proofing & approval, turnaround & shipping, quantities/reorders, finishes or options, quality guarantee) tailored to this product.
        Output only the JSON object.
        PROMPT;

        $out = $gemini->generateJson($prompt);
        if (empty($out['description'])) {
            return null;
        }

        return [
            'description' => trim((string) $out['description']),
            'details'     => array_values(array_filter(array_map(
                fn ($d) => trim((string) $d),
                (array) ($out['details'] ?? [])
            ))),
            'faq' => array_values(array_filter(array_map(
                fn ($x) => [
                    'q' => trim((string) ($x['q'] ?? $x['question'] ?? '')),
                    'a' => trim((string) ($x['a'] ?? $x['answer'] ?? '')),
                ],
                (array) ($out['faq'] ?? [])
            ), fn ($x) => $x['q'] !== '' && $x['a'] !== '')),
        ];
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
