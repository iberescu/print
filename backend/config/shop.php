<?php

return [
    /*
    | Free shipping kicks in at/above this order subtotal (USD).
    */
    'free_shipping_threshold' => (float) env('FREE_SHIPPING_THRESHOLD', 100),

    // Fixed shipping methods (Vistaprint-style). Shipping is charged PER PRODUCT
    // (method price × number of products in the cart). The base method
    // ('standard') is free once the order clears the free-shipping threshold
    // above; the others are paid upgrades. 'days' = business days from today,
    // used to show an estimated delivery date at checkout.
    'shipping_base_method' => 'standard',
    'shipping_methods' => [
        ['code' => 'economy',  'label' => 'Economy',  'days' => 8, 'price' => 7.99],
        ['code' => 'standard', 'label' => 'Standard', 'days' => 6, 'price' => 12.99],
        ['code' => 'express',  'label' => 'Express',  'days' => 3, 'price' => 24.99],
        // code kept as 'nextday' so live carts/orders that stored it still resolve
        ['code' => 'nextday',  'label' => 'Rush',     'days' => 2, 'price' => 44.99],
    ],

    /*
    | Estimated US sales tax — per-state base rates (percent), applied to the
    | taxable amount (subtotal − discount) by the buyer's SHIPPING state. This is
    | an ESTIMATE: state base rate only, with no county/city/ZIP precision and no
    | nexus logic. States with no statewide sales tax are 0. Shown as "estimated".
    */
    'tax_rates' => [
        'AL' => 4.0,   'AK' => 0.0,  'AZ' => 5.6,  'AR' => 6.5,   'CA' => 7.25, 'CO' => 2.9,
        'CT' => 6.35,  'DE' => 0.0,  'DC' => 6.0,  'FL' => 6.0,   'GA' => 4.0,  'HI' => 4.0,
        'ID' => 6.0,   'IL' => 6.25, 'IN' => 7.0,  'IA' => 6.0,   'KS' => 6.5,  'KY' => 6.0,
        'LA' => 5.0,   'ME' => 5.5,  'MD' => 6.0,  'MA' => 6.25,  'MI' => 6.0,  'MN' => 6.875,
        'MS' => 7.0,   'MO' => 4.225,'MT' => 0.0,  'NE' => 5.5,   'NV' => 6.85, 'NH' => 0.0,
        'NJ' => 6.625, 'NM' => 4.875,'NY' => 4.0,  'NC' => 4.75,  'ND' => 5.0,  'OH' => 5.75,
        'OK' => 4.5,   'OR' => 0.0,  'PA' => 6.0,  'RI' => 7.0,   'SC' => 6.0,  'SD' => 4.2,
        'TN' => 7.0,   'TX' => 6.25, 'UT' => 6.1,  'VT' => 6.0,   'VA' => 5.3,  'WA' => 6.5,
        'WV' => 6.0,   'WI' => 5.0,  'WY' => 4.0,
    ],

    /*
    | One-time credit added to a partner's balance when their affiliate
    | application is approved (USD cents). Counts toward what they're owed.
    */
    'affiliate_signup_bonus_cents' => (int) env('AFFILIATE_SIGNUP_BONUS_CENTS', 25000),

    /*
    | Legal entity behind the storefront (shown on legal pages + the footer).
    */
    'company' => [
        'name'    => 'OptiPrime, LLC',
        'brand'   => 'RunMyPrint',
        'state'   => 'Delaware',
        'address' => '390 NE 191st St #13882, Miami, FL 33179',
        'email'   => 'support@runmyprint.com',
    ],

    /*
    | Google Gemini models (verified live against the API).
    */
    'gemini' => [
        'api_key'          => env('GEMINI_API_KEY'),
        'image_model'      => env('GEMINI_IMAGE_MODEL', 'gemini-3-pro-image'),
        'image_model_fast' => env('GEMINI_IMAGE_MODEL_FAST', 'gemini-3.1-flash-image'),
        'image_model_lite' => env('GEMINI_IMAGE_MODEL_LITE', 'gemini-3.1-flash-lite-image'),
        'text_model'       => env('GEMINI_TEXT_MODEL', 'gemini-3.5-flash'),
        'vision_model'     => env('GEMINI_VISION_MODEL', 'gemini-3.5-flash'),
        // support chat answers — high volume, simple task, cheaper flash tier
        'support_model'    => env('GEMINI_SUPPORT_MODEL', 'gemini-2.5-flash'),
        'base_url'         => 'https://generativelanguage.googleapis.com/v1beta',
        // Max concurrent Gemini API calls across all workers (Redis semaphore) —
        // stops the internal engine's parallel generations from rate-limiting each
        // other. 0 = unlimited. Never binds for normal 1-at-a-time traffic.
        'max_concurrency'  => (int) env('GEMINI_MAX_CONCURRENCY', 8),
    ],

    // Replicate — recraft SVG model behind the AI logo maker
    'replicate' => [
        'api_token' => env('REPLICATE_API_TOKEN'),
        'svg_model' => env('REPLICATE_SVG_MODEL', 'recraft-ai/recraft-v4-svg'),
        'base_url'  => 'https://api.replicate.com/v1',
    ],

    'stripe' => [
        'key'            => env('STRIPE_KEY'),
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    | pqSmartGenerator — third-party upsell engine. We POST a capture (logo/pdf/
    | website) server-side AFTER the response is sent, then the Review page's
    | widget polls their API with the returned capture UUID and shows a gallery
    | of generated product mockups. The default client UUID is their manual-test
    | one — set PQSG_CLIENT_UUID to the real per-source UUID for production.
    */
    /*
    | Upsell brand-kit engine: 'pqsg' (third-party pqSmartGenerator, default) or
    | 'internal' (our own Gemini-powered pipeline — crawl + brand summary +
    | logo-on-products + display ads). Flip with UPSELL_ENGINE.
    */
    'upsell_engine' => env('UPSELL_ENGINE', 'pqsg'),

    // Internal engine image models. Two tiers: the LITE model handles the simple
    // logo-on-a-fixed-base mockups (fast, cheap — most of the roster), while the fuller
    // flash model handles the complex artwork that also ingests the website screenshot
    // (display ads, brochure, flyer). The pro tier's 10-20s render was too slow for bulk.
    'internal_engine' => [
        // Complex artwork: ads + website-styled pieces (brochure/flyer) — logo + screenshot.
        'image_model'    => env('INTERNAL_ENGINE_IMAGE_MODEL', env('GEMINI_IMAGE_MODEL_FAST', 'gemini-3.1-flash-image')),
        'ad_image_model' => env('INTERNAL_ENGINE_AD_IMAGE_MODEL', env('GEMINI_IMAGE_MODEL_FAST', 'gemini-3.1-flash-image')),
        // Simple product mockups: logo (or QR) composited onto a fixed product base — the lite tier.
        'lite_image_model' => env('INTERNAL_ENGINE_LITE_IMAGE_MODEL', env('GEMINI_IMAGE_MODEL_LITE', 'gemini-3.1-flash-lite-image')),
        // Logo handling: always keep the original, downscale an over-large working
        // copy to a sane max side, and only upscale genuinely tiny logos — via
        // Replicate real-esrgan (a true super-resolution model, faithful, unlike a
        // generative redraw). Every downstream mockup/ad inherits the working logo.
        'logo_resize_px'        => (int) env('INTERNAL_ENGINE_LOGO_RESIZE_PX', 800),        // downscale big logos to this max side
        'logo_upscale_below_px' => (int) env('INTERNAL_ENGINE_LOGO_UPSCALE_BELOW_PX', 125), // only upscale when the max side is under this
        'logo_upscale_to_px'    => (int) env('INTERNAL_ENGINE_LOGO_UPSCALE_TO_PX', 512),    // cap the upscaled result to this max side
        'esrgan_model'          => env('INTERNAL_ENGINE_ESRGAN_MODEL', 'nightmareai/real-esrgan'),
        'esrgan_scale'          => (int) env('INTERNAL_ENGINE_ESRGAN_SCALE', 4),
        // Cap how many product mockups / display ads to generate per capture
        // (0 = all). Ads default to 4 — enough variety, keeps the pro-tier cost sane.
        'max_products' => (int) env('INTERNAL_ENGINE_MAX_PRODUCTS', 0),
        'max_ads'      => (int) env('INTERNAL_ENGINE_MAX_ADS', 4),
    ],

    // Cloudflare Browser Rendering — renders JS/SPA + bot-walled sites to clean
    // markdown for the internal engine's brand crawl (falls back to a plain fetch).
    'cloudflare' => [
        'account_id'    => env('CLOUDFLARE_ACCOUNT_ID'),
        'browser_token' => env('CLOUDFLARE_BROWSER_TOKEN'),
    ],


    // Support email channel: replies go out from this address; the inbound
    // webhook (/hooks/inbound-email?token=...) requires the shared token.
    'support_email'         => env('SUPPORT_EMAIL', 'contact@runmyprint.com'),
    'support_inbound_token' => env('SUPPORT_INBOUND_TOKEN'),
    // Every inbound email is also forwarded here (ops copy; empty = off).
    'support_forward_to'    => env('SUPPORT_FORWARD_TO'),

    'pqsg' => [
        'enabled'     => (bool) env('PQSG_ENABLED', true),
        'api_base'    => rtrim(env('PQSG_API_BASE', 'https://printbrothers-kickoff-clone.cloudlab-internal.com/api/pqsmartgenerator'), '/'),
        'widget_src'  => env('PQSG_WIDGET_SRC', 'https://printbrothers-kickoff-clone.cloudlab-internal.com/modules/pqsmartgenerator/widget/pqsmartgenerator-widget.js'),
        'client_uuid' => env('PQSG_CLIENT_UUID', 'b7c44ff2-1eaa-4ef4-9d52-0cfd44c7a111'),
        // minutes a just-dispatched strong capture is trusted before its cache
        // entry must exist (covers the dispatch→registered gap at Review time)
        'strong_grace' => (int) env('PQSG_STRONG_GRACE', 10),
    ],
];
