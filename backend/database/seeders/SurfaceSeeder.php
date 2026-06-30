<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Surface;
use Illuminate\Database\Seeder;

class SurfaceSeeder extends Seeder
{
    public function run(): void
    {
        $surfaces = [
            ['name' => 'Business Card', 'slug' => 'business-card', 'width' => 89, 'height' => 51, 'bleed' => 3, 'safety' => 3],
            ['name' => 'A4', 'slug' => 'a4', 'width' => 210, 'height' => 297, 'bleed' => 3, 'safety' => 5],
            ['name' => 'A5', 'slug' => 'a5', 'width' => 148, 'height' => 210, 'bleed' => 3, 'safety' => 5],
            ['name' => 'DL', 'slug' => 'dl', 'width' => 99, 'height' => 210, 'bleed' => 3, 'safety' => 5],
            ['name' => 'Folded Business Card', 'slug' => 'folded-business-card', 'width' => 178, 'height' => 51, 'bleed' => 3, 'safety' => 3,
                'fold_lines' => [['label' => 'Fold', 'orientation' => 'vertical', 'position' => 89]]],
            ['name' => 'Roll-Up Banner 850×2000', 'slug' => 'roll-up-banner', 'width' => 850, 'height' => 2000, 'bleed' => 10, 'safety' => 30,
                'no_print_areas' => [
                    ['label' => 'Top pocket', 'x' => 0, 'y' => 0, 'w' => 850, 'h' => 120],
                    ['label' => 'Bottom pocket', 'x' => 0, 'y' => 1880, 'w' => 850, 'h' => 120],
                ]],
        ];

        $bySlug = [];
        foreach ($surfaces as $s) {
            $bySlug[$s['slug']] = Surface::updateOrCreate(['slug' => $s['slug']], array_merge([
                'unit' => 'mm', 'bleed' => 0, 'safety' => 0, 'no_print_areas' => [], 'fold_lines' => [], 'is_active' => true,
            ], $s));
        }

        // Product-level surfaces (folding + no-print demos).
        foreach (['folded-business-cards' => 'folded-business-card', 'roll-up-banner' => 'roll-up-banner'] as $pSlug => $sSlug) {
            Product::where('slug', $pSlug)->update(['surface_id' => $bySlug[$sSlug]->id]);
        }

        // Flyer "Size" values → matching surface + width/height specs (format → surface).
        $flyerSizes = ['A4' => ['a4', 210, 297], 'A5' => ['a5', 148, 210], 'DL' => ['dl', 99, 210]];
        $this->eachValue('flyers', 'Size', function ($v) use ($bySlug, $flyerSizes) {
            if (isset($flyerSizes[$v->label])) {
                [$sSlug, $w, $h] = $flyerSizes[$v->label];
                $v->update([
                    'surface_id' => $bySlug[$sSlug]->id,
                    'attributes' => [['name' => 'Width', 'value' => "{$w} mm"], ['name' => 'Height', 'value' => "{$h} mm"]],
                ]);
            }
        });

        // Example paper specs (weight + thickness) on Standard Business Cards.
        $paperSpecs = [
            'Matte'         => [['Weight', '350gsm'], ['Thickness', '0.35 mm']],
            'Glossy'        => [['Weight', '350gsm'], ['Thickness', '0.35 mm']],
            'Premium Matte' => [['Weight', '400gsm'], ['Thickness', '0.45 mm']],
            'Recycled'      => [['Weight', '300gsm'], ['Thickness', '0.32 mm']],
        ];
        $this->eachValue('standard-business-cards', 'Paper Stock', function ($v) use ($paperSpecs) {
            if (isset($paperSpecs[$v->label])) {
                $v->update(['attributes' => array_map(fn ($a) => ['name' => $a[0], 'value' => $a[1]], $paperSpecs[$v->label])]);
            }
        });
    }

    private function eachValue(string $productSlug, string $optionName, callable $fn): void
    {
        $product = Product::with('options.values')->where('slug', $productSlug)->first();
        $option = $product?->options->firstWhere('name', $optionName);
        foreach ($option?->values ?? [] as $value) {
            $fn($value);
        }
    }
}
