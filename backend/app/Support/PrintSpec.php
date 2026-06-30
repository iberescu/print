<?php

namespace App\Support;

use App\Models\Product;

/**
 * Resolves the online-designer canvas for a product, honouring the selected
 * "Size" option (A4 flyer → A4, business card → 3.5×2" landscape, …) and adding
 * print bleed + a safe margin. The canvas is the FULL bleed size; the trim
 * (cut) rectangle sits inset by `bleed`, and the safe area inset by
 * `bleed + safety`. Only ratios matter, so dimensions carry the label's unit
 * and are scaled to a fixed px edge.
 */
class PrintSpec
{
    /** The trim's longer edge, in px. BC (3.5×2") trims at exactly 760×434. */
    private const LONG_EDGE = 760;

    /** Bleed / safe margin per unit (≈ 1/8" or 3 mm; 1/4 ft ≈ 3" for large format). */
    private const BLEED  = ['in' => 0.125, 'mm' => 3.0, 'ft' => 0.25];
    private const SAFETY = ['in' => 0.125, 'mm' => 3.0, 'ft' => 0.25];

    /** Named ISO sizes in mm at their natural (portrait) orientation. */
    private const NAMED = [
        'A0' => [841, 1189], 'A1' => [594, 841], 'A2' => [420, 594], 'A3' => [297, 420],
        'A4' => [210, 297], 'A5' => [148, 210], 'A6' => [105, 148],
        'DL' => [99, 210], 'C4' => [229, 324], 'C5' => [162, 229],
    ];

    private const LANDSCAPE = ['vinyl-banner', 'yard-signs'];
    private const PORTRAIT  = ['roll-up-banner'];

    /**
     * @param  int[]  $optionValueIds
     * @return array{w:int,h:int,trimW:int,trimH:int,bleed:int,safety:int,label:string}
     */
    public static function canvas(Product $product, array $optionValueIds = []): array
    {
        [$w, $h, $label, $unit] = self::dimensions($product, $optionValueIds);

        $pxPerUnit = self::LONG_EDGE / max($w, $h);
        $trimW = (int) round($w * $pxPerUnit);
        $trimH = (int) round($h * $pxPerUnit);
        $bleed = (int) round((self::BLEED[$unit] ?? 0) * $pxPerUnit);
        $safety = (int) round((self::SAFETY[$unit] ?? 0) * $pxPerUnit);

        return [
            'w'      => $trimW + 2 * $bleed,
            'h'      => $trimH + 2 * $bleed,
            'trimW'  => $trimW,
            'trimH'  => $trimH,
            'bleed'  => $bleed,
            'safety' => $safety,
            'label'  => $label,
        ];
    }

    /** @return array{0:float,1:float,2:string,3:string} [width, height, label, unit] */
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

    /** @return array{0:float,1:float,2:string,3:string}|null */
    private static function parse(string $label, Product $product): ?array
    {
        $slug = $product->slug;

        // "W × H" (inches or feet): "3.5×2\"", "Square 2.5×2.5\"", "2×4 ft", "33×79\""
        if (preg_match('/(\d+(?:\.\d+)?)\s*[×x]\s*(\d+(?:\.\d+)?)/u', $label, $m)) {
            $w = (float) $m[1];
            $h = (float) $m[2];
            $unit = stripos($label, 'ft') !== false ? 'ft' : 'in';
            if (in_array($slug, self::LANDSCAPE, true) && $h > $w) {
                [$w, $h] = [$h, $w];
            }
            if (in_array($slug, self::PORTRAIT, true) && $w > $h) {
                [$w, $h] = [$h, $w];
            }

            return [$w, $h, trim($label), $unit];
        }

        // Named ISO size: "A4", "DL", "C5"
        if (preg_match('/\b(A[0-6]|DL|C[45])\b/i', $label, $m)) {
            $key = strtoupper($m[1]);
            [$w, $h] = self::NAMED[$key];
            if ($slug === 'envelopes') {          // envelopes print landscape
                [$w, $h] = [max($w, $h), min($w, $h)];
            }

            return [$w, $h, $key, 'mm'];
        }

        // Single dimension → square: "2\"", "3\"", "1.5\""
        if (preg_match('/^(\d+(?:\.\d+)?)\s*"?$/', trim($label), $m)) {
            $s = (float) $m[1];

            return [$s, $s, trim($label), 'in'];
        }

        return null; // not a size label (e.g. "S", "Standard", "Large")
    }

    /** @return array{0:float,1:float,2:string,3:string} */
    private static function fallback(Product $product): array
    {
        return match ($product->slug) {
            'letterhead', 'sheet-labels' => [8.5, 11, 'US Letter', 'in'],   // portrait
            'brochures'                  => [297, 210, 'A4', 'mm'],          // flat sheet, landscape
            'custom-t-shirts'            => [12, 16, 'Print area', 'in'],    // front print area
            'tote-bags'                  => [14, 16, 'Print area', 'in'],
            default                      => $product->category->slug === 'business-cards'
                ? [3.5, 2, '3.5 × 2 in', 'in']                              // business card, landscape
                : [210, 297, 'A4', 'mm'],                                   // safe portrait default
        };
    }
}
