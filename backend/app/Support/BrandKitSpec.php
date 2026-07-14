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

    /** Appended to every image prompt: force a square frame and never crop the product. */
    private const SQUARE_FRAME = 'The final image MUST be a SQUARE 1:1 composition with the WHOLE product '
        .'fully inside the frame and generous even margins on all sides — never crop the product or let any '
        .'part of it touch or run off an edge.';

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
            [
                'key' => 'tumbler', 'label' => 'Tumbler', 'slug' => '20-oz-tumbler', 'decoration' => 'custom', 'logo_render' => 'laser',
                'placement' => 'on the front, centred', 'stock_color' => true,
                'prompt' => 'A studio product shot of an insulated tumbler with a lid, sitting on a light '
                    .'natural-wood table with a soft light wall behind, a soft contact shadow, bright even '
                    .'lighting, crisp focus, centred with generous margins. The tumbler body is a single solid '
                    .'colour chosen from ONLY these: white, black, blue or red — pick the one that best '
                    .'complements the logo. The provided logo is laser-engraved on the front, centred.',
                'base_prompt' => 'A studio product shot of an insulated stainless-steel tumbler with a lid, sitting '
                    .'on a light natural-wood table with a soft light wall behind, a soft contact shadow, bright even '
                    .'lighting, crisp focus, centred with generous margins. The tumbler body is plain solid WHITE — '
                    .'completely blank and unbranded, no logo, no text, no graphics. Show only the product. No watermark.',
            ],
            ['key' => 'tote',    'label' => 'Canvas tote',    'slug' => 'custom-canvas-tote-bags',           'decoration' => 'print',      'scene' => 'a natural cotton-canvas tote bag neatly laid out flat, front side up', 'flat' => true],
            ['key' => 'tshirt',  'label' => 'T-shirt',        'slug' => 'gildan-softstyle-unisex-t-shirt',   'decoration' => 'print',      'scene' => 'a plain WHITE t-shirt neatly laid out flat, front side up', 'placement' => 'in the upper-left chest area (left-breast / pocket position), small', 'flat' => true, 'stock_color' => true],
            ['key' => 'hoodie',  'label' => 'Hoodie',         'slug' => 'jerzees-nublend-hooded-sweatshirt', 'decoration' => 'embroidery', 'scene' => 'a plain WHITE pullover hoodie neatly laid out flat, front side up', 'placement' => 'high on the UPPER-LEFT chest, at the left-breast / pocket position — small and clearly toward the TOP-LEFT of the hoodie front, never centred', 'flat' => true],
            ['key' => 'cap',     'label' => 'Cap',            'slug' => 'embroidered-hats',                  'decoration' => 'embroidery', 'scene' => 'a structured WHITE baseball cap, three-quarter view'],

            // Showcase products with bespoke scenes (decoration = custom → uses its own prompt).
            [
                'key' => 'letterhead', 'label' => 'Letterhead', 'slug' => 'company-letterhead', 'decoration' => 'custom',
                'prompt' => 'A professional printed A4 letterhead / company stationery sheet, portrait '
                    .'orientation, lying flat (top-down) on a light natural-wood table with a soft shadow. The '
                    .'provided logo is printed once in the HEADER at the top of the sheet, at a realistic size, '
                    .'with the company name "{company}" set cleanly beside or beneath it and the website "{url}" '
                    .'in a small contact line. Below the header a thin brand-coloured rule, then the rest of the '
                    .'page left blank and white — do NOT fill the body with paragraphs of text or gibberish words.',
            ],
            [
                'key' => 'infinity', 'label' => 'Infinity-mirror LED', 'slug' => 'led-infinity-mirror', 'decoration' => 'custom',
                // A style TEMPLATE (resources/product-bases/infinity.webp) is sent to Gemini as image 1
                // so it reproduces the real infinity-mirror EFFECT, not just from the text prompt.
                'place_prompt' => 'Image 1 is a PHOTOGRAPHIC EXAMPLE of a finished infinity-mirror LED sign — use it '
                    .'ONLY as a reference for the STYLE, materials and effect to reproduce: the infinity-mirror tunnel '
                    .'of many CRISP, evenly-spaced concentric neon outlines receding into deep black infinite depth, the '
                    .'glossy 3D edges/returns, the two-way mirror face, and the soft neon glow/halo on the wall behind. '
                    .'Image 2 is the brand logo. Build a NEW infinity-mirror sign whose overall SHAPE is DIE-CUT to the '
                    .'logo in image 2 — its outer contour hugs the LOGO\'s shape (NOT the shape, letters or wordmark of '
                    .'image 1). If the logo has a distinct icon/symbol alongside text, use ONLY that icon/shape and OMIT '
                    .'the wordmark; only a pure text wordmark uses the text. The concentric neon tunnel outlines follow '
                    .'the logo\'s own outline, in the brand\'s colours (matching the logo). Floating, mounted on a clean '
                    .'white wall in a bright room, premium acrylic and aluminium, ray-traced reflections, hyper-detailed, '
                    .'photorealistic, 8K. Do NOT copy image 1\'s letters or outline. Output only the sign, no watermark, no text.',
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
                'key' => 'pen', 'label' => 'Pen', 'slug' => 'custom-pens', 'decoration' => 'custom', 'logo_render' => 'laser',
                'placement' => 'laser-engraved small along the middle of the barrel, in landscape orientation', 'stock_color' => true,
                'base_prompt' => 'A studio product shot showing ONE entire promotional ANODISED ALUMINIUM METAL pen, '
                    .'COMPLETE and UNCROPPED, lying HORIZONTALLY (landscape) on a light natural-wood table with a soft '
                    .'shadow. Frame it ZOOMED OUT so the WHOLE pen — from the polished CHROME cone writing tip at one end '
                    .'to the CHROME push-button and pocket clip at the other — sits fully inside the frame at a small '
                    .'size, with generous EMPTY margin on ALL FOUR sides; both ends clearly visible with space around '
                    .'them. It is a metal pen with a smooth matte soft-touch COATED aluminium barrel in solid WHITE, with '
                    .'polished silver/chrome accents: a chrome cone tip, a chrome clip, and two thin chrome rings/bands '
                    .'around the middle of the barrel. The barrel is completely BLANK — no logo, no text, no engraving. '
                    .'Realistic metal materials, subtle reflections and soft lighting. No watermark.',
                'prompt' => 'A studio product shot showing ONE entire promotional pen, COMPLETE and UNCROPPED, lying '
                    .'HORIZONTALLY (landscape) on a light natural-wood table with a soft shadow. CRITICAL: frame it '
                    .'ZOOMED OUT so the WHOLE pen — from the pointed writing tip at one end all the way to the '
                    .'push-button/cap at the other end — sits fully inside the frame at a small size, with generous '
                    .'EMPTY margin on ALL FOUR sides. No part of the pen may touch, run off or be cropped by any '
                    .'edge; both ends of the pen must be clearly visible with space around them. The pen barrel is a '
                    .'single solid colour chosen from ONLY these: white, black, blue or red — pick the one that best '
                    .'complements the logo. The provided logo is laser-engraved small along the barrel in landscape '
                    .'orientation.',
            ],
            [
                'key' => 'sticker', 'label' => 'Kiss-cut sticker', 'slug' => 'kiss-cut-stickers', 'decoration' => 'custom',
                // Fixed laptop base (no sticker); only the die-cut logo sticker is composited at runtime.
                'base_prompt' => 'A close-up product shot of the closed lid of a modern Apple MacBook (slim silver / '
                    .'space-grey aluminium unibody) lying on a light natural-wood table, viewed from above with the '
                    .'front OPENING edge (the side that lifts to open, with the shallow finger scoop) NEAREST the '
                    .'camera and the hinge along the FAR edge. Make it clearly a MacBook. CRITICAL — orient the '
                    .'MacBook\'s own centred Apple logo exactly as on a real modern MacBook: seen closed from the '
                    .'front like this it appears UPSIDE-DOWN to the camera — its leaf/stem points toward the FRONT '
                    .'opening edge nearest the camera and its rounded base toward the hinge at the back. Do NOT draw '
                    .'the Apple logo right-side-up to the camera. The lid is CLEAN — NO stickers, decals or graphics '
                    .'of any kind. Soft realistic lighting, no clutter.',
                'place_prompt' => 'Image 1 is a photo of a closed MacBook laptop lid. Image 2 is a brand logo. Apply a '
                    .'SINGLE kiss-cut vinyl sticker of the logo (image 2) to the BOTTOM-LEFT corner of the laptop lid '
                    .'(not centred), at a small size — die-cut to follow the logo\'s own contour/silhouette with a thin '
                    .'white sticker border, lying flat, and reading UPRIGHT and clearly legible to the camera. Keep the '
                    .'laptop, table, camera angle, lighting and the MacBook\'s own Apple logo EXACTLY as in image 1 — '
                    .'add ONLY the sticker. Output only the photo, no watermark, no extra text.',
                'prompt' => 'A close-up product shot of the closed lid of a modern Apple MacBook (slim silver / '
                    .'space-grey aluminium unibody) lying on a light natural-wood table, viewed from above with the '
                    .'front OPENING edge (the side that lifts to open, with the shallow finger scoop) NEAREST the '
                    .'camera and the hinge along the FAR edge. Make it clearly a MacBook. CRITICAL — orient the '
                    .'MacBook\'s own centred Apple logo exactly as on a real modern MacBook: seen closed from the '
                    .'front like this it appears UPSIDE-DOWN to the camera — its leaf/stem points toward the FRONT '
                    .'opening edge nearest the camera and its rounded base toward the hinge at the back. (On a real '
                    .'MacBook the Apple logo is right-side-up only to a person facing you when the lid is open.) Do '
                    .'NOT draw the Apple logo right-side-up to the camera. Separately, a single kiss-cut vinyl '
                    .'sticker of the PROVIDED logo is applied to the BOTTOM-LEFT corner of the lid (not centred), at '
                    .'a small size, and it reads UPRIGHT and clearly legible to the camera — die-cut to follow the '
                    .'logo\'s own contour/silhouette with a thin white sticker border, lying flat, with soft '
                    .'realistic lighting.',
            ],
            ['key' => 'doorhanger', 'label' => 'Door hanger', 'slug' => 'door-hangers', 'decoration' => 'print', 'scene' => 'a printed door hanger with a rounded top and a die-cut keyhole near the top — a round hole with a small narrow slit cut from the hole out to the LEFT edge; the small cut/slit is ALWAYS on the LEFT side and must always be present', 'placement' => 'centred — just the logo, nothing else', 'flat' => true],
            [
                'key' => 'mousepad', 'label' => 'Mouse pad', 'slug' => 'mouse-pads', 'decoration' => 'custom', 'logo_render' => 'white',
                'placement' => 'small in ONE CORNER of the mouse pad',
                'base_prompt' => 'A studio product shot of a dark charcoal rectangular cloth mouse pad lying flat on '
                    .'a light natural-wood table, shown from a slight top-down angle. The mouse pad is completely BLANK '
                    .'and unbranded — no logo, no text, no graphics. Show only the product. No watermark.',
                'prompt' => 'A studio product shot of a dark charcoal rectangular cloth mouse pad lying flat on '
                    .'a light natural-wood table, shown from a slight top-down angle. The provided logo is printed small in '
                    .'ONE CORNER of the mouse pad.',
            ],
            [
                // image-only card for now (slug intentionally maps to no real product, so the
                // gallery shows just the picture — no price / add-to-cart, and no accessory touched).
                'key' => 'review-stand', 'label' => 'Google review sign', 'slug' => 'google-review-stand', 'decoration' => 'custom',
                'prompt' => 'A premium clear-acrylic tabletop sign held upright in a small acrylic base/stand on a '
                    .'light natural-wood counter, photographed straight-on at a slight angle with soft realistic '
                    .'lighting. The printed card inside is a "Leave us a Google review" counter sign with a clean, '
                    .'uncluttered, on-brand layout: the provided logo at the top, then the company name "{company}", '
                    .'a short line "Scan to leave us a Google review", a QR code printed clearly in the lower half, '
                    .'and the website "{url}" as a small line at the bottom. If no QR code image is supplied, draw a '
                    .'realistic placeholder QR code graphic in that spot.',
            ],
            // Website-styled print pieces — generated LAST and only after the crawl, using the
            // logo + brand summary + homepage screenshot (see after_crawl + use_site_shot).
            [
                'key' => 'brochure', 'label' => 'Tri-fold brochure', 'slug' => 'tri-fold-brochures', 'decoration' => 'custom',
                'after_crawl' => true, 'use_site_shot' => true,
                // Fixed blank tri-fold mockup — LYING on a wood table, gently opened, shot from a
                // three-quarter angle; EXACTLY three panels. Only the print is dynamic.
                'base_prompt' => 'A premium studio product mockup of a BLANK standard tri-fold brochure LYING FLAT on a '
                    .'light natural-wood table, photographed from a THREE-QUARTER angle (from slightly above and to the '
                    .'side — NOT straight top-down and NOT standing upright). It is a standard US Letter tri-fold: an '
                    .'8.5 × 11 inch sheet folded into EXACTLY THREE panels (a tri-fold has THREE panels — no more), each '
                    .'a TALL, NARROW PORTRAIT panel about 3.66 inches wide by 8.5 inches tall (clearly taller than it is '
                    .'wide). The brochure rests on the table gently opened so its three panels fan slightly and the two '
                    .'vertical fold creases and the paper\'s soft 3D lift are visible, with natural contact shadows on '
                    .'the wood. The paper is completely BLANK white — no logo, no text, no graphics of any kind. Soft '
                    .'realistic lighting, generous margins, no clutter, no watermark.',
                'place_prompt' => 'Image 1 is a photo of a blank tri-fold brochure LYING on a light natural-wood table, '
                    .'gently opened, with EXACTLY three tall narrow portrait panels. Image 2 is the brand logo. If a '
                    .'third image is provided it is the brand\'s website screenshot — match its colours, typography and '
                    .'imagery. Print a professional, on-brand tri-fold marketing design across the THREE panels for '
                    .'{company} — {description}, with each panel\'s artwork following that panel\'s own angle and '
                    .'perspective: one panel is the front cover carrying the logo (image 2, reproduced EXACTLY) and a '
                    .'short brand headline; the other two carry tasteful marketing copy and on-brand imagery, organised '
                    .'panel-by-panel. Keep the brochure LYING on the wood table exactly as image 1 — its position, the '
                    .'three-panel fold, the panel angles, the creases, the three-quarter camera view, lighting and '
                    .'shadows unchanged; do NOT add any extra panels or flaps (only THREE panels). Output only the '
                    .'photo, realistic clean print, no watermark, no gibberish text.',
                'prompt' => 'A realistic studio mockup of a printed TRI-FOLD brochure for {company} — {description} — '
                    .'resting on a clean surface at a slight three-quarter angle with soft realistic lighting, its '
                    .'three panels partly fanned so the folds are clearly visible. Design it in the brand\'s own visual '
                    .'style — a colour palette, typography and imagery that genuinely suit this business. The front '
                    .'panel carries the logo and a short brand headline; the inner panels carry tasteful placeholder '
                    .'marketing copy and on-brand imagery. Realistic paper with natural fold shadows, no clutter.',
            ],
            [
                'key' => 'flyer', 'label' => 'Flyer', 'slug' => 'flyers', 'decoration' => 'custom',
                'after_crawl' => true, 'use_site_shot' => true,
                'prompt' => 'A realistic studio PRODUCT PHOTO of a single printed marketing FLYER (one A4/Letter '
                    .'paper sheet) for {company} — {description} — lying flat on a light natural-wood table, shot '
                    .'from above at a slight three-quarter angle with soft realistic lighting and a soft drop '
                    .'shadow, so it clearly reads as a real printed sheet of paper resting on a table (NOT a flat '
                    .'full-frame digital graphic and NOT a website screenshot). Show the WHOLE flyer with a little '
                    .'empty margin of table around it. The flyer is designed in the brand\'s own visual style — '
                    .'colour palette, typography and imagery that suit this business — with the logo at the top, a '
                    .'bold brand headline, a hero image and tasteful placeholder marketing copy. Realistic paper '
                    .'with crisp edges, no clutter.',
            ],
        ];
        $cap = (int) config('shop.internal_engine.max_products', 0);

        return $cap > 0 ? array_slice($all, 0, $cap) : $all;
    }

    /**
     * How many product mockups a capture will actually produce. The website-styled
     * pieces (brochure/flyer, flagged after_crawl) are only generated when there's a
     * URL to crawl — so the "products done" check must not wait on them otherwise.
     */
    public static function expectedProductCount(bool $hasUrl): int
    {
        return count(array_filter(self::products(), fn ($p) => $hasUrl || ! ($p['after_crawl'] ?? false)));
    }

    /**
     * Whether a product's scene needs the crawl summary (e.g. the word-cloud
     * canvas uses the brand keywords). These are dispatched AFTER the summary is
     * ready, not in the initial logo-only fan-out.
     */
    public static function needsSummary(array $p): bool
    {
        // Dispatched AFTER the crawl (they need the summary and/or the screenshot): the
        // website-styled brochure & flyer, plus any {keywords} scene (dormant word-cloud).
        return ($p['after_crawl'] ?? false) || str_contains((string) ($p['prompt'] ?? ''), '{keywords}');
    }

    /**
     * The QR-builder "your [logo +] QR on products" set. With a logo: the merch
     * roster (logo) plus the paper set carrying logo + QR. Without a logo: just the
     * paper set carrying the QR. Paper = business card, letterhead, flyer, poster.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function qrProducts(bool $hasLogo): array
    {
        $paper = self::qrPaperProducts();
        if (! $hasLogo) {
            return $paper; // QR only → paper products only
        }
        // logo + QR: merch roster (logo) minus letterhead (it moves to the paper set), plus the paper set
        // (which carries the real QR — with the logo already in its centre when one was uploaded).
        // exclude letterhead (it moves to the paper set) and the website-styled pieces
        // (brochure/flyer) — the QR flow has no crawl/screenshot to drive those.
        $merch = array_values(array_filter(self::products(), fn ($p) => ($p['key'] ?? '') !== 'letterhead' && ! ($p['after_crawl'] ?? false)));

        return array_merge($merch, $paper);
    }

    /**
     * Paper/print products carrying the QR code. The QR image is passed to Gemini
     * as a FIXED asset (exactly like the logo) and placed as-is — it already has the
     * logo in its centre when the buyer uploaded one, so no separate logo is placed.
     */
    private static function qrPaperProducts(): array
    {
        $scene = fn (string $desc) => "A clean, print-ready studio mockup of {$desc}. Place the provided QR code "
            .'prominently and clearly in a tasteful spot (a corner or beside the contact details), flat and '
            .'straight-on at a realistic size. Add tasteful placeholder text (company name / contact details / '
            .'a short call to action). Realistic lighting, no clutter.';

        return [
            ['key' => 'qr-businesscard', 'label' => 'Business card', 'slug' => 'matte-business-cards', 'decoration' => 'custom', 'inputs' => ['qr'],
                'prompt' => $scene('a professional business card lying flat on a light natural-wood table, photographed straight down')],
            ['key' => 'qr-letterhead', 'label' => 'Letterhead', 'slug' => 'company-letterhead', 'decoration' => 'custom', 'inputs' => ['qr'],
                'prompt' => $scene('an A4 letterhead sheet on a light desk, photographed straight down, with a header/footer layout and faint placeholder body-text lines')],
            ['key' => 'qr-flyer', 'label' => 'Flyer', 'slug' => 'flyers', 'decoration' => 'custom', 'inputs' => ['qr'],
                'prompt' => $scene('a marketing flyer on a light surface, photographed straight down, with a bold headline')],
            ['key' => 'qr-poster', 'label' => 'Poster', 'slug' => 'custom-posters', 'decoration' => 'custom', 'inputs' => ['qr'],
                'prompt' => $scene('a poster mounted flat on a clean light wall, photographed straight on, with a strong headline')],
        ];
    }

    /**
     * QR-code business card for the designer/upload flow (logo + website): we build the
     * QR (website, rounded, black, logo in the centre) with our own tool and Gemini
     * places it, unmodified, on the CARD BACK. inputs=['qr'] → productPrompt adds the
     * "fixed asset, don't modify" clause; the QR path is passed via spec['qr_asset'].
     */
    public static function qrBusinessCard(): array
    {
        return [
            'key'        => 'qr-card',
            'label'      => 'QR code business card',
            'slug'       => 'qr-code-business-cards',
            'decoration' => 'custom',
            'inputs'     => ['qr'],
            // Fixed blank-card base; only the QR (content) is composited so every card looks identical.
            'base_prompt' => 'A clean, print-ready studio mockup of a SINGLE plain WHITE business card lying flat on a '
                .'light natural-wood table, photographed straight down (top-down), centred in frame with even margins. '
                .'The card is completely blank white — NO text, QR code or graphics of any kind. Realistic soft '
                .'lighting, no clutter, no watermark.',
            'place_prompt' => 'Image 1 is a photo of a blank white business card. Image 2 is a QR code. Place the QR '
                .'code (image 2) in the exact CENTRE of the card — centred horizontally and vertically — at a tasteful '
                .'size with even white margins around it. The QR is a FIXED ASSET: place it AS-IS, pixel-for-pixel '
                .'identical, do NOT redraw, recolour, sharpen, crop, rotate or distort it; keep it perfectly square so '
                .'it stays scannable. Keep the card, table, camera angle and lighting EXACTLY as in image 1 — the card '
                .'is otherwise blank white with NO other text or graphics. Output only the photo, no watermark.',
            'prompt'     => 'A clean, print-ready studio mockup of a SINGLE plain WHITE business card lying flat on a '
                .'light natural-wood table, photographed straight down (top-down). Place the provided QR code in the '
                .'exact CENTRE of the card — centred horizontally and vertically — at a tasteful size with even white '
                .'margins around it. The card is otherwise blank white with NO other text or graphics. Realistic soft '
                .'lighting, no clutter.',
        ];
    }

    /**
     * Whether this product uses the pre-generated base + runtime logo-composite flow
     * (fixed-shape merch). Shape-derived pieces (die-cut sticker, infinity mirror),
     * website-styled pieces and the QR/text layouts keep the direct one-shot generation.
     */
    public static function hasBase(array $p): bool
    {
        return self::basePrompt($p) !== null;
    }

    /**
     * Prompt to pre-generate the BLANK, unbranded product base (no logo/graphics).
     * Explicit `base_prompt` wins; otherwise it's derived from a scene-based spec.
     * Returns null for products that shouldn't use the base flow.
     */
    public static function basePrompt(array $p): ?string
    {
        if (isset($p['base_prompt'])) {
            return $p['base_prompt'].' '.self::SQUARE_FRAME;
        }
        // Auto-derive for simple scene-based merch (skip custom/showcase scenes).
        if (($p['decoration'] ?? '') === 'custom' || empty($p['scene'])) {
            return null;
        }
        $framing = ($p['flat'] ?? false) ? self::FLATLAY : self::PHOTO;

        return "A studio product shot of {$p['scene']}. The product is completely BLANK and unbranded — "
            .'no logo, no text, no graphics, no decoration of any kind, just the plain product. '
            .$framing.' Show only the product. No watermark, no text. '.self::SQUARE_FRAME;
    }

    /**
     * Runtime prompt that places the brand logo onto a PRE-GENERATED product photo
     * (image 1 = the blank product base, image 2 = the logo) so the product looks
     * identical every time and only the branding changes. Per the decoration method,
     * and — per product-owner request — Gemini MAY recolour the product to suit the
     * brand, choosing only from the supplied brand palette.
     *
     * @param  array<string,mixed>  $ctx
     */
    public static function placePrompt(array $p, array $ctx = []): string
    {
        $placement = $p['placement'] ?? 'in the natural branding spot, tastefully sized';
        $mode = $p['logo_render'] ?? ($p['decoration'] ?? 'print');
        $how = match ($mode) {
            'laser' => "Laser-engrave the logo onto the product, {$placement}. Keep the logo's exact shapes, "
                .'letterforms and proportions — do NOT redraw or restyle it — rendered as a REALISTIC laser engraving '
                .'that reveals the metal under the coloured coating: a clean brushed-SILVER metallic tone that reads '
                .'clearly as bare polished metal with a soft sheen catching the light — noticeably bright and silvery '
                .'(brighter than a dull grey), yet still a realistic brushed metal, not a harsh chrome mirror. Crisply '
                .'etched and slightly recessed into the surface — never a chalky white, flat print, outline or full colour.',
            'embroidery' => "Embroider the logo onto the product, {$placement}. Keep the logo's shapes, letterforms "
                .'and colours recognisable — do NOT redraw or restyle it — but render it as an unmistakable REAL '
                .'EMBROIDERED patch: tightly-packed raised satin/fill stitches with visible thread lines, directional '
                .'stitch grain and thread sheen, slightly fuzzy edges and a subtle 3D raised relief — never a flat print.',
            'white' => "Print the logo onto the product, {$placement}, rendered in solid WHITE (a clean single-colour "
                ."white version) while keeping the logo's exact shapes, letterforms and proportions — do NOT redraw or "
                .'re-letter it.',
            default => "Print the logo onto the product {$placement}, at a realistic size, in FULL COLOUR. Reproduce "
                .'the logo EXACTLY — same shapes, letterforms, colours and proportions; do NOT redraw, restyle or recolour it.',
        };

        $colors = array_values(array_filter(array_map('trim', (array) ($ctx['colors'] ?? []))));
        $palette = $colors ? implode(', ', array_slice($colors, 0, 6)) : "the brand's own colours";

        // Products that only come in a few stock colours (pen, tumbler, t-shirt) get recoloured
        // to the ONE standard colour closest to the brand — not an arbitrary brand hex.
        $recolor = ! empty($p['stock_color'])
            ? 'Give the PRODUCT a single solid STOCK colour: choose the ONE colour from exactly these seven — red, '
                ."blue, white, black, green, orange, grey — that is CLOSEST to the brand's colours ({$palette}). Use only "
                .'one of those seven colours (never an arbitrary shade), the nearest match to the brand.'
            : "You MAY recolour the PRODUCT itself to complement the brand, choosing ONLY from these brand colours: {$palette}; "
                ."otherwise leave the product's colour as it is in image 1.";

        return 'You are given TWO images: image 1 is a photo of a blank, unbranded product; image 2 is a brand logo. '
            ."Add the brand logo (image 2) onto the product shown in image 1. {$how} "
            .'Keep the product\'s shape, position, camera angle, framing, lighting, shadows and background EXACTLY as in '
            .'image 1 — the product must look like the same photo with the branding (and its colour) changed, nothing '
            ."else. {$recolor} IMPORTANT — never place the logo on a same-colour surface: if the logo and product colour "
            .'would clash or blend, make the product white (or charcoal if the logo is white/very light) so the logo '
            .'stays clearly legible. Output only the finished product photo — no extra text, no watermark, no gibberish. '
            .self::SQUARE_FRAME;
    }

    /**
     * The composite prompt for the base flow: a product's own `place_prompt` override
     * (for special placements — die-cut sticker, brochure content, QR card) with brand
     * tokens filled in, else the generic logo-placement prompt.
     *
     * @param  array<string,mixed>  $ctx
     */
    public static function placePromptFor(array $p, array $ctx = []): string
    {
        if (empty($p['place_prompt'])) {
            return self::placePrompt($p, $ctx);
        }
        $company = trim((string) ($ctx['company'] ?? '')) ?: 'Your Company';
        $url = preg_replace('#^https?://#i', '', rtrim(trim((string) ($ctx['url'] ?? '')), '/')) ?: 'yourcompany.com';
        $description = trim((string) ($ctx['description'] ?? '')) ?: 'a professional business';
        $colors = array_values(array_filter(array_map('trim', (array) ($ctx['colors'] ?? []))));
        $palette = $colors ? implode(', ', array_slice($colors, 0, 6)) : "the brand's own colours";

        return str_replace(
            ['{company}', '{url}', '{description}', '{colors}'],
            [$company, $url, $description, $palette],
            (string) $p['place_prompt'],
        ).' '.self::SQUARE_FRAME;
    }

    /**
     * Every spec that uses the pre-generated base flow — the merch roster plus the
     * standalone QR business card. Used by the base-image generator command.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function baseSpecs(): array
    {
        $specs = array_merge(self::products(), [self::qrBusinessCard()]);

        return array_values(array_filter($specs, fn ($p) => self::hasBase($p)));
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
            // Company name + website come from the designer fields / uploaded artwork
            // (BrandKit) — fall back to placeholders when we don't have them.
            $company = trim((string) ($ctx['company'] ?? '')) ?: 'Your Company';
            $url = preg_replace('#^https?://#i', '', rtrim(trim((string) ($ctx['url'] ?? '')), '/')) ?: 'yourcompany.com';
            $description = trim((string) ($ctx['description'] ?? '')) ?: 'a professional business';
            $prompt = str_replace(['{company}', '{url}', '{description}'], [$company, $url, $description], $prompt);

            // What the scene composites — the logo, the QR code, or both.
            $inputs = $p['inputs'] ?? ['logo'];
            $clauses = [];
            if (in_array('logo', $inputs, true)) {
                $clauses[] = match ($p['logo_render'] ?? 'full') {
                    'white' => 'Keep the logo\'s exact shapes, letterforms and proportions — do NOT redraw, '
                        .'re-letter or distort it — but render it in solid WHITE (a clean single-colour white '
                        .'version of the logo).',
                    'laser' => 'Keep the logo\'s exact shapes, letterforms and proportions — do NOT redraw, '
                        .'re-letter or distort it — but render it as a REALISTIC LASER ENGRAVING on metal: the mark '
                        .'reveals the bare metal under the coating — a natural silvery brushed-metal / frosted-steel '
                        .'tone, subtly recessed and etched into the surface (NOT a flat white or black print). A '
                        .'single metallic tone, never full colour.',
                    default => $logoFidelity,
                };
            }
            if (in_array('qr', $inputs, true)) {
                $clauses[] = 'The provided QR code is a FIXED IMAGE ASSET — treat it exactly like a logo: place '
                    .'it AS-IS, pixel-for-pixel identical, as if pasting the supplied file. Do NOT redraw, '
                    .'regenerate, re-pattern, recolour, sharpen, "improve", crop, rotate or distort it; every '
                    .'module/dot must stay exactly as given so it remains scannable. Keep it perfectly square.';
            }
            if ($ctx['has_site'] ?? false) {
                $clauses[] = 'A full-page SCREENSHOT of the brand\'s website is also provided as a reference — match '
                    .'its colour palette, typography feel and its real imagery/photography and graphic motifs so the '
                    .'piece looks unmistakably on-brand. Use it ONLY as a style/imagery reference — do NOT paste the '
                    .'raw screenshot or show any browser chrome, and use the supplied logo image for the logo '
                    .'(ignore any logo inside the screenshot).';
            }

            return $prompt.' '.implode(' ', $clauses).' No watermark and no random gibberish text. '.self::SQUARE_FRAME;
        }

        $placement = $p['placement'] ?? 'centred';
        $decoration = match ($p['decoration']) {
            'laser' => "Laser-engrave the provided logo onto the surface, {$placement}. Keep the logo's exact "
                .'shapes, letterforms and proportions — do NOT redraw or restyle it — rendered as a REALISTIC metal '
                .'laser engraving: the mark reveals the bare metal under the coating, a natural silvery brushed-metal '
                .'/ frosted-steel tone recessed into the surface (never a flat white/black print or full colour); on '
                .'a silver/steel product use a slightly frosted matte etch so it stays legible.',
            'embroidery' => "Embroider the provided logo onto it, {$placement}. Keep the logo's shapes, "
                .'letterforms and colours recognisable — do NOT redraw or restyle it — but render it as an '
                .'unmistakable REAL EMBROIDERED PATCH: the whole logo built from tightly-packed raised satin/fill '
                .'embroidery stitches with clearly visible individual thread lines, directional stitch grain and '
                .'thread sheen, slightly fuzzy thread edges, and a subtle 3D raised relief casting a soft shadow '
                .'on the fabric. It must read as stitched thread on fabric, absolutely NOT a smooth flat print, '
                .'transfer or decal (embroidery may slightly thicken the very finest detail into thread).',
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
            .' Show only the product — no extra text, no watermark. '.self::SQUARE_FRAME;
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
            'Make it LOUD and genuinely VIBRANT — the most colourful, high-energy ad of the set: a bold, richly SATURATED palette, punchy graphic COLOUR-BLOCKING, strong dynamic diagonals and big expressive shapes. Eye-catching and lively; keep the brand colour central and add vivid complementary colour (tasteful and on-brand, never muddy).',
            'Make it POLISHED and PROFESSIONAL — a refined corporate look, calm restrained palette, a clean structured grid; ordered, trustworthy, enterprise-grade.',
            'Make it IMAGE-LED and minimal — like a premium Airbnb or Apple photo ad: ONE gorgeous editorial photograph fills the ENTIRE banner edge-to-edge as the hero (full-bleed, no colour panels or borders), with the headline set simply and cleanly OVER the image in crisp white or one legible tone, placed on a calmer area of the photo. Strip everything else right back and let the photograph carry the ad — restrained type, no colour-blocking or busy graphics, only a small clean CTA.',
            'Make it MODERN — a sleek, contemporary app-promo look: a saturated brand-colour gradient, a lively duotone subject and floating square-cornered brand cards.',
        ][(($i % 4) + 4) % 4];
    }

    /**
     * Website-styled display ad: Gemini gets the brand LOGO (image 1) and a full-page
     * SCREENSHOT of the brand's website (image 2), and is told to design the ad in the
     * site's own style + imagery, grounded in the crawled summary. Used for the 2nd ad.
     */
    public static function adPromptFromSite(string $headline, string $cta, string $company, string $description = ''): string
    {
        $headline = str_replace('{company}', $company ?: 'us', $headline);
        $about = trim($description) !== '' ? "{$company} — {$description}" : ($company ?: 'this business');

        return "Design a premium Google Display ad banner (landscape ~1.9:1) for {$about}. You are given TWO "
            .'reference images: image 1 is the brand LOGO, image 2 is a full-page SCREENSHOT of the brand\'s own '
            .'website. Make the ad look like it clearly BELONGS to this brand by adopting the website\'s visual '
            .'identity from the screenshot — its colour palette, typography feel, mood, and its real '
            .'imagery/photography, textures and graphic motifs. Reuse the site\'s own look and picture style so '
            .'the banner is visually consistent with the website (take STYLE and imagery cues — do NOT paste the '
            .'raw screenshot, and never show browser chrome, scrollbars, cookie banners or website UI). '
            ."HIERARCHY: the headline \"{$headline}\" set large, bold and clearly dominant in a typeface matching "
            ."the site's feel — written EXACTLY ONCE, never repeating a word or line; then a clean call-to-action "
            ."button with SQUARE corners labelled exactly \"{$cta}\"; then the logo. "
            .'LOGO: place the supplied logo (image 1) small and clean, reproduced EXACTLY — same shapes, '
            .'letterforms, colours and proportions, never redrawn, re-lettered or recoloured (only a flat white '
            .'knockout of the same artwork is allowed if needed for legibility on a dark area). Use ONLY image 1 '
            .'as the logo — ignore any logo that appears inside the website screenshot. '
            .'STRICT: every word spelled correctly and exactly once, no gibberish or placeholder lettering, no '
            .'watermark, exactly ONE logo, no device mockups or app/browser UI. All text and the logo sit fully '
            .'inside a safe margin and stay perfectly legible. Crisp, high-resolution, on-brand ad quality.';
    }

    public static function adPrompt(string $headline, string $cta, string $company, ?string $palette, string $description = '', string $style = '', int $styleIndex = -1): string
    {
        $headline = str_replace('{company}', $company ?: 'us', $headline);

        // Image-led (Airbnb-style full-bleed photo) — a distinct layout with no CTA button.
        if ($styleIndex >= 0 && ((($styleIndex % 4) + 4) % 4) === 2) {
            $about = trim($description) !== '' ? "{$company} — {$description}" : ($company ?: 'this business');

            return "Design a premium editorial IMAGE-LED display ad for {$about}, art-directed to look EXACTLY "
                .'like a high-end Airbnb photo ad. ONE beautiful real photograph fills the ENTIRE banner edge-to-'
                .'edge — full-bleed, with NO borders, frame, panels or colour blocks: a cinematic, natural-light '
                ."editorial scene from {$company}'s actual world (its real place, objects or product), NOT generic "
                .'stock and NOT an obvious cliche; no people unless one genuinely belongs, and then only natural '
                .'and undistorted. '
                ."HEADLINE: set the text \"{$headline}\" LARGE across the MIDDLE of the image, left-aligned from a "
                .'comfortable left margin, on ONE line if it fits, in crisp WHITE, using a clean bold GEOMETRIC '
                .'sans-serif with friendly rounded-geometric letterforms (Airbnb Cereal / Circular / Poppins / '
                .'Montserrat style) — confident and generous but calm. Sit it over a calmer region of the photo so '
                .'it stays perfectly legible (only the faintest natural darkening of the photo if truly needed; no '
                .'solid text box). '
                .'BOTTOM: a single thin WHITE hairline rule runs horizontally across the lower part of the image. '
                ."At its LEFT end, a small white line icon that symbolises {$company}'s own business — a simple "
                .'emblem drawn from its product, tool or industry (NOT a generic map-pin) — with two short lines '
                .'of small white text '
                ."beside it — line one the company name \"{$company}\", line two its website or a two-to-three word "
                .'tagline. At the RIGHT end of that same rule, place the supplied brand logo, small and clean, '
                .'sitting directly on the photo with NO box or panel behind it. The logo SHAPE must stay '
                .'pixel-for-pixel IDENTICAL to the supplied original — every contour, icon, symbol, letterform, '
                .'proportion and spacing exactly the same; NEVER redraw, re-letter, simplify, re-space, restyle, '
                .'distort or add/remove any element. The ONLY thing you may change is its fill colour to a flat '
                .'WHITE knockout of that SAME artwork (an exact white silhouette) so it reads on the photo — the '
                .'shape stays exactly as the original. '
                .'NO call-to-action button and no other graphics, badges or shapes. '
                .'STRICT: the ONLY text is the headline, those two small label lines and the logo — every word '
                .'spelled EXACTLY and correctly, no gibberish or placeholder lettering, no extra words, no '
                .'watermark, exactly ONE logo and no other logos, no device mockups, no app or browser UI, no '
                .'scattered decorative accents. Crisp, high-resolution, editorial photo-ad quality.';
        }

        // Modern (Spotify-style gradient + duotone + floating brand cards) — dedicated layout.
        if ($styleIndex >= 0 && ((($styleIndex % 4) + 4) % 4) === 3) {
            $about = trim($description) !== '' ? "{$company} — {$description}" : ($company ?: 'this business');

            return "Design a bold, MODERN app-style display ad for {$about}, art-directed to look EXACTLY like a "
                .'premium Spotify promo. A rich, SATURATED colour gradient built from the brand colour fills the '
                .'ENTIRE banner (a smooth deep-to-bright two-tone blend), full-bleed with no borders or panels. '
                .'Place the supplied logo at the TOP-LEFT, small and clean, integrated on the gradient with NO box '
                .'behind it. The logo SHAPE must stay pixel-for-pixel IDENTICAL to the supplied original — every '
                .'contour, icon, symbol, letterform, proportion and spacing exactly the same; NEVER redraw, '
                .'re-letter, simplify, re-space, restyle, distort or add/remove any element. The ONLY thing you '
                .'may change is its fill colour to a flat WHITE knockout of that SAME artwork (an exact white '
                .'silhouette) so it reads on the gradient — the shape stays exactly as the original. '
                ."HEADLINE: set the text \"{$headline}\" VERY LARGE in the upper-left, left-aligned, in crisp WHITE, "
                .'using a clean bold GEOMETRIC sans-serif (Spotify Circular / Poppins / Montserrat style), '
                .'confident and modern; it may overlap the subject. '
                ."SUBJECT: a lively, energetic DUOTONE image — a person or a striking motif from {$company}'s actual "
                .'world — tinted to match the gradient and cut out / blended INTO the background with no rectangle, '
                .'placed centre-to-upper, full of movement. '
                .'DYNAMIC SHAPES: across the LOWER third, THREE overlapping boxes positioned and sized EXACTLY '
                .'like the reference — one LARGER central box raised higher, flanked by two SMALLER boxes set '
                .'slightly lower and tucked partly behind it (the outer pair partly overlapped, the far edges '
                .'cropped), all roughly SQUARE (album-cover proportion). Every box is a HARD-EDGED rectangle with '
                .'crisp 90-degree RIGHT-ANGLE corners — absolutely NO rounded corners, NO pill shapes, NOT '
                .'rounded app-card tiles. Give the boxes a NEUTRAL, frosted-glass effect: soft translucent '
                .'white / light-grey panels (desaturated and calm) floating over the gradient with a gentle drop '
                ."shadow. INSIDE each box, one simple clean SHAPE or icon drawn from {$company}'s world (its "
                .'product, tool or motif), also kept in a neutral monochrome tone. At most a single real '
                .'one-word label per box, never gibberish. '
                ."CTA: one clean call-to-action button centred near the BOTTOM, high-contrast, with SHARP SQUARE "
                ."corners, labelled exactly \"{$cta}\". "
                .'STRICT: the only text is the headline, the CTA label, the logo and at most a one-word label per '
                .'card — every word spelled EXACTLY and correctly, no gibberish or placeholder lettering, no '
                .'watermark, exactly ONE brand logo and no other logos, no browser or app UI chrome, no scattered '
                .'decorative accents. Crisp, high-resolution, modern ad quality.';
        }
        $colours = "the colours OF THE SUPPLIED LOGO as the DOMINANT palette — match the logo's own actual "
            .'colours (that is the brand colour)'
            .($palette ? ", and use {$palette} ONLY as light secondary accents; an accent colour must never dominate over the logo's colour" : '');
        $about = trim($description) !== '' ? "{$company} — {$description}" : ($company ?: 'this business');
        $style = $style ?: 'Polished, professional B2B brand ad — corporate, credible, enterprise-grade.';

        return "Design a striking, studio-grade Google Display ad banner (landscape ~1.9:1) for {$about} — the "
            ."work of a designer with real taste, NOT a template assembled from defaults. {$style} "
            // hierarchy
            ."HIERARCHY: one thing dominates — the headline \"{$headline}\" set very large and "
            .'bold so it clearly dominates, in a characterful modern sans that carries the personality; '
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
            .'cliché image of this industry. Avoid generic stock-photo people and crowds; include a person only '
            .'if it genuinely fits the brand, and then as a bold duotone/editorial figure with a natural, '
            .'undistorted face and hands. '
            // logo fidelity — the user's TOP priority; keep it emphatic
            .'CRITICAL — DO NOT CHANGE THE LOGO: treat the supplied logo as a fixed image asset and place it '
            .'AS-IS (as if pasting the original file), never recreated. It must appear pixel-for-pixel '
            .'identical in shapes, letterforms, colours, proportions and spacing. Keep every FILLED/solid '
            .'shape filled — do NOT turn filled areas into outlines or add strokes. Keep its EXACT original '
            .'colours — do NOT brighten, saturate or shift any hue (dark navy stays dark navy, not a brighter '
            .'blue), and never recolour it to white or black for contrast. Do NOT redraw, restyle, re-letter, '
            .'crop, rotate, distort or add effects, and do NOT re-type or re-draw the wordmark — every letter '
            .'stays spelled exactly as in the supplied logo. INTEGRATE the logo naturally INTO the composition, '
            .'never inside an added white or light box, chip, card, badge, pill or panel and with NO rectangle '
            .'or backing shape behind it. Solve legibility by PLACEMENT, NEVER by recolouring: sit the logo on '
            .'a plain area of the design whose tone simply contrasts with it (a light or negative-space zone for '
            .'a dark logo, a darker zone for a light logo) and deliberately compose the layout so such an area '
            .'exists — it should feel woven into the design, not pasted on top, yet keep its EXACT original '
            .'colours and letterforms even when integrated. '
            // palette + typography-as-personality
            ."COLOUR: build from {$colours}, led by the brand colour. Match the colour energy to the chosen "
            .'style — a VIBRANT style should be boldly, richly SATURATED and high-contrast with punchy colour-'
            .'blocking; a minimalist or professional style stays restrained and calm. Keep it cohesive and '
            .'harmonious; avoid muddy, dull or dirty combinations. '
            // CTA (display-ad button, sharp corners)
            ."CTA: one solid rectangular call-to-action block with SHARP SQUARE corners (never rounded or "
            ."pill-shaped), high-contrast, labelled exactly \"{$cta}\". "
            // strict text rules
            .'STRICT: the ONLY text in the image is that headline and that CTA label — no other words, no '
            .'gibberish, misspellings or placeholder lettering; spell the headline and CTA label EXACTLY and '
            .'correctly, letter for letter. Do NOT render any of these design directions, size notes or '
            .'guidance words as text in the image (only the real headline and CTA copy are drawn). No watermark. '
            .'Show the logo EXACTLY ONCE (never duplicated or repeated); no other logos, no phone/laptop/'
            .'device mockups, no browser or app UI, and NO scattered decorative '
            .'accents (dots, plus-signs, confetti). '
            .'Crisp, high-resolution, art-directed ad-creative quality.';
    }
}
