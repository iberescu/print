<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbEvent;
use App\Support\AdsOffer;
use Inertia\Inertia;

/**
 * The ads-offer A/B report: paid29 ($29 for $250 ads) vs free500 ($500 credit
 * free on $100+ orders), segmented by whether the capture had a website URL.
 * Conversion = a PAID order that took the offer (paid29: the $29 line is in the
 * order; free500: the order qualified and the credit was granted).
 */
class ExperimentsController extends Controller
{
    public function index()
    {
        $events = AbEvent::where('test', AdsOffer::TEST)->get();

        $segment = fn (?bool $hasUrl) => $hasUrl === null ? 'unknown' : ($hasUrl ? 'url' : 'no_url');

        $rows = [];
        foreach ([AdsOffer::PAID29, AdsOffer::FREE500] as $variant) {
            foreach (['url', 'no_url', 'unknown', 'all'] as $seg) {
                $of = $events->filter(fn ($e) => $e->variant === $variant
                    && ($seg === 'all' || $segment($e->has_url) === $seg));

                $paid = $of->where('event', 'order_paid');
                $converted = $paid->filter(fn ($e) => (bool) ($e->meta['took_offer'] ?? false));
                $assigned = $of->where('event', 'assigned')->count();

                $rows[] = [
                    'variant'    => $variant,
                    'segment'    => $seg,
                    'assigned'   => $assigned,
                    'engaged'    => $of->where('event', 'offer_added')->count(),
                    'orders'     => $paid->count(),
                    'converted'  => $converted->count(),
                    'convRate'   => $assigned ? round($converted->count() / $assigned * 100, 1) : null,
                    'orderRate'  => $assigned ? round($paid->count() / $assigned * 100, 1) : null,
                    'revenue'    => round((float) $paid->sum('amount'), 2),
                    'aov'        => $paid->count() ? round((float) $paid->sum('amount') / $paid->count(), 2) : null,
                    'convValue'  => round((float) $converted->sum('amount'), 2),
                ];
            }
        }

        return Inertia::render('Admin/Experiments', [
            'rows'  => $rows,
            'since' => $events->min('created_at')?->format('M j, Y'),
            'test'  => [
                'paid29'  => 'Pay $29 → $250 Google Display ads',
                'free500' => 'FREE $500 Google Ads credit on orders ≥ $100',
            ],
        ]);
    }
}
