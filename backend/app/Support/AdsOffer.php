<?php

namespace App\Support;

use App\Models\AbEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The ads-step offer A/B test:
 *   paid29  — pay $29, get $250 of Google Display ads (the original offer)
 *   free500 — a $500 Google Ads promotional credit, free on orders ≥ $100
 *             (auto-added as a $0 order line at checkout, no CTA)
 *
 * Assignment is 50/50, sticky per session, made the first time the buyer sees
 * the ads step (segmented by whether their capture had a website URL — the
 * with/without-URL workflows are reported separately). `?ab_ads=paid29|free500`
 * forces a variant for previews/e2e; forced sessions are excluded from stats.
 */
class AdsOffer
{
    public const TEST = 'ads_offer';

    public const PAID29 = 'paid29';

    public const FREE500 = 'free500';

    public const CREDIT_SLUG = 'google-ads-credit-500';

    public const CREDIT = 500;

    public const QUALIFY_AT = 100.0; // free500: merchandise subtotal that earns the credit

    /** Sticky variant, assigning (and logging) on first sight of the ads step. */
    public static function assign(?bool $hasUrl): string
    {
        self::applyForce();

        $v = session('ab.ads_offer');
        if (! in_array($v, [self::PAID29, self::FREE500], true)) {
            $v = random_int(0, 1) ? self::PAID29 : self::FREE500;
            session()->put('ab.ads_offer', $v);
            session()->put('ab.ads_has_url', $hasUrl);
            self::log('assigned');
        }

        return $v;
    }

    /** The session's variant without assigning one (null before the ads step). */
    public static function current(): ?string
    {
        self::applyForce();
        $v = session('ab.ads_offer');

        return in_array($v, [self::PAID29, self::FREE500], true) ? $v : null;
    }

    public static function forced(): bool
    {
        return (bool) session('ab.ads_forced');
    }

    /** Append an event for the report. Forced (preview/e2e) sessions stay out. */
    public static function log(string $event, array $attrs = []): void
    {
        if (self::forced()) {
            return;
        }
        try {
            AbEvent::create($attrs + [
                'test'    => self::TEST,
                'variant' => session('ab.ads_offer'),
                'has_url' => session('ab.ads_has_url'),
                'event'   => $event,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ab-event log failed: '.$e->getMessage());
        }
    }

    /** The $0 order line carrying the fulfilment obligation for the credit. */
    public static function creditLine(): array
    {
        return [
            'id'               => 'ads-credit-'.Str::random(6),
            'product_id'       => null,
            'name'             => 'Google Ads Credit — $500',
            'slug'             => self::CREDIT_SLUG,
            'image'            => null,
            'quantity'         => 1,
            'quantity_id'      => null,
            'unit_price'       => 0.0,
            'line_total'       => 0.0,
            'options'          => [],
            'option_value_ids' => [],
            'design'           => null,
            'upsell'           => false,
        ];
    }

    /** `?ab_ads=paid29|free500` pins the variant for previews/e2e — honoured on
     *  any upsell/cart request (the buyer may still be steps before the ads one). */
    public static function applyForce(): void
    {
        $forced = request()->query('ab_ads');
        if (in_array($forced, [self::PAID29, self::FREE500], true)) {
            session()->put('ab.ads_offer', $forced);
            session()->put('ab.ads_forced', true);
        }
    }
}
