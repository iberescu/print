<?php

return [
    // The embeddable widget API is called cross-origin from partner sites.
    'paths' => ['api/*', 'widget.js', 'stripe/webhook', 'affiliate/widget/*', 'pqsg/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
