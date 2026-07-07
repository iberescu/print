<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'percent_off', 'first_order_only', 'active', 'expires_at'])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return ['first_order_only' => 'bool', 'active' => 'bool', 'expires_at' => 'datetime'];
    }

    public static function findUsable(string $code): ?self
    {
        $c = static::where('code', strtoupper(trim($code)))->where('active', true)->first();

        return ($c && (! $c->expires_at || $c->expires_at->isFuture())) ? $c : null;
    }
}
