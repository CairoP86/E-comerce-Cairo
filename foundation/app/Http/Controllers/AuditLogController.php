<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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

        return Inertia::render('admin/Audit', [
            'entries' => $entries->through(fn (AuditLog $entry) => [
                ...$entry->only(['id', 'event', 'actor_id', 'subject_id', 'source', 'metadata', 'created_at']),
                // Null when the account was deleted: the id above still tells one person from another.
                'actor' => $people->get($entry->actor_id)?->only(['id', 'name', 'email']),
                'subject' => $people->get($entry->subject_id)?->only(['id', 'name', 'email']),
            ]),
        ]);
    }
}
