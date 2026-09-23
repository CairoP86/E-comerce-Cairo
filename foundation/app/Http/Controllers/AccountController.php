<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Mi cuenta, with the context each kind of person needs: a customer wants to know what they ordered,
 * whoever runs the store wants to know when their account was last used.
 */
class AccountController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return Inertia::render('account/Overview', [
            'context' => $user->role === Role::Customer ? $this->customer($user->id) : $this->staff($user->id),
        ]);
    }

    /** Their own orders, newest first. They carry no link: the confirmation page belongs to the checkout session. */
    private function customer(int $userId): array
    {
        return ['orders' => Order::where('user_id', $userId)->latest('created_at')->latest('id')->limit(3)->get()
            ->map(fn (Order $order) => [
                'number' => $order->number, 'created_at' => $order->created_at->toIso8601String(),
                'total_minor' => $order->total_minor, 'currency' => $order->currency,
                'status' => $order->status->value, 'status_label' => $order->status->label(),
            ])->all()];
    }

    /** Their own sign-ins: enough to notice a session they do not recognise, without opening Auditoría. */
    private function staff(int $userId): array
    {
        return ['sign_ins' => AuditLog::where('event', 'auth.login')->where('actor_id', $userId)
            ->latest('id')->limit(3)->get()
            ->map(fn (AuditLog $entry) => ['id' => $entry->id, 'created_at' => $entry->created_at->toIso8601String(), 'source' => $entry->source])->all()];
    }
}
