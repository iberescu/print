<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Non-personalised accessories — the card-holder cross-sell in the upsell flow
 * depends on this category having products (the crawled-catalogue swap emptied
 * it). Their Gemini images are already committed in storage. Idempotent by slug.
 */
class AccessorySeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::updateOrCreate(
            ['slug' => 'accessories'],
            ['name' => 'Accessories', 'is_active' => true, 'sort_order' => 6],
        );

        $items = [
            ['slug' => 'metal-business-card-holder', 'name' => 'Metal Business Card Holder',
                'tagline' => 'Brushed metal, keeps 15 cards crisp.', 'price' => 12.99],
            ['slug' => 'leather-card-case', 'name' => 'Leather Card Case',
                'tagline' => 'Slim vegan-leather case for your pocket.', 'price' => 19.99],
            ['slug' => 'acrylic-desk-card-stand', 'name' => 'Acrylic Desk Card Stand',
                'tagline' => 'Clear stand that puts your card front and centre.', 'price' => 9.99],
        ];

        foreach ($items as $i => $item) {
            $product = $category->products()->updateOrCreate(['slug' => $item['slug']], [
                'name'            => $item['name'],
                'tagline'         => $item['tagline'],
                'description'     => $item['tagline'],
                'from_price'      => $item['price'],
                'supports_design' => false,
                'supports_upload' => false,
                'is_active'       => true,
                'sort_order'      => 300 + $i,
                'image_path'      => Storage::disk('public')->exists("products/{$item['slug']}.webp") ? "products/{$item['slug']}.webp" : null,
            ]);
            $product->quantities()->updateOrCreate(['quantity' => 1], [
                'unit_price' => $item['price'], 'total_price' => $item['price'], 'is_default' => true, 'sort_order' => 0,
            ]);
        }

        $this->command?->info('Accessories: 3 products seeded.');
    }
}
