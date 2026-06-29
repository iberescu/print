<?php

namespace App\Services;

class Cart
{
    private const KEY = 'cart';

    /** @return array<int,array<string,mixed>> */
    public function items(): array
    {
        return array_values(session(self::KEY, []));
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
        session()->forget(self::KEY);
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
