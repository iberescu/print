<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'address'  => 'array',
        'billing'  => 'array',
        'items'    => 'array',
        'subtotal' => 'decimal:2',
        'shipping' => 'decimal:2',
        'tax'      => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /** Mark paid and send the confirmation exactly once — every paid-transition
     *  path (demo mode, success-page verify, webhook) funnels through here. */
    public function markPaid(): void
    {
        if ($this->status !== 'paid') {
            $this->update(['status' => 'paid']);

            // Ads-offer A/B: one conversion event per paid order. Webhooks have no
            // session, so everything comes from the columns stamped at checkout.
            if ($this->ab_ads_variant) {
                try {
                    $offerSlug = $this->ab_ads_variant === \App\Support\AdsOffer::FREE500
                        ? \App\Support\AdsOffer::CREDIT_SLUG
                        : 'ad-credit-250';
                    \App\Models\AbEvent::create([
                        'test'     => \App\Support\AdsOffer::TEST,
                        'variant'  => $this->ab_ads_variant,
                        'has_url'  => $this->ab_ads_has_url,
                        'event'    => 'order_paid',
                        'order_id' => $this->id,
                        'amount'   => (float) $this->total,
                        'meta'     => [
                            'subtotal'   => (float) $this->subtotal,
                            'took_offer' => collect($this->items)->contains(fn ($i) => ($i['slug'] ?? '') === $offerSlug),
                        ],
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('ab order_paid log failed: '.$e->getMessage());
                }
            }
        }
        if (! $this->confirmation_sent_at) {
            $this->update(['confirmation_sent_at' => now()]);
            try {
                \Illuminate\Support\Facades\Mail::to($this->email)->send(new \App\Mail\OrderConfirmed($this));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('order confirmation mail failed', ['order' => $this->number, 'error' => $e->getMessage()]);
            }
        }
    }
}
