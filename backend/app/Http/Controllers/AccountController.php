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

        // "My designs": projects this account owns (claimed at login or made
        // while signed in) — each edits via /design/{slug}?project={id}.
        $designs = \App\Models\DesignProject::where('user_id', $request->user()->id)
            ->latest('updated_at')->take(24)->get()
            ->map(fn ($p) => [
                'id'      => $p->id,
                'slug'    => $p->product_slug,
                'product' => $p->product_name,
                'preview' => $p->preview,
                'date'    => $p->updated_at?->diffForHumans(),
            ]);

        return Inertia::render('Account', ['orders' => $orders, 'designs' => $designs]);
    }
}
