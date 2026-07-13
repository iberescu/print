<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Extra garment colours for the printed/embroidered apparel products (t-shirt, hoodie).
 * Colours are swatch-type option values (hex in `swatch`), rendered as swatch tiles on the
 * product + final step. Idempotent by label; the first colour in each set is the default.
 */
class ApparelColorsSeeder extends Seeder
{
    public function run(): void
    {
        $sets = [
            // Gildan Softstyle unisex tee — common stocked colours
            'gildan-softstyle-unisex-t-shirt' => [
                'White' => '#ffffff', 'Black' => '#1a1a1a', 'Navy' => '#1b2a4a', 'Sport Grey' => '#b6b8ba',
                'Charcoal' => '#3c4043', 'Red' => '#e11d2f', 'Royal Blue' => '#1f4fd8', 'Light Blue' => '#8ecae6',
                'Irish Green' => '#2e9e4f', 'Forest Green' => '#20452f', 'Purple' => '#5b2a86', 'Maroon' => '#6d1a2d',
                'Orange' => '#e8622c', 'Gold' => '#f2c200', 'Pink' => '#f26fb2',
            ],
            // Jerzees NuBlend hooded sweatshirt — common stocked colours
            'jerzees-nublend-hooded-sweatshirt' => [
                'Black' => '#1a1a1a', 'Navy' => '#1b2a4a', 'Oxford Grey' => '#b6b8ba', 'Charcoal' => '#3c4043',
                'Royal Blue' => '#1f4fd8', 'Red' => '#e11d2f', 'Forest Green' => '#20452f', 'Maroon' => '#6d1a2d',
                'Ash' => '#d7d9da', 'White' => '#ffffff',
            ],
        ];

        foreach ($sets as $slug => $colors) {
            $product = Product::where('slug', $slug)->first();
            if (! $product) {
                $this->command?->warn("ApparelColors: skip {$slug} (product missing)");
                continue;
            }

            $option = $product->options()->where('name', 'Color')->first()
                ?? $product->options()->create(['name' => 'Color', 'type' => 'swatch', 'required' => true, 'sort_order' => 0]);
            if ($option->type !== 'swatch') {
                $option->update(['type' => 'swatch']);
            }

            $i = 0;
            foreach ($colors as $label => $hex) {
                $option->values()->updateOrCreate(
                    ['label' => $label],
                    ['swatch' => $hex, 'is_default' => $i === 0, 'sort_order' => $i],
                );
                $i++;
            }

            $this->command?->info("ApparelColors: {$slug} → ".count($colors).' colours.');
        }
    }
}
