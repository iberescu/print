<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $query = Order::query()->latest();
        if (in_array($status, ['pending', 'paid', 'failed'], true)) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20)->withQueryString()->through(fn (Order $o) => [
            'number' => $o->number,
            'name'   => $o->name,
            'email'  => $o->email,
            'total'  => (float) $o->total,
            'status' => $o->status,
            'items'  => count($o->items ?? []),
            'date'   => $o->created_at?->toDayDateTimeString(),
        ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'status' => $status,
            'counts' => [
                'all'     => Order::count(),
                'pending' => Order::where('status', 'pending')->count(),
                'paid'    => Order::where('status', 'paid')->count(),
                'failed'  => Order::where('status', 'failed')->count(),
            ],
        ]);
    }

    public function show(Order $order)
    {
        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'number'   => $order->number,
                'name'     => $order->name,
                'email'    => $order->email,
                'address'  => $order->address,
                'items'    => $order->items,
                'subtotal' => (float) $order->subtotal,
                'shipping' => (float) $order->shipping,
                'total'    => (float) $order->total,
                'status'   => $order->status,
                'date'     => $order->created_at?->toDayDateTimeString(),
            ],
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate(['status' => ['required', 'in:pending,paid,failed']]);
        $order->update($data);

        return back()->with('success', "Order {$order->number} marked {$data['status']}.");
    }
}
