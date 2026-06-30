<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function show(Request $request)
    {
        $orders = Order::where('email', $request->user()->email)
            ->latest()->take(25)->get()
            ->map(fn (Order $o) => [
                'number' => $o->number,
                'total'  => (float) $o->total,
                'status' => $o->status,
                'items'  => count($o->items ?? []),
                'date'   => $o->created_at?->toDayDateTimeString(),
            ]);

        return Inertia::render('Account', ['orders' => $orders]);
    }
}
