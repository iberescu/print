<?php

namespace App\Support;

/**
 * Static plan for the in-house upsell engine: which "your logo on products"
 * mockups and which display ads to generate, plus the Gemini prompt builders.
 * Every prompt is emphatic about NOT altering the supplied logo — only recolour
 * it to suit the decoration method (print = full colour, laser = black mono,
 * embroidery = stitched).
 */
class BrandKitSpec
{
    /** Common product-photo framing appended to every merch prompt. */
    private const PHOTO = 'Professional e-commerce product photo on a clean, softly-lit light-grey studio '
        .'background, soft realistic shadows, crisp focus, centred, generous margins.';

    /**
     * Merch products. decoration: print|laser|embroidery. slug maps to a real
     * shop product so the gallery card can show a working "Add to order" CTA.
     *
     * @return array<int,array{key:string,label:string,slug:string,decoration:string,scene:string}>
     */
    public static function products(): array
    {
        $all = [
            ['key' => 'mug',     'label' => 'Ceramic mug',    'slug' => 'custom-mugs',                     'decoration' => 'print',      'scene' => 'a plain white ceramic coffee mug'],
            ['key' => 'tumbler', 'label' => 'Steel tumbler',  'slug' => '20-oz-tumbler',                   'decoration' => 'laser',      'scene' => 'a brushed stainless-steel insulated tumbler with a lid'],
            ['key' => 'tote',    'label' => 'Canvas tote',    'slug' => 'custom-canvas-tote-bags',         'decoration' => 'print',      'scene' => 'a natural cotton-canvas tote bag standing upright'],
            ['key' => 'tshirt',  'label' => 'T-shirt',        'slug' => 'gildan-softstyle-unisex-t-shirt', 'decoration' => 'print',      'scene' => 'a neatly folded heather-grey t-shirt'],
            ['key' => 'hoodie',  'label' => 'Hoodie',         'slug' => 'jerzees-nublend-hooded-sweatshirt', 'decoration' => 'embroidery', 'scene' => 'a folded navy pullover hoodie'],
            ['key' => 'cap',     'label' => 'Cap',            'slug' => 'embroidered-hats',                'decoration' => 'embroidery', 'scene' => 'a structured baseball cap, three-quarter view'],
        ];
        $cap = (int) config('shop.internal_engine.max_products', 0);

        return $cap > 0 ? array_slice($all, 0, $cap) : $all;
    }

    /** Build the merch image prompt for a product spec. */
    public static function productPrompt(array $p): string
    {
        $decoration = match ($p['decoration']) {
            'laser' => 'Laser-engrave the provided logo onto the surface. Keep the logo\'s exact shapes, '
                .'letterforms and proportions — do NOT redraw or restyle it — but render it as a single '
                .'dark, monochrome engraving (laser engraving has no colour).',
            'embroidery' => 'Embroider the provided logo onto it. Keep the logo\'s exact shapes, letterforms '
                .'and proportions — do NOT redraw or restyle it — rendered as realistic stitched threads in '
                .'its original colours.',
            default => 'Print the provided logo onto it, centred, at a realistic size. Reproduce the provided '
                .'logo EXACTLY — do NOT redraw, restyle, re-letter or recolour it in any way; keep full colour.',
        };

        return "A studio product shot of {$p['scene']}. {$decoration} ".self::PHOTO
            .' Show only the product — no extra text, no watermark.';
    }

    /**
     * Display-ad angles for the Layout.ai step. {company} filled at runtime.
     *
     * @return array<int,array{key:string,headline:string}>
     */
    public static function ads(): array
    {
        $all = [
            ['headline' => '{company}',                'cta' => 'Learn more'],
            ['headline' => 'Trusted by professionals', 'cta' => 'Learn more'],
            ['headline' => 'Get started today',        'cta' => 'Get started'],
            ['headline' => 'See what we can do',       'cta' => 'Explore'],
            ['headline' => 'Quality you can rely on',  'cta' => 'Learn more'],
            ['headline' => 'Discover {company}',        'cta' => 'Visit site'],
        ];
        $cap = (int) config('shop.internal_engine.max_ads', 0);

        return $cap > 0 ? array_slice($all, 0, $cap) : $all;
    }

    /**
     * Build a Google Display banner prompt from a tailored {headline, cta} concept,
     * grounded in the brand summary (company + what they do + colours) that Gemini
     * produced from the crawled website — so the banner is on-brand and relevant.
     */
    public static function adPrompt(string $headline, string $cta, string $company, ?string $palette, string $description = ''): string
    {
        $headline = str_replace('{company}', $company ?: 'us', $headline);
        $colours = $palette ? "the brand palette ({$palette}) plus tasteful tints and neutrals of it" : "the brand's own colours plus tasteful neutrals";
        $about = trim($description) !== '' ? "{$company} — {$description}" : ($company ?: 'this business');

        return "Design a premium, modern Google Display ad banner in landscape (about 1.9:1), art-directed like "
            ."a real agency creative for {$about}. "
            // subject-grounded signature (avoid generic AI-ad clichés)
            .'Draw the mood and ONE tasteful visual motif from this business\'s actual world — evocative of '
            .'what they do — not generic stock photography, and not abstract gradient blobs or swooshes. '
            // clear focal hierarchy + confident layout
            .'Composition: one clear focal path — the supplied logo as the brand anchor (kept small, top-left '
            .'or a tidy lockup), the headline as the dominant element, then the button. Confident asymmetric '
            .'layout with generous negative space; keep all content inside a safe margin so nothing is cropped. '
            // logo fidelity (hard constraint)
            .'Reproduce the supplied logo EXACTLY — identical shapes, letters, colours and proportions; do NOT '
            .'redraw, restyle, recolour or crop it. '
            // palette + typography-as-personality
            ."Build the design from {$colours}, cohesive and high-contrast. Set the headline \"{$headline}\" in "
            .'a bold, characterful modern sans with strong weight and deliberate spacing — the headline carries '
            ."the personality. Add one solid rounded call-to-action button, high-contrast, labelled exactly "
            ."\"{$cta}\". "
            // restraint + strict text rules
            .'Restraint: one signature element; keep everything else quiet and disciplined — no clutter, no '
            .'busy patterns. The ONLY text in the image is that headline and that button label — no other '
            .'words, no gibberish or placeholder lettering, no watermark, no extra logos, no phone/laptop/'
            .'device mockups, no browser or app UI. Crisp, high-resolution, professional print-ad quality.';
    }
}
