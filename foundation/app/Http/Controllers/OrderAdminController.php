<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPayment;
use App\Support\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderAdminController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/orders/Index', ['orders' => Order::latest()->paginate(20)->through(fn ($order) => [
            'number' => $order->number, 'created_at' => $order->created_at->toIso8601String(),
            'customer' => $order->first_name.' '.$order->last_name, 'total_minor' => $order->total_minor,
            'currency' => $order->currency, 'status' => $order->status->value, 'status_label' => $order->status->label(),
        ])]);
    }

    public function show(string $number)
    {
        $order = Order::where('number', $number)->firstOrFail();
        Audit::record('order.viewed', request()->user()->id, metadata: ['entity_type' => 'order', 'entity_id' => $order->id]);

        return Inertia::render('admin/orders/Show', [
            'order' => $order->publicSummary(),
            'canMarkPaid' => request()->user()->can('mark-order-paid'),
        ]);
    }

    /**
     * Record a payment confirmed outside the platform (V1-E will handle real gateways).
     * Idempotent: repeating it neither duplicates history nor moves an order back.
     */
    public function markPaid(Request $request, string $number, OrderPayment $payment)
    {
        $order = Order::where('number', $number)->firstOrFail();
        $changed = $payment->markPaid($order, $request->user());

        return back()->with('status', $changed
            ? 'Pedido marcado como pagado. Se descontó el stock de la oferta; el cobro se confirmó fuera de la plataforma y no se registró ninguna transacción en línea.'
            : 'Este pedido ya estaba marcado como pagado.');
    }
}
