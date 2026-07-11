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
