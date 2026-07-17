<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Vistaprint-style catalog structure: give every browsable category a set of
 * subcategories and assign each product to one. Also redistributes the old
 * "More Products" catch-all into real categories (13 → new Photo & Gifts,
 * 14 → Marketing, 1 → Stationery) and retires it.
 *
 * Idempotent: run it as often as you like. Products are matched by slug, so it
 * is safe to re-run after catalog imports; missing slugs are reported, not fatal.
 */
class CatalogStructureSeeder extends Seeder
{
    /** category slug => [ subcategory name => [product slug, …] ] (array order = display order) */
    private const PLAN = [
        'business-cards' => [
            'Standard'            => ['matte-business-cards', 'glossy-business-cards', 'uncoated-business-cards', 'standard-business-cards'],
            'Shapes'              => ['rounded-corner-business-cards', 'circle-business-cards', 'oval-business-cards', 'leaf-business-cards', 'square-business-cards'],
            'Premium & Textured'  => ['natural-textured-business-cards', 'soft-touch-business-cards', 'cotton-business-cards', 'linen-business-cards', 'kraft-business-cards', 'premium-plus-business-cards', 'ultra-thick-business-cards', 'pearl-business-cards'],
            'Specialty Finishes'  => ['painted-edge-business-cards', 'foil-accent-business-cards', 'embossed-gloss-business-cards', 'raised-foil-business-cards'],
            'Smart Cards'         => ['qr-code-business-cards', 'magnetic-business-cards', 'business-card-stickers', 'loyalty-business-cards', 'appointment-cards'],
            'Fast Turnaround'     => ['next-day-business-cards', '2-day-business-cards'],
        ],
        'marketing-materials' => [
            'Postcards'                   => ['standard-postcards', 'rounded-corner-postcards', 'die-cut-postcards', 'eddm-postcards', 'embossed-foil-postcards', 'embossed-gloss-postcards', 'foil-accent-postcards', 'standard-postcards-2', 'magnetic-postcards', 'standard-postcards-3'],
            'Flyers, Brochures & Hangers' => ['flyers', 'perforated-flyers', 'bi-fold-brochures', 'tri-fold-brochures', 'z-fold-brochures', 'door-hangers'],
            'Booklets'                    => ['saddle-stitch-booklets', 'wire-bound-booklets', 'perfect-bound-booklets', 'self-cover-booklets'],
            'Rack Cards'                  => ['rack-cards', 'rip-cards', 'foil-accent-rack-cards', 'embossed-gloss-rack-cards', 'embossed-foil-rack-cards'],
            'Menus'                       => ['wedding-event-menus', 'flat-menus'],
            'Calendars'                   => ['custom-wall-calendars', 'custom-desk-calendars', 'magnetic-calendars'],
            'Table & Promo'               => ['custom-tickets', 'table-tents', 'paper-coasters', 'placemats', 'fridge-magnets'],
            'Mailing Services'            => ['postcard-mailing-services'],
        ],
        'signs-banners' => [
            'Banners'             => ['vinyl-banners', 'retractable-banners', 'feather-flags', 'x-banner-frames', 'triangle-zipper-banners', 'tabletop-retractable-banners'],
            'Signs'               => ['yard-signs', 'foam-boards', 'car-door-decals', 'custom-car-magnets', 'acrylic-table-signs', 'mounted-tabletop-signs', 'metal-table-signs', 'plastic-tabletop-signs', 'menu-boards'],
            'Posters'             => ['custom-posters'],
            'Displays & Counters' => ['triangular-point-of-sale-displays', 'tower-displays', 'bounce-up-counters', 'round-podium-counters', 'fabric-pop-up-counter-displays', 'tabletop-a-frames', 'signicade'],
            'Hardware & Stands'   => ['parking-sign-post-bases', 'u-channel-posts', '6ft-t-posts'],
            'Table Covers'        => ['custom-tablecloths'],
        ],
        'stickers-labels' => [
            'Sticker Singles & Die-Cut' => ['bumper-stickers', 'sticker-singles', 'die-cut-sticker-singles', 'kiss-cut-stickers', 'custom-laptop-stickers', 'front-adhesive-stickers', 'campaign-stickers'],
            'Sticker Sheets'            => ['sheet-stickers', 'die-cut-sticker-sheets'],
            'Sticker Shapes'            => ['circle-stickers', 'rectangle-stickers', 'square-stickers', 'oval-stickers'],
            'Specialty Stickers'        => ['face-stickers', 'party-stickers', 'qr-code-stickers', 'holographic-stickers'],
            'Labels'                    => ['product-labels-on-sheets', 'return-address-labels', 'roll-labels', 'die-cut-roll-labels', 'waterproof-labels', 'wine-labels', 'beer-labels', 'cosmetic-labels', 'mailing-labels'],
        ],
        'stationery' => [
            'Cards & Invitations'  => ['custom-thank-you-cards', 'note-cards', 'painted-edge-invitations-announcements', 'wedding-place-cards', 'wedding-thank-you-cards', 'letterpress-wedding-invitations', 'gift-certificates'],
            'Office & Business'    => ['company-letterhead', 'presentation-folders', 'foil-accent-presentation-folders', 'carbonless-forms', 'custom-napkins'],
            'Envelopes'            => ['custom-envelopes', 'colored-envelopes', 'envelope-seals'],
            'Notebooks & Notepads' => ['custom-notebooks', 'notepads', 'custom-post-it-notes', 'faux-leather-journals', 'custom-bookmarks', 'guest-books'],
            'Stamps & Seals'       => ['self-inking-stamps', 'circle-stamps', 'wax-seals'],
        ],
        'apparel-bags' => [
            'T-Shirts'              => ['gildan-softstyle-unisex-t-shirt'],
            'Polos'                 => ['embroidered-polo-shirts'],
            'Sweatshirts & Hoodies' => ['jerzees-nublend-hooded-sweatshirt'],
            'Hats & Beanies'        => ['embroidered-hats', 'embroidered-beanies'],
            'Bags'                  => ['custom-canvas-tote-bags'],
            'Drinkware'             => ['custom-mugs', '20-oz-tumbler', '40-oz-tumblers'],
        ],
        'accessories' => [
            'Business Card Holders'   => ['acrylic-business-card-holders', 'steel-desk-business-card-holder', 'black-leather-vertical-business-card-holders', 'slim-paper-business-card-holder', 'gold-business-card-holders', 'marble-business-card-holders', 'metal-business-card-holder', 'leather-card-case', 'acrylic-desk-card-stand'],
            'Literature Holders'      => ['literature-flyer-holders'],
            'Gift & Key Card Holders' => ['key-card-holders', 'gift-card-holders'],
        ],
        'photo-gifts' => [
            'Wall Art & Prints' => ['canvas-prints', 'framed-photo-prints', 'framed-canvas-prints', 'easel-back-canvas-prints', 'acrylic-photo-blocks', 'tabletop-photo-tiles'],
            'Photo Books'       => ['photo-books'],
            'Home & Living'     => ['custom-pillows', 'pet-face-pillows', 'custom-photo-blankets', 'personalized-beach-towel', 'custom-yoga-mats'],
            'Puzzles & Games'   => ['custom-jigsaw-puzzles'],
        ],
    ];

