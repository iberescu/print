<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(private readonly Cart $cart)
    {
    }

    public function show()
    {
        if ($gate = $this->brandStoreGate()) {
            return $gate;
        }
        if (! $this->cart->count()) {
            return redirect()->route('cart');
        }

        return Inertia::render('Checkout', [
            'items'    => $this->cart->items(),
            'summary'  => $this->summary(),
            'customer' => ['name' => auth()->user()?->name, 'email' => auth()->user()?->email],
        ]);
    }

    public function place(Request $request)
    {
        if ($gate = $this->brandStoreGate()) {
            return $gate;
        }
        if (! $this->cart->count()) {
            return redirect()->route('cart');
        }

        $billingRequired = Rule::requiredIf(fn () => ! $request->boolean('billingSame'));
        $data = $request->validate([
            'email'          => ['required', 'email'],
            'name'           => ['required', 'string', 'max:120'],
            'company'        => ['nullable', 'string', 'max:120'],
            'address'        => ['required', 'string', 'max:200'],
            'city'           => ['required', 'string', 'max:80'],
            'state'          => ['required', 'string', 'max:60'],
            'postal'         => ['required', 'string', 'max:20'],
            'country'        => ['required', 'string', 'max:60'],
            'shippingMethod' => ['nullable', 'string', 'max:40'],
            'itemMethods'    => ['nullable', 'array'],
            'itemMethods.*'  => ['string', 'max:40'],
            'billingSame'    => ['boolean'],
            // 'nullable' is REQUIRED here: when billingSame is true the client still sends these
            // fields empty, and ConvertEmptyStringsToNull turns them into null — without 'nullable'
            // the 'string' rule fails ("must be a string") on hidden fields, silently blocking checkout.
            'billingName'    => ['nullable', $billingRequired, 'string', 'max:120'],
            'billingCompany' => ['nullable', 'string', 'max:120'],
            'billingAddress' => ['nullable', $billingRequired, 'string', 'max:200'],
            'billingCity'    => ['nullable', $billingRequired, 'string', 'max:80'],
            'billingState'   => ['nullable', $billingRequired, 'string', 'max:60'],
            'billingPostal'  => ['nullable', $billingRequired, 'string', 'max:20'],
            'billingCountry' => ['nullable', $billingRequired, 'string', 'max:60'],
        ]);

        $shippingAddr = [
            'name'    => $data['name'],
            'company' => $data['company'] ?? null,
            'line'    => $data['address'],
            'city'    => $data['city'],
            'state'   => $data['state'],
            'postal'  => $data['postal'],
            'country' => $data['country'],
        ];
        $billingAddr = $request->boolean('billingSame') ? $shippingAddr : [
            'name'    => $data['billingName'],
            'company' => $data['billingCompany'] ?? null,
            'line'    => $data['billingAddress'],
            'city'    => $data['billingCity'],
            'state'   => $data['billingState'],
            'postal'  => $data['billingPostal'],
            'country' => $data['billingCountry'],
        ];

        // Lock in each product's chosen delivery speed before any total is computed.
        foreach ((array) ($data['itemMethods'] ?? []) as $id => $code) {
            $this->cart->setItemShipping((string) $id, (string) $code);
        }

        // First-order codes are enforced here, where the email is finally known.
        $coupon = $this->cart->coupon();
        if ($coupon?->first_order_only && Order::where('email', $data['email'])->exists()) {
            $this->cart->removeCoupon();

            return redirect()->route('checkout')->with('error', "The code {$coupon->code} is for first orders only — it was removed.");
        }

        $tax = $this->cart->tax($data['state']);

        // Ads-offer A/B (variant B): the $500 credit rides qualifying orders as a
        // $0 line (fulfilment obligation); below the threshold the free-website
        // line (earned WITH the credit) is dropped too. Forced (preview/e2e)
        // sessions aren't stamped, so they never pollute the report.
        $items = $this->cart->items();
        $adsVariant = \App\Support\AdsOffer::current();
        if ($adsVariant === \App\Support\AdsOffer::FREE500) {
            if ((float) $this->cart->subtotal() >= \App\Support\AdsOffer::QUALIFY_AT) {
                $items[] = \App\Support\AdsOffer::creditLine();
            } else {
                $items = array_values(array_filter($items, fn ($i) => ($i['slug'] ?? '') !== 'starter-website'));
            }
        }

        $order = Order::create([
            'number'      => 'RMP-'.strtoupper(Str::random(8)),
            'email'       => $data['email'],
            'name'        => $data['name'],
            'address'     => $shippingAddr,
            'billing'     => $billingAddr,
            'items'       => $items,
            'ab_ads_variant' => \App\Support\AdsOffer::forced() ? null : $adsVariant,
            'ab_ads_has_url' => \App\Support\AdsOffer::forced() ? null : session('ab.ads_has_url'),
            'brand_kit_key'  => session('pqsg.key'), // links the order to its capture (brand-store portal emails)
            'subtotal'    => $this->cart->subtotal(),
            'coupon_code' => $coupon?->code,
            'discount'        => $this->cart->discount(),
            'shipping'        => $this->cart->shipping(),
            'shipping_method' => $this->cart->shippingLabel(),
            'tax'             => $tax,
            'total'           => round($this->cart->total() + $tax, 2),
            'status'          => 'pending',
        ]);

        $secret = config('shop.stripe.secret');

        // Demo mode is a DEV convenience only — a production box without Stripe
        // keys must refuse checkout, not give the products away for free.
        if (! $secret) {
            if (app()->isProduction()) {
                \Illuminate\Support\Facades\Log::error('checkout attempted without Stripe keys in production', ['order' => $order->number]);

                return redirect()->route('cart')->with('error', 'Payments are temporarily unavailable — please try again shortly.');
            }
            $order->markPaid();
            $this->cart->clear();

            return redirect()->route('checkout.success', ['number' => $order->number]);
        }

        // Real Stripe Checkout Session
        \Stripe\Stripe::setApiKey($secret);
        $lineItems = collect($this->cart->items())->map(fn ($i) => [
            'price_data' => [
                'currency'     => 'usd',
                'product_data' => ['name' => $i['name'].' (×'.$i['quantity'].')'],
                'unit_amount'  => (int) round(((float) $i['line_total']) * 100),
            ],
            'quantity' => 1,
        ])->all();

        if ($this->cart->shipping() > 0) {
            $lineItems[] = [
                'price_data' => ['currency' => 'usd', 'product_data' => ['name' => 'Shipping — '.$this->cart->shippingLabel()], 'unit_amount' => (int) round($this->cart->shipping() * 100)],
                'quantity'   => 1,
            ];
        }
        if ($tax > 0) {
            $lineItems[] = [
                'price_data' => ['currency' => 'usd', 'product_data' => ['name' => 'Estimated sales tax'], 'unit_amount' => (int) round($tax * 100)],
                'quantity'   => 1,
            ];
        }

        $params = [
            'mode'           => 'payment',
            'line_items'     => $lineItems,
            'customer_email' => $data['email'],
            'success_url'    => route('checkout.success', ['number' => $order->number]).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'     => route('cart'),
            'metadata'       => ['order' => $order->number],
        ];
        if ($coupon) {
            $params['discounts'] = [['coupon' => $this->stripeCouponId($coupon->percent_off)]];
        }

        $session = \Stripe\Checkout\Session::create($params);
        $order->update(['stripe_session_id' => $session->id]);

        return Inertia::location($session->url);
    }

    /** A reusable Stripe percent-off coupon per rate (created once, then reused). */
    private function stripeCouponId(int $percent): string
    {
        $id = "RMP-{$percent}PCT";
        try {
            \Stripe\Coupon::retrieve($id);
        } catch (\Throwable) {
            \Stripe\Coupon::create(['id' => $id, 'percent_off' => $percent, 'duration' => 'once']);
        }

        return $id;
    }

    /** RTB House conversion payload: the order's personalized-set lines mapped
     *  to the session capture's ALIAS offer ids (pre-provisioned in the feed).
     *  Null when the tag is off / there's no aliased store / nothing matches. */
    private function rtbConversion(Order $order): ?array
    {
        if (! config('shop.rtbhouse.tag')) {
            return null;
        }
        $alias = app()->bound('brandStore')
            ? app('brandStore')->alias?->alias
            : (function () {
                $key = session('pqsg.key');
                $kit = $key ? \App\Models\BrandKit::where('key', $key)->first() : null;

                return $kit ? \App\Models\BrandStore::with('alias')->where('brand_kit_id', $kit->id)->first()?->alias?->alias : null;
            })();
        if (! $alias) {
            return null;
        }

        $feedSlugs = collect(\App\Support\BrandKitSpec::products())->pluck('slug')->filter()->unique();
        $offerIds = collect($order->items)->pluck('slug')->filter()
            ->intersect($feedSlugs)->map(fn ($s) => "{$alias}-{$s}")->values()->all();

        return $offerIds ? [
            'offerIds' => $offerIds,
            'value'    => (float) $order->total,
            'orderId'  => $order->number,
        ] : null;
    }

    /** Brand-store hosts: browsing is open, ORDERING needs the employee session
     *  (magic link @their-domain). Null on the main shop / when signed in. */
    private function brandStoreGate()
    {
        if (app()->bound('brandStore') && ! session()->has('brandstore.auth.'.app('brandStore')->id)) {
            return redirect('/store-login');
        }

        return null;
    }

    public function success(Request $request)
    {
        $order = Order::where('number', $request->query('number'))->first();
        if (! $order) {
            return redirect()->route('home');
        }

        if ($order->status !== 'paid' && config('shop.stripe.secret') && $request->query('session_id')) {
            try {
                \Stripe\Stripe::setApiKey(config('shop.stripe.secret'));
                $s = \Stripe\Checkout\Session::retrieve($request->query('session_id'));
                if (($s->payment_status ?? null) === 'paid') {
                    $order->markPaid();
                }
            } catch (\Throwable $e) {
                // leave as pending; webhook will reconcile
            }
        }

        if ($order->status === 'paid') {
            $this->cart->clear();
        }

        return Inertia::render('CheckoutSuccess', [
            'order' => ['number' => $order->number, 'email' => $order->email, 'total' => (float) $order->total, 'status' => $order->status],
            'rtb'   => $this->rtbConversion($order),
        ]);
    }

    public function webhook(Request $request): Response
    {
        try {
            $secret = config('shop.stripe.webhook_secret');

            // In production an unsigned webhook is an attack surface (anyone could mark
            // orders paid) — reject outright. The unsigned fallback exists for local dev only.
            if (! $secret && app()->isProduction()) {
                return response('webhook secret not configured', 400);
            }

            $payload = $request->getContent();
            $event = $secret
                ? \Stripe\Webhook::constructEvent($payload, (string) $request->header('Stripe-Signature'), $secret)
                : json_decode($payload);

            if (($event->type ?? null) === 'checkout.session.completed') {
                $num = $event->data->object->metadata->order ?? null;
                if ($num) {
                    Order::where('number', $num)->first()?->markPaid();
                }
            }
        } catch (\Throwable $e) {
            return response('invalid', 400);
        }

        return response('ok');
    }

    private function summary(): array
    {
        return [
            'subtotal'        => $this->cart->subtotal(),
            'coupon'          => $this->cart->coupon()?->code,
            'discount'        => $this->cart->discount(),
            'shipping'        => $this->cart->shipping(),
            'total'           => $this->cart->total(),
            'threshold'       => $this->cart->threshold(),
            'methods'         => $this->cart->methods(),
            'shipping_method' => $this->cart->shippingMethod(),
            'tax_rates'       => $this->cart->taxRates(),
        ];
    }
}
