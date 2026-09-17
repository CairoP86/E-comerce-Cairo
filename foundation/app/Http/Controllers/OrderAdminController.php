<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Audit;
use Inertia\Inertia;

class OrderAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/orders/Index', ['orders' => Order::latest()->paginate(20)->through(fn ($order) => [
            'number' => $order->number, 'created_at' => $order->created_at->toIso8601String(),
            'customer' => $order->first_name.' '.$order->last_name, 'total_minor' => $order->total_minor,
            'currency' => $order->currency, 'status_label' => $order->status->label(),
        ])]);
    }

    public function show(string $number)
    {
        $order = Order::where('number', $number)->firstOrFail();
        Audit::record('order.viewed', request()->user()->id, metadata: ['entity_type' => 'order', 'entity_id' => $order->id]);

        return Inertia::render('admin/orders/Show', ['order' => $order->publicSummary()]);
    }
}
