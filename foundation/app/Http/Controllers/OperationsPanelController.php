<?php

namespace App\Http\Controllers;

use App\Availability\CatalogFreshness;
use App\Enums\OrderStatus;
use App\Models\Order;
use Inertia\Inertia;

/** The operations panel: what needs the operator's attention today. */
class OperationsPanelController extends Controller
{
    public function __invoke(CatalogFreshness $freshness)
    {
        return Inertia::render('admin/Overview', [
            'pendingOrders' => Order::where('status', OrderStatus::PendingPayment)->count(),
            'freshnessSummary' => $freshness->publishedSummary(),
        ]);
    }
}
