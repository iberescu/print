<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportTemplates extends Command
{
    protected $signature = 'templates:import {--path=}';
    protected $description = 'Import generated business-card templates (fabric JSON + previews) into the DB';

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
        $n = 0;

        foreach ($index as $rec) {
            $ref = $rec['id'] ?? null;
            if (! $ref) continue;
            $jsonFile = "{$base}/json/{$ref}.json";
            if (! is_file($jsonFile)) continue;

            $data = json_decode(file_get_contents($jsonFile), true);
            if (! $data) continue;

            $previewPath = null;
            $png = "{$base}/previews/{$ref}.png";
            if (is_file($png)) {
                $previewPath = "templates/{$ref}.png";
                $disk->put($previewPath, file_get_contents($png));
            }

            Template::updateOrCreate(['ref' => $ref], [
                'name'         => $this->prettyName($rec['style'] ?? "Template {$ref}"),
                'category'     => 'business-cards',
                'style'        => $rec['style'] ?? null,
                'font'         => $rec['font'] ?? null,
                'score'        => $rec['score'] ?? null,
                'data'         => $data,
                'preview_path' => $previewPath,
                'is_active'    => true,
                'sort_order'   => (int) $ref,
            ]);
            $n++;
        }

        $this->info("Imported/updated {$n} templates.");
        return self::SUCCESS;
    }

    private function prettyName(string $s): string
    {
        return ucwords(trim(preg_replace('/\s+/', ' ', str_replace('-', ' ', $s))));
    }
}
