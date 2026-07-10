<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(private readonly Cart $cart)
    {
    }

    public function show()
    {
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
        if (! $this->cart->count()) {
            return redirect()->route('cart');
        }

        $data = $request->validate([
            'email'          => ['required', 'email'],
            'name'           => ['required', 'string', 'max:120'],
            'address'        => ['required', 'string', 'max:200'],
            'city'           => ['required', 'string', 'max:80'],
            'postal'         => ['required', 'string', 'max:20'],
            'country'        => ['required', 'string', 'max:60'],
            'shippingMethod' => ['nullable', 'string', 'max:40'],
        ]);

        // Lock in the chosen shipping method before any total is computed.
        if (! empty($data['shippingMethod'])) {
            $this->cart->setShippingMethod($data['shippingMethod']);
        }

        // First-order codes are enforced here, where the email is finally known.
        $coupon = $this->cart->coupon();
        if ($coupon?->first_order_only && Order::where('email', $data['email'])->exists()) {
            $this->cart->removeCoupon();

            return redirect()->route('checkout')->with('error', "The code {$coupon->code} is for first orders only — it was removed.");
        }

        $order = Order::create([
            'number'      => 'RMP-'.strtoupper(Str::random(8)),
            'email'       => $data['email'],
            'name'        => $data['name'],
            'address'     => ['line' => $data['address'], 'city' => $data['city'], 'postal' => $data['postal'], 'country' => $data['country']],
            'items'       => $this->cart->items(),
            'subtotal'    => $this->cart->subtotal(),
            'coupon_code' => $coupon?->code,
            'discount'        => $this->cart->discount(),
            'shipping'        => $this->cart->shipping(),
            'shipping_method' => $this->cart->shippingLabel(),
            'total'           => $this->cart->total(),
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
        ];
    }
}
