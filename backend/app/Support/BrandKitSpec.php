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
    private const PHOTO = 'Professional e-commerce product photo: the product sitting on a light '
        .'natural-wood table with a soft light wall behind, a soft contact shadow under the product, bright '
        .'even lighting, crisp focus, centred, generous margins.';

    /** Top-down flat-lay framing for flat products (apparel, paper). */
    private const FLATLAY = 'Shot as a clean top-down flat-lay: the product lying flat on a light '
        .'natural-wood table, the camera pointing straight down from directly above, soft natural shadows, '
        .'crisp focus, the whole product centred in frame with generous even margins.';

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
            ['key' => 'tote',    'label' => 'Canvas tote',    'slug' => 'custom-canvas-tote-bags',           'decoration' => 'print',      'scene' => 'a natural cotton-canvas tote bag neatly laid out flat, front side up', 'flat' => true],
            ['key' => 'tshirt',  'label' => 'T-shirt',        'slug' => 'gildan-softstyle-unisex-t-shirt',   'decoration' => 'print',      'scene' => 'a t-shirt neatly laid out flat, front side up', 'placement' => 'in the upper-left chest area (left-breast / pocket position), small', 'flat' => true],
            ['key' => 'hoodie',  'label' => 'Hoodie',         'slug' => 'jerzees-nublend-hooded-sweatshirt', 'decoration' => 'embroidery', 'scene' => 'a pullover hoodie neatly laid out flat, front side up', 'placement' => 'in the upper-left chest area (left-breast position), small', 'flat' => true],
            ['key' => 'cap',     'label' => 'Cap',            'slug' => 'embroidered-hats',                  'decoration' => 'embroidery', 'scene' => 'a structured baseball cap, three-quarter view'],

            // Showcase products with bespoke scenes (decoration = custom → uses its own prompt).
            [
                'key' => 'letterhead', 'label' => 'Letterhead', 'slug' => 'company-letterhead', 'decoration' => 'custom',
                'prompt' => 'A professional printed A4 letterhead / company stationery sheet, portrait '
                    .'orientation, lying flat (top-down) on a light natural-wood table with a soft shadow. The '
                    .'provided logo is printed once in the HEADER at the top of the sheet, at a realistic size. '
                    .'Below it a clean, elegant letterhead: a thin brand-coloured rule under the header and a '
                    .'small placeholder contact line, the rest of the page left blank and white — do NOT fill '
                    .'the body with paragraphs of text or gibberish words.',
            ],
            [
                'key' => 'infinity', 'label' => 'Infinity-mirror LED', 'slug' => 'led-infinity-mirror', 'decoration' => 'custom',
                'prompt' => 'Ultra-realistic 3D infinity-mirror sign die-cut to the shape of the provided '
                    .'logo. IMPORTANT: if the logo has a distinct icon, symbol or graphic mark alongside '
                    .'text, use ONLY that icon/shape and OMIT the text/wordmark entirely; only if the logo '
                    .'is purely a text wordmark with no icon, use the text. It is a channel-letter style '
                    .'sign whose outer contour hugs that shape — NOT a rectangle, square or framed box. '
                    .'Glossy black 3D sides follow the outline; a two-way mirror face reveals an '
                    .'infinity-mirror tunnel INSIDE the shape: the shape\'s OWN OUTLINE repeated as many '
                    .'CRISP, sharp, evenly-spaced concentric neon outlines that shrink and recede straight '
                    .'back into deep black infinite depth — a clean tunnel built from the shape itself, NOT '
                    .'random wavy or diffuse lines. Bright, well-defined neon LED tube lighting in the '
                    .'brand\'s own colours (matching the logo) on the faces, with a vivid glowing rim-light '
                    .'along the raised 3D edges/returns and a soft neon halo on the clean white wall behind. '
                    .'Floating, mounted on a clean white wall in a bright room, premium acrylic and aluminium, '
                    .'ray-traced reflections, clean bright lighting, hyper-detailed, luxury modern product '
                    .'photography, '
                    .'photorealistic, 8K.',
            ],
            [
                'key' => 'wordcloud', 'label' => 'Word-cloud wall art', 'slug' => 'canvas-prints', 'decoration' => 'custom',
                'prompt' => 'A LARGE gallery-wrapped canvas (a big statement piece that fills most of the wall) '
                    .'hanging on a clean white wall, photographed straight on. A dense word cloud COMPLETELY '
                    .'FILLS the shape of the provided logo. IMPORTANT: if the logo has a distinct icon or '
                    .'symbol alongside text, fill ONLY that icon/shape with the words and OMIT the '
                    .'text/wordmark; only if the logo is purely text (no icon), fill the text shape. Pack it '
                    .'DENSELY with ABOUT 100 words in total — a few large focal words and MANY small ones — at '
                    .'varied orientations (horizontal, diagonal AND vertical, rotated at assorted angles) in '
                    .'random positions, leaving almost no empty space inside the shape. Use ONLY these brand '
                    .'words, repeating them at different sizes to reach ~100 words: {keywords}. Set them in the '
                    .'brand colours; keep the overall shape clearly recognisable.',
            ],
            [
                'key' => 'pen', 'label' => 'Pen', 'slug' => 'custom-pens', 'decoration' => 'custom',
                'prompt' => 'A studio product shot of a single sleek promotional pen lying HORIZONTALLY '
                    .'(landscape) on a light natural-wood table with a soft shadow. Show the ENTIRE pen fully '
                    .'within the frame from tip to end — not cropped — with generous margins around it. The '
                    .'provided logo is printed small along the barrel of the pen in landscape orientation.',
            ],
            [
                'key' => 'sticker', 'label' => 'Kiss-cut sticker', 'slug' => 'kiss-cut-stickers', 'decoration' => 'custom',
                'prompt' => 'A close-up product shot of the back lid of a modern laptop on a light natural-wood table, with a '
                    .'single kiss-cut vinyl sticker of the provided logo applied to the BOTTOM-LEFT corner of '
                    .'the laptop lid (not centred), at a small size. The sticker is die-cut to follow the '
                    .'logo\'s own contour/silhouette — a tight outline hugging the logo with a thin white '
                    .'sticker border — lying flat on the lid, at a slight angle, with soft realistic lighting.',
            ],
            ['key' => 'doorhanger', 'label' => 'Door hanger', 'slug' => 'door-hangers', 'decoration' => 'print', 'scene' => 'a printed door hanger with a rounded top and a die-cut keyhole near the top — a round hole with a small narrow slit cut from the hole out to the LEFT edge; the small cut/slit is ALWAYS on the LEFT side and must always be present', 'placement' => 'centred — just the logo, nothing else', 'flat' => true],
            [
                'key' => 'mousepad', 'label' => 'Mouse pad', 'slug' => 'mouse-pads', 'decoration' => 'custom', 'logo_render' => 'white',
                'prompt' => 'A studio product shot of a dark charcoal rectangular cloth mouse pad lying flat on '
                    .'a light natural-wood table, shown from a slight top-down angle. The provided logo is printed small in '
                    .'ONE CORNER of the mouse pad.',
            ],
        ];
        $cap = (int) config('shop.internal_engine.max_products', 0);

        return $cap > 0 ? array_slice($all, 0, $cap) : $all;
    }

    /**
     * Whether a product's scene needs the crawl summary (e.g. the word-cloud
     * canvas uses the brand keywords). These are dispatched AFTER the summary is
     * ready, not in the initial logo-only fan-out.
     */
    public static function needsSummary(array $p): bool
    {
        return str_contains((string) ($p['prompt'] ?? ''), '{keywords}');
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
                $words = $kw ? implode(', ', array_slice($kw, 0, 30)) : (trim((string) ($ctx['company'] ?? '')) ?: 'the brand');
                $prompt = str_replace('{keywords}', $words, $prompt);
            }

            $fidelity = match ($p['logo_render'] ?? 'full') {
                'white' => 'Keep the logo\'s exact shapes, letterforms and proportions — do NOT redraw, '
                    .'re-letter or distort it — but render it in solid WHITE (a clean single-colour white '
                    .'version of the logo).',
                default => $logoFidelity,
            };

            return $prompt.' '.$fidelity.' No watermark and no random gibberish text.';
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

        $framing = ($p['flat'] ?? false) ? self::FLATLAY : self::PHOTO;

        return "A studio product shot of {$p['scene']}. {$decoration}{$contrast} ".$framing
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
    /**
     * Four distinct visual styles cycled across the display ads so they don't
     * look alike: vibrant, professional, minimalist, futuristic.
     */
    public static function adStyle(int $i): string
    {
        return [
            'Make it BOLD, CREATIVE and VIBRANT — energetic saturated colour, a dynamic diagonal composition and playful confident shapes; eye-catching and lively (still tasteful and on-brand).',
            'Make it POLISHED and PROFESSIONAL — a refined corporate look, calm restrained palette, a clean structured grid; ordered, trustworthy, enterprise-grade.',
            'Make it MINIMALIST — maximum negative space, one small focal element, only one or two colours; quiet, elegant, essentials only.',
            'Make it FUTURISTIC and high-tech — sleek and modern with subtle glow, gradient depth and an abstract tech/3D motif; a cutting-edge, premium sci-fi feel.',
        ][(($i % 4) + 4) % 4];
    }

    public static function adPrompt(string $headline, string $cta, string $company, ?string $palette, string $description = '', string $style = ''): string
    {
        $headline = str_replace('{company}', $company ?: 'us', $headline);
        $colours = "the colours OF THE SUPPLIED LOGO as the DOMINANT palette — match the logo's own actual "
            .'colours (that is the brand colour)'
            .($palette ? ", and use {$palette} ONLY as light secondary accents; an accent colour must never dominate over the logo's colour" : '');
        $about = trim($description) !== '' ? "{$company} — {$description}" : ($company ?: 'this business');
        $style = $style ?: 'Polished, professional B2B brand ad — corporate, credible, enterprise-grade.';

        return "Design a striking, studio-grade Google Display ad banner (landscape ~1.9:1) for {$about} — the "
            ."work of a designer with real taste, NOT a template assembled from defaults. {$style} "
            // hierarchy
            ."HIERARCHY: one thing dominates — the headline \"{$headline}\" set BIG and confident (3-6x the "
            .'size of any supporting text), in a bold, characterful modern sans that carries the personality; '
            .'then the CTA; then the small logo. If everything is the same weight there is no design. '
            // composition + depth + frame-breaking
            .'COMPOSITION: deliberate and asymmetric — NOT a centred stack with equal margins. Off-grid '
            .'placement, intentional imbalance, and generous INTENTIONAL negative space (crowded is amateur — '
            .'let it breathe). Build layered depth (background → a mid-ground motif/shape → type on top) and '
            .'break the frame with confidence — let a background shape or the motif crop at / bleed past an '
            .'edge. But ALL TEXT and the logo stay fully inside a safe margin and completely readable — never '
            .'let the headline, CTA or logo touch or run off an edge or get clipped. '
            // subject-grounded, editorial, anti-cliché, no people
            ."MOTIF: ONE tasteful visual element drawn from {$company}'s actual world (its objects, materials, "
            .'environment, or an abstract form tied to what they do) — editorial and cinematic, dramatic '
            .'off-angle lighting. NOT generic stock, NOT lazy gradient blobs or swooshes, NOT the most-obvious '
            .'cliché image of this industry. NO people, faces, hands or crowds. '
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
            ."COLOUR: build from {$colours}. Harmonious and intentional — one dominant colour over the most "
            .'surface with the accent used sparingly; get contrast from VALUE (light vs dark) and scale, not '
            .'saturation wars; avoid neon and clashing primaries. '
            // CTA (display-ad button, sharp corners)
            ."CTA: one solid rectangular call-to-action block with SHARP SQUARE corners (never rounded or "
            ."pill-shaped), high-contrast, labelled exactly \"{$cta}\". "
            // strict text rules
            .'STRICT: the ONLY text in the image is that headline and that CTA label — no other words, no '
            .'gibberish or placeholder lettering, no watermark, no extra logos, no phone/laptop/device '
            .'mockups, no browser or app UI, and NO scattered decorative accents (dots, plus-signs, confetti). '
            .'Crisp, high-resolution, art-directed ad-creative quality.';
    }
}
