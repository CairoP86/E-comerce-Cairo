<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        return Inertia::render('admin/orders/Show', [
            'order' => $order->publicSummary(),
            'canMarkPaid' => request()->user()->can('mark-order-paid'),
        ]);
    }

    /**
     * Record a payment confirmed outside the platform (V1-E will handle real gateways).
     * Idempotent: repeating it neither duplicates history nor moves an order back.
     */
    public function markPaid(Request $request, string $number)
    {
        $order = Order::where('number', $number)->firstOrFail();
        $changed = DB::transaction(function () use ($order, $request) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === OrderStatus::Paid) {
                return false;
            }
            if ($locked->status !== OrderStatus::PendingPayment) {
                throw ValidationException::withMessages(['order' => 'Solo un pedido pendiente de pago puede marcarse como pagado.']);
            }
            $from = $locked->status;
            $locked->forceFill(['status' => OrderStatus::Paid])->save();
            DB::table('order_status_history')->insert(['order_id' => $locked->id, 'from_status' => $from->value, 'to_status' => OrderStatus::Paid->value, 'actor_id' => $request->user()->id, 'created_at' => now()]);
            Audit::record('order.marked_paid', $request->user()->id, metadata: ['entity_type' => 'order', 'entity_id' => $locked->id, 'from_status' => $from->value, 'to_status' => OrderStatus::Paid->value]);

            return true;
        }, 3);

        return back()->with('status', $changed
            ? 'Pedido marcado como pagado. El cobro se confirmó fuera de la plataforma; no se registró ninguna transacción en línea.'
            : 'Este pedido ya estaba marcado como pagado.');
    }
}
