<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Inertia\Inertia;

/** The activity log, read only: who did what, named rather than numbered. */
class AuditLogController extends Controller
{
    public function __invoke()
    {
        $entries = AuditLog::query()->latest('id')->paginate(25);
        // One lookup for the whole page: the same person usually appears in many rows.
        $people = User::whereIn('id', collect($entries->items())->flatMap(fn (AuditLog $entry) => [$entry->actor_id, $entry->subject_id])->filter()->unique())
            ->get(['id', 'name', 'email'])->keyBy('id');

        // Orders are identified by a uuid in the log, but the operator knows them by their number.
        $orders = Order::whereIn('id', collect($entries->items())
            ->filter(fn (AuditLog $entry) => ($entry->metadata['entity_type'] ?? null) === 'order')
            ->pluck('metadata.entity_id')->filter()->unique())->pluck('number', 'id');

        return Inertia::render('admin/Audit', [
            'entries' => $entries->through(fn (AuditLog $entry) => [
                ...$entry->only(['id', 'event', 'actor_id', 'subject_id', 'source', 'metadata', 'created_at']),
                // Null when the account was deleted: the id above still tells one person from another.
                'actor' => $people->get($entry->actor_id)?->only(['id', 'name', 'email']),
                'subject' => $people->get($entry->subject_id)?->only(['id', 'name', 'email']),
                // Null for anything else, and for an order that no longer exists.
                'order_number' => $orders->get($entry->metadata['entity_id'] ?? ''),
            ]),
        ]);
    }
}
