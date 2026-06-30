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
            'email'   => ['required', 'email'],
            'name'    => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:200'],
            'city'    => ['required', 'string', 'max:80'],
            'postal'  => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:60'],
        ]);

        $order = Order::create([
            'number'   => 'RMP-'.strtoupper(Str::random(8)),
            'email'    => $data['email'],
            'name'     => $data['name'],
            'address'  => ['line' => $data['address'], 'city' => $data['city'], 'postal' => $data['postal'], 'country' => $data['country']],
            'items'    => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
            'shipping' => $this->cart->shipping(),
            'total'    => $this->cart->total(),
            'status'   => 'pending',
        ]);

        $secret = config('shop.stripe.secret');

        // Demo mode (no Stripe keys configured): mark paid and finish.
        if (! $secret) {
            $order->update(['status' => 'paid']);
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
                'price_data' => ['currency' => 'usd', 'product_data' => ['name' => 'Shipping'], 'unit_amount' => (int) round($this->cart->shipping() * 100)],
                'quantity'   => 1,
            ];
        }

        $session = \Stripe\Checkout\Session::create([
            'mode'           => 'payment',
            'line_items'     => $lineItems,
            'customer_email' => $data['email'],
            'success_url'    => route('checkout.success', ['number' => $order->number]).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'     => route('cart'),
            'metadata'       => ['order' => $order->number],
        ]);
        $order->update(['stripe_session_id' => $session->id]);

        return Inertia::location($session->url);
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
                    $order->update(['status' => 'paid']);
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
            $payload = $request->getContent();
            $event = $secret
                ? \Stripe\Webhook::constructEvent($payload, (string) $request->header('Stripe-Signature'), $secret)
                : json_decode($payload);

            if (($event->type ?? null) === 'checkout.session.completed') {
                $num = $event->data->object->metadata->order ?? null;
                if ($num) {
                    Order::where('number', $num)->update(['status' => 'paid']);
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
            'subtotal' => $this->cart->subtotal(),
            'shipping' => $this->cart->shipping(),
            'total'    => $this->cart->total(),
        ];
    }
}
