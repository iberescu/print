<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuild the templates catalogue from a committed bundle (see ExportTemplates):
 * fabric JSON + previews + shared hero images, with product/orientation scope.
 */
class ImportTemplates extends Command
{
    protected $signature = 'templates:import {--path=}';
    protected $description = 'Import templates (fabric JSON + previews + heroes) from the bundle into the DB';

    public function handle(): int
    {
        $base = $this->option('path') ?: base_path('templates');
        $indexFile = "{$base}/index.json";
        if (! is_file($indexFile)) {
            $this->error("No index.json found at {$base}");

            return self::FAILURE;
        }

        $index = json_decode(file_get_contents($indexFile), true) ?: [];
        $disk = Storage::disk('public');

        // Restore shared hero background images first — the fabric JSON references
        // them by /storage/templates/{base}.hero.ext, shared across orientations.
        $heroes = 0;
        foreach (glob("{$base}/heroes/*") ?: [] as $hf) {
            $disk->put('templates/'.basename($hf), file_get_contents($hf));
            $heroes++;
        }

        $n = 0;
        foreach ($index as $rec) {
            $ref = $rec['id'] ?? null;
            if (! $ref) {
                continue;
            }
            $jsonFile = "{$base}/json/{$ref}.json";
            if (! is_file($jsonFile)) {
                continue;
            }
            $data = json_decode(file_get_contents($jsonFile), true);
            if (! $data) {
                continue;
            }

            $previewPath = null;
            foreach (['webp', 'png', 'jpg'] as $ext) {
                $pf = "{$base}/previews/{$ref}.{$ext}";
                if (is_file($pf)) {
                    $previewPath = "templates/{$ref}.{$ext}";
                    $disk->put($previewPath, file_get_contents($pf));
                    break;
                }
            }

            Template::updateOrCreate(['ref' => $ref], [
                'name'         => $rec['name'] ?? $this->prettyName($rec['style'] ?? "Template {$ref}"),
                'category'     => $rec['category'] ?? 'business-cards',
                'product_slug' => $rec['product'] ?? null,
                'orientation'  => $rec['orientation'] ?? null,
                'style'        => $rec['style'] ?? null,
                'font'         => $rec['font'] ?? null,
                'score'        => $rec['score'] ?? null,
                'data'         => $data,
                'preview_path' => $previewPath,
                'is_active'    => true,
                'sort_order'   => (int) (preg_replace('/\D/', '', $ref) ?: '0'),
            ]);
            $n++;
        }

        $this->info("Imported/updated {$n} templates + {$heroes} heroes.");

        return self::SUCCESS;
    }

    private function prettyName(string $s): string
    {
        return ucwords(trim(preg_replace('/\s+/', ' ', str_replace('-', ' ', $s))));
    }
}
