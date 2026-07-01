<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Export the templates table back into a reproducible import bundle
 * ({base}/index.json + json/{ref}.json + previews/{ref}.png) so that
 * `templates:import` can rebuild an identical catalogue on any environment.
 * This is the inverse of ImportTemplates and keeps template state in git.
 */
class ExportTemplates extends Command
{
    protected $signature = 'templates:export {--path=}';
    protected $description = 'Export DB templates (fabric JSON + previews) into an import bundle';

    public function handle(): int
    {
        $base = $this->option('path') ?: base_path('templates');
        @mkdir("{$base}/json", 0777, true);
        @mkdir("{$base}/previews", 0777, true);

        $disk = Storage::disk('public');
        $index = [];
        $n = 0;
        $missingPreview = 0;

        Template::where('category', 'business-cards')
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
                        'id'    => $t->ref,
                        'style' => $t->style,
                        'font'  => $t->font,
                        'score' => $t->score === null ? null : (float) $t->score,
                    ];
                    $n++;
                }
            });

        file_put_contents("{$base}/index.json", json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Exported {$n} templates to {$base} ({$missingPreview} missing previews).");

        return self::SUCCESS;
    }
}
