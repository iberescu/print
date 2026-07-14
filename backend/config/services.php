<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // Google Ads API (campaign management via ads:setup) + the site tag.
    // The label_* values are conversion-action labels printed by ads:setup.
    'google_ads' => [
        'developer_token'   => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'client_id'         => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret'     => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token'     => env('GOOGLE_ADS_REFRESH_TOKEN'),
        'customer_id'       => env('GOOGLE_ADS_CUSTOMER_ID'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
        'merchant_id'       => env('GOOGLE_ADS_MERCHANT_ID'), // Merchant Center, for Shopping campaigns
        'tag_id'            => env('GOOGLE_ADS_TAG_ID'),
        'label_purchase'    => env('GOOGLE_ADS_LABEL_PURCHASE'),
        'label_logo'        => env('GOOGLE_ADS_LABEL_LOGO'),
        'label_cart'        => env('GOOGLE_ADS_LABEL_CART'),
    ],

    // Optional IP→company intelligence for the embeddable widget's IP mode (IPinfo).
    // Without a token we fall back to reverse DNS only.
    'ipinfo' => [
        'token' => env('IPINFO_TOKEN'),
    ],

];
