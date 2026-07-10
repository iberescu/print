<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Register a capture with the pqSmartGenerator upsell engine (third party).
 * Dispatched AFTER the response is sent (dispatchAfterResponse) so the user
 * never waits on their API; the returned capture UUID is cached under our key
 * and the Review page's widget picks it up by polling /pqsg/status/{key}.
 */
class SendPqsgCapture
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,          // our correlation + idempotency key
        public readonly string $source,       // 'runmyprint-designer' | 'runmyprint-upload' | 'runmyprint-logo-maker'
        public readonly ?string $logoUrl = null,
        public readonly ?string $pdfUrl = null,
        public readonly ?string $website = null,
        public readonly ?string $imageUrl = null, // e.g. the review preview — engine extracts the brand from it
    ) {
    }

    public function handle(): void
    {
        $cfg = config('shop.pqsg');
        if (! ($cfg['enabled'] ?? false)) {
            return;
        }

        $payload = array_filter([
            'client_uuid'        => $cfg['client_uuid'],
            'source'             => $this->source,
            'external_reference' => $this->key,
            'idempotency_key'    => $this->key,
            'logo_url'           => $this->logoUrl,
            'pdf_url'            => $this->pdfUrl,
            'website'            => $this->website,
            'image_url'          => $this->imageUrl,
        ], fn ($v) => $v !== null && $v !== '');

        // Capture-time generation controls (one capture feeds every gallery step):
        // the 16-product merch set renders WITH SVG previews ("your logo on more
        // products" step), and the Layout.ai ads step gets the facebook_ads_nano
        // canvases (direct images, no SVG preview). Pipeline templates we never
        // display are skipped entirely.
        $payload['products'] = [
            ...collect([
                'business_card_qr_logo', 'roll_stickers', 'canvas', 'bottle', 'tshirt_words',
                'bags', 'cloudlab_sortv2', 'glass_logo', 'sticker', 'cloudlab_pix',
                'cloudlab_umbrela', 'cloudlab_usb', 'chocolate_bar', 'google_v2', 'office', 'hoodie',
            ])->mapWithKeys(fn ($k) => [$k => ['generate' => true, 'preview' => true]])->all(),
            'facebook_ads_nano'                 => ['generate' => true, 'preview' => false, 'template_count' => 8],
            'pipeline_business_card_horizontal' => ['generate' => false],
            'pipeline_poster_4x5'               => ['generate' => false],
            'pipeline_flyer_mockup'             => ['generate' => false],
        ];
        // facebook_ads_nano has preview:false (direct images, no SVG mockup) — the
        // widget's "linked/forced products only" mode hides it unless the CAPTURE
        // forces display (engine-team guidance; the widget-side display-products
        // attribute alone isn't enough).
        $payload['displayProducts'] = ['facebook_ads_nano' => true];
        // thin brand signals produced German ad copy — pin the template language
        $payload['template_language'] = 'en';

        // at least one content source is required by their API
        if (! array_intersect_key($payload, array_flip(['logo_url', 'pdf_url', 'website', 'image_url']))) {
            return;
        }

        $sources = ['logo_url', 'pdf_url', 'website', 'image_url'];

        try {
            $resp = Http::timeout(10)->acceptJson()->post($cfg['api_base'].'/capture', $payload);
            $body = $resp->json() ?? [];
            $uuid = $body['uuid'] ?? $body['capture_uuid'] ?? ($body['data']['uuid'] ?? null);

            if ($resp->successful() && $uuid) {
                // 12h: shopping sessions outlive short TTLs, and a session key
                // pointing at an evicted uuid shows dead gallery steps
                Cache::put("pqsg:{$this->key}", $uuid, now()->addHours(12));
                Log::info('pqsg capture registered', [
                    'key'    => $this->key,
                    'uuid'   => $uuid,
                    'source' => $this->source,
                    'sent'   => array_values(array_intersect($sources, array_keys($payload))),
                ]);
            } else {
                // NOTE: one failing source rejects the whole capture engine-side
                // (e.g. a bot-blocked website, HTTP 429) — fix requested from the
                // engine team; we deliberately do NOT work around it here.
                Log::error('pqsg capture rejected', [
                    'status' => $resp->status(), 'body' => $resp->body(),
                    'key' => $this->key, 'source' => $this->source,
                    'sent' => array_values(array_intersect($sources, array_keys($payload))),
                ]);
            }
        } catch (\Throwable $e) {
            // upsell is strictly best-effort — never surface a failure to the shopper
            Log::error('pqsg capture failed', ['error' => $e->getMessage(), 'key' => $this->key]);
        }
    }
}