    public function run(): void
    {
        // New consumer category that absorbs the photo/home items from "other".
        Category::updateOrCreate(
            ['slug' => 'photo-gifts'],
            [
                'name'        => 'Photo & Gifts',
                'tagline'     => 'Turn your photos into keepsakes',
                'description' => 'Canvas prints, framed art, photo books and personalised gifts — made from your favourite images.',
                'sort_order'  => 6,
                'is_active'   => true,
            ]
        );
        // Keep the nav order sensible now that Photo & Gifts sits at 6.
        Category::where('slug', 'accessories')->update(['sort_order' => 7]);
        Category::where('slug', 'services')->update(['sort_order' => 8]);

        $assigned = 0;
        $missing = [];

        foreach (self::PLAN as $catSlug => $subs) {
            $category = Category::where('slug', $catSlug)->first();
            if (! $category) {
                $this->command?->warn("  category '{$catSlug}' not found — skipping");
                continue;
            }

            $order = 0;
            foreach ($subs as $subName => $slugs) {
                $order++;
                $sub = Subcategory::updateOrCreate(
                    ['category_id' => $category->id, 'slug' => Str::slug($subName)],
                    ['name' => $subName, 'sort_order' => $order, 'is_active' => true]
                );

                $tileImage = null;
                foreach ($slugs as $slug) {
                    $product = Product::where('slug', $slug)->first();
                    if (! $product) {
                        $missing[] = $slug;
                        continue;
                    }
                    // handles redistribution: category_id may change too
                    $product->category_id = $category->id;
                    $product->subcategory_id = $sub->id;
                    $product->save();
                    $assigned++;
                    $tileImage = $tileImage ?: $product->image_path;
                }

                // Tile image = first product's image, unless one is already set.
                if ($tileImage && ! $sub->image_path) {
                    $sub->update(['image_path' => $tileImage]);
                }
            }
        }

        // Merchandising: Standard Business Cards leads the Business Cards category.
        // sort_order is unsigned, so reindex the category — Standard = 0, the rest
        // keep their relative order from 1. Idempotent (stable ordering each run).
        $standard = Product::where('slug', 'standard-business-cards')->first();
        if ($standard) {
            $standard->update([
                'sort_order' => 0,
                // square-corner card hero with real artwork (versioned = CF-safe)
                'image_path' => 'products/standard-business-cards-v2.webp',
            ]);
            $others = Product::where('category_id', $standard->category_id)
                ->where('id', '!=', $standard->id)
                ->orderBy('sort_order')->orderBy('id')->get();
            $i = 1;
            foreach ($others as $p) {
                $p->update(['sort_order' => $i++]);
            }
        }

        // The Layout.ai ad-credit product lives only in CatalogSeeder, which the
        // prod catalog:import path never runs — so a fresh import drops it and the
        // ads step's "Add to my order" 404s. Recreate it here (idempotent).
        $this->ensureAdCredit();
        $this->ensureWebsiteOffer();

        // "More Products" is now empty — retire it from the storefront.
        Category::where('slug', 'other')->update(['is_active' => false]);

        Cache::forget('nav.categories');

        $this->command?->info("  assigned {$assigned} products to subcategories");
        if ($missing) {
            $this->command?->warn('  missing product slugs: '.implode(', ', $missing));
        }
        $orphans = Product::where('is_active', true)
            ->whereNull('subcategory_id')
            ->whereHas('category', fn ($q) => $q->where('is_active', true)->where('slug', '!=', 'services'))
            ->pluck('slug');
        if ($orphans->isNotEmpty()) {
            $this->command?->warn('  products with no subcategory: '.$orphans->implode(', '));
        }
    }

