<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        // Customers are derived from orders (no separate accounts) — group by email.
        $customers = Order::query()
            ->selectRaw('email, MAX(name) as name, COUNT(*) as orders, SUM(total) as spent, MAX(created_at) as last_order')
            ->groupBy('email')
            ->orderByDesc('last_order')
            ->paginate(20)
            ->through(fn ($r) => [
                'email'     => $r->email,
                'name'      => $r->name,
                'orders'    => (int) $r->orders,
                'spent'     => round((float) $r->spent, 2),
                'lastOrder' => $r->last_order,
            ]);

        return Inertia::render('Admin/Customers/Index', ['customers' => $customers]);
    }
}
