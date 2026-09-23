<?php

namespace App\Http\Controllers;

use App\Availability\CatalogFreshness;
use App\Support\OperatorTurn;
use App\Support\PanelSnapshot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** The operations panel: what needs the operator's attention today. */
class OperationsPanelController extends Controller
{
    public function __invoke(Request $request, CatalogFreshness $freshness, PanelSnapshot $snapshot)
    {
        $filters = $request->validate(['dias' => ['nullable', Rule::in(PanelSnapshot::RANGES)]]);

        return Inertia::render('admin/Overview', [
            'snapshot' => $snapshot->for((int) ($filters['dias'] ?? PanelSnapshot::RANGES[0])),
            'pendingOrders' => OperatorTurn::pendingOrders(),
            'oldestPendingOrder' => OperatorTurn::oldestPendingOrder(),
            'freshnessSummary' => $freshness->publishedSummary(),
        ]);
    }
}
