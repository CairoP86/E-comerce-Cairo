<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPayment;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'timeline' => $this->timeline($order),
        ]);
    }

    /**
     * What happened to this order, oldest first. The status history is the record the store already
     * keeps on every change, so the timeline never disagrees with the badge at the top.
     */
    private function timeline(Order $order): array
    {
        $steps = DB::table('order_status_history')->where('order_id', $order->id)
            ->orderBy('created_at')->orderBy('id')->get(['to_status', 'actor_id', 'created_at']);
        $people = User::whereIn('id', $steps->pluck('actor_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');

        return $steps->map(fn ($step) => [
            'status' => $step->to_status,
            'created_at' => CarbonImmutable::parse($step->created_at, 'UTC')->toIso8601String(),
            // Null when the checkout placed it: no one from the team was involved.
            'actor' => $people->get($step->actor_id)?->only(['id', 'name']),
        ])->all();
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
