<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderPayment;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrderAdminController extends Controller
{
    /** The strip's segments, as they appear in the list's URL. */
    private const STATES = ['pendientes' => OrderStatus::PendingPayment, 'pagados' => OrderStatus::Paid];

    public function index(Request $request)
    {
        $filters = $request->validate(['estado' => ['nullable', Rule::in(array_keys(self::STATES))]]);
        $query = Order::latest();
        if ($filters['estado'] ?? null) {
            $query->where('status', self::STATES[$filters['estado']]);
        }
        $counts = Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $pending = (int) $counts->get(OrderStatus::PendingPayment->value, 0);
        $paid = (int) $counts->get(OrderStatus::Paid->value, 0);

        return Inertia::render('admin/orders/Index', [
            'filters' => $filters,
            // The strip always counts every order, so a filter never hides how much is waiting.
            'orderCounts' => ['pending' => $pending, 'paid' => $paid, 'total' => $pending + $paid],
            'orders' => $query->paginate(20)->withQueryString()->through(fn ($order) => [
                'number' => $order->number, 'created_at' => $order->created_at->toIso8601String(),
                'customer' => $order->first_name.' '.$order->last_name, 'total_minor' => $order->total_minor,
                'currency' => $order->currency, 'status' => $order->status->value, 'status_label' => $order->status->label(),
            ]),
        ]);
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
