<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Surface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import a crawled Vistaprint catalogue (research/data/vistaprint-100.json) into the
 * app: categories, products, print options + values (with price deltas & spec
 * attributes), quantity tiers, and surfaces (finished size, bleed/safety, fold lines,
 * no-print areas). A size/format option value gets its own surface — that's how a
 * print option changes the designer's surface.
 *
 *   php artisan catalog:import --dry                 # parse + report, write nothing
 *   php artisan catalog:import                       # upsert by slug
 *   php artisan catalog:import --fresh               # wipe crawled products first
 *   php artisan catalog:import --file=/path/to.json
 *
 * After import, run:  php artisan images:generate --only=products   (Gemini images)
 *                     php artisan products:seo --generate            (Gemini SEO copy)
 */
class ImportCatalog extends Command
{
    protected $signature = 'catalog:import {--file=} {--fresh} {--dry}';

    protected $description = 'Import a crawled catalogue (products, options, prices, surfaces) into the DB';

    /** Category slug => display name. */
    private const CATEGORIES = [
        'business-cards'      => 'Business Cards',
        'marketing-materials' => 'Marketing Materials',
        'signs-banners'       => 'Signs & Banners',
        'stickers-labels'     => 'Stickers & Labels',
        'stationery'          => 'Stationery',
        'apparel-bags'        => 'Apparel & Bags',
        'other'               => 'More Products',
    ];

    /** Default bleed/safety per unit (finished-size units) when the crawl can't tell us. */
    private const BLEED = ['in' => 0.125, 'mm' => 3, 'cm' => 0.3, 'ft' => 0.05];

    private const SAFETY = ['in' => 0.125, 'mm' => 3, 'cm' => 0.3, 'ft' => 0.1];

