<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ingest AI-generated templates from a staging dir into the DB. Layout:
 *   {path}/{productKey}/meta.json   (array of {ref,style,font,score,cat,product,hero})
 *   {path}/{productKey}/{ref}.json  (fabric JSON)
 *   {path}/{productKey}/{ref}.png   (rendered preview)
 * Each product key maps to an orientation (canvas aspect the design was authored for).
 */
class IngestGeneratedTemplates extends Command
{
    protected $signature = 'templates:ingest {path} {--fresh : delete existing generated templates for the products first}';

    protected $description = 'Import AI-generated templates (json + preview) with product/orientation scope';

    private const ORIENT = [
        'bcard' => 'landscape', 'flyer' => 'portrait', 'poster' => 'portrait',
        'postcard' => 'landscape', 'letterhead' => 'portrait', 'banner' => 'portrait',
    ];

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');
        $disk = Storage::disk('public');
        $total = 0;

        foreach (glob("{$path}/*", GLOB_ONLYDIR) as $dir) {
            $pk = basename($dir);
            $metaFile = "{$dir}/meta.json";
            if (! is_file($metaFile)) {
                continue;
            }
            $meta = json_decode(file_get_contents($metaFile), true) ?: [];
            $orientation = self::ORIENT[$pk] ?? null;

            if ($this->option('fresh') && $meta) {
                Template::where('product_slug', $meta[0]['product'] ?? '___')->where('ref', 'like', "{$pk}-%")->delete();
            }

            foreach ($meta as $m) {
                $ref = $m['ref'];
                $json = "{$dir}/{$ref}.json";
                $png = "{$dir}/{$ref}.png";
                if (! is_file($json) || ! is_file($png)) {
                    continue;
                }
                $disk->put("templates/{$ref}.png", file_get_contents($png));
                // Hero images are shared across a design's orientations and named by the
                // design base (e.g. bcard-001.hero.jpg), which is what the fabric JSON's
                // /storage/templates/{base}.hero.* src points at — copy by heroBase, not ref.
                $heroBase = $m['heroBase'] ?? $ref;
                foreach (glob("{$dir}/{$heroBase}.hero.*") as $hf) {
                    $disk->put('templates/'.basename($hf), file_get_contents($hf));
                }
                Template::updateOrCreate(['ref' => $ref], [
                    'name'         => Str::of($m['style'] ?? 'B2B')->limit(60),
                    'category'     => $m['cat'] ?? 'business-cards',
                    'product_slug' => $m['product'] ?? null,
                    'orientation'  => $m['orientation'] ?? $orientation,
                    'style'        => $m['style'] ?? null,
                    'font'         => $m['font'] ?? null,
                    'score'        => $m['score'] ?? 7,
                    'data'         => json_decode(file_get_contents($json), true),
                    'preview_path' => "templates/{$ref}.png",
                    'is_active'    => true,
                    'sort_order'   => (int) (preg_replace('/\D/', '', $ref) ?: '0'),
                ]);
                $total++;
            }
            $this->info("  {$pk}: ".count($meta)." templates");
        }

        $this->info("Ingested {$total} templates.");

        return self::SUCCESS;
    }
}
