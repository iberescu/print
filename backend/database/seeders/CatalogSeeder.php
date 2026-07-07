<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $catData) {
            $products = $catData['products'];
            unset($catData['products']);

            $category = Category::updateOrCreate(
                ['slug' => $catData['slug']],
                $catData + ['image_path' => "categories/{$catData['slug']}.png"]
            );

            foreach (array_values($products) as $i => $p) {
                $options    = $p['options'] ?? [];
                $quantities = $p['quantities'] ?? [];
                unset($p['options'], $p['quantities']);

                $slug = Str::slug($p['name']);
                $product = $category->products()->updateOrCreate(
                    ['slug' => $slug],
                    $p + [
                        'slug'       => $slug,
                        'image_path' => "products/{$slug}.png",
                        'sort_order' => $i,
                    ]
                );

                // Rebuild children (idempotent reseed; FK cascade clears values/quantities)
                $product->options()->delete();
                $product->quantities()->delete();

                foreach (array_values($options) as $oi => $opt) {
                    $values = $opt['values'];
                    unset($opt['values']);
                    $option = $product->options()->create($opt + ['sort_order' => $oi]);
                    foreach (array_values($values) as $vi => $val) {
                        $option->values()->create($val + ['sort_order' => $vi]);
                    }
                }

                foreach (array_values($quantities) as $qi => $q) {
                    $product->quantities()->create([
                        'quantity'    => $q[0],
                        'unit_price'  => $q[1],
                        'is_default'  => $q[2] ?? false,
                        'total_price' => $q[3] ?? null, // exact crawled total when provided
                        'sort_order'  => $qi,
                    ]);
                }
            }
        }
    }

    private function catalog(): array
    {
        $rec = 'Recommended';

        return [
            [
                'name' => 'Business Cards', 'slug' => 'business-cards', 'sort_order' => 0,
                'tagline' => 'Make a lasting first impression.',
                'description' => 'Premium custom business cards in a range of stocks, finishes and shapes.',
                'products' => [
                    [
                        'name' => 'Standard Business Cards', 'from_price' => 7.50, 'badge' => 'Bestseller',
                        'tagline' => 'The everyday essential — sharp, affordable, fast.',
                        'description' => 'Classic 3.5×2" cards on quality stock. Design online or upload your artwork.',
                        'options' => [
                            ['name' => 'Paper Stock', 'values' => [
                                ['label' => 'Matte', 'is_default' => true],
                                ['label' => 'Glossy', 'price_delta' => 1],
                                ['label' => 'Premium Matte', 'price_delta' => 13, 'badge' => $rec, 'description' => 'Sturdy, high-end feel'],
                                ['label' => 'Recycled', 'price_delta' => 1],
                            ]],
                            ['name' => 'Corners', 'values' => [
                                ['label' => 'Square', 'is_default' => true],
                                ['label' => 'Rounded', 'price_delta' => 7],
                            ]],
                        ],
                        // Exact Vistaprint "Matte Business Cards" tiers (crawled snapshot).
                        'quantities' => [[50, 0.15, true, 7.50], [100, 0.11, false, 11.24], [250, 0.06, false, 14.99], [500, 0.04, false, 18.74], [1000, 0.03, false, 29.99]],
                    ],
                    [
                        'name' => 'Premium Business Cards', 'from_price' => 22.99,
                        'tagline' => 'Thicker stock and luxe finishes that feel the part.',
                        'description' => 'Heavyweight cards with soft-touch and premium finish options.',
                        'options' => [
                            ['name' => 'Paper Stock', 'values' => [
                                ['label' => 'Premium', 'is_default' => true],
                                ['label' => 'Premium Plus', 'price_delta' => 5, 'badge' => $rec, 'description' => 'Noticeably thicker & heavier'],
                                ['label' => 'Soft Touch', 'price_delta' => 6, 'description' => 'Velvety suede-like coating'],
                            ]],
                            ['name' => 'Finish', 'values' => [
                                ['label' => 'Matte', 'is_default' => true],
                                ['label' => 'Glossy'],
                                ['label' => 'Soft-Touch', 'price_delta' => 3],
                            ]],
                        ],
                        // Exact Vistaprint "Premium Plus Business Cards" tiers (crawled snapshot).
                        'quantities' => [[50, 0.46, true, 22.99], [100, 0.28, false, 27.99], [250, 0.16, false, 38.99], [500, 0.10, false, 46.99], [1000, 0.08, false, 79.99]],
                    ],
                    [
                        'name' => 'Rounded Corner Business Cards', 'from_price' => 16.00,
                        'tagline' => 'Soft, modern corners that stand out.',
                        'description' => 'Smooth rounded corners on durable card stock.',
                        'options' => [
                            ['name' => 'Paper Stock', 'values' => [
                                ['label' => 'Matte', 'is_default' => true], ['label' => 'Glossy', 'price_delta' => 1],
                            ]],
                            ['name' => 'Size', 'values' => [
                                ['label' => 'Standard 3.5×2"', 'is_default' => true],
                                ['label' => 'Square 2.5×2.5"', 'price_delta' => 11],
                            ]],
                        ],
                        // Exact Vistaprint "Rounded Corner Business Cards" tiers (crawled snapshot).
                        'quantities' => [[50, 0.32, true, 16.00], [100, 0.23, false, 22.99], [250, 0.13, false, 31.99], [500, 0.08, false, 39.99], [1000, 0.07, false, 65.99]],
                    ],
                    [
                        'name' => 'Folded Business Cards', 'from_price' => 25.00,
                        'tagline' => 'Twice the space for your message.',
                        'description' => 'Folded cards that double as a mini brochure.',
                        'options' => [
                            ['name' => 'Fold', 'values' => [
                                ['label' => 'Short Fold', 'is_default' => true], ['label' => 'Long Fold'],
                            ]],
                            ['name' => 'Paper Stock', 'values' => [
                                ['label' => 'Matte', 'is_default' => true], ['label' => 'Glossy'],
                            ]],
                        ],
                        'quantities' => [[50, 0.50, true], [100, 0.40], [250, 0.30], [500, 0.24]],
                    ],
                ],
            ],
            [
                'name' => 'Marketing Materials', 'slug' => 'marketing-materials', 'sort_order' => 1,
                'tagline' => 'Promote your business in print.',
                'description' => 'Flyers, postcards, brochures and posters that get you noticed.',
                'products' => [
                    [
                        'name' => 'Flyers', 'from_price' => 9.00, 'badge' => 'Popular',
                        'tagline' => 'Spread the word, affordably.',
                        'description' => 'Vibrant flyers for events, promos and handouts.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => 'A5', 'is_default' => true], ['label' => 'DL'], ['label' => 'A4', 'price_delta' => 10],
                            ]],
                            ['name' => 'Paper', 'values' => [
                                ['label' => 'Matte 130gsm', 'is_default' => true],
                                ['label' => 'Gloss 170gsm', 'price_delta' => 5, 'badge' => $rec],
                                ['label' => 'Premium 250gsm', 'price_delta' => 12],
                            ]],
                            ['name' => 'Sides', 'values' => [
                                ['label' => 'Single-sided', 'is_default' => true],
                                ['label' => 'Double-sided', 'price_delta' => 8],
                            ]],
                        ],
                        'quantities' => [[25, 0.36, true, 9.00], [50, 0.26, false, 13.00], [100, 0.20, false, 20.00], [250, 0.13, false, 32.50], [500, 0.09, false, 45.00], [750, 0.08, false, 60.00], [1000, 0.07, false, 70.00]], // exact Vistaprint tiers (crawled)
                    ],
                    [
                        'name' => 'Postcards', 'from_price' => 15.00,
                        'tagline' => 'Direct mail that lands.',
                        'description' => 'Premium postcards with multiple sizes and finishes.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => '4×6"', 'is_default' => true], ['label' => '5×7"', 'price_delta' => 6], ['label' => '6×9"', 'price_delta' => 10],
                            ]],
                            ['name' => 'Finish', 'values' => [
                                ['label' => 'Matte', 'is_default' => true], ['label' => 'Glossy'], ['label' => 'UV Gloss', 'price_delta' => 8, 'badge' => $rec],
                            ]],
                            ['name' => 'Sides', 'values' => [
                                ['label' => 'Single-sided', 'is_default' => true], ['label' => 'Double-sided', 'price_delta' => 6],
                            ]],
                        ],
                        'quantities' => [[25, 0.60, true, 15.00], [50, 0.38, false, 19.00], [100, 0.22, false, 21.99], [250, 0.14, false, 35.00], [500, 0.09, false, 45.00], [750, 0.08, false, 60.00], [1000, 0.07, false, 70.00]], // exact Vistaprint tiers (crawled)
                    ],
                    [
                        'name' => 'Brochures', 'from_price' => 19.99,
                        'tagline' => 'Tell your full story.',
                        'description' => 'Folded brochures in bi-fold, tri-fold and Z-fold layouts.',
                        'options' => [
                            ['name' => 'Fold', 'values' => [
                                ['label' => 'Bi-Fold', 'is_default' => true], ['label' => 'Tri-Fold'], ['label' => 'Z-Fold', 'price_delta' => 5],
                            ]],
                            ['name' => 'Paper', 'values' => [
                                ['label' => 'Gloss 170gsm', 'is_default' => true], ['label' => 'Silk 250gsm', 'price_delta' => 15, 'badge' => $rec],
                            ]],
                        ],
                        'quantities' => [[25, 0.80, true, 19.99], [50, 0.80, false, 40.00], [100, 0.65, false, 65.00], [250, 0.48, false, 120.00], [500, 0.38, false, 190.00], [750, 0.30, false, 225.00], [1000, 0.25, false, 250.00]], // exact Vistaprint Bi-Fold tiers (crawled)
                    ],
                    [
                        'name' => 'Posters', 'from_price' => 4.99,
                        'tagline' => 'Big, bold, eye-catching.',
                        'description' => 'Large-format posters in matte, satin or gloss.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => 'A2', 'is_default' => true], ['label' => 'A1', 'price_delta' => 8], ['label' => 'A0', 'price_delta' => 20],
                            ]],
                            ['name' => 'Paper', 'values' => [
                                ['label' => 'Matte', 'is_default' => true], ['label' => 'Satin', 'price_delta' => 4], ['label' => 'Gloss', 'price_delta' => 4],
                            ]],
                        ],
                        'quantities' => [[1, 4.99, true, 4.99], [2, 4.50, false, 9.00], [3, 4.00, false, 12.00], [4, 4.00, false, 16.00], [5, 3.80, false, 19.00], [10, 2.20, false, 22.00], [20, 2.00, false, 40.00]], // exact Vistaprint tiers (crawled)
                    ],
                ],
            ],
            [
                'name' => 'Signs & Banners', 'slug' => 'signs-banners', 'sort_order' => 2,
                'tagline' => 'Big-format printing that gets noticed.',
                'description' => 'Banners, signs and displays for events and storefronts.',
                'products' => [
                    [
                        'name' => 'Roll-Up Banner', 'from_price' => 89.00, 'badge' => 'Pro',
                        'tagline' => 'Portable, reusable, professional.',
                        'description' => 'Retractable banner stand with print — set up in seconds.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => '33×79"', 'is_default' => true], ['label' => '47×79"', 'price_delta' => 30],
                            ]],
                            ['name' => 'Base', 'values' => [
                                ['label' => 'Standard', 'is_default' => true], ['label' => 'Premium', 'price_delta' => 25, 'badge' => $rec],
                            ]],
                        ],
                        'quantities' => [[1, 89.00, true], [2, 79.00], [5, 69.00]],
                    ],
                    [
                        'name' => 'Vinyl Banner', 'from_price' => 6.99,
                        'tagline' => 'Weatherproof and built to last.',
                        'description' => 'Durable outdoor vinyl banners with hemmed edges.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => '2×4 ft', 'is_default' => true], ['label' => '3×6 ft', 'price_delta' => 20], ['label' => '4×8 ft', 'price_delta' => 45],
                            ]],
                            ['name' => 'Grommets', 'values' => [
                                ['label' => 'Standard (every 2 ft)', 'is_default' => true], ['label' => 'None'],
                            ]],
                        ],
                        'quantities' => [[1, 6.99, true, 6.99], [2, 4.50, false, 9.00], [3, 4.34, false, 13.02], [4, 4.25, false, 17.00], [5, 4.20, false, 21.00], [6, 4.17, false, 25.02], [7, 4.15, false, 29.05]], // exact Vistaprint tiers (crawled)
                    ],
                    [
                        'name' => 'Yard Signs', 'from_price' => 7.99,
                        'tagline' => 'Plant your message anywhere.',
                        'description' => 'Coroplast yard signs with optional H-stake.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => '18×24"', 'is_default' => true], ['label' => '24×36"', 'price_delta' => 12],
                            ]],
                            ['name' => 'Sides', 'values' => [
                                ['label' => 'Single-sided', 'is_default' => true], ['label' => 'Double-sided', 'price_delta' => 6],
                            ]],
                        ],
                        'quantities' => [[1, 7.99, true, 7.99], [2, 7.50, false, 15.00], [3, 7.34, false, 22.02], [4, 7.25, false, 29.00], [5, 7.20, false, 36.00], [6, 7.17, false, 43.02], [7, 7.15, false, 50.05]], // exact Vistaprint tiers (crawled)
                    ],
                    [
                        'name' => 'Window Decals', 'from_price' => 11.90,
                        'tagline' => 'Stick your brand to any glass.',
                        'description' => 'Static-cling or adhesive vinyl decals for windows and storefronts.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => '12×12"', 'is_default' => true], ['label' => '18×18"', 'price_delta' => 8], ['label' => '24×24"', 'price_delta' => 18],
                            ]],
                            ['name' => 'Type', 'values' => [
                                ['label' => 'Static Cling', 'is_default' => true], ['label' => 'Adhesive Vinyl'],
                            ]],
                        ],
                        'quantities' => [[1, 11.90, true, 11.90], [2, 10.90, false, 21.80], [3, 10.70, false, 32.10], [4, 10.50, false, 42.00], [5, 10.30, false, 51.50]], // exact Vistaprint tiers (crawled)
                    ],
                ],
            ],
            [
                'name' => 'Stickers & Labels', 'slug' => 'stickers-labels', 'sort_order' => 3,
                'tagline' => 'Seal it, brand it, stick it.',
                'description' => 'Custom stickers, roll labels and sheet labels in any shape.',
                'products' => [
                    [
                        'name' => 'Custom Stickers', 'from_price' => 10.00, 'badge' => 'Bestseller',
                        'tagline' => 'Any shape, any size, peel and stick.',
                        'description' => 'Durable stickers in paper, vinyl, clear and holographic.',
                        'options' => [
                            ['name' => 'Shape', 'values' => [
                                ['label' => 'Circle', 'is_default' => true], ['label' => 'Square'], ['label' => 'Rounded Square'], ['label' => 'Die-Cut', 'price_delta' => 5, 'badge' => $rec],
                            ]],
                            ['name' => 'Size', 'values' => [
                                ['label' => '2"', 'is_default' => true], ['label' => '3"', 'price_delta' => 3], ['label' => '4"', 'price_delta' => 6],
                            ]],
                            ['name' => 'Material', 'values' => [
                                ['label' => 'Matte Paper', 'is_default' => true], ['label' => 'White Vinyl', 'price_delta' => 4], ['label' => 'Clear Vinyl', 'price_delta' => 6], ['label' => 'Holographic', 'price_delta' => 10],
                            ]],
                        ],
                        'quantities' => [[10, 1.00, true, 10.00], [25, 0.96, false, 24.00], [50, 0.90, false, 45.00], [100, 0.70, false, 70.00], [150, 0.60, false, 90.00], [200, 0.52, false, 103.99], [250, 0.44, false, 110.00]], // exact Vistaprint Sticker Singles tiers (crawled)
                    ],
                    [
                        'name' => 'Roll Labels', 'from_price' => 34.50,
                        'tagline' => 'Product labels on a roll.',
                        'description' => 'Perfect for bottles, jars and packaging.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => '1.5"', 'is_default' => true], ['label' => '2"', 'price_delta' => 4], ['label' => '3"', 'price_delta' => 8],
                            ]],
                            ['name' => 'Material', 'values' => [
                                ['label' => 'Matte', 'is_default' => true], ['label' => 'Gloss'], ['label' => 'Clear', 'price_delta' => 5],
                            ]],
                        ],
                        'quantities' => [[50, 0.69, true, 34.50], [100, 0.55, false, 55.00], [200, 0.30, false, 60.00], [250, 0.26, false, 65.00], [500, 0.16, false, 80.00], [1000, 0.11, false, 104.44], [1500, 0.09, false, 135.00]], // exact Vistaprint tiers (crawled)
                    ],
                    [
                        'name' => 'Sheet Labels', 'from_price' => 15.12,
                        'tagline' => 'Address, shipping and round labels.',
                        'description' => 'Easy-peel labels on letter-size sheets.',
                        'options' => [
                            ['name' => 'Type', 'values' => [
                                ['label' => 'Address', 'is_default' => true], ['label' => 'Shipping', 'price_delta' => 3], ['label' => 'Round', 'price_delta' => 2],
                            ]],
                            ['name' => 'Finish', 'values' => [
                                ['label' => 'Matte', 'is_default' => true], ['label' => 'Gloss', 'price_delta' => 2],
                            ]],
                        ],
                        'quantities' => [[72, 0.21, true, 15.12], [80, 0.21, false, 16.80], [90, 0.21, false, 18.90], [96, 0.20, false, 19.20], [100, 0.20, false, 20.00], [120, 0.20, false, 24.00], [144, 0.18, false, 24.99]], // exact Vistaprint Sheet Stickers tiers (crawled)
                    ],
                ],
            ],
            [
                'name' => 'Stationery', 'slug' => 'stationery', 'sort_order' => 4,
                'tagline' => 'Cohesive, professional correspondence.',
                'description' => 'Letterhead, envelopes and notepads to match your brand.',
                'products' => [
                    [
                        'name' => 'Letterhead', 'from_price' => 86.99,
                        'tagline' => 'Put your brand on every page.',
                        'description' => 'Premium letterhead on quality uncoated stock.',
                        'options' => [
                            ['name' => 'Paper', 'values' => [
                                ['label' => 'Premium 100gsm', 'is_default' => true], ['label' => 'Conqueror 120gsm', 'price_delta' => 10, 'badge' => $rec],
                            ]],
                            ['name' => 'Sides', 'values' => [
                                ['label' => 'Single-sided', 'is_default' => true], ['label' => 'Double-sided', 'price_delta' => 8],
                            ]],
                        ],
                        'quantities' => [[100, 0.87, true, 86.99], [150, 0.67, false, 99.99], [200, 0.63, false, 124.99], [250, 0.60, false, 149.99]], // exact Vistaprint tiers (crawled)
                    ],
                    [
                        'name' => 'Envelopes', 'from_price' => 30.00,
                        'tagline' => 'Branded, from the outside in.',
                        'description' => 'Custom-printed envelopes in DL, C5 and C4.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => 'DL', 'is_default' => true], ['label' => 'C5', 'price_delta' => 6], ['label' => 'C4', 'price_delta' => 12],
                            ]],
                            ['name' => 'Print', 'values' => [
                                ['label' => 'Front only', 'is_default' => true], ['label' => 'Front + Back', 'price_delta' => 10],
                            ]],
                        ],
                        'quantities' => [[50, 0.60, true], [100, 0.45], [250, 0.32], [500, 0.26]],
                    ],
                    [
                        'name' => 'Notepads', 'from_price' => 19.00,
                        'tagline' => 'Sticky brand recall on every desk.',
                        'description' => 'Custom notepads, glued at the top, 25–100 sheets.',
                        'options' => [
                            ['name' => 'Size', 'values' => [
                                ['label' => 'A6', 'is_default' => true], ['label' => 'A5'], ['label' => 'A4', 'price_delta' => 8],
                            ]],
                            ['name' => 'Sheets per pad', 'values' => [
                                ['label' => '25', 'is_default' => true], ['label' => '50', 'price_delta' => 5], ['label' => '100', 'price_delta' => 12, 'badge' => $rec],
                            ]],
                        ],
                        'quantities' => [[2, 9.50, true], [5, 7.50], [10, 5.99], [25, 4.50]],
                    ],
                ],
            ],
            [
                'name' => 'Apparel & Bags', 'slug' => 'apparel-bags', 'sort_order' => 5,
                'tagline' => 'Wear your brand.',
                'description' => 'Custom-printed t-shirts and tote bags.',
                'products' => [
                    [
                        'name' => 'Custom T-Shirts', 'from_price' => 15.00, 'badge' => 'New',
                        'tagline' => 'Soft, durable, brand-ready tees.',
                        'description' => 'Premium cotton tees with front and back print options.',
                        'options' => [
                            ['name' => 'Color', 'type' => 'swatch', 'values' => [
                                ['label' => 'White', 'is_default' => true, 'swatch' => '#ffffff'],
                                ['label' => 'Black', 'swatch' => '#111111'],
                                ['label' => 'Navy', 'swatch' => '#1f2a44'],
                                ['label' => 'Heather Grey', 'swatch' => '#b0b3b8'],
                                ['label' => 'Red', 'swatch' => '#c0392b'],
                            ]],
                            ['name' => 'Size', 'values' => [
                                ['label' => 'S', 'is_default' => true], ['label' => 'M'], ['label' => 'L'], ['label' => 'XL'], ['label' => 'XXL', 'price_delta' => 2],
                            ]],
                            ['name' => 'Print', 'values' => [
                                ['label' => 'Front only', 'is_default' => true], ['label' => 'Front + Back', 'price_delta' => 6],
                            ]],
                        ],
                        'quantities' => [[1, 14.99, true], [10, 11.99], [25, 9.99], [50, 8.49]],
                    ],
                    [
                        'name' => 'Tote Bags', 'from_price' => 12.00,
                        'tagline' => 'Carry your brand everywhere.',
                        'description' => 'Sturdy cotton totes printed with your design.',
                        'options' => [
                            ['name' => 'Color', 'type' => 'swatch', 'values' => [
                                ['label' => 'Natural', 'is_default' => true, 'swatch' => '#e7dcc4'],
                                ['label' => 'Black', 'swatch' => '#111111'],
                                ['label' => 'Navy', 'swatch' => '#1f2a44'],
                            ]],
                            ['name' => 'Size', 'values' => [
                                ['label' => 'Standard', 'is_default' => true], ['label' => 'Large', 'price_delta' => 3],
                            ]],
                        ],
                        'quantities' => [[1, 11.99, true], [10, 9.49], [25, 7.99], [50, 6.99]],
                    ],
                ],
            ],
            [
                'name' => 'Accessories', 'slug' => 'accessories', 'sort_order' => 6,
                'tagline' => 'Finishing touches for your cards.',
                'description' => 'Holders, cases and stands to present and protect your business cards in style.',
                'products' => [
                    [
                        'name' => 'Metal Business Card Holder', 'from_price' => 12.00, 'badge' => 'Pairs well',
                        'tagline' => 'Keep your cards crisp and close to hand.',
                        'description' => 'Sleek brushed-metal holder that protects your cards and looks sharp on any desk. Not personalised — ships ready to use.',
                        'supports_design' => false, 'supports_upload' => false,
                        'quantities' => [[1, 12.00, true], [2, 11.00], [5, 9.50]],
                    ],
                    [
                        'name' => 'Leather Card Case', 'from_price' => 19.00,
                        'tagline' => 'Premium leather, pocket-ready.',
                        'description' => 'Full-grain leather case that carries around 25 cards in understated style. Not personalised.',
                        'supports_design' => false, 'supports_upload' => false,
                        'quantities' => [[1, 19.00, true], [2, 17.00], [5, 15.00]],
                    ],
                    [
                        'name' => 'Acrylic Desk Card Stand', 'from_price' => 9.00,
                        'tagline' => 'Display your cards front and centre.',
                        'description' => 'Crystal-clear acrylic stand that shows off your cards at reception desks and events. Not personalised.',
                        'supports_design' => false, 'supports_upload' => false,
                        'quantities' => [[1, 9.00, true], [2, 8.00], [5, 6.50]],
                    ],
                ],
            ],
        ];
    }
}
