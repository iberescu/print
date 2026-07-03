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
        public readonly string $source,       // 'runmyprint-designer' | 'runmyprint-upload'
        public readonly ?string $logoUrl = null,
        public readonly ?string $pdfUrl = null,
        public readonly ?string $website = null,
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
        ], fn ($v) => $v !== null && $v !== '');

        // at least one content source is required by their API
        if (! array_intersect_key($payload, array_flip(['logo_url', 'pdf_url', 'website']))) {
            return;
        }

        try {
            $resp = Http::timeout(10)->acceptJson()->post($cfg['api_base'].'/capture', $payload);
            $body = $resp->json() ?? [];
            $uuid = $body['uuid'] ?? $body['capture_uuid'] ?? ($body['data']['uuid'] ?? null);

            if ($resp->successful() && $uuid) {
                Cache::put("pqsg:{$this->key}", $uuid, now()->addHours(2));
            } else {
                Log::warning('pqsg capture rejected', ['status' => $resp->status(), 'body' => $resp->body(), 'key' => $this->key]);
            }
        } catch (\Throwable $e) {
            // upsell is strictly best-effort — never surface a failure to the shopper
            Log::warning('pqsg capture failed', ['error' => $e->getMessage(), 'key' => $this->key]);
        }
    }
}
