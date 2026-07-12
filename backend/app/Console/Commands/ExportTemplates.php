<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Export the templates table into a reproducible import bundle so that
 * `templates:import` can rebuild an identical catalogue on any environment
 * (this is the inverse of ImportTemplates and keeps template state in git):
 *   {base}/index.json            — [{id,product,orientation,category,name,style,font,score}]
 *   {base}/json/{ref}.json       — fabric JSON
 *   {base}/previews/{ref}.png    — rendered gallery preview
 *   {base}/heroes/{file}         — shared AI background images the JSON references
 */
class ExportTemplates extends Command
{
    protected $signature = 'templates:export {--path=}';
    protected $description = 'Export DB templates (fabric JSON + previews + heroes) into an import bundle';

    public function handle(): int
    {
        $base = $this->option('path') ?: base_path('templates');
        @mkdir("{$base}/json", 0777, true);
        @mkdir("{$base}/previews", 0777, true);
        @mkdir("{$base}/heroes", 0777, true);

        $disk = Storage::disk('public');
        $index = [];
        $n = 0;
        $missingPreview = 0;

        Template::where('is_active', true)
            ->orderBy('id')
            ->chunkById(50, function ($chunk) use ($base, $disk, &$index, &$n, &$missingPreview) {
                foreach ($chunk as $t) {
                    file_put_contents(
                        "{$base}/json/{$t->ref}.json",
                        json_encode($t->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    );

                    if ($t->preview_path && $disk->exists($t->preview_path)) {
                        file_put_contents("{$base}/previews/{$t->ref}.png", $disk->get($t->preview_path));
                    } else {
                        $missingPreview++;
                    }

                    $index[] = [
                        'id'          => $t->ref,
                        'product'     => $t->product_slug,
                        'orientation' => $t->orientation,
                        'category'    => $t->category,
                        'name'        => $t->name,
                        'style'       => $t->style,
                        'font'        => $t->font,
                        'score'       => $t->score === null ? null : (float) $t->score,
                    ];
                    $n++;
                }
            });

        // Hero background images are shared across a design's orientations and referenced
        // by the fabric JSON (src=/storage/templates/{base}.hero.ext); ship them all.
        $heroes = 0;
        foreach ($disk->files('templates') as $f) {
            if (str_contains(basename($f), '.hero.')) {
                file_put_contents("{$base}/heroes/".basename($f), $disk->get($f));
                $heroes++;
            }
        }

        file_put_contents("{$base}/index.json", json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Exported {$n} templates + {$heroes} heroes to {$base} ({$missingPreview} missing previews).");

        return self::SUCCESS;
    }
}
