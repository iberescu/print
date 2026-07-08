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

    /** Engine mockup → OUR shop product, so every card gets a real "Add to order". */
    private const MERCH_TO_PRODUCT = [
        'business_card_qr_logo' => 'qr-code-business-cards',
        'roll_stickers'         => 'roll-labels',
        'canvas'                => 'canvas-prints',
        'tshirt_words'          => 'gildan-softstyle-unisex-t-shirt',
        'bags'                  => 'custom-canvas-tote-bags',
        'glass_logo'            => 'acrylic-table-signs',
        'sticker'               => 'custom-laptop-stickers',
        'google_v2'             => 'mounted-tabletop-signs',
        'hoodie'                => 'jerzees-nublend-hooded-sweatshirt',
        // bottle/pen/umbrella/usb/chocolate/apron/office: no catalog match — card stays a showcase
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

    /** Gallery feeds, streamed as the engine finishes them: ?set=merch (default,
     *  the "your logo on more products" mockups) or ?set=ads (the Layout.ai
     *  facebook-ad canvases — type template_preview, which the third-party
     *  widget never displayed; we render them ourselves). */
    public function feed(string $key, Request $request): JsonResponse
    {
        abort_unless(Str::isUuid($key), 404);

        $uuid = Cache::get("pqsg:{$key}");
        if (! $uuid) {
            return response()->json(['done' => false, 'images' => []]);
        }

        // proxy the engine's widget feed. The cache only smooths poll bursts —
        // keep it SHORT (1 s, matching the page's poll cadence) so fresh
        // mockups reach the shopper the second they exist.
        $state = Cache::remember("pqsg:engine:{$uuid}", 1, function () use ($uuid) {
            try {
                return Http::timeout(8)->acceptJson()
                    ->get(config('shop.pqsg.api_base')."/capture/{$uuid}/widget")->json() ?? [];
            } catch (\Throwable) {
                return [];
            }
        });

        $done = in_array($state['computed_status'] ?? '', ['completed', 'partially_completed'], true)
            || (bool) ($state['is_complete'] ?? false);

        if ($request->query('set') === 'ads') {
            $images = collect($state['images'] ?? [])
                ->filter(fn ($i) => ($i['type'] ?? '') === 'template_preview'
                    && ($i['product_key'] ?? $i['special_product_key'] ?? '') === 'pipeline_facebook_ad'
                    && ($i['url'] ?? '') !== '')
                ->take(8)
                ->values()
                ->map(fn ($i, $n) => [
                    'key'     => 'ad-'.$n,
                    'img'     => (string) $i['url'],
                    'label'   => 'Ad concept '.($n + 1),
                    'product' => null,
                ])->all();

            return response()->json(['done' => $done, 'images' => $images]);
        }

        // the mapped shop products, one indexed query (name + entry price for the card CTA)
        $shop = \App\Models\Product::query()
            ->whereIn('slug', array_values(self::MERCH_TO_PRODUCT))
            ->where('is_active', true)
            ->get(['slug', 'name', 'from_price'])
            ->keyBy('slug');

        $wanted = array_flip(self::MERCH_PRODUCTS);
        $images = collect($state['images'] ?? [])
            ->filter(fn ($i) => ($i['type'] ?? '') === 'product_mockup'
                && (isset($wanted[$i['product_key'] ?? '']) || isset($wanted[$i['special_product_key'] ?? ''])))
            ->unique(fn ($i) => $i['product_key'] ?? $i['special_product_key'])
            ->map(function ($i) use ($shop) {
                $key = (string) ($i['product_key'] ?? $i['special_product_key']);
                $engine = (string) ($i['product_label'] ?? '');
                $mapped = $shop->get(self::MERCH_TO_PRODUCT[$key] ?? '');

                return [
                    'key'   => $key,
                    'img'   => (string) ($i['url'] ?? ''),
                    // curated title, else the engine label unless it reads internal
                    'label' => self::MERCH_LABELS[$key]
                        ?? (preg_match('/cloudlab|pipeline|v\d/i', $engine) ? 'Your brand, mocked up' : $engine),
                    // the REAL product behind the mockup → "+ Add to order"
                    'product' => $mapped ? [
                        'slug'      => $mapped->slug,
                        'name'      => $mapped->name,
                        'fromPrice' => (float) $mapped->from_price,
                    ] : null,
                ];
            })
            ->filter(fn ($i) => $i['img'] !== '')
            ->values()->all();

        return response()->json(['done' => $done, 'images' => $images]);
    }
}
