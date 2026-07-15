<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Surface;
use App\Support\PrintSpec;
use App\Support\SurfaceResolver;
use Illuminate\Console\Command;

/**
 * Sanity-audit every surface and its wiring to products/option values: dims,
 * bleed/safety proportions, die-cut path geometry, fold/no-print bounds, shape
 * options without surfaces, size labels that disagree with their surface, junk
 * values. Read-only — fix data via catalog:import or the admin Surface Manager.
 *
 *   php artisan surfaces:audit            # report, exit 1 when ERRORs exist
 *   php artisan surfaces:audit --info     # include INFO-level notes
 */
class AuditSurfaces extends Command
{
    protected $signature = 'surfaces:audit {--info : also show informational notes}';

    protected $description = 'Audit surface data (dims, bleed/safety, cut paths, option wiring) for the whole catalog';

    private const MM = ['mm' => 1.0, 'cm' => 10.0, 'in' => 25.4, 'ft' => 304.8];

    /** @var array<int, array{0:string,1:string,2:string}> */
    private array $rows = [];

    public function handle(): int
    {
        Surface::all()->each(fn (Surface $s) => $this->auditSurface($s));
        Product::with(['surface', 'options.values.surface'])->get()->each(fn (Product $p) => $this->auditProduct($p));

        $rows = collect($this->rows)
            ->when(! $this->option('info'), fn ($c) => $c->reject(fn ($r) => $r[0] === 'INFO'))
            ->sortBy(fn ($r) => array_search($r[0], ['ERROR', 'WARN', 'INFO'], true))
            ->values();

        if ($rows->isEmpty()) {
            $this->info('Surface audit: no findings. All '.Surface::count().' surfaces / '.Product::count().' products look sane.');

            return self::SUCCESS;
        }

        $this->table(['level', 'where', 'finding'], $rows->all());
        $counts = $rows->countBy(0);
        $this->line(sprintf(
            'Surfaces: %d · products: %d — %d error(s), %d warning(s), %d info.',
            Surface::count(), Product::count(),
            $counts['ERROR'] ?? 0, $counts['WARN'] ?? 0, $counts['INFO'] ?? 0,
        ));

        return ($counts['ERROR'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function add(string $level, string $where, string $finding): void
    {
        $this->rows[] = [$level, $where, $finding];
    }

    private function auditSurface(Surface $s): void
    {
        $where = "surface {$s->slug}";
        $w = (float) $s->width;
        $h = (float) $s->height;
        $minDim = min($w, $h);

        if ($w <= 0 || $h <= 0) {
            $this->add('ERROR', $where, "non-positive dims {$w}x{$h}");

            return;
        }
        if (! isset(self::MM[$s->unit])) {
            $this->add('ERROR', $where, "unknown unit '{$s->unit}'");
        }
        if (max($w, $h) / $minDim > 25) {
            $this->add('WARN', $where, sprintf('suspicious aspect ratio %.0f:1 (%sx%s%s)', max($w, $h) / $minDim, $w, $h, $s->unit));
        }

        // embroidery/decoration surfaces legitimately have no bleed
        $embroidery = str_contains($s->slug, 'embroider');
        if ((float) $s->bleed <= 0 && ! $embroidery) {
            $this->add('WARN', $where, 'bleed is 0 — designer shows no trim margin');
        }
        if ((float) $s->bleed > 0.15 * $minDim) {
            $this->add('ERROR', $where, "bleed {$s->bleed}{$s->unit} exceeds 15% of the short edge ({$minDim}{$s->unit})");
        }
        if ((float) $s->safety > 0.2 * $minDim) {
            $this->add('ERROR', $where, "safety {$s->safety}{$s->unit} exceeds 20% of the short edge");
        }

        if ($s->cut_path) {
            $this->auditCutPath($s, $where);
        }

        foreach ($s->fold_lines ?? [] as $f) {
            $limit = ($f['orientation'] ?? 'vertical') === 'vertical' ? $w : $h;
            $pos = (float) ($f['position'] ?? -1);
            if ($pos <= 0 || $pos >= $limit) {
                $this->add('ERROR', $where, "fold at {$pos}{$s->unit} outside the {$limit}{$s->unit} surface");
            } elseif ($pos < 0.05 * $limit || $pos > 0.95 * $limit) {
                // a fold 5% from the edge is almost always a unit mix-up
                // (inch positions on a metric surface), not a real crease
                $this->add('WARN', $where, "fold at {$pos}{$s->unit} hugs the edge of the {$limit}{$s->unit} surface — unit mix-up?");
            }
        }
        foreach ($s->no_print_areas ?? [] as $z) {
            $x = (float) ($z['x'] ?? 0);
            $y = (float) ($z['y'] ?? 0);
            $zw = (float) ($z['w'] ?? 0);
            $zh = (float) ($z['h'] ?? 0);
            if ($x < -0.01 || $y < -0.01 || $x + $zw > $w * 1.01 || $y + $zh > $h * 1.01) {
                $this->add('ERROR', $where, sprintf('no-print zone "%s" (%.4g,%.4g %.4gx%.4g) leaves the surface', $z['label'] ?? '?', $x, $y, $zw, $zh));
            }
        }
    }

    private function auditCutPath(Surface $s, string $where): void
    {
        $d = (string) $s->cut_path;

        if (preg_match('/[a-z]/', $d)) {
            $this->add('WARN', $where, 'cut path uses relative/lowercase commands — editor falls back to the stretched render');
        }
        if (preg_match('/[^MLHVCSQTAZ0-9eE.,\s-]/', $d)) {
            $this->add('ERROR', $where, 'cut path has unsupported commands/characters');
        }

        $box = $this->pathBounds($d);
        if (! $box) {
            $this->add('ERROR', $where, 'cut path has no coordinates');

            return;
        }
        [$x0, $x1, $y0, $y1] = $box;
        if ($x0 < -25 || $x1 > 125 || $y0 < -25 || $y1 > 125) {
            $this->add('ERROR', $where, sprintf('cut coords out of the 0–100 space (x %.4g..%.4g, y %.4g..%.4g)', $x0, $x1, $y0, $y1));
        } elseif ($x1 - $x0 < 65 || $y1 - $y0 < 65) {
            $this->add('WARN', $where, sprintf('cut spans only x %.4g..%.4g / y %.4g..%.4g of the trim box — die floats inside the canvas', $x0, $x1, $y0, $y1));
        }
        if (! preg_match('/Z\s*$/i', trim($d))) {
            $this->add('INFO', $where, 'cut path not explicitly closed (fill treats it as closed)');
        }

        // a die on a SHARED dimension surface leaks the cut into other products
        if (preg_match('/^s-[\d.]+x[\d.]+(mm|cm|in|ft)(-fold)?$/', $s->slug)) {
            $this->add('ERROR', $where, 'die-cut lives on a SHARED dimension surface (needs a dedicated -cut-/-shape- slug)');
        }
    }

    /**
     * Approximate bbox of an absolute-command SVG path: control points count as-is
     * (bezier hulls over-estimate mildly), arcs contribute their full ellipse
     * extents via center parameterization — endpoints alone miss the sweep (a
     * circle drawn as two half-arcs has both endpoints on one axis).
     *
     * @return array{0:float,1:float,2:float,3:float}|null [minX, maxX, minY, maxY]
     */
    private function pathBounds(string $d): ?array
    {
        $xs = $ys = [];
        $cx = $cy = $startX = $startY = 0.0;

        foreach ((array) preg_split('/(?=[MLHVCSQTAZ])/i', $d, -1, PREG_SPLIT_NO_EMPTY) as $seg) {
            $cmd = strtoupper($seg[0]);
            preg_match_all('/-?\d+(?:\.\d+)?/', $seg, $m);
            $nums = array_map('floatval', $m[0]);

            if ($cmd === 'Z') {
                [$cx, $cy] = [$startX, $startY];

                continue;
            }
            if ($cmd === 'H') {
                foreach ($nums as $x) {
                    $xs[] = $cx = $x;
                }

                continue;
            }
            if ($cmd === 'V') {
                foreach ($nums as $y) {
                    $ys[] = $cy = $y;
                }

                continue;
            }
            if ($cmd === 'A') {
                for ($i = 0; $i + 6 < count($nums); $i += 7) {
                    [$rx, $ry, , $fa, $fs, $x2, $y2] = array_slice($nums, $i, 7);
                    // center parameterization (rotation 0) — W3C F.6.5; axis extremes
                    // count only when the sweep actually reaches them
                    $mx = ($cx - $x2) / 2;
                    $my = ($cy - $y2) / 2;
                    $rx = abs($rx) ?: 0.01;
                    $ry = abs($ry) ?: 0.01;
                    $lam = ($mx ** 2) / ($rx ** 2) + ($my ** 2) / ($ry ** 2);
                    if ($lam > 1) {
                        $rx *= sqrt($lam);
                        $ry *= sqrt($lam);
                    }
                    $den = ($rx ** 2) * ($my ** 2) + ($ry ** 2) * ($mx ** 2);
                    $c = $den > 1e-9 ? sqrt(max(0, (($rx ** 2) * ($ry ** 2) - $den) / $den)) : 0.0;
                    $c *= ((int) $fa !== (int) $fs) ? 1 : -1;
                    $ecx = $c * $rx * $my / $ry + ($cx + $x2) / 2;
                    $ecy = -$c * $ry * $mx / $rx + ($cy + $y2) / 2;

                    $t1 = atan2(($cy - $ecy) / $ry, ($cx - $ecx) / $rx);
                    $t2 = atan2(($y2 - $ecy) / $ry, ($x2 - $ecx) / $rx);
                    $dt = $t2 - $t1;
                    if (! $fs && $dt > 0) {
                        $dt -= 2 * M_PI;
                    }
                    if ($fs && $dt < 0) {
                        $dt += 2 * M_PI;
                    }
                    $reached = function (float $a) use ($t1, $dt): bool {
                        $rel = fmod(($dt >= 0 ? $a - $t1 : $t1 - $a) + 4 * M_PI, 2 * M_PI);

                        return $rel <= abs($dt) + 1e-6;
                    };
                    array_push($xs, $cx, $x2);
                    array_push($ys, $cy, $y2);
                    if ($reached(0.0)) {
                        $xs[] = $ecx + $rx;
                    }
                    if ($reached(M_PI)) {
                        $xs[] = $ecx - $rx;
                    }
                    if ($reached(M_PI / 2)) {
                        $ys[] = $ecy + $ry;
                    }
                    if ($reached(3 * M_PI / 2)) {
                        $ys[] = $ecy - $ry;
                    }
                    [$cx, $cy] = [$x2, $y2];
                }

                continue;
            }
            foreach ($nums as $i => $v) {
                $i % 2 === 0 ? $xs[] = $v : $ys[] = $v;
            }
            if (count($nums) >= 2) {
                $cx = $nums[count($nums) - 2];
                $cy = $nums[count($nums) - 1];
            }
            if ($cmd === 'M') {
                [$startX, $startY] = [$nums[0] ?? 0.0, $nums[1] ?? 0.0];
            }
        }

        return $xs && $ys ? [min($xs), max($xs), min($ys), max($ys)] : null;
    }

    private function auditProduct(Product $p): void
    {
        $where = "product {$p->slug}";

        if (! $p->surface && ! $p->decoration) {
            $sizeable = $p->options->first(fn ($o) => preg_match('/size|format|dimension/i', $o->name)
                && $o->values->first(fn ($v) => $v->surface || PrintSpec::parsesAsSize($v->label, $p)));
            if (! $sizeable) {
                $this->add('WARN', $where, 'no surface and no parseable size option — designer uses the generic category default');
            }
        }

        foreach ($p->options as $o) {
            // a Size option whose values are bare letters is a crawl double-read
            // (dims captured, labels lost) — works, but PIM-rename it
            if (preg_match('/size/i', $o->name) && $o->values->count() > 1
                && $o->values->every(fn ($v) => preg_match('/^[A-Z]$/', $v->label))) {
                $this->add('INFO', $where, "size option {$o->id} uses letter labels (".$o->values->pluck('label')->implode('/').') — crawl lost the names');
            }

            foreach ($o->values as $v) {
                if (preg_match('/not available|other selections|^see |^select\b|^choose\b/i', $v->label)) {
                    $this->add('ERROR', $where, "junk option value \"{$v->label}\" ({$o->name})");
                }

                // a generated option preview that no value links = a fresh import
                // stripped the final step's material card image
                if (! $v->image_path) {
                    $preview = sprintf('option-previews/%s/%s-%s.webp', $p->slug, \Illuminate\Support\Str::slug($o->name), \Illuminate\Support\Str::slug($v->label));
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($preview)) {
                        $this->add('ERROR', $where, "preview exists but is UNLINKED: {$preview}");
                    }
                }

                // size labels that disagree with the surface they map to
                if ($v->surface && ($dims = PrintSpec::parsedDims($v->label, $p))) {
                    [$lw, $lh] = $dims;
                    $unit = $dims[3];
                    $f = (self::MM[$unit] ?? 1) / (self::MM[$v->surface->unit] ?? 1);
                    $sw = (float) $v->surface->width;
                    $sh = (float) $v->surface->height;
                    $match = fn ($a, $b) => abs($a - $b) <= 0.06 * max($a, $b);
                    $ok = ($match($lw * $f, $sw) && $match($lh * $f, $sh))
                        || ($match($lw * $f, $sh) && $match($lh * $f, $sw)); // orientation swap is fine
                    if (! $ok && ! str_contains($v->surface->slug, '-shape-')) {
                        $this->add('WARN', $where, sprintf('value "%s" parses to %.4gx%.4g%s but its surface %s is %.4gx%.4g%s',
                            $v->label, $lw, $lh, $unit, $v->surface->slug, $sw, $sh, $v->surface->unit));
                    }
                }
            }

            // shape options: every recognizable value should carry a surface after import
            // (a FLAT value on a plain product intentionally has none — nothing to change)
            if (preg_match('/shape/i', $o->name) && $o->values->count() >= 2) {
                $curated = in_array($p->slug, ['feather-flags', 'circle-business-cards', 'oval-business-cards', 'leaf-business-cards', 'retractable-banners'], true);
                foreach ($o->values as $v) {
                    if ($v->surface_id || $curated) {
                        continue; // wired, or refineShapedSurfaces intentionally cleared it
                    }
                    $kind = ImportCatalog::classifyShape($v->label);
                    if ($kind === null) {
                        $this->add('INFO', $where, "exotic die (canvas keeps product default): \"{$v->label}\" ({$o->name})");
                    } elseif ($kind !== 'keep' && ($kind !== 'flat' || $p->surface?->cut_path)) {
                        $this->add('ERROR', $where, "shape value left unwired: \"{$v->label}\" ({$o->name})"
                            .($p->surface?->cut_path ? ' — designer is stuck on the product die' : ''));
                    }
                }
            }

            // END-STATE: resolve the designer geometry for each shape value and
            // check the rendered die against what the label promises (this is what
            // caught Oval BCs rendering flat: a crawl-artifact plain surface on the
            // "Oval" value read as an explicit flatten)
            if (preg_match('/shape/i', $o->name)) {
                foreach ($o->values as $v) {
                    $kind = ImportCatalog::classifyShape($v->label);
                    if ($kind === null || $kind === 'keep') {
                        continue; // exotic/format values legitimately show the product default
                    }
                    $cut = SurfaceResolver::resolve($p, [$v->id])['cut'] ?? null;
                    $custom = str_contains(strtolower($v->label), 'custom');
                    $expectCut = $kind !== 'flat' || (! $custom && (bool) $p->surface?->cut_path);
                    if ($expectCut && ! $cut) {
                        $this->add('ERROR', $where, "shape \"{$v->label}\" resolves WITHOUT its die in the designer");
                    } elseif (! $expectCut && $cut) {
                        $this->add('ERROR', $where, "shape \"{$v->label}\" should be flat but resolves with a die");
                    }
                }
            }
        }

        // die-named products (Oval/Circle/Leaf/Rounded-Corner…) must open the
        // designer with their die — unless the default shape is genuinely flat.
        // ("Die-Cut …" products are EXCLUDED: the customer's artwork drives that
        // die, so a flat canvas is the honest editor.)
        if (preg_match('/\b(oval|circle|leaf|heart|rounded corner)\b/i', $p->name)) {
            $defShape = null;
            foreach ($p->options as $o) {
                if (preg_match('/shape/i', $o->name)) {
                    $defShape = $o->values->first(fn ($v) => $v->is_default);
                    break;
                }
            }
            $defKind = $defShape ? ImportCatalog::classifyShape($defShape->label) : null;
            if ($defKind !== 'flat' && ! (SurfaceResolver::resolve($p, [])['cut'] ?? null)) {
                $this->add('ERROR', $where, 'die-named product opens the designer FLAT (no cut resolves by default)');
            }
        }
    }
}
