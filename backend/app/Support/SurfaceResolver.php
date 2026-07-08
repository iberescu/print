<?php

namespace App\Support;

use App\Models\Product;

/**
 * Resolves the designer canvas for a product + selected option values.
 *
 * The die-cut edge and the canvas dimensions resolve INDEPENDENTLY, then merge:
 * a Shape value carries the cut (possibly none — a wired "Rectangle"/"Custom"
 * surface flattens a die-cut default), a Size/Format value carries the dims, a
 * cut-bearing value on any other option (e.g. Corners → Rounded) still wins the
 * cut. Bleed/safety come from the cut's surface — the die template's measured
 * margins beat a size value's print-standard defaults.
 *
 * Shared by DesignController (the live designer) and surfaces:audit (which
 * resolves every shape/size selection to prove the data renders right).
 */
class SurfaceResolver
{
    /** @param int[] $opts */
    public static function resolve(Product $product, array $opts): array
    {
        $base = $product->surface;

        // Per option: the explicitly selected value, else its default (the product
        // page sends the full selection; template/deep links may send none).
        $picked = [];
        foreach ($product->options as $opt) {
            $v = $opt->values->first(fn ($v) => in_array($v->id, $opts, true))
                ?? $opt->values->first(fn ($v) => $v->is_default);
            if ($v) {
                $picked[] = [$opt, $v];
            }
        }

        $cut = $base?->cut_path;
        $cutSrc = $base;                // surface whose bleed/safety apply
        $dims = null;                   // plain value surface that sets the dims
        $shapeDims = null;              // a shape value's own surface can set dims too
        foreach ($picked as [$opt, $v]) {
            if (! $v->surface) {
                continue;
            }
            if (preg_match('/shape/i', $opt->name)) {
                if ($v->surface->cut_path) {
                    $cut = $v->surface->cut_path;
                    $cutSrc = $v->surface;
                    $shapeDims = $v->surface;
                } elseif (str_contains($v->surface->slug, '-shape-')) {
                    // a surface the importer wired for this shape, cutless on
                    // purpose: an explicit flat choice (Rectangle, Custom die-cut)
                    $cut = null;
                    $cutSrc = $v->surface;
                    $shapeDims = $v->surface;
                } else {
                    // crawl artifact: a shape value pointing at a shared dims
                    // surface (Oval BCs → s-3.5x2in) — dims only, NEVER flattens
                    $dims ??= $v->surface;
                }
            } elseif ($v->surface->cut_path) {
                $cut = $v->surface->cut_path;   // Corners → Rounded etc.
                $cutSrc = $v->surface;
            } else {
                $dims ??= $v->surface;          // size/format/paper value
            }
        }

        // Dims: explicit value surface > parseable size label > shape surface > product.
        $spec = null;
        $specSrc = null;                // surface the spec was built from (null = parsed label)
        if ($dims) {
            $spec = PrintSpec::fromSurface($dims);
            $specSrc = $dims;
        } else {
            foreach ($picked as [$opt, $v]) {
                if (preg_match('/size|format|dimension/i', $opt->name) && ! $v->surface
                    && PrintSpec::parsesAsSize($v->label, $product)) {
                    $spec = PrintSpec::canvas($product, $opts);
                    break;
                }
            }
        }
        if (! $spec && $shapeDims) {
            $spec = PrintSpec::fromSurface($shapeDims);
            $specSrc = $shapeDims;
        }
        if (! $spec) {
            $spec = $base ? PrintSpec::fromSurface($base) : PrintSpec::canvas($product, $opts);
            $specSrc = $base;
        }

        $spec['cut'] = $cut;
        if ($cutSrc && $cutSrc->id !== $specSrc?->id) {
            $spec = PrintSpec::withGuidesFrom($spec, $cutSrc);
        }

        return $spec;
    }
}
