<?php

namespace App\Http\Controllers;

use App\Availability\CatalogFreshness;
use App\Support\OperatorTurn;
use Inertia\Inertia;

/** The operations panel: what needs the operator's attention today. */
class OperationsPanelController extends Controller
{
    public function __invoke(CatalogFreshness $freshness)
    {
        return Inertia::render('admin/Overview', [
            'pendingOrders' => OperatorTurn::pendingOrders(),
            'oldestPendingOrder' => OperatorTurn::oldestPendingOrder(),
            'freshnessSummary' => $freshness->publishedSummary(),
        ]);
    }
}
