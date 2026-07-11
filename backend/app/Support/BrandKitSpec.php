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
            ['key' => 'mug',     'label' => 'Ceramic mug',    'slug' => 'custom-mugs',                       'decoration' => 'print',      'scene' => 'a plain white ceramic coffee mug'],
            ['key' => 'tumbler', 'label' => 'Steel tumbler',  'slug' => '20-oz-tumbler',                     'decoration' => 'laser',      'scene' => 'a brushed stainless-steel insulated tumbler with a lid'],
            ['key' => 'tote',    'label' => 'Canvas tote',    'slug' => 'custom-canvas-tote-bags',           'decoration' => 'print',      'scene' => 'a natural cotton-canvas tote bag standing upright'],
            ['key' => 'tshirt',  'label' => 'T-shirt',        'slug' => 'gildan-softstyle-unisex-t-shirt',   'decoration' => 'print',      'scene' => 'a t-shirt laid flat and shown from the front', 'placement' => 'in the upper-left chest area (left-breast / pocket position), small'],
            ['key' => 'hoodie',  'label' => 'Hoodie',         'slug' => 'jerzees-nublend-hooded-sweatshirt', 'decoration' => 'embroidery', 'scene' => 'a folded pullover hoodie'],
            ['key' => 'cap',     'label' => 'Cap',            'slug' => 'embroidered-hats',                  'decoration' => 'embroidery', 'scene' => 'a structured baseball cap, three-quarter view'],

            // Showcase products with bespoke scenes (decoration = custom → uses its own prompt).
            [
                'key' => 'letterhead', 'label' => 'Letterhead', 'slug' => 'company-letterhead', 'decoration' => 'custom',
                'prompt' => 'A professional printed A4 letterhead / company stationery sheet, portrait '
                    .'orientation, photographed flat (top-down) on a clean light desk with soft shadows. The '
                    .'provided logo is printed once in the HEADER at the top of the sheet, at a realistic size. '
                    .'Below it a clean, elegant letterhead: a thin brand-coloured rule under the header and a '
                    .'small placeholder contact line, the rest of the page left blank and white — do NOT fill '
                    .'the body with paragraphs of text or gibberish words.',
            ],
            [
                'key' => 'infinity', 'label' => 'Infinity-mirror LED', 'slug' => 'led-infinity-mirror', 'decoration' => 'custom',
                'prompt' => 'A modern infinity-mirror LED wall panel mounted on a dark interior wall, '
                    .'photographed straight on in a dimly lit room. The glowing LED light forms the exact '
                    .'shape of the provided logo and repeats into infinite receding depth (infinity-mirror '
                    .'effect), glowing in the logo\'s own colours against a deep black mirror.',
            ],
            [
                'key' => 'wordcloud', 'label' => 'Word-cloud canvas', 'slug' => 'framed-canvas-prints', 'decoration' => 'custom',
                'prompt' => 'A framed canvas art print hanging on a clean interior wall, photographed straight '
                    .'on. The canvas shows a word cloud that COMPLETELY FILLS the silhouette of the provided '
                    .'logo — the words are sized and packed to fill the logo\'s outline so the entire logo '
                    .'shape is formed out of words. Use ONLY these brand words: {keywords}. Set them in the '
                    .'brand colours on a clean background, keeping the overall logo shape clearly recognisable.',
            ],
            [
                'key' => 'ledrhombus', 'label' => 'LED rhombus panel', 'slug' => 'led-panel', 'decoration' => 'custom',
                'prompt' => 'A wall-mounted decorative LED panel built from many small illuminated rhombus '
                    .'(diamond) tiles fitted together like a mosaic, photographed straight on. The lit rhombus '
                    .'tiles are arranged and coloured to approximate the shape of the provided logo as closely '
                    .'as the diamond grid allows — like a low-resolution diamond-pixel display of the logo — '
                    .'glowing in the logo\'s colours.',
            ],
            [
                'key' => 'pen', 'label' => 'Pen', 'slug' => 'custom-pens', 'decoration' => 'custom',
                'prompt' => 'A studio close-up product shot of a single sleek promotional pen lying '
                    .'HORIZONTALLY (landscape) on a clean light-grey surface with soft shadows. The provided '
                    .'logo is printed small along the barrel of the pen in landscape orientation.',
            ],
        ];
        $cap = (int) config('shop.internal_engine.max_products', 0);

        return $cap > 0 ? array_slice($all, 0, $cap) : $all;
    }

    /**
     * Build the merch image prompt for a product spec. $ctx carries brand data
     * from the crawl summary (keywords/company/colors) for the custom scenes.
     *
     * @param  array<string,mixed>  $ctx
     */
    public static function productPrompt(array $p, array $ctx = []): string
    {
        $logoFidelity = 'Reproduce the provided logo EXACTLY — same shapes, letterforms, colours and '
            .'proportions; do NOT redraw, restyle, re-letter or recolour it.';

        // Showcase products carry their own full scene prompt.
        if (($p['decoration'] ?? '') === 'custom') {
            $prompt = (string) ($p['prompt'] ?? '');
            if (str_contains($prompt, '{keywords}')) {
                $kw = array_values(array_filter(array_map('trim', (array) ($ctx['keywords'] ?? []))));
                $words = $kw ? implode(', ', array_slice($kw, 0, 18)) : (trim((string) ($ctx['company'] ?? '')) ?: 'the brand');
                $prompt = str_replace('{keywords}', $words, $prompt);
            }

            return $prompt.' '.$logoFidelity.' No watermark and no random gibberish text.';
        }

        $placement = $p['placement'] ?? 'centred';
        $decoration = match ($p['decoration']) {
            'laser' => "Laser-engrave the provided logo onto the surface, {$placement}. Keep the logo's exact "
                .'shapes, letterforms and proportions — do NOT redraw or restyle it — rendered as a single '
                .'dark, monochrome engraving (laser engraving has no colour).',
            'embroidery' => "Embroider the provided logo onto it, {$placement}. Keep the logo's exact shapes, "
                .'letterforms and proportions — do NOT redraw or restyle it — as realistic stitched threads '
                .'in its original colours.',
            default => "Print the provided logo onto it {$placement}, at a realistic size. {$logoFidelity} "
                .'Keep full colour.',
        };

        // Feedback: never place the logo on a same-colour surface (no blue-on-blue).
        $contrast = ' IMPORTANT — logo/product contrast: the logo must stand out from the surface it sits on. '
            .'If the logo\'s colours are similar to the product\'s colour (e.g. a blue logo on a blue '
            .'product), make the PRODUCT WHITE instead (or charcoal if the logo itself is white/very light) '
            .'so the logo is clearly legible — never let the logo blend into a same-colour background.';

        return "A studio product shot of {$p['scene']}. {$decoration}{$contrast} ".self::PHOTO
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
        $colours = $palette
            ? "the brand's own colours ({$palette}) — taken from its logo and website — as the DOMINANT palette"
            : "the brand's own colours (taken directly from its logo) as the DOMINANT palette";
        $about = trim($description) !== '' ? "{$company} — {$description}" : ($company ?: 'this business');

        return "Design a premium, modern Google Display ad banner in landscape (about 1.9:1), art-directed like "
            ."a real agency creative for {$about}. Make it a professional B2B brand ad — corporate, credible "
            .'and enterprise-grade, not a consumer retail sale. '
            // subject-grounded signature (avoid generic AI-ad clichés)
            .'Draw the mood and ONE tasteful visual motif from this business\'s actual world — evocative of '
            .'what they do — not generic stock photography, and not abstract gradient blobs or swooshes. '
            // clear focal hierarchy + confident layout
            .'Composition: one clear focal path — the supplied logo as the brand anchor (kept small, top-left '
            .'or a tidy lockup), the headline as the dominant element, then the button. Confident asymmetric '
            .'layout with generous negative space; keep all content inside a safe margin so nothing is cropped. '
            // logo fidelity — the user's TOP priority; keep it emphatic
            .'CRITICAL — DO NOT CHANGE THE LOGO: treat the supplied logo as a fixed image asset and place it '
            .'AS-IS (as if pasting the original file), never recreated. It must appear pixel-for-pixel '
            .'identical in shapes, letterforms, colours, proportions and spacing. Keep every FILLED/solid '
            .'shape filled — do NOT turn filled areas into outlines or add strokes. Keep its EXACT original '
            .'colours — do NOT brighten, saturate or shift any hue (dark navy stays dark navy, not a brighter '
            .'blue), and never recolour it to white or black for contrast. Do NOT redraw, restyle, re-letter, '
            .'crop, rotate, distort or add effects. If the area behind it would be dark or busy, sit the logo '
            .'on a clean solid light or white panel so it stays legible in its true colours. '
            // palette + typography-as-personality
            ."Build the design from {$colours}, used across the whole banner — background, shapes, accents and "
            ."the CTA — cohesive and high-contrast. Set the headline \"{$headline}\" in "
            .'a bold, characterful modern sans with strong weight and deliberate spacing — the headline carries '
            ."the personality. Add one solid rectangular call-to-action button with sharp square corners, "
            ."high-contrast, labelled exactly \"{$cta}\". Use sharp rectangular geometry throughout — NO "
            ."rounded corners anywhere (button, panels, dividers or image frame). "
            // restraint + strict text rules
            .'Restraint: one signature element; keep everything else quiet and disciplined — no clutter, no '
            .'busy patterns. The ONLY text in the image is that headline and that button label — no other '
            .'words, no gibberish or placeholder lettering, no watermark, no extra logos, no phone/laptop/'
            .'device mockups, no browser or app UI. Crisp, high-resolution, professional print-ad quality.';
    }
}
