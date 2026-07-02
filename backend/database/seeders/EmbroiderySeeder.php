<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Surface;
use Illuminate\Database\Seeder;

/**
 * Embroidered apparel — decoration=embroidery products stitched, not printed.
 * Their designer surface is the EMBROIDERY AREA (bleed 0, small stitch margin);
 * the editor shows stitch guidance instead of print bleed. Idempotent by slug.
 */
class EmbroiderySeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::updateOrCreate(
            ['slug' => 'apparel-bags'],
            ['name' => 'Apparel & Bags', 'is_active' => true, 'sort_order' => 5],
        );

        $surfaces = [
            'hat-front-embroidery'    => ['name' => 'Hat front — embroidery 12 × 6 cm', 'unit' => 'mm', 'width' => 120, 'height' => 60, 'safety' => 5],
            'polo-chest-embroidery'   => ['name' => 'Polo chest logo — embroidery 10 × 10 cm', 'unit' => 'mm', 'width' => 100, 'height' => 100, 'safety' => 5],
            'beanie-cuff-embroidery'  => ['name' => 'Beanie cuff — embroidery 11 × 5 cm', 'unit' => 'mm', 'width' => 110, 'height' => 50, 'safety' => 5],
        ];
        $surfaceIds = [];
        foreach ($surfaces as $slug => $s) {
            $surfaceIds[$slug] = Surface::updateOrCreate(['slug' => $slug], $s + [
                'bleed' => 0, 'no_print_areas' => [], 'fold_lines' => [], 'cut_path' => null, 'is_active' => true,
            ])->id;
        }

        $threadOption = ['name' => 'Thread colours', 'type' => 'select', 'values' => [
            ['label' => 'Up to 4 colours', 'is_default' => true],
            ['label' => 'Up to 6 colours', 'price_delta' => 5],
        ]];
        $swatch = fn (array $colors) => ['name' => 'Colour', 'type' => 'swatch', 'values' => collect($colors)
            ->map(fn ($hex, $label) => ['label' => $label, 'swatch' => $hex, 'is_default' => $label === array_key_first($colors)])
            ->values()->all()];

        $products = [
            [
                'slug' => 'embroidered-hats', 'name' => 'Embroidered Hats', 'surface' => 'hat-front-embroidery',
                'tagline' => 'Classic caps with your logo stitched front and centre.',
                'description' => 'Structured six-panel caps with your logo embroidered on the front. Durable stitching that won\'t crack or fade like prints.',
                'badge' => 'New', 'from_price' => 18.99,
                'options' => [$swatch(['Black' => '#111111', 'Navy' => '#1f2a44', 'Khaki' => '#b0a184', 'Red' => '#c0392b', 'White' => '#f5f5f5']), $threadOption],
                'tiers' => [[1, 18.99], [5, 84.99], [12, 179.99], [24, 329.99], [48, 599.99]],
            ],
            [
                'slug' => 'embroidered-polo-shirts', 'name' => 'Embroidered Polo Shirts', 'surface' => 'polo-chest-embroidery',
                'tagline' => 'Team-ready polos with a stitched chest logo.',
                'description' => 'Comfortable piqué polos with your logo embroidered on the left chest — the uniform staple for crews, front-of-house and events.',
                'badge' => null, 'from_price' => 24.99,
                'options' => [
                    $swatch(['Black' => '#111111', 'Navy' => '#1f2a44', 'Heather Grey' => '#9aa0a6', 'White' => '#f5f5f5']),
                    ['name' => 'Size', 'type' => 'select', 'values' => [
                        ['label' => 'S'], ['label' => 'M', 'is_default' => true], ['label' => 'L'], ['label' => 'XL'], ['label' => '2XL', 'price_delta' => 2],
                    ]],
                    $threadOption,
                ],
                'tiers' => [[1, 24.99], [5, 114.99], [12, 249.99], [24, 449.99], [48, 819.99]],
            ],
            [
                'slug' => 'embroidered-beanies', 'name' => 'Embroidered Beanies', 'surface' => 'beanie-cuff-embroidery',
                'tagline' => 'Warm knit beanies with your mark on the cuff.',
                'description' => 'Snug rib-knit beanies with your logo embroidered on the fold-over cuff. Cold-season branding your team will actually wear.',
                'badge' => null, 'from_price' => 16.99,
                'options' => [$swatch(['Black' => '#111111', 'Charcoal' => '#3c4043', 'Forest' => '#0c1f17', 'Burgundy' => '#6d1a2d']), $threadOption],
                'tiers' => [[1, 16.99], [5, 74.99], [12, 154.99], [24, 279.99], [48, 499.99]],
            ],
        ];

        foreach ($products as $i => $p) {
            $product = $category->products()->updateOrCreate(['slug' => $p['slug']], [
                'name'            => $p['name'],
                'tagline'         => $p['tagline'],
                'description'     => $p['description'],
                'badge'           => $p['badge'],
                'from_price'      => $p['from_price'],
                'supports_design' => true,
                'supports_upload' => true,
                'decoration'      => 'embroidery',
                'is_active'       => true,
                'surface_id'      => $surfaceIds[$p['surface']],
                'sort_order'      => 200 + $i,
                'image_path'      => \Illuminate\Support\Facades\Storage::disk('public')->exists("products/{$p['slug']}.webp") ? "products/{$p['slug']}.webp" : null,
            ]);

            $product->options()->delete();
            foreach ($p['options'] as $oi => $opt) {
                $option = $product->options()->create([
                    'name' => $opt['name'], 'type' => $opt['type'], 'required' => true, 'sort_order' => $oi,
                ]);
                foreach ($opt['values'] as $vi => $val) {
                    $option->values()->create($val + ['sort_order' => $vi]);
                }
            }

            $product->quantities()->delete();
            foreach ($p['tiers'] as $qi => [$qty, $total]) {
                $product->quantities()->create([
                    'quantity' => $qty, 'total_price' => $total, 'unit_price' => round($total / $qty, 4),
                    'is_default' => $qi === 0, 'sort_order' => $qi,
                ]);
            }
        }

        $this->command?->info('Embroidery: 3 products + 3 surfaces seeded.');
    }
}