    public function handle(): int
    {
        $file = $this->option('file') ?: base_path('database/seed/vistaprint-100.json');
        if (! is_file($file)) {
            $this->error("Crawl file not found: {$file}");

            return self::FAILURE;
        }

        $records = json_decode(file_get_contents($file), true);
        if (! is_array($records) || ! $records) {
            $this->error('No records in crawl file.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry');
        $records = array_values(array_filter($records, fn ($r) => ($r['isProduct'] ?? true) && ! empty($r['quantities'])));
        $this->info(($dry ? '[DRY] ' : '').'Importing '.count($records).' products from '.basename($file));

        if ($this->option('fresh') && ! $dry) {
            $n = Product::query()->delete();
            $this->warn("--fresh: removed {$n} existing products (options/quantities cascaded).");
        }

        $slugs = [];
        $stats = ['products' => 0, 'options' => 0, 'values' => 0, 'tiers' => 0, 'surfaces' => 0];
        $order = 0;

        foreach ($records as $rec) {
            $title = trim((string) ($rec['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $catSlug = $this->categorize($title, (string) ($rec['category'] ?? ''), (string) ($rec['ourCategory'] ?? ''));
            $slug = $this->uniqueSlug($title, $slugs);
            $slugs[] = $slug;

            $quantities = $this->tiers($rec['quantities'] ?? []);
            if (! $quantities) {
                $this->line('  skip (no priced tiers): '.Str::limit($title, 40));

                continue;
            }
            $fromPrice = min(array_map(fn ($q) => $q['total_price'], $quantities));

            $summary = sprintf(
                '  %-34s %-18s $%-7s %d opts / %d tiers%s',
                Str::limit($slug, 33),
                $catSlug,
                number_format($fromPrice, 2),
                count($rec['options'] ?? []),
                count($quantities),
                ($rec['surface']['width'] ?? null) ? "  surface {$rec['surface']['width']}x{$rec['surface']['height']}{$rec['surface']['unit']}" : ''
            );

            if ($dry) {
                $this->line($summary);
                $stats['products']++;

                continue;
            }

            DB::transaction(function () use ($rec, $catSlug, $slug, $title, $quantities, $fromPrice, &$stats, &$order) {
                $category = Category::updateOrCreate(
                    ['slug' => $catSlug],
                    ['name' => self::CATEGORIES[$catSlug], 'is_active' => true, 'sort_order' => array_search($catSlug, array_keys(self::CATEGORIES), true)],
                );

                $productSurface = $this->surfaceFor($rec['surface'] ?? null, $title, $stats);

                $product = $category->products()->updateOrCreate(['slug' => $slug], [
                    'name'            => $title,
                    'tagline'         => Str::limit((string) ($rec['category'] ?? $category->name), 120, ''),
                    'from_price'      => $fromPrice,
                    'supports_design' => true,
                    'supports_upload' => true,
                    'is_active'       => true,
                    'surface_id'      => $productSurface?->id,
                    'sort_order'      => $order++,
                    // link the committed product image if one exists for this slug
                    'image_path'      => Storage::disk('public')->exists("products/{$slug}.webp") ? "products/{$slug}.webp" : null,
                ]);

                $product->options()->delete();
                foreach (array_values($rec['options'] ?? []) as $oi => $opt) {
                    $name = trim((string) ($opt['name'] ?? ''));
                    if ($name === '' || empty($opt['values'])) {
                        continue;
                    }
                    $isColour = (bool) preg_match('/colou?r/i', $name);
                    $option = $product->options()->create([
                        'name'       => $name,
                        'type'       => $isColour ? 'swatch' : 'select',
                        'required'   => true,
                        'sort_order' => $oi,
                    ]);
                    $stats['options']++;
                    foreach (array_values($opt['values']) as $vi => $val) {
                        $label = trim((string) ($val['label'] ?? ''));
                        if ($label === '') {
                            continue;
                        }
                        $dims = $val['dimensions'] ?? null;
                        $valSurface = $dims ? $this->surfaceFor($dims + ['label' => $label], "{$title} {$label}", $stats) : null;
                        $option->values()->create([
                            'label'       => $label,
                            'price_delta' => (float) ($val['priceDelta'] ?? 0),
                            'is_default'  => $vi === 0,
                            'attributes'  => $this->dimAttributes($dims),
                            'surface_id'  => $valSurface?->id,
                            'sort_order'  => $vi,
                        ]);
                        $stats['values']++;
                    }
                }

                $product->quantities()->delete();
                foreach ($quantities as $qi => $q) {
                    $product->quantities()->create($q + ['is_default' => $qi === 0, 'sort_order' => $qi]);
                    $stats['tiers']++;
                }

                $stats['products']++;
            });

            $this->line($summary);
        }

        if (! $dry) {
            $this->markFeatured();
            $this->refineShapedSurfaces();
        }

        $this->newLine();
        $this->info(($dry ? '[DRY] would import: ' : 'Imported: ')
            ."{$stats['products']} products, {$stats['options']} options, {$stats['values']} values, {$stats['tiers']} tiers, {$stats['surfaces']} surfaces.");
        if (! $dry) {
            $this->line('Next: php artisan images:generate --only=products   &&   php artisan products:seo --generate');
        }

        return self::SUCCESS;
    }

    /** Normalise + sort quantity tiers to the DB shape, correcting a common crawl
     *  error: Gemini sometimes captured the PER-UNIT price as the tier total. Real
     *  totals rise with quantity; if the captured values fall (25=$0.36, 50=$0.26…)
     *  they're per-unit, so multiply back by quantity. */
    private function tiers(array $quantities): array
    {
        $out = [];
        foreach ($quantities as $q) {
            $qty = (int) ($q['quantity'] ?? 0);
            $val = (float) ($q['totalPrice'] ?? 0);
            if ($qty < 1 || $val <= 0) {
                continue;
            }
            $out[$qty] = ['quantity' => $qty, 'value' => $val];
        }
        ksort($out);
        $rows = array_values($out);

        $perUnit = count($rows) >= 2 && $rows[0]['value'] > $rows[count($rows) - 1]['value'];

        return array_map(function ($r) use ($perUnit) {
            $total = $perUnit ? $r['value'] * $r['quantity'] : $r['value'];

            return ['quantity' => $r['quantity'], 'total_price' => round($total, 2), 'unit_price' => round($total / $r['quantity'], 4)];
        }, $rows);
    }

    /** Get-or-create a surface from crawled dimensions; null when there are none. */
    private function surfaceFor(?array $s, string $context, array &$stats): ?Surface
    {
        if (! $s) {
            return null;
        }
        $w = (float) ($s['width'] ?? 0);
        $h = (float) ($s['height'] ?? 0);
        $unit = in_array($s['unit'] ?? '', ['in', 'mm', 'cm', 'ft'], true) ? $s['unit'] : 'in';
        if ($w <= 0 || $h <= 0) {
            return null;
        }

        $folded = (bool) ($s['folded'] ?? false) || ! empty($s['fold']);
        $cut = is_string($s['cut'] ?? null) && strlen($s['cut']) > 10 ? $s['cut'] : null;
        $trim = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        $tw = $trim($w);
        $th = $trim($h);
        // dimension surfaces are SHARED across products — a die-cut outline makes the
        // surface product-specific, so give it a dedicated slug (oval and plain cards
        // share 88.9x50.8 but must not share a cut path)
        $slug = 's-'.$tw.'x'.$th.$unit.($folded ? '-fold' : '')
            .($cut ? '-cut-'.Str::slug(Str::limit($context, 28, '')) : '');

        // Prefer real geometry read from the spec template SVG; else print-standard / heuristic.
        $bleed = is_numeric($s['bleed'] ?? null) && $s['bleed'] > 0 ? (float) $s['bleed'] : (self::BLEED[$unit] ?? 0);
        $safety = is_numeric($s['safety'] ?? null) && $s['safety'] >= 0 ? (float) $s['safety'] : (self::SAFETY[$unit] ?? 0);

        $foldLines = [];
        if (! empty($s['fold']) && is_array($s['fold'])) {
            foreach ($s['fold'] as $f) {
                if (! is_numeric($f['position'] ?? null)) {
                    continue;
                }
                $foldLines[] = ['label' => 'Fold', 'orientation' => ($f['orientation'] ?? 'vertical') === 'horizontal' ? 'horizontal' : 'vertical', 'position' => round((float) $f['position'], 2)];
            }
        } elseif ($folded) {
            $vertical = ($s['foldOrientation'] ?? 'vertical') !== 'horizontal';
            $foldLines[] = ['label' => 'Fold', 'orientation' => $vertical ? 'vertical' : 'horizontal', 'position' => round(($vertical ? $w : $h) / 2, 2)];
        }

        $noPrint = [];
        if (! empty($s['noPrint']) && is_array($s['noPrint'])) {
            foreach ($s['noPrint'] as $z) {
                if (! is_numeric($z['w'] ?? null) || ! is_numeric($z['h'] ?? null)) {
                    continue;
                }
                $noPrint[] = [
                    'label' => Str::limit((string) ($z['label'] ?? 'No print'), 24, ''),
                    'x' => round((float) ($z['x'] ?? 0), 2), 'y' => round((float) ($z['y'] ?? 0), 2),
                    'w' => round((float) $z['w'], 2), 'h' => round((float) $z['h'], 2),
                ];
            }
        } else {
            $note = (string) ($s['noPrintNote'] ?? '');
            if ($note !== '' && preg_match('/pocket|pole|grommet|hem/i', $note)) {
                $band = round($h * 0.06, 2);
                $noPrint[] = ['label' => Str::limit($note, 24, ''), 'x' => 0, 'y' => 0, 'w' => $w, 'h' => $band];
                if (preg_match('/top.*bottom|both|pole/i', $note)) {
                    $noPrint[] = ['label' => 'Bottom '.Str::limit($note, 16, ''), 'x' => 0, 'y' => $h - $band, 'w' => $w, 'h' => $band];
                }
            }
        }

        $existing = Surface::where('slug', $slug)->first();
        if ($existing) {
            if ($cut && ! $existing->cut_path) {
                $existing->update(['cut_path' => $cut]);
            }

            return $existing;
        }
        $stats['surfaces']++;

        return Surface::create([
            'name'           => trim("{$tw} × {$th} {$unit}".($folded ? ' (folded)' : '')),
            'slug'           => $slug,
            'unit'           => $unit,
            'width'          => $w,
            'height'         => $h,
            'bleed'          => $bleed,
            'safety'         => $safety,
            'no_print_areas' => $noPrint,
            'fold_lines'     => $foldLines,
            'cut_path'       => $cut,
            'is_active'      => true,
        ]);
    }

    /** Dimensions -> [{name:Width,value:"210 mm"}, {name:Height,...}] spec attributes. */
    private function dimAttributes(?array $dims): array
    {
        if (! $dims || ! ($dims['width'] ?? null)) {
            return [];
        }
        $unit = $dims['unit'] ?? 'in';

        return [
            ['name' => 'Width', 'value' => "{$dims['width']} {$unit}"],
            ['name' => 'Height', 'value' => "{$dims['height']} {$unit}"],
        ];
    }

    /**
     * Map a product to a catalog category from its TITLE (clean — avoids the
     * "signs-posters" URL-path pollution). Signage/stickers are checked before
     * marketing so posters/yard-signs/banners don't get stolen by /poster/.
     */
    private function categorize(string $title, string $vpCategory, string $fallback): string
    {
        $s = strtolower($title.' '.$vpCategory);

        return match (true) {
            (bool) preg_match('/business.?card/', $s)                                                                        => 'business-cards',
            (bool) preg_match('/sticker|\blabel/', $s)                                                                       => 'stickers-labels',
            (bool) preg_match('/banner|poster|yard.?sign|lawn.?sign|a.?frame|\bsign|decal|cling|feather|flag|tablecloth|backdrop|foam|car.?magnet|point of sale/', $s) => 'signs-banners',
            (bool) preg_match('/flyer|postcard|brochure|leaflet|menu|greeting|calendar|door.?hanger|literature/', $s)        => 'marketing-materials',
            (bool) preg_match('/letterhead|envelope|notepad|notebook|stationery|folder|certificate|stamp|bookmark/', $s)     => 'stationery',
            (bool) preg_match('/t.?shirt|shirt|tote|\bbag|hoodie|\bhat|\bcap|apparel|clothing|polo|mug|drinkware|\bpen/', $s) => 'apparel-bags',
            default => in_array($fallback, array_keys(self::CATEGORIES), true) ? $fallback : 'other',
        };
    }

    /**
     * Curate the home "Most Popular" row: one representative product per type
     * (Business Cards, Flyers, Postcards, Door Hangers, Posters, Banners, Flags,
     * Stickers). Only seeds when nothing is featured yet, so it won't clobber
     * admin curation on a re-import.
     */
    private function markFeatured(): void
    {
        if (Product::where('featured', true)->exists()) {
            return;
        }
        foreach (['business card', 'flyer', 'postcard', 'door hanger', 'poster', 'banner', 'flag', 'sticker'] as $kw) {
            Product::where('is_active', true)->where('name', 'like', "%{$kw}%")
                ->orderBy('sort_order')->first()?->update(['featured' => true]);
        }
        $this->line('  featured '.Product::where('featured', true)->count().' products for the home Most Popular row.');
    }

    /**
     * Shaped products (feather flags, die-cut cards) aren't plain rectangles — the
     * crawl can't capture their outline, so apply known real-world geometry: a die-cut
     * `cut_path` (normalized 0–100 vs the trim box) and no-print zones (pole sleeve).
     * Each product gets a DEDICATED surface — never mutate a shared dimension surface
     * (s-88.9x50.8mm is also used by standard rectangular cards).
     */
    private function refineShapedSurfaces(): void
    {
        // Feather / quill outline: straight pole edge left, sweeping curve right, tapered tail.
        $feather = 'M 0 100 L 0 6 Q 0 0 10 0 Q 55 0 78 8 Q 100 18 99 42 Q 97 68 84 84 Q 72 96 58 100 Z';
        // Full ellipse (non-uniform trim box turns it into the oval automatically).
        $ellipse = 'M 50 0 A 50 50 0 1 0 50 100 A 50 50 0 1 0 50 0 Z';
        // Leaf: opposite rounded corners.
        $leaf = 'M 0 45 Q 0 0 45 0 L 100 0 L 100 55 Q 100 100 55 100 L 0 100 Z';

        $shapes = [
            ['match' => 'feather flag', 'slug' => 'feather-flag', 'name' => 'Feather Flag 2.4 × 7.5 ft', 'unit' => 'ft',
                'width' => 2.4, 'height' => 7.5, 'bleed' => 0.05, 'safety' => 0.15, 'cut' => $feather,
                'no_print' => [
                    ['label' => 'Pole sleeve', 'x' => 0, 'y' => 0, 'w' => 0.3, 'h' => 7.5],
                    ['label' => 'Bottom hem', 'x' => 0.3, 'y' => 7.35, 'w' => 1.1, 'h' => 0.15],
                ]],
            ['match' => 'circle business card', 'slug' => 'circle-business-card', 'name' => 'Circle Card Ø 63.5 mm', 'unit' => 'mm',
                'width' => 63.5, 'height' => 63.5, 'bleed' => 3.18, 'safety' => 3, 'cut' => $ellipse, 'no_print' => []],
            ['match' => 'oval business card', 'slug' => 'oval-business-card', 'name' => 'Oval Card 88.9 × 50.8 mm', 'unit' => 'mm',
                'width' => 88.9, 'height' => 50.8, 'bleed' => 3.18, 'safety' => 3, 'cut' => $ellipse, 'no_print' => []],
            ['match' => 'leaf business card', 'slug' => 'leaf-business-card', 'name' => 'Leaf Card 88.9 × 50.8 mm', 'unit' => 'mm',
                'width' => 88.9, 'height' => 50.8, 'bleed' => 3.18, 'safety' => 3, 'cut' => $leaf, 'no_print' => []],
            // rectangular, but the graphic clamps into a top bar and rolls into the base cassette
            ['match' => 'retractable banner', 'slug' => 'retractable-banner-596', 'name' => 'Retractable Banner 596 × 1575 mm', 'unit' => 'mm',
                'width' => 596, 'height' => 1575, 'bleed' => 3, 'safety' => 15, 'cut' => null,
                'no_print' => [
                    ['label' => 'Top clamp bar', 'x' => 0, 'y' => 0, 'w' => 596, 'h' => 30],
                    ['label' => 'Rolls into base', 'x' => 0, 'y' => 1475, 'w' => 596, 'h' => 100],
                ]],
        ];

        foreach ($shapes as $s) {
            $product = Product::with('surface')->where('name', 'like', "%{$s['match']}%")->first();
            if (! $product) {
                continue;
            }
            // the crawler-extracted outline (from Vistaprint's own template) always wins;
            // this curated geometry is only a fallback when Vistaprint provided nothing.
            // Curated NO-PRINT zones (pole sleeves…) still merge in — templates don't carry them.
            if ($product->surface?->cut_path) {
                if (empty($product->surface->no_print_areas) && ! empty($s['no_print'])) {
                    $product->surface->update(['no_print_areas' => $s['no_print'], 'name' => $s['name']]);
                    $this->line("  shaped surface: {$product->name} -> crawler outline + curated no-print zones");
                } else {
                    $this->line("  shaped surface: {$product->name} -> crawler template outline (fallback skipped)");
                }

                continue;
            }
            $surface = Surface::updateOrCreate(['slug' => $s['slug']], [
                'name'           => $s['name'],
                'unit'           => $s['unit'],
                'width'          => $s['width'],
                'height'         => $s['height'],
                'bleed'          => $s['bleed'],
                'safety'         => $s['safety'],
                'no_print_areas' => $s['no_print'],
                'fold_lines'     => [],
                'cut_path'       => $s['cut'],
                'is_active'      => true,
            ]);
            $product->update(['surface_id' => $surface->id]);
            // the designer prefers the default option value's surface — clear value-level
            // links so the refined product surface (with its cut/no-print) actually applies
            foreach ($product->options as $o) {
                $o->values()->update(['surface_id' => null]);
            }
            $this->line("  shaped surface: {$product->name} -> {$surface->name}");
        }
    }

    private function uniqueSlug(string $title, array $taken): string
    {
        $base = Str::slug($title) ?: 'product';
        $slug = $base;
        $i = 2;
        while (in_array($slug, $taken, true)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
