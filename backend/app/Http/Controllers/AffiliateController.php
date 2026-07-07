<?php

namespace App\Http\Controllers;

use App\Jobs\SendPqsgCapture;
use App\Models\Affiliate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * B2B affiliate program (req: partner-embedded widget, $15–20 CPM).
 * Partners embed /affiliate-widget.js and pass their user's logo or website;
 * the third-party upsell engine renders the logo onto real products and the
 * widget shows them as an ad. Impressions/clicks land in daily stats — the
 * admin page turns those into money owed.
 *
 * The widget endpoints are CORS-open (partner sites call them cross-origin)
 * and use GET/beacon-friendly shapes so no preflight is ever needed.
 */
class AffiliateController extends Controller
{
    /** Product mockups worth showing inside a small ad unit. */
    private const AD_PRODUCTS = [
        'tshirt_words', 'business_card_qr_logo', 'hoodie', 'bottle', 'sticker',
        'bags', 'glass_logo', 'cloudlab_umbrela', 'chocolate_bar', 'canvas',
        'cloudlab_sortv2', 'cloudlab_pix', 'office', 'google_v2',
    ];

    public function show()
    {
        return Inertia::render('Affiliates', [
            'heroImage'     => \App\Support\Img::url('promos/affiliates-hero'),
            'showcaseImage' => \App\Support\Img::url('promos/affiliates-widget'),
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email'   => ['required', 'email', 'max:160', 'unique:affiliates,email'],
            'website' => ['required', 'string', 'max:200'],
        ]);

        Affiliate::create($data + ['key' => Str::random(40), 'status' => 'pending']);

        return back()->with('success', 'Application received — we review within one business day and email your widget key.');
    }

    // ---- widget API (cross-origin, called from partner sites) ---------------

    /** Register a capture for the partner's visitor (their logo or website). */
    public function widgetCapture(Request $request): JsonResponse
    {
        $affiliate = $this->affiliate($request);
        if (! $affiliate) {
            return $this->cors(['error' => 'unknown or inactive key'], 403);
        }

        $data = $request->validate([
            'logo_url' => ['nullable', 'url', 'starts_with:https://,http://', 'max:500'],
            'website'  => ['nullable', 'string', 'max:200'],
        ]);
        $website = trim((string) ($data['website'] ?? ''));
        if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }
        if (empty($data['logo_url']) && $website === '') {
            return $this->cors(['error' => 'pass logo_url or website'], 422);
        }

        $key = (string) Str::uuid();
        SendPqsgCapture::dispatchAfterResponse(
            key: $key,
            source: 'runmyprint-affiliate-'.$affiliate->id,
            logoUrl: $data['logo_url'] ?? null,
            website: $website !== '' ? $website : null,
        );

        return $this->cors(['capture' => $key]);
    }

    /** Poll the capture: ready + a small set of ad-worthy product mockups. */
    public function widgetStatus(Request $request): JsonResponse
    {
        if (! $this->affiliate($request)) {
            return $this->cors(['error' => 'unknown or inactive key'], 403);
        }
        $capture = (string) $request->query('capture', '');
        if (! Str::isUuid($capture)) {
            return $this->cors(['error' => 'bad capture'], 422);
        }

        $uuid = Cache::get("pqsg:{$capture}");
        if (! $uuid) {
            return $this->cors(['ready' => false, 'done' => false, 'images' => []]);
        }

        // proxy the engine's widget feed (cached briefly — many visitors poll)
        $state = Cache::remember("affiliate:engine:{$uuid}", 8, function () use ($uuid) {
            try {
                return Http::timeout(8)->acceptJson()
                    ->get(config('shop.pqsg.api_base')."/capture/{$uuid}/widget")->json() ?? [];
            } catch (\Throwable) {
                return [];
            }
        });

        $wanted = array_flip(self::AD_PRODUCTS);
        $images = collect($state['images'] ?? [])
            ->filter(fn ($i) => ($i['type'] ?? '') === 'product_mockup'
                && (isset($wanted[$i['product_key'] ?? '']) || isset($wanted[$i['special_product_key'] ?? ''])))
            ->unique(fn ($i) => $i['product_key'] ?? $i['special_product_key'])
            ->take(8)
            ->map(fn ($i) => ['url' => $i['url'], 'label' => $i['product_label'] ?? ''])
            ->values()->all();

        // ready at 3+ mockups, or with whatever exists once the engine settles —
        // some captures legitimately yield a smaller set
        $done = in_array($state['computed_status'] ?? '', ['completed', 'partially_completed'], true)
            || (bool) ($state['is_complete'] ?? false);

        return $this->cors([
            'ready'  => count($images) >= 3 || ($done && count($images) >= 1),
            'done'   => $done,
            'images' => $images,
        ]);
    }

    /** Impression counter (sendBeacon/GET — no preflight). */
    public function widgetTrack(Request $request): JsonResponse
    {
        if ($affiliate = $this->affiliate($request)) {
            $affiliate->track('impression');
        }

        return $this->cors(['ok' => true]);
    }

    /** Click-through: count, then land WITH the visitor's capture so the whole
     *  shop (upsell galleries, previews) is personalised to their logo. */
    public function widgetGo(Request $request)
    {
        if ($affiliate = $this->affiliate($request)) {
            $affiliate->track('click');
        }

        $capture = (string) $request->query('capture', '');
        if (Str::isUuid($capture) && Cache::has("pqsg:{$capture}")) {
            session(['pqsg.key' => $capture, 'pqsg.strong' => $capture, 'pqsg.strong_at' => now()->toIso8601String()]);
        }

        return redirect('/product/standard-business-cards?utm_source=affiliate&utm_medium=widget&utm_campaign='.($affiliate->id ?? 'unknown'));
    }

    private function affiliate(Request $request): ?Affiliate
    {
        $key = (string) ($request->input('key') ?: $request->query('key', ''));

        return $key === '' ? null : Affiliate::where('key', $key)->where('status', 'active')->first();
    }

    private function cors(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)->header('Access-Control-Allow-Origin', '*');
    }
}
