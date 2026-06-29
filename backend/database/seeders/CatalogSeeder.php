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
                        'quantity'   => $q[0],
                        'unit_price' => $q[1],
                        'is_default' => $q[2] ?? false,
                        'sort_order' => $qi,
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
                        'name' => 'Standard Business Cards', 'from_price' => 10.00, 'badge' => 'Bestseller',
                        'tagline' => 'The everyday essential — sharp, affordable, fast.',
                        'description' => 'Classic 3.5×2" cards on quality stock. Design online or upload your artwork.',
                        'options' => [
                            ['name' => 'Paper Stock', 'values' => [
                                ['label' => 'Matte', 'is_default' => true],
                                ['label' => 'Glossy'],
                                ['label' => 'Premium Matte', 'price_delta' => 5, 'badge' => $rec, 'description' => 'Sturdy, high-end feel'],
                                ['label' => 'Recycled', 'price_delta' => 3],
                            ]],
                            ['name' => 'Corners', 'values' => [
                                ['label' => 'Square', 'is_default' => true],
                                ['label' => 'Rounded', 'price_delta' => 4],
                            ]],
                        ],
                        'quantities' => [[50, 0.20, true], [100, 0.16], [250, 0.10], [500, 0.08], [1000, 0.06]],
                    ],
                    [
                        'name' => 'Premium Business Cards', 'from_price' => 20.00,
                        'tagline' => 'Thicker stock and luxe finishes that feel the part.',
                        'description' => 'Heavyweight cards with soft-touch and premium finish options.',
                        'options' => [
                            ['name' => 'Paper Stock', 'values' => [
                                ['label' => 'Premium', 'is_default' => true],
                                ['label' => 'Premium Plus', 'price_delta' => 7, 'badge' => $rec, 'description' => 'Noticeably thicker & heavier'],
                                ['label' => 'Soft Touch', 'price_delta' => 12, 'description' => 'Velvety suede-like coating'],
                            ]],
                            ['name' => 'Finish', 'values' => [
                                ['label' => 'Matte', 'is_default' => true],
                                ['label' => 'Glossy'],
                                ['label' => 'Soft-Touch', 'price_delta' => 3],
                            ]],
                        ],
                        'quantities' => [[50, 0.40, true], [100, 0.30], [250, 0.22], [500, 0.18], [1000, 0.14]],
                    ],
                    [
                        'name' => 'Rounded Corner Business Cards', 'from_price' => 15.00,
                        'tagline' => 'Soft, modern corners that stand out.',
                        'description' => 'Smooth rounded corners on durable card stock.',
                        'options' => [
                            ['name' => 'Paper Stock', 'values' => [
                                ['label' => 'Matte', 'is_default' => true], ['label' => 'Glossy'],
                            ]],
                            ['name' => 'Size', 'values' => [
                                ['label' => 'Standard 3.5×2"', 'is_default' => true],
                                ['label' => 'Square 2.5×2.5"', 'price_delta' => 2],
                            ]],
                        ],
                        'quantities' => [[50, 0.30, true], [100, 0.24], [250, 0.16], [500, 0.12]],
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
                        'name' => 'Flyers', 'from_price' => 20.00, 'badge' => 'Popular',
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
                        'quantities' => [[25, 0.80, true], [50, 0.55], [100, 0.35], [250, 0.22], [500, 0.18]],
                    ],
                    [
                        'name' => 'Postcards', 'from_price' => 22.00,
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
                        'quantities' => [[25, 0.88, true], [50, 0.60], [100, 0.40], [250, 0.28], [500, 0.22]],
                    ],
                    [
                        'name' => 'Brochures', 'from_price' => 45.00,
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
                        'quantities' => [[25, 1.80, true], [50, 1.20], [100, 0.90], [250, 0.65]],
                    ],
                    [
                        'name' => 'Posters', 'from_price' => 13.00,
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
                        'quantities' => [[1, 12.99, true], [5, 9.99], [10, 7.99], [25, 5.99]],
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
                        'name' => 'Vinyl Banner', 'from_price' => 29.00,
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
                        'quantities' => [[1, 29.00, true], [2, 26.00], [5, 22.00], [10, 18.00]],
                    ],
                    [
                        'name' => 'Yard Signs', 'from_price' => 19.00,
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
                        'quantities' => [[1, 19.00, true], [5, 15.00], [10, 12.00], [25, 9.50]],
                    ],
                    [
                        'name' => 'Window Decals', 'from_price' => 14.00,
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
                        'quantities' => [[1, 14.00, true], [5, 11.00], [10, 9.00]],
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
                        'quantities' => [[50, 0.20, true], [100, 0.14], [250, 0.09], [500, 0.07], [1000, 0.05]],
                    ],
                    [
                        'name' => 'Roll Labels', 'from_price' => 20.00,
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
                        'quantities' => [[100, 0.20, true], [250, 0.13], [500, 0.09], [1000, 0.06]],
                    ],
                    [
                        'name' => 'Sheet Labels', 'from_price' => 13.00,
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
                        'quantities' => [[10, 1.30, true], [25, 0.90], [50, 0.70], [100, 0.55]],
                    ],
                ],
            ],
            [
                'name' => 'Stationery', 'slug' => 'stationery', 'sort_order' => 4,
                'tagline' => 'Cohesive, professional correspondence.',
                'description' => 'Letterhead, envelopes and notepads to match your brand.',
                'products' => [
                    [
                        'name' => 'Letterhead', 'from_price' => 25.00,
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
                        'quantities' => [[50, 0.50, true], [100, 0.35], [250, 0.25], [500, 0.20]],
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
        ];
    }
}
