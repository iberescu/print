<?php

namespace App\Services;

class Cart
{
    private const KEY = 'cart';
    private const UPSELL = 'cart_upsell';
    private const UPSELL_I = 'cart_upsell_i';
    private const UPSELL_LINE = 'cart_upsell_line';

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
        }
    }

    public function add(array $item): string
    {
        $cart = session(self::KEY, []);
        $id = bin2hex(random_bytes(6));
        $item['id'] = $id;
        $cart[$id] = $item;
        session([self::KEY => $cart]);

        return $id;
    }

    public function remove(string $id): void
    {
        $cart = session(self::KEY, []);
        unset($cart[$id]);
        session([self::KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget([self::KEY, self::UPSELL, self::UPSELL_I, self::UPSELL_LINE]);
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

    public function shipping(): float
    {
        $sub = $this->subtotal();
        if ($sub <= 0 || $this->qualifiesFreeShipping()) {
            return 0.0;
        }

        return 4.99;
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->shipping(), 2);
    }
}