    /** The $29 Layout.ai ad-credit sold on the upsell ads step (Services category). */
    private function ensureAdCredit(): void
    {
        $services = Category::firstOrCreate(
            ['slug' => 'services'],
            ['name' => 'Services', 'sort_order' => 8, 'is_active' => true, 'tagline' => 'Beyond print — services that launch your brand.']
        );

        $product = Product::updateOrCreate(
            ['slug' => 'ad-credit-250'],
            [
                'category_id'     => $services->id,
                'subcategory_id'  => null,
                'name'            => 'Ad Credit — $250 Google Display Ads',
                'tagline'         => 'Your first ad campaign, designed and managed by Layout.ai.',
                'description'     => '$250 of Google Display advertising for $29. Ready-to-run ad designs built from your brand; you approve the campaign before anything runs. Thousands of highly targeted visitors or your $29 back; unused credit refunded. Fulfilled with our partner Layout.ai.',
                'from_price'      => 29.00,
                'supports_design' => false,
                'supports_upload' => false,
                'is_active'       => true,
                'sort_order'      => 0,
            ]
        );

        $product->quantities()->updateOrCreate(
            ['quantity' => 1],
            ['unit_price' => 29.00, 'total_price' => 29.00, 'is_default' => true, 'sort_order' => 0]
        );
    }

    /** The free-website bundle line: included at $0 with the $29 ads package for
     *  buyers WITHOUT a website (the $0 order line keeps the build obligation
     *  visible to fulfilment). */
    private function ensureWebsiteOffer(): void
    {
        $services = Category::firstOrCreate(
            ['slug' => 'services'],
            ['name' => 'Services', 'sort_order' => 8, 'is_active' => true, 'tagline' => 'Beyond print — services that launch your brand.']
        );

        $product = Product::updateOrCreate(
            ['slug' => 'starter-website'],
            [
                'category_id'     => $services->id,
                'subcategory_id'  => null,
                'name'            => 'Free Website — Lifetime Hosting',
                'tagline'         => 'Included free with your qualifying ads offer. Domain name not included.',
                'description'     => 'A professional one-page website designed from your logo and brand colours, with lifetime hosting — no monthly fees. Domain name NOT included: connect a domain you already own, or register one at any registrar (from ~$10/yr). Included FREE with your qualifying ads offer; you approve the design before it goes live.',
                'from_price'      => 0.00,
                'supports_design' => false,
                'supports_upload' => false,
                'is_active'       => true,
                'sort_order'      => 1,
            ]
        );

        $product->quantities()->updateOrCreate(
            ['quantity' => 1],
            ['unit_price' => 0.00, 'total_price' => 0.00, 'is_default' => true, 'sort_order' => 0]
        );
    }
}
