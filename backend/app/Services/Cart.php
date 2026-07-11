<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Cart
{
    private const KEY = 'cart';
    private const COUPON = 'cart_coupon';
    private const UPSELL = 'cart_upsell';
    private const UPSELL_I = 'cart_upsell_i';
    private const UPSELL_LINE = 'cart_upsell_line';
    private const SHIP = 'cart_shipping';

    /** @return array<int,array<string,mixed>> */
    public function items(): array
    {
        return array_values(session(self::KEY, []));
    }

    /** @return array<string,mixed>|null */
    public function item(string $id): ?array
    {
        return session(self::KEY, [])[$id] ?? null;
    }

    /** Merge new values into an existing line (re-pricing on the final step). */
    public function update(string $id, array $patch): void
    {
        $cart = session(self::KEY, []);
        if (isset($cart[$id])) {
            $cart[$id] = array_merge($cart[$id], $patch);
            session([self::KEY => $cart]);
            $this->snapshot();
        }
    }

    public function add(array $item): string
    {
        $cart = session(self::KEY, []);
        $id = bin2hex(random_bytes(6));
        $item['id'] = $id;
        $cart[$id] = $item;
        session([self::KEY => $cart]);
        $this->snapshot();

        return $id;
    }

    public function remove(string $id): void
    {
        $cart = session(self::KEY, []);
        unset($cart[$id]);
        session([self::KEY => $cart]);
        $this->snapshot();
    }

    public function clear(): void
    {
        session()->forget([self::KEY, self::COUPON, self::UPSELL, self::UPSELL_I, self::UPSELL_LINE, self::SHIP]);
        if ($uid = Auth::id()) {
            DB::table('cart_reminders')->where('user_id', $uid)->delete();
        }
    }

    /** Abandoned-cart trail: signed-in carts get a DB snapshot the hourly
     *  carts:remind command turns into a nudge email. Guests have no address
     *  to write to, so only authed carts are recorded. */
    private function snapshot(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $items = $this->items();
        if (! $items) {
            DB::table('cart_reminders')->where('user_id', $user->id)->delete();

            return;
        }
        DB::table('cart_reminders')->upsert([[
            'user_id'     => $user->id,
            'email'       => $user->email,
            'items'       => json_encode(array_map(fn ($i) => [
                'name' => $i['name'] ?? '', 'quantity' => $i['quantity'] ?? 1,
                'line_total' => $i['line_total'] ?? 0, 'preview' => $i['design']['preview'] ?? null,
            ], $items)),
            'subtotal'    => $this->subtotal(),
            'reminded_at' => null,
            'updated_at'  => now(),
            'created_at'  => now(),
        ]], ['user_id'], ['email', 'items', 'subtotal', 'reminded_at', 'updated_at']);
    }

    // ---- coupon --------------------------------------------------------------

    /** Apply a code; returns an error string or null on success. */
    public function applyCoupon(string $code): ?string
    {
        $coupon = Coupon::findUsable($code);
        if (! $coupon) {
            return 'That code is not valid.';
        }
        session([self::COUPON => $coupon->code]);

        return null;
    }

    public function removeCoupon(): void
    {
        session()->forget(self::COUPON);
    }

    public function coupon(): ?Coupon
    {
        $code = session(self::COUPON);

        return $code ? Coupon::findUsable($code) : null;
    }

    public function discount(): float
    {
        $coupon = $this->coupon();

        return $coupon ? round($this->subtotal() * $coupon->percent_off / 100, 2) : 0.0;
    }

    /** Forced upsell flow: an ordered list of step keys the buyer passes before the cart.
     *  $lineId is the just-added line the 'finalize' step re-prices. */
    public function setUpsell(array $steps, ?string $lineId = null): void
    {
        session([self::UPSELL => array_values($steps), self::UPSELL_I => 0, self::UPSELL_LINE => $lineId]);
    }

    /** The cart line being finalised (quantity/material still editable). */
    public function upsellLineId(): ?string
    {
        return session(self::UPSELL_LINE);
    }

    /** @return array<int,string> */
    public function upsellSteps(): array
    {
        return session(self::UPSELL, []);
    }

    public function upsellIndex(): int
    {
        return (int) session(self::UPSELL_I, 0);
    }

    public function upsellCurrent(): ?string
    {
        return $this->upsellSteps()[$this->upsellIndex()] ?? null;
    }

    public function upsellPending(): bool
    {
        return $this->upsellCurrent() !== null;
    }

    public function advanceUpsell(): void
    {
        session([self::UPSELL_I => $this->upsellIndex() + 1]);
    }

    public function clearUpsell(): void
    {
        session()->forget([self::UPSELL, self::UPSELL_I, self::UPSELL_LINE]);
    }

    public function count(): int
    {
        return count(session(self::KEY, []));
    }

    public function subtotal(): float
    {
        return round(array_sum(array_map(
            fn ($i) => (float) ($i['line_total'] ?? 0),
            session(self::KEY, [])
        )), 2);
    }

    public function threshold(): float
    {
        return (float) config('shop.free_shipping_threshold', 50);
    }

    public function qualifiesFreeShipping(): bool
    {
        return $this->subtotal() >= $this->threshold();
    }

    public function remainingForFree(): float
    {
        return max(0, round($this->threshold() - $this->subtotal(), 2));
    }

    /**
     * The fixed shipping methods with their effective price for this cart.
     * Shipping is charged PER PRODUCT, so price = unit price × number of
     * products. The base method is free once the order clears the free-shipping
     * threshold. Each method also carries an estimated delivery date computed as
     * today + N business days.
     *
     * @return array<int,array{code:string,label:string,eta:string,deliver_by:string,unit_price:float,price:float,free:bool}>
     */
    public function methods(): array
    {
        $base = config('shop.shipping_base_method', 'standard');
        $qualifies = $this->qualifiesFreeShipping();
        $count = max(1, $this->count()); // shipping is per product

        return array_map(function ($m) use ($base, $qualifies, $count) {
            $free = $m['code'] === $base && $qualifies;
            $unit = (float) $m['price'];
            $date = now()->addWeekdays((int) ($m['days'] ?? 0));

            return [
                'code'       => $m['code'],
                'label'      => $m['label'],
                'eta'        => 'Delivery as soon as '.$date->format('D, M j').'*',
                'deliver_by' => $date->toDateString(),
                'unit_price' => $unit,
                'price'      => $free ? 0.0 : round($unit * $count, 2),
                'free'       => $free,
            ];
        }, config('shop.shipping_methods', []));
    }

    /** Selected shipping method code, defaulting to the first configured method. */
    public function shippingMethod(): string
    {
        $codes = array_column(config('shop.shipping_methods', []), 'code');
        $selected = session(self::SHIP);

        return in_array($selected, $codes, true) ? $selected : ($codes[0] ?? 'economy');
    }

    public function setShippingMethod(string $code): void
    {
        $codes = array_column(config('shop.shipping_methods', []), 'code');
        if (in_array($code, $codes, true)) {
            session([self::SHIP => $code]);
        }
    }

    public function shippingLabel(): string
    {
        $code = $this->shippingMethod();
        foreach (config('shop.shipping_methods', []) as $m) {
            if ($m['code'] === $code) {
                return $m['label'];
            }
        }

        return 'Shipping';
    }

    public function shipping(): float
    {
        if ($this->subtotal() <= 0) {
            return 0.0;
        }
        $code = $this->shippingMethod();
        foreach ($this->methods() as $m) {
            if ($m['code'] === $code) {
                return (float) $m['price'];
            }
        }

        return 0.0;
    }

    public function total(): float
    {
        return round($this->subtotal() - $this->discount() + $this->shipping(), 2);
    }
}
