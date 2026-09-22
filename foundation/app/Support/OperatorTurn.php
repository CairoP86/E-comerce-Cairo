<?php

namespace App\Support;

use App\Availability\CatalogFreshness;
use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * What only the operator can unblock right now: orders awaiting payment confirmation, and published
 * products whose offer is expired or unusable. "About to expire" is not here: that is soon, not now.
 * The sidebar counters read this; the panel reads the same queries, so they never disagree.
 */
class OperatorTurn
{
    public function __construct(private CatalogFreshness $freshness) {}

    public static function pendingOrders(): int
    {
        return Order::where('status', OrderStatus::PendingPayment)->count();
    }

    /** The customer who has waited longest leads the panel's strip. @return array{number: string, created_at: string}|null */
    public static function oldestPendingOrder(): ?array
    {
        $order = Order::where('status', OrderStatus::PendingPayment)->oldest()->oldest('id')->first(['number', 'created_at']);

        return $order ? ['number' => $order->number, 'created_at' => $order->created_at->toIso8601String()] : null;
    }

    /** @return array{orders: int, offers: int} */
    public function counts(): array
    {
        $summary = $this->freshness->publishedSummary();

        return ['orders' => self::pendingOrders(), 'offers' => $summary['expired'] + $summary['invalid']];
    }
}
