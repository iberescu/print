<?php

namespace App\Http\Controllers;

use App\Jobs\SendPqsgCapture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * pqSmartGenerator integration endpoints.
 *
 *  - POST /pqsg/upload: fire-and-forget artwork upload from the editor's upload
 *    mode — stores the file and registers a capture (pdf_url / logo_url) with
 *    the upsell engine after the response is sent. Never blocks the editor.
 *  - GET /pqsg/status/{key}: the Review page polls this until the third-party
 *    capture UUID is known, then hands it to the gallery widget.
 *  - GET /pqsg/feed/{key}: the "your logo on more products" upsell step polls
 *    this — the engine's widget feed proxied + filtered to the merch set, so
 *    the shop renders its own cards instead of the sealed third-party widget.
 */
class PqsgController extends Controller
{
    /** Engine product keys shown on the merch gallery step (mirrors the widget-era allow-list). */
    private const MERCH_PRODUCTS = [
        'business_card_qr_logo', 'roll_stickers', 'canvas', 'bottle', 'tshirt_words',
        'bags', 'cloudlab_sortv2', 'glass_logo', 'sticker', 'cloudlab_pix',
        'cloudlab_umbrela', 'cloudlab_usb', 'chocolate_bar', 'google_v2', 'office', 'hoodie',
    ];

    /** Customer-facing card titles — the engine's own labels leak internal names ("Cloudlab Sort V2"). */
    private const MERCH_LABELS = [
        'business_card_qr_logo' => 'QR business card',
        'roll_stickers'         => 'Roll stickers',
        'canvas'                => 'Canvas print',
        'bottle'                => 'Water bottle',
        'tshirt_words'          => 'T-shirt',
        'bags'                  => 'Tote bag',
        'cloudlab_sortv2'       => 'Apron',
        'glass_logo'            => 'Glass sign',
        'sticker'               => 'Laptop sticker',
        'cloudlab_pix'          => 'Branded pen',
        'google_v2'             => 'Google review stand',
        'cloudlab_umbrela'      => 'Umbrella',
        'cloudlab_usb'          => 'USB drive',
        'chocolate_bar'         => 'Chocolate bar',
        'office'                => 'Office branding',
        'hoodie'                => 'Hoodie',
    ];
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:20480'], // 20 MB
        ]);

        $file = $request->file('file');
        $isPdf = strtolower($file->getClientOriginalExtension()) === 'pdf';
        $path = $file->store('uploads/artwork/'.now()->format('Ym'), 'public');
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $url = url($disk->url($path));

        // PDFs render to page images (MuPDF) so the editor can show a real
        // preview and let the customer position each page on the canvas.
        $pages = $isPdf ? \App\Support\PdfToImage::pages($disk->path($path)) : [];

        // One key PER capture — it doubles as the engine's idempotency key, so
        // reuse replays the previous capture (stale logo in the funnel). The
        // session carries the latest for Review/the funnel; 'strong' marks it
        // as real artwork so Review's weak image fallback won't clobber it.
        $key = (string) Str::uuid();
        session(['pqsg.key' => $key, 'pqsg.strong' => $key, 'pqsg.strong_at' => now()->toIso8601String()]);

        SendPqsgCapture::dispatchAfterResponse(
            key: $key,
            source: 'runmyprint-upload',
            logoUrl: $isPdf ? null : $url,
            pdfUrl: $isPdf ? $url : null,
        );

        return response()->json(['key' => $key, 'pages' => $pages]);
    }

    public function status(string $key): JsonResponse
    {
        abort_unless(Str::isUuid($key), 404);

        return response()->json(['uuid' => Cache::get("pqsg:{$key}")]);
    }

    /** Merch mockups for the gallery step, streamed as the engine finishes them. */
    public function feed(string $key): JsonResponse
    {
        abort_unless(Str::isUuid($key), 404);

        $uuid = Cache::get("pqsg:{$key}");
        if (! $uuid) {
            return response()->json(['done' => false, 'images' => []]);
        }

        // proxy the engine's widget feed (cached briefly — the step polls every ~2.5 s)
        $state = Cache::remember("pqsg:engine:{$uuid}", 8, function () use ($uuid) {
            try {
                return Http::timeout(8)->acceptJson()
                    ->get(config('shop.pqsg.api_base')."/capture/{$uuid}/widget")->json() ?? [];
            } catch (\Throwable) {
                return [];
            }
        });

        $wanted = array_flip(self::MERCH_PRODUCTS);
        $images = collect($state['images'] ?? [])
            ->filter(fn ($i) => ($i['type'] ?? '') === 'product_mockup'
                && (isset($wanted[$i['product_key'] ?? '']) || isset($wanted[$i['special_product_key'] ?? ''])))
            ->unique(fn ($i) => $i['product_key'] ?? $i['special_product_key'])
            ->map(function ($i) {
                $key = (string) ($i['product_key'] ?? $i['special_product_key']);
                $engine = (string) ($i['product_label'] ?? '');

                return [
                    'key'   => $key,
                    'img'   => (string) ($i['url'] ?? ''),
                    // curated title, else the engine label unless it reads internal
                    'label' => self::MERCH_LABELS[$key]
                        ?? (preg_match('/cloudlab|pipeline|v\d/i', $engine) ? 'Your brand, mocked up' : $engine),
                    'link'  => $i['product_link'] ?? $i['link'] ?? null,
                ];
            })
            ->filter(fn ($i) => $i['img'] !== '')
            ->values()->all();

        $done = in_array($state['computed_status'] ?? '', ['completed', 'partially_completed'], true)
            || (bool) ($state['is_complete'] ?? false);

        return response()->json(['done' => $done, 'images' => $images]);
    }
}
