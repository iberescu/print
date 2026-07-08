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
        'accessories'         => 'Accessories',
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
        // out-of-scope product types (user: no packaging) — belt & braces on top of the crawler filter
        $exclude = '/packaging|pizza box|deli paper|tissue paper|\bribbon\b|wrapping paper|crinkle|mailer box|shipping box|sos bag|clamshell|product box|\bpouch|butcher paper|take-?out|paper bag|to-?go bag|plastic cup/i';
        $records = array_values(array_filter($records, fn ($r) => ! preg_match($exclude, (string) ($r['title'] ?? ''))));
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

                // upsert GLOBALLY by slug (slugs are unique) so a product whose category
                // mapping changed MOVES category instead of colliding on insert
                $product = Product::updateOrCreate(['slug' => $slug], [
                    'category_id'     => $category->id,
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
                    // "Option N" placeholders (a dropdown Gemini couldn't label) get a
                    // proper name derived from what their values actually are
                    if (preg_match('/^Option \d+$/i', $name)) {
                        $name = $this->deriveOptionName(array_map(fn ($v) => (string) ($v['label'] ?? ''), $opt['values'])) ?? $name;
                    }
                    $isColour = (bool) preg_match('/colou?r/i', $name);
                    $option = $product->options()->create([
                        'name'       => $name,
                        'type'       => $isColour ? 'swatch' : 'select',
                        'required'   => true,
                        'sort_order' => $oi,
                    ]);
                    $stats['options']++;
                    $kept = 0;
                    foreach (array_values($opt['values']) as $vi => $val) {
                        $label = trim((string) ($val['label'] ?? ''));
                        // crawl noise: section headers/disclaimers/dropdown placeholders read as values
                        if ($label === '' || preg_match('/not available|other selections|^see |^select\b|^choose\b/i', $label)) {
                            continue;
                        }
                        $dims = $val['dimensions'] ?? null;
                        $valSurface = $dims ? $this->surfaceFor($dims + ['label' => $label], "{$title} {$label}", $stats) : null;
                        // colour swatches: the crawl leaves the hex in the label
                        // ("Blue - #0083CA") or just a name ("Navy Blue") — pull a
                        // hex either way so the product page shows the real colour,
                        // and clean the hex out of the shown label
                        $swatch = $isColour ? $this->swatchFor($label) : null;
                        if ($swatch) {
                            $label = trim(preg_replace('/[\s\-–—|:]*#?[0-9a-f]{6}\b/i', '', $label)) ?: $label;
                        }
                        // options:previews images are keyed by slug+labels — relink the
                        // generated file, or a fresh import silently strips the final
                        // step's material cards (383 orphaned previews on prod, 07-08)
                        $preview = sprintf('option-previews/%s/%s-%s.webp', $slug, Str::slug($name), Str::slug($label));
                        $option->values()->create([
                            'label'       => $label,
                            'price_delta' => (float) ($val['priceDelta'] ?? 0),
                            'is_default'  => $kept === 0, // first KEPT value
                            'attributes'  => $this->dimAttributes($dims),
                            'surface_id'  => $valSurface?->id,
                            'swatch'      => $swatch,
                            'image_path'  => Storage::disk('public')->exists($preview) ? $preview : null,
                            'sort_order'  => $vi,
                        ]);
                        $kept++;
                        $stats['values']++;
                    }
                    if ($kept === 0) {
                        $option->delete();      // every value was crawl noise ("Select...")
                        $stats['options']--;
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
            $this->wireShapeSurfaces();
            $this->refineShapedSurfaces();   // curated products win: clears value-level wiring
            $this->refineNamedDies();
            $this->wireCornerSurfaces();
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

        // Prefer real geometry read from the spec template SVG; else print-standard /
        // heuristic. Crawled values are only trusted within sane print bounds — Gemini
        // once returned a 1730mm bleed on a 54mm surface — and even the standard
        // default is capped so tiny formats (0.5" labels) keep a usable canvas.
        $maxMargin = 0.15 * min($w, $h);
        $bleed = is_numeric($s['bleed'] ?? null) && $s['bleed'] > 0 && $s['bleed'] <= $maxMargin
            ? (float) $s['bleed'] : min(self::BLEED[$unit] ?? 0, $maxMargin);
        $safety = is_numeric($s['safety'] ?? null) && $s['safety'] >= 0 && $s['safety'] <= $maxMargin
            ? (float) $s['safety'] : min(self::SAFETY[$unit] ?? 0, $maxMargin);

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
            $fix = [];
            if ($cut && ! $existing->cut_path) {
                $fix['cut_path'] = $cut;
            }
            // repair out-of-bounds margins from earlier imports (admin refinements
            // within sane bounds are left alone)
            if ((float) $existing->bleed > $maxMargin) {
                $fix['bleed'] = $bleed;
            }
            if ((float) $existing->safety > $maxMargin) {
                $fix['safety'] = $safety;
            }
            if ($fix) {
                $existing->update($fix);
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

    /** Common print-colour names → hex, for the product-page swatch dots. */
    private const COLOUR_HEX = [
        'white' => '#ffffff', 'black' => '#1a1a1a', 'natural' => '#e8dcc4', 'kraft' => '#c8a97e',
        'cream' => '#f5efe0', 'ivory' => '#fffff0', 'grey' => '#8a8d91', 'gray' => '#8a8d91',
        'silver' => '#c0c0c0', 'gold' => '#c9a227', 'rose gold' => '#b76e79', 'copper' => '#b87333',
        'bronze' => '#cd7f32', 'red' => '#e11d2f', 'dark red' => '#8b1a1a', 'maroon' => '#7d1128',
        'burgundy' => '#6d1a2e', 'orange' => '#f57c1f', 'yellow' => '#ffd400', 'lime' => '#9acd32',
        'green' => '#2e9e4f', 'dark green' => '#1c5e34', 'forest green' => '#1c5e34', 'teal' => '#0099b1',
        'turquoise' => '#30d5c8', 'cyan' => '#00b7eb', 'blue' => '#0083ca', 'royal blue' => '#1f4fd8',
        'navy' => '#1b2a4a', 'navy blue' => '#1b2a4a', 'dark blue' => '#14274e', 'light blue' => '#8ecae6',
        'sky blue' => '#8ecae6', 'purple' => '#6b3fa0', 'violet' => '#7f4fc9', 'pink' => '#f26fb2',
        'hot pink' => '#ff69b4', 'magenta' => '#d0208f', 'brown' => '#6b4423', 'tan' => '#d2b48c',
        'beige' => '#e8dcc4', 'clear' => '#e9f2f5', 'frosted' => '#dbe4e6', 'transparent' => '#e9f2f5',
        'holographic' => '#c9d6e5',
    ];

    /**
     * A hex colour for a swatch value: a hex embedded in the label wins
     * ("Blue - #0083CA"), else the colour name maps to a standard hex; the
     * longest matching name wins ("dark blue" beats "blue"). Null = unknown.
     */
    private function swatchFor(string $label): ?string
    {
        if (preg_match('/#?([0-9a-f]{6})\b/i', $label, $m)) {
            return '#'.strtolower($m[1]);
        }
        $l = strtolower($label);
        $best = null;
        foreach (self::COLOUR_HEX as $name => $hex) {
            if (str_contains($l, $name) && (! $best || strlen($name) > strlen($best[0]))) {
                $best = [$name, $hex];
            }
        }

        return $best[1] ?? null;
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
            // non-printable accessories (card holders/cases/stands) join the upsell pool
            (bool) preg_match('/\b(holder|case|stand)s?\b/', $s)                                                             => 'accessories',
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

    /** mm per unit, for scaling curated geometry to a surface's unit. */
    private const MM_PER_UNIT = ['mm' => 1.0, 'cm' => 10.0, 'in' => 25.4, 'ft' => 304.8];

    /**
     * Exact parametric die outlines (normalized 0–100 over the trim box; the box's
     * aspect ratio shapes them — an ellipse on a non-square box IS the oval). These
     * are geometric primitives, not copied product dies; Vistaprint's own template,
     * when the crawler captured one, still wins at the product level.
     */
    private const SHAPE_PATHS = [
        'ellipse'     => 'M 50 0 A 50 50 0 1 0 50 100 A 50 50 0 1 0 50 0 Z',
        'leaf'        => 'M 0 45 Q 0 0 45 0 L 100 0 L 100 55 Q 100 100 55 100 L 0 100 Z',
        'half-circle' => 'M 0 100 A 50 100 0 0 1 100 100 Z',
        'hexagon'     => 'M 25 0 L 75 0 L 100 50 L 75 100 L 25 100 Z',
        'star'        => 'M 50 0 L 61.8 38.1 L 100 38.1 L 69.1 61.8 L 80.9 100 L 50 76.4 L 19.1 100 L 30.9 61.8 L 0 38.1 L 38.2 38.1 Z',
        'heart'       => 'M 50 100 C 24 80 0 58 0 30 C 0 12 14 0 29 0 C 38 0 46 5 50 13 C 54 5 62 0 71 0 C 86 0 100 12 100 30 C 100 58 76 80 50 100 Z',
        'arrow'       => 'M 0 28 L 62 28 L 62 0 L 100 50 L 62 100 L 62 72 L 0 72 Z',
        'shield'      => 'M 50 100 C 22 88 0 68 0 38 L 0 0 L 100 0 L 100 38 C 100 68 78 88 50 100 Z',
    ];

    /**
     * A "Shape" option value changes the finished die — wire each recognizable value
     * to a dedicated surface carrying the right cut (Circle/Oval → ellipse, Rounded
     * Rectangle → arcs, Heart/Star/Hexagon → primitives…). A flat value (Rectangle,
     * Square, Custom die-cut) gets a surface WITHOUT a cut so it can clear a die-cut
     * product default — this is what lets "Product Labels" stop being a circle when
     * the shopper picks Rectangle. Exotic dies we can't synthesize honestly
     * (Graduation Cap, House, Football, pet faces…) are left unwired: the canvas
     * keeps the product default and the audit reports them.
     */
    private function wireShapeSurfaces(): void
    {
        $wired = 0;
        $skipped = [];

        foreach (Product::with('surface', 'options.values.surface')->get() as $p) {
            foreach ($p->options as $o) {
                if (! preg_match('/shape/i', $o->name) || $o->values->count() < 2) {
                    continue; // single-value shape options describe the product itself (Oval BCs)
                }
                // surfaceless products (yard signs, most stickers) still carry dims
                // in their Size option labels — first one that parses wins
                $parsed = null;
                if (! $p->surface) {
                    $sizeOpt = $p->options->first(fn ($so) => preg_match('/size|format|dimension/i', $so->name));
                    foreach ($sizeOpt?->values->sortByDesc('is_default') ?? [] as $sv) {
                        if ($parsed = \App\Support\PrintSpec::parsedDims($sv->label, $p)) {
                            break;
                        }
                    }
                }

                foreach ($o->values as $v) {
                    $kind = self::classifyShape($v->label);
                    if ($kind === null) {
                        $skipped[] = "{$p->slug}: {$v->label}";

                        continue;
                    }
                    if ($kind === 'keep') {
                        continue; // the value's crawled dims surface (if any) already does the job
                    }

                    // dims: the value's own crawled surface, else the product's, else the size label
                    $src = $v->surface ?? $p->surface;
                    if (! $src && $parsed) {
                        [$pw, $ph, , $punit] = $parsed;
                        $max = 0.15 * min($pw, $ph);
                        $src = new Surface([   // unsaved carrier — just dims + margins
                            'unit'   => $punit,
                            'width'  => $pw,
                            'height' => $ph,
                            'bleed'  => min(self::BLEED[$punit] ?? 0, $max),
                            'safety' => min(self::SAFETY[$punit] ?? 0, $max),
                        ]);
                    }
                    if (! $src) {
                        $skipped[] = "{$p->slug}: {$v->label} (no dims)";

                        continue;
                    }
                    $w = (float) $src->width;
                    $h = (float) $src->height;
                    // a square-ish shape squares the canvas (Circle sticker on a 2×4 base)
                    if ($this->wantsSquare($v->label)) {
                        $w = $h = min($w, $h);
                    }

                    $cut = $this->shapeCutPath($kind, $w, $h, $src->unit);
                    // a flat shape only needs its own surface to CLEAR a die-cut
                    // product default or to change the dims (Square on a rect base)
                    if ($kind === 'flat' && ! $p->surface?->cut_path
                        && abs($w - (float) $src->width) < 0.001 && abs($h - (float) $src->height) < 0.001) {
                        continue;
                    }
                    // Square/Rectangle on a die-cut product re-boxes the product's own
                    // die (a square rounded-corner card keeps its rounded corners);
                    // only "Custom" flattens — there the die follows the artwork
                    if ($kind === 'flat' && $p->surface?->cut_path && ! str_contains(strtolower($v->label), 'custom')) {
                        $cut = $p->surface->cut_path;
                    }

                    $trim = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                    $slug = Str::limit('s-'.$trim($w).'x'.$trim($h).$src->unit.'-shape-'.Str::slug($v->label).'-'.$p->slug, 96, '');
                    $surface = Surface::updateOrCreate(['slug' => $slug], [
                        'name'           => trim($trim($w).' × '.$trim($h).' '.$src->unit." ({$v->label})"),
                        'unit'           => $src->unit,
                        'width'          => $w,
                        'height'         => $h,
                        'bleed'          => $src->bleed,
                        'safety'         => $src->safety,
                        'no_print_areas' => [],
                        'fold_lines'     => [],
                        'cut_path'       => $cut,
                        'is_active'      => true,
                    ]);
                    $v->update(['surface_id' => $surface->id]);
                    $wired++;
                }
            }
        }
        // drop shape surfaces a re-import stopped referencing (rule changes leave orphans)
        $orphans = Surface::where('slug', 'like', '%-shape-%')
            ->whereNotIn('id', \App\Models\OptionValue::whereNotNull('surface_id')->pluck('surface_id'))
            ->whereNotIn('id', Product::whereNotNull('surface_id')->pluck('surface_id'))
            ->delete();

        if ($wired) {
            $this->line("  shape surfaces: wired {$wired} shape values to dedicated surfaces".($orphans ? ", pruned {$orphans} orphans" : '').'.');
        }
        if ($skipped) {
            $this->line('  shape surfaces: left '.count($skipped).' exotic values unwired ('.Str::limit(implode('; ', array_slice($skipped, 0, 4)), 110).'…).');
        }
    }

    /** Bucket a shape label; null = a die we can't synthesize honestly. (Public: surfaces:audit mirrors this.) */
    public static function classifyShape(string $label): ?string
    {
        $l = strtolower(trim(preg_replace('/\s*new\s*$/i', '', $label))); // "HeartNew" → heart

        return match (true) {
            str_contains($l, 'custom')                        => 'flat', // die follows the artwork; honest canvas is flat
            // "Standard" describes the product's own format (Rounded Corner BCs:
            // Standard vs Square SIZES) — never rewire, the product die/dims stay
            str_contains($l, 'standard')                      => 'keep',
            str_contains($l, 'rounded')                       => 'rounded',
            (bool) preg_match('/half\s*(circle|moon)/', $l)   => 'half-circle',
            (bool) preg_match('/half\s*left\s*arch/', $l)     => 'arch-left',
            (bool) preg_match('/half\s*right\s*arch/', $l)    => 'arch-right',
            (bool) preg_match('/\barch(way)?\b/', $l)         => 'arch',
            (bool) preg_match('/circle|oval|\bround\b/', $l)  => 'ellipse',
            str_contains($l, 'leaf')                          => 'leaf',
            str_contains($l, 'hexagon')                       => 'hexagon',
            str_contains($l, 'starburst')                     => 'starburst',
            str_contains($l, 'star')                          => 'star',
            str_contains($l, 'heart')                         => 'heart',
            str_contains($l, 'arrow')                         => 'arrow',
            str_contains($l, 'shield')                        => 'shield',
            (bool) preg_match('/rectangle|square/', $l)       => 'flat',
            default                                           => null, // Graduation Cap, House, Football, Jar, pet faces…
        };
    }

    /** Square the canvas for shapes that are square by definition. */
    private function wantsSquare(string $label): bool
    {
        $l = strtolower($label);
        $square = str_contains($l, 'square')
            || (preg_match('/^circle\b/', $l) === 1 && ! str_contains($l, 'oval'));

        return $square && ! str_contains($l, 'rectangle'); // "Square/Rectangle" keeps the base dims
    }

    /** The cut path for a shape kind at given dims (null = flat, no die). */
    private function shapeCutPath(string $kind, float $w, float $h, string $unit): ?string
    {
        if ($kind === 'flat') {
            return null;
        }
        if ($kind === 'rounded') {
            return $this->roundedCutPath($w, $h, $unit);
        }
        if ($kind === 'starburst') {
            $pts = [];
            for ($i = 0; $i < 24; $i++) {
                $r = $i % 2 === 0 ? 50 : 41;
                $a = -M_PI / 2 + $i * M_PI / 12;
                $pts[] = round(50 + $r * cos($a), 1).' '.round(50 + $r * sin($a), 1);
            }

            return 'M '.implode(' L ', $pts).' Z';
        }
        if (in_array($kind, ['arch', 'arch-left', 'arch-right'], true)) {
            // arch dome radius = half width (full) or full width (halves), capped
            $ry = min(95, round(100 * ($kind === 'arch' ? $w / 2 : $w) / max($h, 0.01), 1));

            return match ($kind) {
                'arch'       => "M 0 100 L 0 {$ry} A 50 {$ry} 0 0 1 100 {$ry} L 100 100 Z",
                'arch-left'  => "M 0 100 L 0 {$ry} A 100 {$ry} 0 0 1 100 0 L 100 100 Z",
                'arch-right' => "M 0 100 L 0 0 A 100 {$ry} 0 0 1 100 {$ry} L 100 100 Z",
            };
        }

        return self::SHAPE_PATHS[$kind] ?? null;
    }

    /** Rounded-rectangle die (≈3.5 mm corner radius) normalized to a w×h trim box. */
    private function roundedCutPath(float $w, float $h, string $unit): string
    {
        $r = 3.5 / (self::MM_PER_UNIT[$unit] ?? 1.0);
        $rx = round(100 * $r / max($w, 0.01), 2);
        $ry = round(100 * $r / max($h, 0.01), 2);

        return 'M '.$rx.' 0 L '.(100 - $rx).' 0 A '.$rx.' '.$ry.' 0 0 1 100 '.$ry
            .' L 100 '.(100 - $ry).' A '.$rx.' '.$ry.' 0 0 1 '.(100 - $rx).' 100'
            .' L '.$rx.' 100 A '.$rx.' '.$ry.' 0 0 1 0 '.(100 - $ry)
            .' L 0 '.$ry.' A '.$rx.' '.$ry.' 0 0 1 '.$rx.' 0 Z';
    }

    /**
     * A product whose NAME declares its die (Circle Stickers, Oval Stickers,
     * Rounded Corner Postcards, Circle Stamps…) but whose crawl brought no
     * template gets the parametric cut on a DEDICATED clone of its surface —
     * same fallback-only rule as refineShapedSurfaces, never a shared surface.
     */
    private function refineNamedDies(): void
    {
        foreach (Product::with('surface')->get() as $p) {
            $s = $p->surface;
            if (! $s || $s->cut_path) {
                continue; // no dims to shape / the crawler template already rules
            }
            $name = strtolower($p->name);
            // only flat print products — "Round Podium Counters" is a cylinder whose
            // PRINT surface is a rectangular wrap, not a round die
            if (! preg_match('/sticker|label|stamp|card|magnet|postcard|coaster|decal|tag|invitation/', $name)) {
                continue;
            }
            $cut = match (true) {
                str_contains($name, 'rounded corner')               => $this->roundedCutPath((float) $s->width, (float) $s->height, $s->unit),
                (bool) preg_match('/\b(circle|oval|round)\b/', $name) => self::SHAPE_PATHS['ellipse'],
                (bool) preg_match('/\bleaf\b/', $name)              => self::SHAPE_PATHS['leaf'],
                (bool) preg_match('/\bheart\b/', $name)             => self::SHAPE_PATHS['heart'],
                default                                             => null,
            };
            if (! $cut) {
                continue;
            }
            $ded = Surface::updateOrCreate(['slug' => Str::limit($s->slug.'-cut-'.$p->slug, 96, '')], [
                'name'           => $s->name.' ('.$p->name.')',
                'unit'           => $s->unit,
                'width'          => $s->width,
                'height'         => $s->height,
                'bleed'          => $s->bleed,
                'safety'         => $s->safety,
                'no_print_areas' => $s->no_print_areas ?? [],
                'fold_lines'     => $s->fold_lines ?? [],
                'cut_path'       => $cut,
                'is_active'      => true,
            ]);
            $p->update(['surface_id' => $ded->id]);
            $this->line("  named die: {$p->name} -> {$ded->slug}");
        }
    }

    /**
     * A "Corners: Rounded" option value changes the finished SHAPE — give the value
     * its own surface with a rounded-rect cut so the designer shows it. Reuses the
     * real crawled rounded-corner template when the dims match a standard card,
     * otherwise synthesizes the arcs. (Selected-value surfaces with a cut win in
     * DesignController::geometry.)
     */
    private function wireCornerSurfaces(): void
    {
        $template = Surface::where('slug', 'like', '%-cut-rounded-corner%')->whereNotNull('cut_path')->first();
        $wired = 0;

        foreach (Product::with('surface', 'options.values')->get() as $p) {
            $s = $p->surface;
            if (! $s || $s->cut_path) {
                continue; // no dims to work from / already a die-cut shape
            }
            foreach ($p->options as $o) {
                if (! preg_match('/corner/i', $o->name)) {
                    continue;
                }
                foreach ($o->values as $v) {
                    if (! preg_match('/round/i', $v->label) || $v->surface_id) {
                        continue;
                    }
                    $w = (float) $s->width;
                    $h = (float) $s->height;
                    $cut = ($template && abs($w - 88.9) < 0.6 && abs($h - 50.8) < 0.6)
                        ? $template->cut_path
                        : $this->roundedCutPath($w, $h, $s->unit);

                    $rounded = Surface::updateOrCreate(['slug' => Str::limit($s->slug, 90, '').'-rounded'], [
                        'name'           => $s->name.' (rounded corners)',
                        'unit'           => $s->unit,
                        'width'          => $w,
                        'height'         => $h,
                        'bleed'          => $s->bleed,
                        'safety'         => $s->safety,
                        'no_print_areas' => $s->no_print_areas ?? [],
                        'fold_lines'     => $s->fold_lines ?? [],
                        'cut_path'       => $cut,
                        'is_active'      => true,
                    ]);
                    $v->update(['surface_id' => $rounded->id]);
                    $wired++;
                }
            }
        }
        if ($wired) {
            $this->line("  corner surfaces: wired {$wired} \"Rounded\" values to rounded-cut surfaces.");
        }
    }

    /**
     * Derive a human option name from its VALUES ("Horizontal/Vertical" → Orientation,
     * dimension lists → Size, "Glossy/Matte/…" → Finish, …). Returns null when no rule
     * fits (the placeholder name is kept and reported).
     */
    private function deriveOptionName(array $labels): ?string
    {
        $clean = array_map(fn ($l) => strtolower(trim(preg_replace('/(recommended|new|out of stock)\s*$/i', '', $l))), $labels);
        $all = implode(' | ', $clean);
        $every = fn (string $re) => count($clean) > 0 && count(array_filter($clean, fn ($l) => preg_match($re, $l))) === count($clean);
        $most = fn (string $re) => count(array_filter($clean, fn ($l) => preg_match($re, $l))) >= max(1, (int) ceil(count($clean) * 0.6));

        return match (true) {
            $every('/^(horizontal|vertical|portrait|landscape)$/')                                     => 'Orientation',
            $most('/\d\s*(?:"|”|″|in\b|ft\b|cm\b|mm\b)?\s*x\s*\d/')                                    => 'Size',
            $every('/^(standard|rounded|square)(\s+corners?)?$/')                                      => 'Corners',
            $most('/^(rectangle|square|circle|oval|arrow|hexagon|shield|heart|star)([\/ ].*)?$/')      => 'Shape',
            $every('/recipients?$/')                                                                   => 'Recipients',
            $every('/^(none|perforated)$/')                                                            => 'Perforation',
            $every('/^(indoor|outdoor)$/')                                                             => 'Usage',
            $most('/\d\s*(mm|mil|pt|gsm)\b/')                                                          => 'Thickness',
            $every('/^\d+(\.\d+)?\s*[\'′]$/u')                                                         => 'Size',
            $every('/^(standard|premium|elite)( plus)?$/')                                             => 'Quality',
            (bool) preg_match('/\bframe\b/', $all)                                                     => 'Frame',
            (bool) preg_match('/bopp|plastic|vinyl|polyester|kraft\b/', $all)                          => 'Material',
            (bool) preg_match('/oz\.|cans?\b|bottles?\b|growlers?\b/', $all)                           => 'Container',
            (bool) preg_match('/foil|embossed/', $all) && $most('/gold|silver|rose|copper|none|gloss/') => 'Foil',
            $every('/^(gold|silver|rose gold|copper|none)$/')                                          => 'Foil',
            $most('/glossy|matte|uncoated|recycled|soft touch|satin|linen|pearl|cotton|fine grit|synthetic/') => 'Finish',
            $every('/^(budget|standard|premium(\s+plus)?|deluxe|economy)(\s+\w+)?$/')                  => 'Paper Stock',
            (bool) preg_match('/-?column|roll\b/', $all)                                               => 'Roll Format',
            (bool) preg_match('/fold/', $all)                                                          => 'Fold Type',
            $every('/^(black|white|walnut|natural|navy|grey|gray|brown|oak|clear)$/')                  => 'Colour',
            $every('/^(single|double)[- ]sided$|^front|^back/')                                        => 'Printed Sides',
            default => null,
        };
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
