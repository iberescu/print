<?php

return [
    /*
    | Free shipping kicks in at/above this order subtotal (USD).
    */
    'free_shipping_threshold' => (float) env('FREE_SHIPPING_THRESHOLD', 100),

    // Fixed shipping methods (Vistaprint-style). The base method ('economy') is
    // free once the order clears the free-shipping threshold above; faster
    // methods are paid upgrades and always cost their fixed price.
    'shipping_base_method' => 'economy',
    'shipping_methods' => [
        ['code' => 'economy',  'label' => 'Economy',  'eta' => '7–10 business days',     'price' => 7.99],
        ['code' => 'standard', 'label' => 'Standard', 'eta' => '5–7 business days',       'price' => 12.99],
        ['code' => 'express',  'label' => 'Express',  'eta' => '2 business days (48 hr)', 'price' => 24.99],
        ['code' => 'nextday',  'label' => 'Next Day', 'eta' => 'Next business day',       'price' => 44.99],
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
        'text_model'       => env('GEMINI_TEXT_MODEL', 'gemini-3.5-flash'),
        'vision_model'     => env('GEMINI_VISION_MODEL', 'gemini-3.5-flash'),
        // support chat answers — high volume, simple task, cheaper flash tier
        'support_model'    => env('GEMINI_SUPPORT_MODEL', 'gemini-2.5-flash'),
        'base_url'         => 'https://generativelanguage.googleapis.com/v1beta',
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
