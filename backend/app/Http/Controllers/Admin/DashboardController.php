<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'revenue'    => round((float) Order::where('status', 'paid')->sum('total'), 2),
            'orders'     => Order::count(),
            'paid'       => Order::where('status', 'paid')->count(),
            'pending'    => Order::where('status', 'pending')->count(),
            'products'   => Product::count(),
            'active'     => Product::where('is_active', true)->count(),
            'customers'  => (int) Order::query()->distinct()->count('email'),
        ];

        $recent = Order::latest()->take(8)->get()->map(fn (Order $o) => [
            'number' => $o->number,
            'name'   => $o->name,
            'email'  => $o->email,
            'total'  => (float) $o->total,
            'status' => $o->status,
            'items'  => count($o->items ?? []),
            'date'   => $o->created_at?->toDayDateTimeString(),
        ]);

        return Inertia::render('Admin/Dashboard', ['stats' => $stats, 'recent' => $recent]);
    }
}
