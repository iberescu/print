<?php

namespace App\Support;

use App\Models\Product;

/**
 * Resolves the online-designer canvas size for a product, honouring the
 * selected "Size" option (A4 flyer → A4, business card → 3.5×2" landscape, …).
 * Only the aspect ratio + orientation matter for the canvas, so dimensions can
 * be carried in whatever unit the label uses — they're scaled to a fixed px edge.
 */
class PrintSpec
{
    /** The longer canvas edge, in px. BC (3.5×2") lands on exactly 760×434. */
    private const LONG_EDGE = 760;

    /** Named ISO sizes in mm at their natural (portrait) orientation. */
    private const NAMED = [
        'A0' => [841, 1189], 'A1' => [594, 841], 'A2' => [420, 594], 'A3' => [297, 420],
        'A4' => [210, 297], 'A5' => [148, 210], 'A6' => [105, 148],
        'DL' => [99, 210], 'C4' => [229, 324], 'C5' => [162, 229],
    ];

    /** Products whose print is conventionally landscape / portrait regardless of label order. */
    private const LANDSCAPE = ['vinyl-banner', 'yard-signs'];
    private const PORTRAIT  = ['roll-up-banner'];

    /**
     * @param  int[]  $optionValueIds  selected option-value ids (to read the chosen Size)
     * @return array{w:int,h:int,label:string}
     */
    public static function canvas(Product $product, array $optionValueIds = []): array
    {
        [$w, $h, $label] = self::dimensions($product, $optionValueIds);
        $scale = self::LONG_EDGE / max($w, $h);

        return [
            'w'     => max(80, (int) round($w * $scale)),
            'h'     => max(80, (int) round($h * $scale)),
            'label' => $label,
        ];
    }

    /** @return array{0:float,1:float,2:string} [width, height, displayLabel] */
    private static function dimensions(Product $product, array $ids): array
    {
        $size = $product->options->firstWhere('name', 'Size');
        if ($size) {
            $val = $size->values->whereIn('id', $ids)->first()
                ?? $size->values->firstWhere('is_default', true)
                ?? $size->values->first();
            if ($val && ($parsed = self::parse($val->label, $product))) {
                return $parsed;
            }
        }

        return self::fallback($product);
    }

    /** @return array{0:float,1:float,2:string}|null */
    private static function parse(string $label, Product $product): ?array
    {
        $slug = $product->slug;

        // "W × H" (inches or feet): "3.5×2\"", "Square 2.5×2.5\"", "2×4 ft", "33×79\""
        if (preg_match('/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)/u', $label, $m)) {
            $w = (float) $m[1];
            $h = (float) $m[2];
            if (in_array($slug, self::LANDSCAPE, true) && $h > $w) {
                [$w, $h] = [$h, $w];
            }
            if (in_array($slug, self::PORTRAIT, true) && $w > $h) {
                [$w, $h] = [$h, $w];
            }

            return [$w, $h, trim($label)];
        }

        // Named ISO size: "A4", "DL", "C5"
        if (preg_match('/\b(A[0-6]|DL|C[45])\b/i', $label, $m)) {
            $key = strtoupper($m[1]);
            [$w, $h] = self::NAMED[$key];
            if ($slug === 'envelopes') {          // envelopes print landscape
                [$w, $h] = [max($w, $h), min($w, $h)];
            }

            return [$w, $h, $key];
        }

        // Single dimension → square: "2\"", "3\"", "1.5\""
        if (preg_match('/^(\d+(?:\.\d+)?)\s*"?$/', trim($label), $m)) {
            $s = (float) $m[1];

            return [$s, $s, trim($label)];
        }

        return null; // not a size label (e.g. "S", "Standard", "Large")
    }

    /** @return array{0:float,1:float,2:string} */
    private static function fallback(Product $product): array
    {
        return match ($product->slug) {
            'letterhead', 'sheet-labels' => [8.5, 11, 'US Letter'],   // portrait
            'brochures'                  => [297, 210, 'A4'],          // flat sheet, landscape
            'custom-t-shirts'            => [12, 16, 'Print area'],    // front print area
            'tote-bags'                  => [14, 16, 'Print area'],
            default                      => $product->category->slug === 'business-cards'
                ? [3.5, 2, '3.5 × 2 in']                              // business card, landscape
                : [210, 297, 'A4'],                                   // safe portrait default
        };
    }
}
