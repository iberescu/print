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

        // In-house engine: extract the brand from the uploaded image (or the PDF's
        // first rendered page) and build a BrandKit instead of a third-party capture.
        if (config('shop.upsell_engine') === 'internal') {
            $key = app(\App\Services\BrandKitCapture::class)->capture([
                'source'     => $isPdf ? 'pdf' : 'image',
                'sourceFile' => $isPdf ? ($pages[0] ?? null) : $url,
            ]);

            return response()->json(['key' => $key, 'pages' => $pages]);
        }

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

        // Internal engine has no third-party uuid — the key itself is the handle.
        if (config('shop.upsell_engine') === 'internal') {
            return response()->json(['uuid' => \App\Models\BrandKit::where('key', $key)->exists() ? $key : null]);
        }

        return response()->json(['uuid' => $this->resolveUuid($key)]);
    }

    /**
     * Resolve the engine capture uuid for a session key, resilient to cache loss.
     * Tries Redis, then the (DB-backed) session mirror, then a uuid carried in the
     * request URL, and re-warms both stores from whichever hit. The Redis cache can
     * be flushed (deploys, restarts, eviction); the session survives cache:clear, and
     * the URL survives even that — so the pointer to a customer's ads is not lost.
     */
    private function resolveUuid(string $key, ?string $hint = null): ?string
    {
        $uuid = Cache::get("pqsg:{$key}") ?: session("pqsg.uuid.{$key}");
        if (! $uuid && $hint && Str::isUuid($hint)) {
            $uuid = $hint;
        }
        if ($uuid) {
            Cache::put("pqsg:{$key}", $uuid, now()->addHours(12));
            session(["pqsg.uuid.{$key}" => $uuid]);
        }

        return $uuid;
    }

    /** Gallery feeds, streamed as the engine finishes them: ?set=merch (default,
     *  the "your logo on more products" mockups) or ?set=ads (the Layout.ai
     *  facebook_ads_nano canvases — direct images we render ourselves). */
    public function feed(string $key, Request $request): JsonResponse
    {
        abort_unless(Str::isUuid($key), 404);

        if (config('shop.upsell_engine') === 'internal') {
            return $this->internalFeed($key, (string) $request->query('set'));
        }

        $uuid = $this->resolveUuid($key, $request->query('uuid'));
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
                ->filter(fn ($i) => in_array('facebook_ads_nano', [$i['product_key'] ?? '', $i['special_product_key'] ?? ''], true)
                    && ($i['url'] ?? '') !== '')
                ->take(6)
                ->values()
                ->map(fn ($i, $n) => [
                    'key'     => 'ad-'.$n,
                    'img'     => (string) $i['url'],
                    'label'   => 'Ad concept '.($n + 1),
                    'product' => null,
                ])->all();

            return response()->json(['done' => $done, 'images' => $images]);
        }

        // the mapped shop products, one indexed query (name + entry price + qty tiers for the card CTA)
        $shop = \App\Models\Product::query()
            ->whereIn('slug', array_values(self::MERCH_TO_PRODUCT))
            ->where('is_active', true)
            ->with('quantities')
            ->get()
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
                        'slug'       => $mapped->slug,
                        'name'       => $mapped->name,
                        'fromPrice'  => (float) $mapped->from_price,
                        'quantities' => self::tierList($mapped),
                    ] : null,
                ];
            })
            ->filter(fn ($i) => $i['img'] !== '')
            ->values()->all();

        return response()->json(['done' => $done, 'images' => $images]);
    }

    /**
     * GET /pqsg/brand-profile/{key}: the Layout.ai "search ads" preview polls this.
     * Proxies the engine's async brand-profile endpoint (4 Google keywords + brand
     * data + logo) and returns a compact, client-friendly shape. Read-only.
     */
    public function brandProfile(string $key, Request $request): JsonResponse
    {
        abort_unless(Str::isUuid($key), 404);

        if (config('shop.upsell_engine') === 'internal') {
            $kit = \App\Models\BrandKit::where('key', $key)->first();
            $s = $kit?->summary ?? [];
            $stage = $kit?->stages['summary'] ?? 'pending';
            $ready = in_array($stage, ['done', 'skipped'], true) && ! empty($s);

            return response()->json([
                'ready'       => $ready,
                'status'      => $ready ? 'complete' : 'pending',
                'company'     => $s['company'] ?? $kit?->company,
                'description' => $s['description'] ?? null,
                'keywords'    => array_values(array_filter((array) ($s['google_search_keywords'] ?? []))),
                'logo'        => $kit?->logo_url,
            ]);
        }

        $uuid = $this->resolveUuid($key, $request->query('uuid'));
        if (! $uuid) {
            return response()->json(['ready' => false, 'status' => 'pending']);
        }

        $data = Cache::remember("pqsg:brand:{$uuid}", 4, function () use ($uuid) {
            try {
                return Http::timeout(8)->acceptJson()
                    ->get(config('shop.pqsg.api_base')."/capture/{$uuid}/brand-profile")->json() ?? [];
            } catch (\Throwable) {
                return [];
            }
        });

        $keywords = $data['google_search_keywords'] ?? ($data['brand_data']['googleSearchKeywords'] ?? []);

        return response()->json([
            'ready'       => ($data['ready'] ?? false) === true || ($data['status'] ?? '') === 'complete',
            'status'      => $data['status'] ?? 'pending',
            'company'     => $data['brand_data']['companyName'] ?? null,
            'description' => $data['brand_data']['description'] ?? null,
            'keywords'    => array_values(array_filter((array) $keywords)),
            'logo'        => $data['main_logo']['url'] ?? null,
        ]);
    }

    /** In-house engine feed — serves the BrandKit's products (?set=merch) or ads (?set=ads). */
    private function internalFeed(string $key, string $set): JsonResponse
    {
        $kit = \App\Models\BrandKit::where('key', $key)->first();
        if (! $kit) {
            return response()->json(['done' => false, 'images' => []]);
        }
        // Safety net so the gallery always settles even if a job dies.
        $stale = $kit->updated_at && $kit->updated_at->lt(now()->subMinutes(6));
        $stages = $kit->stages ?? [];

        if ($set === 'ads') {
            $images = collect($kit->ads ?? [])->values()->map(fn ($a, $i) => [
                'key' => $a['key'] ?? 'ad-'.$i, 'img' => $a['img'], 'label' => 'Ad concept '.($i + 1), 'product' => null,
            ])->all();
            $expected = count($kit->summary['ad_concepts'] ?? []) ?: count(\App\Support\BrandKitSpec::ads());
            $done = ($stages['ads'] ?? 'pending') === 'skipped'
                || count($images) >= $expected || $stale;

            return response()->json(['done' => $done, 'images' => $images]);
        }

        $slugs = collect($kit->products ?? [])->pluck('product_slug')->filter()->all();
        $shop = \App\Models\Product::whereIn('slug', $slugs)->where('is_active', true)
            ->with('quantities')->get()->keyBy('slug');

        $images = collect($kit->products ?? [])->map(function ($p) use ($shop) {
            $prod = $shop->get($p['product_slug'] ?? '');

            return [
                'key'     => $p['key'],
                'img'     => $p['img'],
                'label'   => $p['label'] ?? 'Your logo, mocked up',
                'product' => $prod ? [
                    'slug'       => $prod->slug,
                    'name'       => $prod->name,
                    'fromPrice'  => (float) $prod->from_price,
                    'quantities' => self::tierList($prod),
                ] : null,
            ];
        })->all();
        $done = ($stages['products'] ?? 'pending') === 'skipped'
            || count($images) >= count(\App\Support\BrandKitSpec::products()) || $stale;

        return response()->json(['done' => $done, 'images' => $images]);
    }

    /** Quantity tiers for a product's "add to order" qty picker. */
    private static function tierList(\App\Models\Product $product): array
    {
        return $product->quantities->sortBy('sort_order')->values()->map(fn ($q) => [
            'id'        => $q->id,
            'quantity'  => $q->quantity,
            'total'     => (float) $q->totalPrice(),
            'isDefault' => (bool) $q->is_default,
        ])->all();
    }
}
